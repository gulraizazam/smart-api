<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\PackageService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class UpsellingReportController extends Controller
{
    public function index()
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);

        return view('admin.reports.upselling', get_defined_vars());
    }
    public function consultantRevenueReport()
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);

        return view('admin.reports.consultant_revenue', get_defined_vars());
    }
   public function loadUpsellingReport(Request $request)
{
    $request->validate([
        'centre_id' => 'required|integer|exists:locations,id',
    ]);

    $locationId = $request->centre_id;
    $dates = explode(' - ', $request->input('date_range'));
    $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
    $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

    $roleHasUsers = User::whereHas('roles', function($query) {
        $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
    })->pluck('id');

    $fdmUserIds = User::whereHas('roles', function ($q) {
            $q->where('name', 'FDM');
        })
        ->whereHas('user_has_locations', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        })
        ->pluck('id');

    // Step 1: Get doctors for the location
    $doctorIds = DB::table('doctor_has_locations')
    ->where('location_id', $locationId)
    ->whereIn('user_id', $roleHasUsers)
     ->distinct()
    ->pluck('user_id');

    $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();
    if ($allSellerIds->isEmpty()) {
        return response()->json([
            'status' => 200,
            'message' => 'No doctors found for the selected location.',
            'data' => [],
        ]);
    }

    $reportQuery = PackageService::query()
        ->join('users', 'package_services.sold_by', '=', 'users.id')
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->whereIn('package_services.sold_by', $allSellerIds)
        ->where('packages.location_id', $locationId);

    // Apply date range filter on created_at
    if ($startDate && $endDate) {
        $reportQuery->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');
    }

    // Fetch summary report data (only doctor names and total amounts)
    $reportData = $reportQuery
        ->select(
            'users.name as doctor_name',
            'package_services.sold_by as doctor_id',
            DB::raw("
                SUM(
                    CASE
                        WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                        THEN package_services.tax_including_price
                        ELSE 0
                    END
                ) as total_sold_amount
            ")
        )
        ->groupBy('package_services.sold_by', 'users.name')
        ->get();

    // Store filters in session for detail view
    session(['upselling_filters' => [
        'location_id' => $locationId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'all_seller_ids' => $allSellerIds->toArray()
    ]]);

    return view('admin.reports.upsellingReport', compact('reportData'));
}

