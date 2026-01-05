<?php

namespace App\Http\Controllers;

use App;
use Gate;
use App\Helpers\DashboardHelper;
use App\Models\User;
use App\Models\Invoices;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Locations;
use App\Models\Appointments;
use App\Models\RoleHasUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\ResourceHasRota;
use App\Models\AppointmentTypes;
use App\Reports\dashboardreport;
use App\Helpers\GeneralFunctions;
use App\Models\DoctorHasLocations;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\AppointmentStatuses;
use Illuminate\Support\Facades\Auth;
use App\Models\AppointmentsDailyStats;
use App\Models\Feedback;
use Illuminate\Support\Facades\Config;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\DashboardRevenueService;
use App\Services\Dashboard\DashboardChartService;

class DashboardReportsController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    protected $statsService;
    protected $revenueService;
    protected $chartService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        DashboardStatsService $statsService,
        DashboardRevenueService $revenueService,
        DashboardChartService $chartService
    ) {
        $this->middleware('auth');
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
        
        $this->statsService = $statsService;
        $this->revenueService = $revenueService;
        $this->chartService = $chartService;
    }

    public function collectionByCentre(Request $request)
    {
        $day = $request->type ?? 'today';
        $result = $this->revenueService->getCollectionByCentre($day, $request);
        $data = $result['data'];
        $total = $result['total'];
        
        $dataArray = array_values($data[$day] ?? []);

        // Calculate percentage for each slice (skip header row at index 0)
        if (count($dataArray) > 1) {
            $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));
            for ($i = 1; $i < count($dataArray); $i++) {
                if (is_array($dataArray[$i]) && isset($dataArray[$i][0], $dataArray[$i][1])) {
                    $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
                    $dataArray[$i][0] = $dataArray[$i][0] . " (" . number_format($percentage, 1) . "%)";
                }
            }
        }

        $data[$day] = $dataArray;

        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $data,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function CollectionByServiceCategory(Request $request)
    {
        $type = '';
        if ($request->today) $type = 'today';
        elseif ($request->yesterday) $type = 'yesterday';
        elseif ($request->last7days) $type = 'last7days';
        elseif ($request->thismonth) $type = 'thismonth';
        elseif ($request->lastmonth) $type = 'lastmonth';
        
        $result = $this->revenueService->getCollectionByServiceCategory($type, $request);
        
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function RevenueByServiceCategory(Request $request)
    {
        $result = $this->revenueService->getRevenueByServiceCategory($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];
        $colors = $result['colors'];

        $day = $request->type ?: 'today';
        $dataArray = $data[$day] ?? [];

        // Calculate percentage for each slice
        if (count($dataArray) > 1) {
            $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));
            for ($i = 1; $i < count($dataArray); $i++) {
                $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
                $dataArray[$i][0] = $dataArray[$i][0] . " (" . number_format($percentage, 1) . "%)";
            }
        }

        $data[$day] = $dataArray;

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function myCollectionByCentre(Request $request)
    {
        $result = $this->revenueService->getMyCollectionByCentre($request->type ?? '', $request);
        
        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $result['data'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function revenueByCentre(Request $request)
    {
        $result = $this->revenueService->getRevenueByCentre($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];

        // Calculate percentages
        if (count($data) > 1) {
            $totalValue = array_sum(array_column(array_slice($data, 1), 1));
            for ($i = 1; $i < count($data); $i++) {
                $percentage = $totalValue != 0 ? ($data[$i][1] / $totalValue) * 100 : 0;
                $data[$i][0] = $data[$i][0] . " (" . number_format($percentage, 1) . "%)";
            }
        }

        return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
            'pie' => $data,
            'total' => number_format($total, 2),
        ]);
    }

    public function myRevenueByCentre(Request $request)
    {
        $result = $this->revenueService->getMyRevenueByCentre($request->type ?? '', $request);
        
        return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
            'pie' => $result['data'],
            'total' => number_format($result['total'], 2),
        ]);
    }

    public function revenueByService(Request $request)
    {
        $result = $this->revenueService->getRevenueByService($request->type ?? '', $request, 'dashboard_revenue_by_service');
        $data = $result['data'];
        $total = $result['total'];
        $colors = $result['colors'];

        $day = $request->type ?: 'today';
        $dataArray = $data[$day] ?? [];

        // Calculate percentage for each slice
        if (count($dataArray) > 1) {
            $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));
            for ($i = 1; $i < count($dataArray); $i++) {
                $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
                $dataArray[$i][0] = $dataArray[$i][0] . " (" . number_format($percentage, 1) . "%)";
            }
        }

        $data[$day] = $dataArray;

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function myRevenueByService(Request $request)
    {
        $result = $this->revenueService->getRevenueByService($request->period ?? '', $request, 'dashboard_my_revenue_by_service');
        
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function AppointmentByStatus(Request $request)
    {
        $day = $request->period ?: 'today';
        
        if (!Gate::allows('dashboard_appointment_by_status')) {
            return ApiHelper::apiResponse($this->success, 'service data', true, [
                'pie' => [],
                'colors' => [],
                'total' => 0,
            ]);
        }

        $result = $this->chartService->getAppointmentByStatus($day, $request->type, $request->get('performance'));
        $chartData = $result['chartData'];
        $colors = $result['colors'];

        // Calculate percentages
        if (count($chartData) > 1) {
            $totalValue = array_sum(array_column(array_slice($chartData, 1), 1));
            for ($i = 1; $i < count($chartData); $i++) {
                $percentage = $totalValue != 0 ? ($chartData[$i][1] / $totalValue) * 100 : 0;
                $chartData[$i][0] = $chartData[$i][0] . " (" . number_format($percentage, 1) . "%)";
            }
        }

        $data[$day] = $chartData;

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => 0,
        ]);
    }

    public function AppointmentByType(Request $request)
    {
        $period = $request->period ?: 'today';
        $result = $this->chartService->getAppointmentByType($period, $request->get('performance'));
        
        $data = [];
        $data[$period] = $result['chartData'];

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $result['colors'] ?: ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'],
            'total' => $result['total'],
        ]);
    }

    public function getChild(Request $request)
    {
        if ($request->child_id) {
            $service = Services::find($request->child_id);

            return ApiHelper::apiResponse($this->success, 'service data', true, [
                'child' => $service->name ?? 'N/A',
            ]);
        } else {
            return ApiHelper::apiResponse($this->success, 'service data', true, [
                'child' => 'N/A',
            ]);
        }
    }

    public function CentreWiseArrival(Request $request)
{
    $lables = [];
    $total_apts = [];
    $arrived_apts = [];
    $walkin_apts = [];
    
    try {
        $period = $request->period == '' ? 'thismonth' : $request->period;
        $center_ids = $request->centre_id == 'All' ? DashboardHelper::getUserCentres() : [$request->centre_id];

        // Fetch all locations in a single query and filter valid ones (ntn or stn not null)
        $locations = Locations::whereIn('id', $center_ids)
            ->where(function ($q) {
                $q->whereNotNull('ntn')->orWhereNotNull('stn');
            })
            ->pluck('name', 'id')
            ->toArray();
        
        // Get only valid center IDs
        $validCenterIds = array_keys($locations);
        
        if (empty($validCenterIds)) {
            return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
                'bar' => [],
                'total' => [],
                'arrived' => [],
                'walkin' => [],
            ]);
        }

        // Get FDM role and users in optimized queries
        $fdm_role = Role::where('name', 'FDM')->first();
        $fdm_users = $fdm_role ? RoleHasUsers::where('role_id', $fdm_role->id)->pluck('user_id')->toArray() : [];

        $periods = [
            'today' => [
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
        ];

        // Get arrived and converted appointment status IDs
        $accountId = Auth::User()->account_id;
        $statusIds = \App\Models\AppointmentStatuses::where('account_id', $accountId)
            ->where(function ($q) {
                $q->where('is_arrived', 1)->orWhere('is_converted', 1);
            })
            ->pluck('id')
            ->toArray();
        
        $arrivedStatusIds = !empty($statusIds) ? $statusIds : [2, 16];

        // Build query with proper parameter binding
        $query = AppointmentsDailyStats::select('centre_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN appointment_status_id IN (' . implode(',', array_map('intval', $arrivedStatusIds)) . ') THEN 1 ELSE 0 END) as arrived')
            ->whereBetween('scheduled_date', [$periods[$period]['start_date'], $periods[$period]['end_date']])
            ->whereIn('centre_id', $validCenterIds)
            ->groupBy('centre_id');

        // Add walkin calculation only if FDM users exist
        if (!empty($fdm_users)) {
            $fdmUserIds = implode(',', array_map('intval', $fdm_users));
            $arrivedIds = implode(',', array_map('intval', $arrivedStatusIds));
            $query->selectRaw("SUM(CASE WHEN appointment_status_id IN ({$arrivedIds}) AND user_id IN ({$fdmUserIds}) THEN 1 ELSE 0 END) as walkin");
        } else {
            $query->selectRaw('0 as walkin');
        }

        $stats = $query->get()->keyBy('centre_id')->toArray();

        // Build result arrays using pre-fetched locations map
        foreach ($validCenterIds as $centreId) {
            $centreName = $locations[$centreId] ?? null;
            if ($centreName) {
                $lables[] = $centreName;
                $total_apts[] = isset($stats[$centreId]) ? (int) $stats[$centreId]['total'] : 0;
                $arrived_apts[] = isset($stats[$centreId]) ? (int) $stats[$centreId]['arrived'] : 0;
                $walkin_apts[] = isset($stats[$centreId]) ? (int) $stats[$centreId]['walkin'] : 0;
            }
        }

        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total' => $total_apts,
            'arrived' => $arrived_apts,
            'walkin' => $walkin_apts,
        ]);
        
    } catch (\Exception $e) {
        \Log::error('CentreWiseArrival Error: ' . $e->getMessage());
        
        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => [],
            'total' => [],
            'arrived' => [],
            'walkin' => [],
        ]);
    }
}

    public function CSRWiseArrival(Request $request)
    {
        $period = $request->period ?: 'thismonth';
        $result = $this->chartService->getCSRWiseArrivalStats($period, $request->user_id ?? 'All');

        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $result['labels'],
            'total' => $result['total'],
            'arrived' => $result['arrived'],
        ]);
    }

    public function CallWiseArrival(Request $request)
    {
        $period = $request->period ?: 'today';
        $result = $this->chartService->getCallWiseArrival($period, $request->user_id);

        return ApiHelper::apiResponse($this->success, 'csr wise arrival data', true, [
            'bar' => $result['labels'],
            'total' => $result['total'],
            'arrived' => $result['arrived'],
        ]);
    }

    public function DoctoreWiseConversion(Request $request)
    {
        $total_apts = $converted_apts = $lables = [];
        $appointments_info = [];
        $total = 0;
        $period = $request->period;
        $returnCategoryData = [];
        $sum_conversion_spend2 = 0;
        $periods = GeneralFunctions::GetPeriods();
        $where_not = ['All Centres', 'All South Region', 'All Central Region'];
        $startDate = $periods[$period]['start_date'];
        $endDate = $periods[$period]['end_date'];

        // Get locations
        if ($request->centre_id == 'all') {
            $locations = Locations::whereNotIn('name', $where_not)->where('active', 1)->pluck('id')->toArray();
        } else {
            $locations = [$request->centre_id];
        }

        // Get consultants in single optimized query
        $consultantIds = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locations)
            ->when($request->doc_id && $request->doc_id != 0 && $request->doc_id != "all-docs", function ($query) use ($request) {
                return $query->where('user_id', $request->doc_id);
            })
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        $consultants = User::whereIn('id', $consultantIds)->where('active', 1)->get()->keyBy('id');

        if (empty($consultantIds)) {
            return ApiHelper::apiResponse($this->success, 'doctor wise conversion data', true, [
                'labels' => [],
                'total_appointments' => [],
                'converted_appointments' => [],
                'categories' => [],
                'category_total' => [],
                'sum_val' => 0
            ]);
        }

        // Get arrived and converted status IDs in single query
        $statusInfo = AppointmentStatuses::where('account_id', Auth::User()->account_id)
            ->where(function($q) {
                $q->where('is_arrived', 1)->orWhere('is_converted', 1);
            })->get();
        
        $arrivedStatusId = $statusInfo->firstWhere('is_arrived', 1)->id ?? config('constants.appointment_status_arrived');
        $convertedStatusId = $statusInfo->firstWhere('is_converted', 1)->id ?? null;

        // Build status condition closure
        $statusCondition = function($query) use ($arrivedStatusId, $convertedStatusId) {
            $query->where('appointments.base_appointment_status_id', $arrivedStatusId);
            if ($convertedStatusId) {
                $query->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
            }
        };

        // Get converted appointments with eager loading (fixes N+1)
        $converted_appointments = Appointments::with(['location:id,name', 'patient:id,name,phone', 'doctor:id,name', 'service:id,name', 'region:id,name', 'city:id,name'])
            ->leftJoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->where($statusCondition)
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereIn('appointments.location_id', $locations)
            ->where('package_advances.cash_amount', '>', 0)
            ->where('package_advances.created_at', '>=', $startDate . ' 00:00:00')
            ->where('package_advances.created_at', '<=', $endDate . ' 23:59:59')
            ->select('appointments.*')
            ->distinct()
            ->get();

        $appointmentIds = $converted_appointments->pluck('id')->toArray();

        if (!empty($appointmentIds)) {
            // Bulk fetch invoices (fixes N+1)
            $invoices = Invoices::whereIn('appointment_id', $appointmentIds)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('appointment_id')
                ->map(fn($group) => $group->first());

            // Bulk fetch packages (fixes N+1)
            $packages = Packages::whereIn('appointment_id', $appointmentIds)->get()->keyBy('appointment_id');
            $packageIds = $packages->pluck('id')->toArray();

            // Bulk fetch package bundles (fixes N+1)
            $packageBundles = PackageBundles::whereIn('package_id', $packageIds)->get()->groupBy('package_id');

            // Bulk fetch package services existence check
            $allBundleIds = $packageBundles->flatten()->pluck('id')->toArray();
            $packageServicesExist = PackageService::whereIn('package_bundle_id', $allBundleIds)
                ->select('package_bundle_id', DB::raw('MIN(created_at) as min_created_at'))
                ->groupBy('package_bundle_id')
                ->get()
                ->keyBy('package_bundle_id');

            // Bulk fetch first payments per package (fixes N+1)
            $firstPayments = PackageAdvances::whereIn('package_id', $packageIds)
                ->where('cash_flow', 'in')
                ->where('cash_amount', '>', 0)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('package_id')
                ->map(fn($group) => $group->first());

            // Bulk fetch all package advances for conversion spend (fixes N+1)
            $allPackageAdvances = PackageAdvances::whereIn('package_id', $packageIds)
                ->where('cash_amount', '>', 0)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $startDate . ' 00:00:00')
                ->where('created_at', '<=', $endDate . ' 23:59:59')
                ->get()
                ->groupBy('package_id');

            $canViewContact = Gate::allows('contact');
            $processedAppointments = [];

            foreach ($converted_appointments as $appointment) {
                if (in_array($appointment->id, $processedAppointments)) {
                    continue;
                }
                $processedAppointments[] = $appointment->id;

                // Build appointment info
                $phoneNumber = $canViewContact ? ($appointment->patient->phone ?? '') : '***********';
                $appointments_info[$appointment->id] = [
                    'patient_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'doctor' => $appointment->doctor->name ?? '',
                    'client' => $appointment->patient->name ?? '',
                    'phone' => $phoneNumber,
                    'service' => $appointment->service->name ?? '',
                    'service_id' => $appointment->service->id ?? 0,
                    'region' => $appointment->region->name ?? '',
                    'city' => $appointment->city->name ?? '',
                    'centre' => $appointment->location->name ?? '',
                    'doi' => Carbon::parse($appointment->created_at)->format('M d Y'),
                    'converted' => '',
                    'conversion_spend' => '',
                    'conversion_date' => '',
                ];

                // Get invoice
                $invoice = $invoices->get($appointment->id);
                if (!$invoice) continue;

                $invoiceDate = Carbon::parse($invoice->created_at)->format('Y-m-d');

                // Get package
                $package = $packages->get($appointment->id);
                if (!$package) continue;

                // Check package services exist after invoice date
                $bundleIds = $packageBundles->get($package->id, collect())->pluck('id')->toArray();
                $hasServiceAfterInvoice = false;
                foreach ($bundleIds as $bundleId) {
                    $serviceInfo = $packageServicesExist->get($bundleId);
                    if ($serviceInfo && Carbon::parse($serviceInfo->min_created_at)->format('Y-m-d') >= $invoiceDate) {
                        $hasServiceAfterInvoice = true;
                        break;
                    }
                }
                if (!$hasServiceAfterInvoice) continue;

                // Get first payment
                $firstPayment = $firstPayments->get($package->id);
                if (!$firstPayment) continue;

                $firstPaymentDate = Carbon::parse($firstPayment->created_at)->format('Y-m-d');
                if ($firstPaymentDate < $invoiceDate) continue;
                if ($firstPaymentDate < $startDate || $firstPaymentDate > $endDate) continue;

                // Calculate conversion spend
                $packagesadvances = $allPackageAdvances->get($package->id, collect())
                    ->filter(fn($pa) => Carbon::parse($pa->created_at)->format('Y-m-d') >= $invoiceDate);

                if ($packagesadvances->isNotEmpty()) {
                    $revenue_in = 0;
                    $out = 0;

                    $appointments_info[$appointment->id]['converted'] = 'Yes';
                    foreach ($packagesadvances as $packagesadvance) {
                        $package_advance = GeneralFunctions::genericfunctionforstaffwiserevenue($packagesadvance);
                        if ($package_advance) {
                            $revenue_in += (float) ($package_advance['revenue'] ?? 0);
                            $out += (float) ($package_advance['refund_out'] ?? 0);
                        }
                    }
                    $actual = $revenue_in - $out;
                    $appointments_info[$appointment->id]['conversion_spend'] = $actual;
                    $appointments_info[$appointment->id]['conversion_date'] = $firstPayment->created_at;
                    $total += $actual;
                }
            }
        }

        // Get total appointments per doctor in single query (fixes N+1 loop)
        $doctorAppointmentCounts = Appointments::whereBetween('scheduled_date', [$startDate, $endDate])
            ->where('appointment_type_id', 1)
            ->where($statusCondition)
            ->whereIn('doctor_id', $consultantIds)
            ->whereIn('location_id', $locations)
            ->select('doctor_id', DB::raw('COUNT(*) as total'))
            ->groupBy('doctor_id')
            ->get()
            ->keyBy('doctor_id');

        // Build labels and counts
        $appointmentsCollection = collect($appointments_info);
        foreach ($consultants as $doctorId => $doctor) {
            $lables[] = $doctor->name;
            $total_apts[] = $doctorAppointmentCounts->get($doctorId)->total ?? 0;
            $converted_apts[] = $appointmentsCollection
                ->where('doctor_id', $doctorId)
                ->where('conversion_spend', '!=', '')
                ->count();
        }

        // Get service-wise arrivals in single query
        $total_arrived_appointments = Appointments::join('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.appointment_type_id', 1)
            ->where($statusCondition)
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereIn('appointments.location_id', $locations)
            ->where('appointments.scheduled_date', '>=', $startDate)
            ->where('appointments.scheduled_date', '<=', $endDate)
            ->select('appointments.service_id', 'services.name', DB::raw('COUNT(*) as arrived'))
            ->groupBy('appointments.service_id', 'services.name')
            ->get();

        // Get service-wise category counts in single query (fixes N+1 loop)
        $categoryCountsQuery = Appointments::where('appointment_type_id', 1)
            ->where($statusCondition)
            ->whereIn('location_id', $locations)
            ->where('scheduled_date', '>=', $startDate)
            ->where('scheduled_date', '<=', $endDate);

        if ($request->doc_id) {
            $categoryCountsQuery->whereIn('doctor_id', $consultantIds);
        }

        $categoryCounts = $categoryCountsQuery
            ->select('service_id', DB::raw('COUNT(*) as total'))
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        // Process conversion data
        $maxConversion = $appointmentsCollection->filter(fn($apt) => ($apt['conversion_spend'] ?? 0) > 0)->groupBy('service_id');
        $new_array = [];

        foreach ($maxConversion as $serviceId => $conversions) {
            $sum_conversion_spend = $conversions->sum('conversion_spend');
            $sum_conversion_spend2 += $sum_conversion_spend;
            $serviceName = $conversions->first()['service'] ?? '';
            $new_array[$serviceName] = [
                'service' => $serviceName,
                'total_conversion' => $conversions->count(),
                'avg' => $sum_conversion_spend / $conversions->count(),
            ];
        }

        // Build return category data
        $processedCategories = [];
        foreach ($total_arrived_appointments as $arrive_category) {
            $name = $arrive_category->name;
            $category_total_records = $categoryCounts->get($arrive_category->service_id)->total ?? 0;

            if (isset($new_array[$name])) {
                $returnCategoryData[] = [
                    'service' => $name,
                    'total_arrival' => $category_total_records,
                    'total_conversion' => $new_array[$name]['total_conversion'],
                    'avg' => $new_array[$name]['avg']
                ];
            } else {
                $returnCategoryData[] = [
                    'service' => $name,
                    'total_arrival' => $category_total_records,
                    'total_conversion' => 0,
                    'avg' => 0
                ];
            }
            $processedCategories[] = $name;
        }

        // Add categories with conversions but no arrivals
        foreach ($new_array as $category_name => $category_data) {
            if (!in_array($category_name, $processedCategories)) {
                $returnCategoryData[] = [
                    'service' => $category_name,
                    'total_arrival' => 0,
                    'total_conversion' => $category_data['total_conversion'],
                    'avg' => $category_data['avg']
                ];
            }
        }

        return ApiHelper::apiResponse($this->success, 'doctor wise conversion data', true, [
            'labels' => $lables,
            'total_appointments' => $total_apts,
            'converted_appointments' => $converted_apts,
            'categories' => $returnCategoryData,
            'category_total' => $total_arrived_appointments,
            'sum_val' => $sum_conversion_spend2
        ]);
    }
    public function DoctoreWiseFeedback(Request $request)
    {
        $period = $request->period;
        $centreId = $request->centre_id;

        // Get date range from period
        $dateRanges = GeneralFunctions::GetPeriods($period);
        $dateRange = ($period == "all") ? null : ($dateRanges[$period] ?? null);

        // Get relevant location IDs
        $whereNot = ['All Centres', 'All South Region', 'All Central Region'];
        if ($centreId === 'all') {
            $locationIds = Locations::whereNotIn('name', $whereNot)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();
        } else {
            $locationIds = [$centreId];
        }

        // Get doctors assigned to those locations
        $doctorIds = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locationIds)
            ->when($request->doc_id && $request->doc_id !== '0' && $request->doc_id !== 'all-docs', function ($query) use ($request) {
                return $query->where('user_id', $request->doc_id);
            })
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        if (empty($doctorIds)) {
            return ApiHelper::apiResponse($this->success, 'Doctor wise feedback data', true, [
                'labels' => [],
                'rating' => [],
                'total' => []
            ]);
        }

        // Get active doctors keyed by ID
        $doctors = User::whereIn('id', $doctorIds)
            ->where('active', 1)
            ->get()
            ->keyBy('id');

        // Build single query for all doctors' feedback stats (fixes N+1)
        $feedbackQuery = Feedback::whereIn('feedback.doctor_id', $doctorIds);
        
        // Apply location filter if specific centre
        if ($centreId !== 'all') {
            $feedbackQuery->where('feedback.location_id', $centreId);
        }

        // Apply date range filter via join instead of whereHas for better performance
        if ($dateRange) {
            $feedbackQuery->join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
                ->where('appointments.scheduled_date', '>=', $dateRange['start_date'] . ' 00:00:00')
                ->where('appointments.scheduled_date', '<=', $dateRange['end_date'] . ' 23:59:59');
        }

        // Get aggregated stats per doctor in single query
        $feedbackStats = $feedbackQuery
            ->select('feedback.doctor_id', DB::raw('AVG(feedback.rating) as avg_rating'), DB::raw('COUNT(*) as total_feedbacks'))
            ->groupBy('feedback.doctor_id')
            ->get()
            ->keyBy('doctor_id');

        // Build doctor ratings array
        $doctorRatings = [];
        foreach ($doctors as $doctorId => $doctor) {
            $stats = $feedbackStats->get($doctorId);
            $doctorRatings[] = [
                'name' => $doctor->name,
                'rating' => round($stats->avg_rating ?? 0, 2),
                'total' => $stats->total_feedbacks ?? 0,
            ];
        }

        // Sort by rating descending
        usort($doctorRatings, fn($a, $b) => $b['rating'] <=> $a['rating']);

        return ApiHelper::apiResponse($this->success, 'Doctor wise feedback data', true, [
            'labels' => array_column($doctorRatings, 'name'),
            'rating' => array_column($doctorRatings, 'rating'),
            'total' => array_column($doctorRatings, 'total')
        ]);
    }
    public function AllDoctorsWiseConversion(Request $request)
    {
        $total_apts = [];
        $converted_apts = [];
        $lables = [];
        $appointments = array();
        $total = 0;
        $appointments_info = array();
        $period = $request->period;
        $returnCategoryData = [];
        $total_arrived_appointments = 0;
        $periods = GeneralFunctions::GetPeriods();
        $where_not = ['All Centres', 'All South Region', 'All Central Region'];
        if ($request->centre_id == 'all') {
            $locations = Locations::whereNotIn('name', $where_not)->where(['active' => 1])->pluck('id');
        } else {
            $locations = $request->centre_id;
        }
        $consultants = DoctorHasLocations::whereIn('location_id', $locations)->when($request->doc_id != null, function ($query) use ($request) {
            return $query->whereIn('user_id', [$request->doc_id]);
        })
            ->distinct('user_id')
            ->pluck('user_id');

        $total_arrived_appointments = Appointments::with('location:id,name')
            ->join('services', 'appointments.service_id', 'services.id')
            ->where([
                'appointments.base_appointment_status_id' => config('constants.appointment_status_arrived'),
                'appointments.appointment_type_id' => 1
            ])
            ->whereIn('appointments.doctor_id', $consultants)
            ->whereIn('appointments.location_id', $locations)
            ->selectRaw('count(*) as arrived, service_id,services.name')
            ->whereBetween('appointments.scheduled_date', [
                $periods[$period]['start_date'],
                $periods[$period]['end_date']
            ])
            ->groupBy('service_id')
            ->get();
        foreach ($locations as $location) {
            $location_name = Locations::find($location);
            if ($location_name) {
                array_push($lables, $location_name->name);
            }

            $converted_appointments =  Appointments::with('location:id,name')
                ->leftjoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
                ->where([
                    'appointments.base_appointment_status_id' => config('constants.appointment_status_arrived'),
                    'appointments.appointment_type_id' => 1
                ])
                ->whereIn('appointments.doctor_id', $consultants)
                ->where(['appointments.location_id' => $location])
                ->where('package_advances.cash_amount', '>', 0)
                ->select('appointments.*')
                ->where('package_advances.created_at', '>=', $periods[$period]['start_date'] . ' 00:00:00')
                ->where('package_advances.created_at', '<=', $periods[$period]['end_date'] . ' 23:59:59')

                ->get();

            if (count($converted_appointments)) {
                foreach ($converted_appointments as $appointment) {
                    if (!in_array($appointment->id, $appointments)) {
                        if(Gate::allows('contact')){
                        $phoneNumber = $appointment->patient->phone;
                        }else{
                            $phoneNumber ='***********';
                        }
                        $appointments_info[$appointment->id] = array(
                            'patient_id' => $appointment->patient_id,
                            'appointment_id' => $appointment->id,
                            'doctor_id' => $appointment->doctor_id,
                            'doctor' => $appointment->doctor->name,
                            'client' => $appointment->patient->name,
                            'phone' =>$phoneNumber,
                            'service' => $appointment->service->name,
                            'service_id' => $appointment->service->id,
                            'region' => $appointment->region->name,
                            'city' => $appointment->city->name,
                            'centre' => $appointment->location->name,
                            'doi' => \Carbon\Carbon::parse($appointment->created_at)->format('M d Y'),
                            'converted' => '',
                            'conversion_spend' => '',
                            'conversion_date' => '',
                        );
                    }
                    $appointments[] = $appointment->id;
                    $package_info = PackageAdvances::where(['appointment_id' => $appointment->id])->pluck('id');
                    if (count($package_info)) {
                        $actual = 0;
                        $revenue_in = 0;
                        $out = 0;
                        $packagesadvances = PackageAdvances::whereIn('id', $package_info)
                            ->where(['cash_flow' => "in"])
                            ->where('cash_amount', '>', 0)
                            ->where('package_advances.created_at', '>=', $periods[$period]['start_date'] . ' 00:00:00')
                            ->where('package_advances.created_at', '<=', $periods[$period]['end_date'] . ' 23:59:59')

                            ->get();

                        if (count($packagesadvances) > 0) {
                            $first_advance = PackageAdvances::whereIn('id', $package_info)
                                ->where('cash_amount', '>', 0)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            $date = Carbon::parse($first_advance->updated_at)->format('Y-m-d');
                            if (($date >= $periods[$period]['start_date']) && ($date <= $periods[$period]['end_date'])) {
                                $appointments_info[$appointment->id]['converted'] = 'Yes';
                                foreach ($packagesadvances as $packagesadvance) {
                                    $package_advance = GeneralFunctions::genericfunctionforstaffwiserevenue($packagesadvance);
                                    if ($package_advance) {
                                        $revenue_in += $package_advance['revenue'] ? $package_advance['revenue'] : 0;
                                        $out += $package_advance['refund_out'] ? $package_advance['refund_out'] : 0;
                                    }
                                }
                                $actual = $revenue_in - $out;
                                $appointments_info[$appointment->id]['conversion_spend'] = $actual;
                                $appointments_info[$appointment->id]['converted'] = 'Yes';
                                $appointments_info[$appointment->id]['conversion_date'] = $first_advance->created_at;
                                $count[$appointment->location->id][] = 1;
                                $locationData[$appointment->location->name]['total_count'] = count($count[$appointment->location->id]);
                                if ($appointment['converted'] != '') {
                                    $arrived_count[$appointment->location->id][] = 1;
                                    $locationData[$appointment->location->name]['total_count'] = count($arrived_count[$appointment->location->id]);
                                }
                                $total += $appointments_info[$appointment->id]['conversion_spend'] ? $appointments_info[$appointment->id]['conversion_spend'] : 0;
                                $locationData[$appointment->location->name]['total'] = $total;
                            }
                        }
                    }
                }
            }

            $total_appointments = Appointments::whereBetween('scheduled_date', [$periods[$period]['start_date'], $periods[$period]['end_date']])
                ->where(['appointment_type_id' => 1, 'base_appointment_status_id' => 2, 'appointments.location_id' => $location])
                ->whereIn('appointments.doctor_id', $consultants)
                ->count();

            array_push($converted_apts, collect($appointments_info)
                ->whereIn('appointment_id', $converted_appointments->pluck('id')->toArray())
                ->where('conversion_spend', '!=', "")->count());
            array_push($total_apts, $total_appointments);

            $maxConversion = collect($appointments_info)->filter(function ($appointment) {
                if ($appointment['conversion_spend'] > 0) {
                    return $appointment;
                }
            });
            $maxConversion = $maxConversion->groupBy('service_id');
            $new_array = [];
            $sum_conversion_spend2 = 0;
            foreach ($maxConversion as $key => $conversions) {
                $sum_conversion_total = 0;
                $sum_conversion_spend = 0;
                foreach ($conversions as $conversion) {
                    $name = $conversion['service'];
                    $sum_conversion_spend += $conversion['conversion_spend'];
                    $sum_conversion_total++;
                    $sum_conversion_spend2 += $conversion['conversion_spend'];
                }
                $avg_by_category = ($sum_conversion_spend / count($conversions));
                $new_array[$name] = [
                    'service' => $name,
                    'total_conversion' => $sum_conversion_total,
                    'avg' => $avg_by_category,
                ];
            }

            foreach ($total_arrived_appointments->toArray() as $key => $arrive_category) {
                if (array_key_exists($arrive_category['name'], $new_array)) {
                    $name = [$arrive_category['name']][0];

                    $sum_conversion_total = $new_array[$arrive_category['name']]['total_conversion'];
                    $avg_valu = $new_array[$arrive_category['name']]['avg'];

                    $category_total_records = Appointments::where(['service_id' => $arrive_category['service_id'], 'base_appointment_status_id' => 2, 'appointment_type_id' => 1])
                        ->whereIn('appointments.location_id', $locations)
                        // ->whereIn('appointments.doctor_id', $consultants)
                        ->whereBetween('scheduled_date', [$periods[$period]['start_date'], $periods[$period]['end_date']])
                        ->count();
                } else {
                    $name = [$arrive_category['name']][0];
                    $sum_conversion_total = 0;
                    $avg_valu = 0;

                    $category_total_records = Appointments::where(['service_id' => $arrive_category['service_id'], 'base_appointment_status_id' => 2, 'appointment_type_id' => 1])
                        ->whereIn('appointments.location_id', $locations)
                        //->whereIn('appointments.doctor_id', $consultants)
                        ->where('scheduled_date', '>=', $periods[$period]['start_date'])
                        ->where('scheduled_date', '<=', $periods[$period]['end_date'])
                        //->whereBetween('scheduled_date', [$periods[$period]['start_date'], $periods[$period]['end_date']])
                        ->count();
                }

                $returnCategoryData[$key] = [
                    'service' => $name,
                    'total_arrival' => $category_total_records,
                    'total_conversion' => $sum_conversion_total,
                    'avg' => $avg_valu
                ];
            }
        }


        return ApiHelper::apiResponse($this->success, 'doctor wise conversion data', true, [
            'labels' => $lables,
            'total_appointments' => $total_apts,
            'converted_appointments' => $converted_apts,
            'categories' => $returnCategoryData,
            'category_total' => $total_arrived_appointments,
            'sum_val' => $sum_conversion_spend2
        ]);
    }
    public function GetCentreDoctors(Request $request)
    {
        $consultants = $this->chartService->getCentreDoctors($request->centre_id ?? 'All');
        return response()->json(['status' => 1, 'doctors' => $consultants]);
    }
    public function FollowUpReport()
    {
        $locations = Locations::getActiveRecordsByCity('', DashboardHelper::getUserCentres(), Auth::User()->account_id);
        $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        return view('admin.reports.followup', get_defined_vars());
    }

    public function FollowUpReportMonthly()
    {
        $locations = Locations::getActiveRecordsByCity('', DashboardHelper::getUserCentres(), Auth::User()->account_id);
        $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        return view('admin.reports.followupmonthly', get_defined_vars());
    }
    public function loadFollowupReport(Request $request)
    {
        if (isset($request->date_range) && $request->date_range) {
            $date_range = explode(' - ', $request->date_range);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if ($request->report_type == "monthly") {
            $where = [];
            if (isset($request->date_range) && $request->date_range) {
                $where[] = ['created_at', '>=', $start_date . ' 00:00:00'];
                $where[] = ['created_at', '<=', $end_date . ' 23:59:00'];
            }
            if ($request->patient_id) {
                $where[] = ['patient_id', '=', $request->patient_id];
            }
            $data = $request->all();
            $patient_data = GeneralFunctions::LoadPatientFollowUpReportMonthly($data, $where);
            return view('admin.reports.patients_follow_up_report_monthly', get_defined_vars());
        } else {
            $where = [];
            if (isset($request->date_range) && $request->date_range) {
                $where[] = ['created_at', '>=', $start_date . ' 00:00:00'];
                $where[] = ['created_at', '<=', $end_date . ' 23:59:00'];
            }
            if ($request->patient_id) {
                $where[] = ['patient_id', '=', $request->patient_id];
            }
            $data = $request->all();
            $patient_data = GeneralFunctions::PatientFollowUpReport($data, $where);
            return view('admin.reports.patients_follow_up_report', get_defined_vars());
        }
    }
    public function ViewFeedback($doctorId)
{
    // Get all parent services (services without a parent_id)
    $parentServices = Services::where('parent_id',0)->get();

    $feedbackData = [];

    foreach ($parentServices as $service) {
        // Average rating directly on the parent service
        $parentRating = Feedback::where('doctor_id', $doctorId)
            ->where('service_id', $service->id)
            ->avg('rating');

        // Get child services of this parent
        $children = Services::where('parent_id', $service->id)->get();

        $childRatings = [];

        foreach ($children as $child) {
            $avgRating = Feedback::where('doctor_id', $doctorId)
                ->where('treatment_id', $child->id)
                ->avg('rating');

            // Only include child if it has a rating
            if ($avgRating !== null) {
                $childRatings[] = [
                    'id' => $child->id,
                    'name' => $child->name,
                    'color' => $child->color,
                    'avg_rating' => round($avgRating, 2),
                ];
            }
        }

        // Include the parent only if it or at least one child has a rating
        if ($parentRating !== null || count($childRatings) > 0) {
            $feedbackData[] = [
                'id' => $service->id,
                'name' => $service->name,
                'color' => $service->color,
                'avg_rating' => $parentRating !== null ? round($parentRating, 2) : 0,
                'treatments' => $childRatings
            ];
        }
    }

    return view('admin.reports.feedbackBarChart', compact('feedbackData'));
}

}
