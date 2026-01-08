<?php

namespace App\Http\Controllers;

use App\Helpers\DashboardHelper;
use App\Models\User;
use App\Models\Regions;
use App\Helpers\Filters;
use App\Models\Activity;
use App\Models\Invoices;
use App\Models\Services;
use App\Models\Locations;
use App\Models\AuditTrails;
use App\Models\Appointments;
use Illuminate\Http\Request;
use App\Models\AppointmentLog;
use Illuminate\Support\Carbon;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use App\Models\AuditTrailTables;
use App\Reports\dashboardreport;
use App\Models\AuditTrailActions;
use Illuminate\Support\Facades\DB;
use App\Models\AppointmentStatuses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\DashboardRevenueService;
use App\Services\Dashboard\DashboardChartService;

class HomeController extends Controller
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

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $userCentres = DashboardHelper::getUserCentres();
        $dateTimeInfo = DashboardHelper::getDateTimeInfo();
        
        // Minimal data for initial page load - stats loaded via API
        $data = [
            'today' => $dateTimeInfo['today'],
            'startWeek' => $dateTimeInfo['startWeek'],
            'month' => $dateTimeInfo['month'],
            'currentTime' => $dateTimeInfo['currentTime'],
            'location_id' => $userCentres,
            'requestType' => $request->type ?? 'today',
        ];
        
        // Get centres for dropdowns
        $centresExclude = ['All South Region', 'All Central Region', 'All Centres'];
        $data['centres'] = Locations::whereIn('id', $userCentres)
            ->whereNotIn('name', $centresExclude)
            ->where('active', 1)
            ->select('id', 'name')
            ->get();
        
        $data['firstCentre'] = $data['centres']->first();
        
        // Check user roles for conditional display
        $user = Auth::user();
        $data['isAdmin'] = $user->hasRole('Administrator') || 
            $user->hasRole('Super-Admin') || 
            $user->hasRole('Head of Operations') || 
            $user->hasRole('Finance') ||
            $user->hasRole('HRM');
        $data['isCSRRole'] = $user->hasRole('CSR Supervisor') || 
            $user->hasRole('Social Lead') || 
            $user->hasRole('CSR');
        $data['hasMultipleCentres'] = $data['isAdmin'] || count($data['centres']) > 1;
        
        // Get CSR users for CSR Supervisor/Social Lead dropdown
        if ($data['isCSRRole']) {
            $csrRoleIds = \App\Models\RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id');
            $data['csrUsers'] = User::whereIn('id', $csrRoleIds)
                ->where('active', 1)
                ->select('id', 'name')
                ->get();
        } else {
            $data['csrUsers'] = collect();
        }

        return view('admin.home', $data);
    }

    public function getStats(Request $request)
    {
        $userCentres = DashboardHelper::getUserCentres();
        [$start_date, $end_date] = DashboardHelper::getDateRangeFromRequest($request);
        $dateTimeInfo = DashboardHelper::getDateTimeInfo();
        
        // Use services for stats and revenue
        $data = $this->statsService->getConsultancies($start_date, $end_date, $userCentres);
        $data = array_merge($data, $this->statsService->getTreatments($start_date, $end_date, $userCentres));
        $data = array_merge($data, $this->revenueService->getSalesByCentre($userCentres, $start_date, $end_date));
        $data = array_merge($data, $this->revenueService->getCollectionStats($userCentres, $request->type, $request));
        
        $data['today'] = $dateTimeInfo['today'];
        $data['startWeek'] = $dateTimeInfo['startWeek'];
        $data['month'] = $dateTimeInfo['month'];
        $data['currentTime'] = $dateTimeInfo['currentTime'];
        $data['location_id'] = $userCentres;
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        $data['appointment_status_arrived'] = DashboardHelper::getArrivedStatusId();

        return response()->json(['status' => 200, 'msg' => 'All stats', 'data' => $data]);
    }

    public function getActivity(Request $request)
    {
        $data = [];
        [$start_date, $end_date] = DashboardHelper::getDateRangeFromRequest($request);
        $perPage = 10;
        $page = $request->get('page', 1);
        
        $data = $this->recentActivitiesPaginated($data, $perPage, $page);
        $data['location_id'] = DashboardHelper::getUserCentres();
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        $data['appointment_status_arrived'] = DashboardHelper::getArrivedStatusId();
        $data['current_page'] = $page;
        $data['per_page'] = $perPage;

        // If AJAX request for more activities, return JSON
        if ($request->ajax() && $page > 1) {
            return response()->json([
                'html' => view('admin.activity-items', ['finance_log' => $data['recent_activities']['finance_log']])->render(),
                'has_more' => $data['has_more'],
                'total' => $data['total_activities'],
            ]);
        }

        return view('admin.activity', $data);
    }

    public function collectionByCentre(Request $request)
    {
        $result = $this->revenueService->getCollectionByCentre($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];
        
        $day = $request->type ?? 'today';
        $dataArray = $data[$day] ?? [];

        $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));

        // Calculate the percentage for each slice
        for ($i = 1; $i < count($dataArray); $i++) {
            $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
            $dataArray[$i][0] = $dataArray[$i][0] . " (" . number_format($percentage ?? 0, 1) . "%)";
        }
        $data[$day] = $dataArray;
        
        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $data,
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

    public function CollectionByServiceCategory(Request $request)
    {
        $result = $this->revenueService->getCollectionByServiceCategory($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];
        $colors = $result['colors'];

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function revenueByCentre(Request $request)
    {
        $result = $this->revenueService->getRevenueByCentre($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];

        // Calculate percentages
        $totalValue = array_sum(array_column(array_slice($data, 1), 1));
        for ($i = 1; $i < count($data); $i++) {
            $percentage = $totalValue != 0 ? ($data[$i][1] / $totalValue) * 100 : 0;
            $data[$i][0] = $data[$i][0] . " (" . number_format($percentage ?? 0, 1) . "%)";
        }

        return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
            'pie' => $data,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function myRevenueByCentre(Request $request)
    {
        $result = $this->revenueService->getMyRevenueByCentre($request->type ?? '', $request);
        
        return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
            'pie' => $result['data'],
            'total' => number_format($result['total'] ?? 0, 2),
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
        $result = $this->revenueService->getRevenueByService($request->type ?? '', $request, 'dashboard_my_revenue_by_service');
        
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    private function getTableFilter($filters)
    {
        if (isset($filters['query']) && isset($filters['query']['filter'])) {
            return $filters['query']['filter'];
        }

        return [];
    }

    
    private function recentActivitiesPaginated($data, $perPage = 10, $page = 1)
    {
        if (!Gate::allows('dashboard_recent_activities')) {
            return [
                'recent_activities' => [
                    'finance_log' => [],
                    'unauthorized' => true,
                ],
                'has_more' => false,
                'total_activities' => 0,
            ];
        }

        $centres = DashboardHelper::getUserCentres();
        $todayStart = Carbon::today();
        $todayEnd = Carbon::tomorrow();
        
        // Get total count for pagination info
        $totalCount = Activity::whereIn('centre_id', $centres)
            ->whereIn('action', ['received', 'consumed', 'refunded'])
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->count();
        
        // Get paginated activities
        $activities = Activity::with([
            'plan' => fn ($q) => $q->select('id', 'name'),
            'centre' => fn ($q) => $q->select('id', 'name'),
            'user' => fn ($q) => $q->select('id', 'name')
        ])
            ->whereIn('centre_id', $centres)
            ->whereIn('action', ['received', 'consumed', 'refunded'])
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->latest()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        
        // Get all unique created_by IDs and fetch users in one query
        $createdByIds = $activities->pluck('created_by')->filter(function ($id) {
            return is_numeric($id) && $id > 0;
        })->unique()->values()->toArray();
        
        $users = User::whereIn('id', $createdByIds)->pluck('name', 'id');
        
        // Add created_by_name to each activity
        $activities->each(function ($activity) use ($users) {
            $createdBy = $activity->created_by;
            if (is_numeric($createdBy) && isset($users[$createdBy])) {
                $activity->created_by_name = $users[$createdBy];
            } elseif (!is_numeric($createdBy) && $createdBy) {
                $activity->created_by_name = $createdBy;
            } else {
                $activity->created_by_name = 'N/A';
            }
        });

        $data['recent_activities'] = [
            'finance_log' => $activities,
        ];
        $data['has_more'] = ($page * $perPage) < $totalCount;
        $data['total_activities'] = $totalCount;

        return $data;
    }

    private function recentActivities($data)
    {
        if (!Gate::allows('dashboard_recent_activities')) {
            return $data['recent_activities'] = [
                'finance_log' => [],
                'appointment_log' => [],
                'unauthorized' => true,
            ];
            
        }

        $centres = DashboardHelper::getUserCentres();

        // Use date range instead of whereDate for better index usage
        $todayStart = Carbon::today();
        $todayEnd = Carbon::tomorrow();
        
        // Use centre_id (integer) instead of location name (string) for better performance
        $activities = Activity::with([
            'plan' => fn ($q) => $q->select('id', 'name'),
            'centre' => fn ($q) => $q->select('id', 'name'),
            'user' => fn ($q) => $q->select('id', 'name')
        ])
            ->whereIn('centre_id', $centres)
            ->whereIn('action', ['received', 'consumed', 'refunded'])
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->latest()
            ->get();
        
        // Get all unique created_by IDs and fetch users in one query
        $createdByIds = $activities->pluck('created_by')->filter(function ($id) {
            return is_numeric($id) && $id > 0;
        })->unique()->values()->toArray();
        
        $users = User::whereIn('id', $createdByIds)->pluck('name', 'id');
        
        // Add created_by_name to each activity
        $activities->each(function ($activity) use ($users) {
            $createdBy = $activity->created_by;
            if (is_numeric($createdBy) && isset($users[$createdBy])) {
                $activity->created_by_name = $users[$createdBy];
            } elseif (!is_numeric($createdBy) && $createdBy) {
                $activity->created_by_name = $createdBy;
            } else {
                $activity->created_by_name = 'N/A';
            }
        });

        return $data['recent_activities'] = [
            'finance_log' => $activities,
        ];
    }

    private function viewAppointmentLog()
    {
        try {
            [$start, $end] = DashboardHelper::getDateRangeFromRequest(request());

            $query = AppointmentLog::where('date', '>=', $start)
                ->where('date', '<=', $end);

            if (auth()->id() != 1) {
                $query->where('user_id', auth()->id());
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function viewLog()
    {
        $appointment = AuditTrailTables::whereName('appointments')->first();

        $query = AuditTrails::has('auditTrailChanges')
            ->with('auditTrailChanges')
            ->where('audit_trail_table_name', '=', $appointment->id)
            ->whereDate('created_at', Carbon::now()->format('Y-m-d'))->orderBy('created_at', 'DESC');
        if (auth()->id() != 1) {
            $query->where('user_id', auth()->id());
        }

        $audit_trails = $query->get();

        $data = [];

        foreach ($audit_trails as $audit_trail) {

            $audit_trail_action = AuditTrailActions::find($audit_trail->audit_trail_action_name);

            $data[$audit_trail->id] = [
                'action' => $audit_trail_action->name,
                'user_id' => $audit_trail->userr->name,
                'created_at' => $audit_trail->created_at,
            ];

            foreach ($audit_trail->auditTrailChanges as $auditTrailChange) {

                switch ($auditTrailChange->field_name) {
                    case 'scheduled_date':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'scheduled_time':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'name':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'patient_id':
                        $data[$audit_trail->id]['phone'] = $auditTrailChange->user->phone;
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'appointment_type_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->AppointmentType->name;
                        break;
                    case 'base_appointment_status_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentStatus->name;
                        break;
                    case 'appointment_status_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentStatus->name;
                        break;
                    case 'created_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'updated_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'converted_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'service_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->service->name;
                        break;
                    case 'doctor_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = isset($auditTrailChange->doctor) ? $auditTrailChange->doctor->name : 'N/A';
                        break;
                    case 'resource_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = isset($auditTrailChange->resource) ? $auditTrailChange->resource->name : 'N/A';
                        break;
                    case 'region_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->region->name;
                        break;
                    case 'city_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->city->name;
                        break;
                    case 'location_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->location->name;
                        break;
                    case 'send_message':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    default:
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                }

                if (!isset($data[$audit_trail->id]['scheduled_date'])) {

                    unset($data[$audit_trail->id]);
                }

                if (!isset($data[$audit_trail->id]['appointment_type_id'])) {

                    unset($data[$audit_trail->id]);
                }
            }
        }

        return $data;
    }
}
