<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\PackageService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UpsellingReportController extends Controller
{
    public function index()
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);

        return view('admin.reports.upselling', get_defined_vars());
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
        $query->where('name', 'Aesthetic Doctor');
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

    return view('admin.reports.doctorUpsellingDetail', compact('detailData', 'doctorName', 'totalAmount'));
}
}