// New function for doctor detail view
public function doctorUpsellingDetail($doctorId)
{
    $filters = session('upselling_filters');

    if (!$filters) {
        return redirect()->back()->with('error', 'Session expired. Please reload the report.');
    }

    $reportQuery = PackageService::query()
        ->join('users', 'package_services.sold_by', '=', 'users.id')
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->join('services', 'package_services.service_id', '=', 'services.id')
        ->where('package_services.sold_by', $doctorId)
        ->whereIn('package_services.sold_by', $filters['all_seller_ids'])
        ->where('packages.location_id', $filters['location_id'])
        ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
        ->whereNotNull('sold_by');

    $detailData = $reportQuery
        ->select(
            'users.name as doctor_name',
            'package_services.package_id',
            'services.name as service_name',
            'package_services.tax_including_price',
            'package_services.created_at',
            'appointments.patient_id',
            'appointments.name as patient_name',
            'appointments.scheduled_date',
            DB::raw("
                CASE
                    WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                    THEN package_services.tax_including_price
                    ELSE 0
                END as actual_amount
            ")
        )
        ->where(DB::raw("
            CASE
                WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                THEN package_services.tax_including_price
                ELSE 0
            END
        "), '>', 0)
        ->orderBy('package_services.created_at', 'desc')
        ->get();

    $doctorName = $detailData->first()->doctor_name ?? 'Unknown Doctor';
    $totalAmount = $detailData->sum('actual_amount');
    
    // Count unique upselling packages instead of service records
    $uniqueUpsellings = $detailData->unique('package_id')->count();

    return view('admin.reports.doctorUpsellingDetail', compact('detailData', 'doctorName', 'totalAmount', 'uniqueUpsellings'));
}
public function loadConsultantRevenueReport(Request $request)
{
    $request->validate([
        'centre_id' => 'required|integer|exists:locations,id',
    ]);

    $locationId = $request->centre_id;
    $dates = explode(' - ', $request->input('date_range'));
    $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
    $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

    // Step 1: Get only Consultant users
    $consultantUserIds = User::whereHas('roles', function($query) {
        $query->where('name', 'Consultant');
    })->pluck('id');

    // Step 2: Get consultants assigned to the specific location
    $consultantIds = DB::table('doctor_has_locations')
        ->where('location_id', $locationId)
        ->whereIn('user_id', $consultantUserIds)
        ->distinct()
        ->pluck('user_id');

    if ($consultantIds->isEmpty()) {
        return response()->json([
            'status' => 200,
            'message' => 'No consultants found for the selected location.',
            'data' => [],
        ]);
    }

    $reportQuery = PackageService::query()
        ->join('users', 'package_services.sold_by', '=', 'users.id')
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->whereIn('package_services.sold_by', $consultantIds)
        ->where('packages.location_id', $locationId);

    // Apply date range filter on created_at
    if ($startDate && $endDate) {
        $reportQuery->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');
    }

    // Fetch consultant revenue data (opposite logic - primary consultation revenue)
    $reportData = $reportQuery
        ->select(
            'users.name as consultant_name',
            'package_services.sold_by as consultant_id',
            DB::raw("
                SUM(
                    CASE
                        WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                        THEN package_services.tax_including_price
                        ELSE 0
                    END
                ) as total_consultation_revenue
            ")
        )
        ->groupBy('package_services.sold_by', 'users.name')
        ->get();

    // Store filters in session for detail view
    session(['consultant_revenue_filters' => [
        'location_id' => $locationId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'consultant_ids' => $consultantIds->toArray()
    ]]);

    return view('admin.reports.consultantRevenueReport', compact('reportData'));
}
public function consultantRevenueDetail($consultantId)
{
    $filters = session('consultant_revenue_filters');

    if (!$filters) {
        return redirect()->back()->with('error', 'Session expired. Please reload the report.');
    }

    $reportQuery = PackageService::query()
        ->join('users', 'package_services.sold_by', '=', 'users.id')
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->join('services', 'package_services.service_id', '=', 'services.id')
        ->where('package_services.sold_by', $consultantId)
        ->whereIn('package_services.sold_by', $filters['consultant_ids'])
        ->where('packages.location_id', $filters['location_id'])
        ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
        ->whereNotNull('sold_by');

    $detailData = $reportQuery
        ->select(
            'users.name as consultant_name',
            'package_services.package_id',
            'services.name as service_name',
            'package_services.tax_including_price',
            'package_services.created_at',
            'appointments.patient_id',
            'appointments.name as patient_name',
            'appointments.scheduled_date',
            DB::raw("
                CASE
                    WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                    THEN package_services.tax_including_price
                    ELSE 0
                END as actual_amount
            ")
        )
        ->where(DB::raw("
            CASE
                WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                THEN package_services.tax_including_price
                ELSE 0
            END
        "), '>', 0)
        ->orderBy('package_services.created_at', 'desc')
        ->get();

    $consultantName = $detailData->first()->consultant_name ?? 'Unknown Consultant';
    $totalAmount = $detailData->sum('actual_amount');
    
    // Count unique consultations (appointments) instead of service records
    $uniqueConsultations = $detailData->unique('package_id')->count();
    
    // Alternative: Count unique appointments if you want true appointment count
    // $uniqueAppointments = $detailData->unique(function($item) {
    //     return $item->patient_id . '-' . $item->scheduled_date;
    // })->count();

    return view('admin.reports.consultantRevenueDetail', compact('detailData', 'consultantName', 'totalAmount', 'uniqueConsultations'));
}
public function getDoctorUpsellingData(Request $request)
{
    try {
        $centreId = $request->centre_id;
        $period = $request->period ?: 'thismonth';
        
        // Define date ranges
        $periods = [
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d 23:59:59'),
            ],
        ];

        $startDate = $periods[$period]['start_date'];
        $endDate = $periods[$period]['end_date'];

        // Get users with specific roles
        $roleHasUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
        })->pluck('id');

        $fdmUserIds = User::whereHas('roles', function ($q) {
                $q->where('name', 'FDM');
            })
            ->whereHas('user_has_locations', function ($q) use ($centreId) {
                if ($centreId !== 'all') {
                    $q->where('location_id', $centreId);
                }
            })
            ->pluck('id');

        // Get doctors for the location(s)
        if ($centreId === 'all') {
            // Get all locations user has access to
            $userLocations = \App\Helpers\ACL::getUserCentres();
            $doctorIds = DB::table('doctor_has_locations')
                ->whereIn('location_id', $userLocations)
                ->whereIn('user_id', $roleHasUsers)
                ->distinct()
                ->pluck('user_id');
        } else {
            $doctorIds = DB::table('doctor_has_locations')
                ->where('location_id', $centreId)
                ->whereIn('user_id', $roleHasUsers)
                ->distinct()
                ->pluck('user_id');
        }

        $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();

        if ($allSellerIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No doctors found for the selected location.',
                'data' => [],
            ]);
        }

        $reportQuery = PackageService::query()
            ->join('users', 'package_services.sold_by', '=', 'users.id')
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->whereIn('package_services.sold_by', $allSellerIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');

        // Apply location filter
        if ($centreId !== 'all') {
            $reportQuery->where('packages.location_id', $centreId);
        } else {
            $userLocations = \App\Helpers\ACL::getUserCentres();
            $reportQuery->whereIn('packages.location_id', $userLocations);
        }

        // Fetch upselling data (only non-primary consultation sales)
        $reportData = $reportQuery
            ->select(
                'users.name as doctor_name',
                'package_services.sold_by as doctor_id',
                DB::raw("
                    SUM(
                        CASE
                            WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                            THEN package_services.tax_including_price
                            ELSE 0
                        END
                    ) as total_sold_amount
                "),
                DB::raw("
                    SUM(
                        CASE
                            WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                            AND package_services.is_consumed = 1
                            AND package_services.consumed_at BETWEEN '{$startDate}' AND '{$endDate}'
                            THEN package_services.tax_including_price
                            ELSE 0
                        END
                    ) as total_consumed_amount
                ")
            )
            ->groupBy('package_services.sold_by', 'users.name')
//->havingRaw('total_sold_amount > 0') // Only include doctors with actual upselling
            ->orderBy('total_sold_amount', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Doctor upselling data retrieved successfully.',
            'data' => $reportData,
        ]);

    } catch (\Exception $e) {
        \Log::error('Doctor Upselling Data Error: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving doctor upselling data.',
            'data' => [],
        ], 500);
    }
}

// You'll also need to add this route in your web.php:
// Route::get('/admin/dashboard/doctor/upselling/data', [YourController::class, 'getDoctorUpsellingData'])->name('admin.dashboard.doctor.upselling.data');

}
