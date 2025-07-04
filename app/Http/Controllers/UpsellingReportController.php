<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\PackageService;
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
        $role = Role::where('name', 'Aesthetic Doctor')->first();
        $roleHasUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Aesthetic Doctor');
        })->pluck('id');
        
        // Step 1: Get doctors for the location
        $doctorIds = DB::table('doctor_has_locations')
        ->where('location_id', $locationId)
        ->whereIn('user_id', $roleHasUsers)
         ->distinct()
        ->pluck('user_id');

    if ($doctorIds->isEmpty()) {
        return response()->json([
            'status' => 200,
            'message' => 'No doctors found for the selected location.',
            'data' => [],
        ]);
    }

    $reportQuery = PackageService::query()
        ->join('users', 'package_services.sold_by', '=', 'users.id')
        ->join('packages', 'package_services.package_id', '=', 'packages.id') // Join packages
        ->whereIn('package_services.sold_by', $doctorIds)
        ->where('packages.location_id', $locationId); // Filter by location

    // Apply date range filter on created_at
    if ($startDate && $endDate) {
        $reportQuery->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');
    }

    // Fetch report data
    $reportData = $reportQuery
    ->join('appointments', 'packages.appointment_id', '=', 'appointments.id') // Join appointments
    ->select(
        'users.name as doctor_name',
        'package_services.sold_by as doctor_id',
        DB::raw("
            SUM(
                CASE
                    WHEN appointments.doctor_id != package_services.sold_by
                    THEN package_services.tax_including_price
                    ELSE 0
                END
            ) as total_sold_amount
        "),
        DB::raw("
            SUM(
                CASE
                    WHEN package_services.consumed_at IS NOT NULL
                        AND appointments.doctor_id != package_services.sold_by
                        " . ($startDate && $endDate ? "AND package_services.consumed_at BETWEEN '{$startDate}' AND '{$endDate}'" : "") . "
                    THEN package_services.tax_including_price
                    ELSE 0
                END
            ) as total_consumed_amount
        ")
    )
    ->groupBy('package_services.sold_by', 'users.name')
    ->get();


        return view('admin.reports.upsellingReport', compact('reportData'));
    }
}
