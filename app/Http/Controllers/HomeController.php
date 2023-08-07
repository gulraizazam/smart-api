<?php

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Activity;
use App\Models\AppointmentLog;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AuditTrailActions;
use App\Models\AuditTrails;
use App\Models\AuditTrailTables;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Reports\dashboardreport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HomeController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $data = [];
        $timeZone = 'Asia/Karachi';
        $location_id = $this->getUserLocation();
        [$start_date, $end_date] = $this->getDates($request);
        $data = $this->consultancies($data, $start_date, $end_date);
        $data = $this->treatments($data, $start_date, $end_date);
        $data = $this->salesByCentre($request, $data);
        $data = $this->collection_by_center($request, $data);
        $data['today'] = Carbon::now()->timezone($timeZone)->format('Y-m-d');
        $data['startWeek'] = Carbon::now()->timezone($timeZone)->startOfWeek()->format('Y-m-d');
        $data['month'] = Carbon::now()->timezone($timeZone)->format('Y-m-d');
        $data['currentTime'] = Carbon::now()->timezone($timeZone)->format('H:i:s');
        if (auth()->id() == 1) {
            $data['location_id'] = [];
        } else {
            $data['location_id'] = ACL::getUserCentres();
        }
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        $data['appointment_status_arrived'] = config('constants.appointment_status_arrived');

        return view('admin.home', $data);
    }

    public function getStats(Request $request)
    {
        $data = [];
        $timeZone = 'Asia/Karachi';
        $location_id = $this->getUserLocation();
        [$start_date, $end_date] = $this->getDates($request);
        $data = $this->consultancies($data, $start_date, $end_date);
        $data = $this->treatments($data, $start_date, $end_date);
        $data = $this->salesByCentre($request, $data);
        $data = $this->collection_by_center($request, $data);
        $data['today'] = Carbon::now()->timezone($timeZone)->format('Y-m-d');
        $data['startWeek'] = Carbon::now()->timezone($timeZone)->startOfWeek()->format('Y-m-d');
        $data['month'] = Carbon::now()->timezone($timeZone)->format('Y-m-d');
        $data['currentTime'] = Carbon::now()->timezone($timeZone)->format('H:i:s');
        if (auth()->id() == 1) {
            $data['location_id'] = [];
        } else {
            $data['location_id'] = ACL::getUserCentres();
        }
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        $data['appointment_status_arrived'] = config('constants.appointment_status_arrived');

        return response()->json(['status' => 200, 'msg' => 'All stats', 'data' => $data]);
    }

    public function getActivity(Request $request)
    {
        $data = [];
        $timeZone = 'Asia/Karachi';
        $location_id = $this->getUserLocation();
        [$start_date, $end_date] = $this->getDates($request);
        $data = $this->recentActivities($data);
        if (auth()->id() == 1) {
            $data['location_id'] = [];
        } else {
            $data['location_id'] = ACL::getUserCentres();
        }
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        $data['appointment_status_arrived'] = config('constants.appointment_status_arrived');

        return view('admin.activity', $data);
    }

    public function datatable(Request $request)
    {
        if (! Gate::allows('dashboard_upcomings')) {
            return [];
        }
        $filter = $this->getTableFilter($request->all());

        $today = Carbon::now()->format('Y-m-d');

        $todayTime = Carbon::now()->timezone('Asia/Karachi')->format('H:i').':00';

        if ($request->has('sort')) {

            [$orderBy, $order] = getSortBy($request, 'scheduled_date', 'DESC', 'appointments');

            Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'appointments', 'order', $order);
        } else {

            $orderBy = 'scheduled_date';
            $order = 'desc';
            if ($orderBy == 'scheduled_date') {
                $orderBy = 'appointments.scheduled_date';

                Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'appointments', 'order', $order);
            }
        }

        $countQuery = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->where('appointment_status_id', config('constants.appointment_status_pending'))
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        $appoints = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->where('appointment_status_id', config('constants.appointment_status_pending'))
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        $todayAppoints = 0;
        if (hasFilter($filter, 'type') && $filter['type'] != 'today') {

            $todayAppoints = $appoints->whereDate('scheduled_date', $today)
                ->where('appointments.scheduled_time', '>=', $todayTime)->count();
        }

        if (hasFilter($filter, 'type') && $filter['type'] == 'week') {

            $end_week = Carbon::parse($today)->addDays(6)->format('Y-m-d');

            $where[] = [
                'appointments.scheduled_date',
                '>',
                $today,
            ];
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $end_week,
            ];

            $countQuery->where($where);

        } elseif (hasFilter($filter, 'type') && $filter['type'] == 'month') {

            $end_month = Carbon::parse($today)->addMonth()->format('Y-m-d');

            $where[] = [
                'appointments.scheduled_date',
                '>',
                $today,
            ];
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $end_month,
            ];

            $countQuery->where($where);
        } else {
            $countQuery->whereDate('appointments.scheduled_date', $today);

            $countQuery->where('appointments.scheduled_time', '>=', $todayTime);
        }

        $iTotalRecords = $countQuery->count() + $todayAppoints;

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $records = [];
        $records['data'] = [];

        $resultQuery = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->where('appointment_status_id', config('constants.appointment_status_pending'))
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        $todayResult = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->where('appointment_status_id', config('constants.appointment_status_pending'))
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        if ($orderBy == 'name') { /* Need to append appropriate table name to order by, it was missing before*/
            $orderBy = 'appointments.name';
        }

        if (hasFilter($filter, 'type') && $filter['type'] == 'week') {

            $end_week = Carbon::parse($today)->addDays(6)->format('Y-m-d');

            $where[] = [
                'appointments.scheduled_date',
                '>',
                $today,
            ];
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $end_week,
            ];

            $resultQuery->where($where);

        } elseif (hasFilter($filter, 'type') && $filter['type'] == 'month') {

            $end_month = Carbon::parse($today)->addMonth()->format('Y-m-d');

            $where[] = [
                'appointments.scheduled_date',
                '>',
                $today,
            ];
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $end_month,
            ];

            $resultQuery->where($where);
        } else {
            $resultQuery->whereDate('appointments.scheduled_date', $today);

            $resultQuery->where('appointments.scheduled_time', '>=', $todayTime);
        }

        $todayData = [];
        if (hasFilter($filter, 'type') && $filter['type'] != 'today') {

            $todayData = $todayResult->whereDate('scheduled_date', $today)
                ->where('appointments.scheduled_time', '>=', $todayTime)->pluck('appointments.id')->toArray();

        }

        $Appointments = $resultQuery->orWhereIn('appointments.id', $todayData)->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->orderBy('appointments.scheduled_time', 'ASC')
            ->get();

        $invoicearray = [];

        if ($Appointments) {
            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);
            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
            $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();

            // Default Un-scheduled Appointment Status
            $unscheduled_appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id, ['id']);
            $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly(Auth::User()->account_id);

            $index = 0;
            $invoiceid = 0;
            foreach ($Appointments as $appointment) {

                $invoice = Invoices::where([
                    ['appointment_id', '=', $appointment->app_id],
                    ['invoice_status_id', '=', $invoice_status->id],
                ])->first();
                $invoicearray[] = $invoice;
                if ($invoice) {
                    $invoiceid = $invoice->id;
                }
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } elseif ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $records['data'][$index] = [
                    'id' => $appointment->app_id,
                    'patient_id' => $appointment->patient_id,
                    'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
                    'name' => ($appointment->patient_name) ? $appointment->patient_name : $appointment->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($appointment->phone),
                    'scheduled_date' => ($appointment->scheduled_date) ? \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
                    'doctor_id' => $appointment->doctor->name ?? 'N/A',
                    'doctorId' => $appointment->doctor->id ?? 0,
                    'region_id' => (array_key_exists($appointment->region_id, $Regions)) ? $Regions[$appointment->region_id]->name : 'N/A',
                    'city_id' => $appointment->city_id ? $appointment->city->name : 'N/A',
                    'cityId' => $appointment->city_id ?? 0,
                    'location_id' => $appointment->location_id ? $appointment->location->name : 'N/A',
                    'locationId' => $appointment->location_id ?? 'N/A',
                    'service_id' => $appointment->service->name ?? 'N/A',
                    'resource_id' => $appointment->resource_id ?? 0,
                    'appointment_type_id' => $appointment->appointment_type->name,
                    'appointment_type' => $appointment->appointment_type->id,
                    'consultancy_type' => $consultancy_type,
                    'created_at' => Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A',
                    'converted_by' => array_key_exists($appointment->converted_by, $Users) ? $Users[$appointment->converted_by]->name : 'N/A',
                    'updated_by' => array_key_exists($appointment->app_updated_by, $Users) ? $Users[$appointment->app_updated_by]->name : 'N/A',
                    'unscheduled_appointment_status' => $unscheduled_appointment_status,
                    'cancelled_appointment_status' => $cancelled_appointment_status,
                    'appointment_status_id' => ($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : ''),
                    'appointment_status' => $appointment->appointment_status_id,
                    'invoice_id' => $invoiceid,
                    'invoice' => $invoice,
                ];

                $index++;
            }

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records['permissions'] = [
            'status' => Gate::allows('appointments_appointment_status'),
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function collection_by_center(Request $request, $data)
    {

        $data['collection'] = 0;
        if (! Gate::allows('dashboard_states')) {
            $data['collection'] = null;

            return $data;
        }
        $locations = ACL::getUserCentres();
        [$start_date, $end_date] = $this->getDates($request);
        switch ($request->type) {
            case 'today':
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'today', $request);
                $data['todaycollection'][] = $total;
                break;
            case 'yesterday':
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'yesterday', $request);
                $data['todaycollection'][] = $total;
                break;
            case 'last7days':
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'last7days', $request);
                $data['todaycollection'][] = $total;
                break;
            case 'week':
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'week', $request);
                $data['todaycollection'][] = $total;
                break;
            case 'month':
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'thisMonth', $request);
                $data['todaycollection'][] = $total;
                break;
            case 'lastmonth':
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'lastmonth', $request);
                $data['todaycollection'][] = $total;
                break;
            default:
                [$total] = dashboardreport::collectionbycenter($locations, Auth::User()->account_id, 'today', $request);
                $data['todaycollection'][] = $total;
                break;
        }

        return $data;
    }

    public function collectionByCentre(Request $request)
    {
        $data = [
            'today' => [],
            'yesterday' => [],
            'week' => [],
            'month' => [],
        ];

        if (Gate::allows('dashboard_collection_by_centre')) {

            $location_information = ACL::getUserCentres();
            switch ($request->type) {
                case 'today':
                    [$report_data, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;

                case 'yesterday':
                    [$report_data, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['yesterday'][] = $record;
                        }
                    }
                    break;

                case 'week':
                    [$report_data, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['week'][] = $record;
                        }
                    }
                    break;

                case 'month':
                    [$report_data, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['month'][] = $record;
                        }
                    }
                    break;

                default:
                    [$report_data, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;
            }

        }

        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $data,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function myCollectionByCentre(Request $request)
    {
        $data = [
            'today' => [],
            'yesterday' => [],
            'week' => [],
            'month' => [],
        ];

        if (Gate::allows('dashboard_my_collection_by_centre')) {

            $location_information = Locations::getActiveSorted(ACL::getUserCentres());

            switch ($request->type) {
                case 'today':
                    [$report_data, $total] = dashboardreport::myCollectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'today', $request);

                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;

                case 'yesterday':
                    [$report_data, $total] = dashboardreport::myCollectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['yesterday'][] = $record;
                        }
                    }
                    break;

                case 'week':
                    [$report_data, $total] = dashboardreport::myCollectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['week'][] = $record;
                        }
                    }
                    break;

                case 'month':
                    [$report_data, $total] = dashboardreport::myCollectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['month'][] = $record;
                        }
                    }
                    break;
                default:
                    [$report_data, $total] = dashboardreport::myCollectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'today', $request);

                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;
            }

        }

        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $data,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function RevenueByServiceCategory(Request $request)
    {
        $data = [];
        $total = 0;
        $today = [];
        $colors = [];
        if (Gate::allows('dashboard_revenue_by_service')) {
            $services = Services::select('id')->where([
                'account_id' => Auth::User()->account_id,
                'active' => '1',
                'parent_id' => '0',
            ])->get();
            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            if ($request->type == '') {
                $todayRecords = Invoices::leftjoin('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('invoices.id', 'invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $prepareData = [];
                foreach ($todayRecords as $key => $todayRecord) {
                    $parent_services = Services::with('parent')->where('id', $todayRecord->service_id)->first();
                    $service_name = $parent_services->parent ? $parent_services->parent->name : $parent_services->name;
                    $service_id = $parent_services->parent ? $parent_services->parent->id : $parent_services->id;

                    if (array_key_exists($service_id, $prepareData)) {
                        $prepareData[$service_id]['total'] += $todayRecord->total_price;
                    } else {
                        $prepareData[$service_id] = [
                            'id' => $service_id,
                            'name' => $service_name,
                            'total' => $todayRecord->total_price,
                        ];
                    }
                }
                $today[0] = ['Task', 'Hours per Day'];
                foreach ($prepareData as $todayRecord) {
                    $today[$todayRecord['id']] = [
                        $todayRecord['name'],
                        $todayRecord['total'],
                    ];
                }
                if (count($today) > 0) {
                    foreach ($today as $record) {
                        $data['today'][] = $record;
                    }
                }
            }
            if ($request->type == 'today') {
                $todayRecords = Invoices::leftjoin('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('invoices.id', 'invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                foreach ($todayRecords as $todayRecord) {
                    $parent_services = Services::with('parent')->where('id', $todayRecord->service_id)->first();
                    $today[0] = ['Task', 'Hours per Day'];
                    $today[$todayRecord->service_id] = [
                        $parent_services->parent->name,
                        $todayRecord->total_price,
                    ];
                    $colors[] = $parent_services->color;
                    $total += $todayRecord->total_price;
                }
                if (count($today) > 0) {
                    foreach ($today as $record) {
                        $data['today'][] = $record;
                    }
                }
            }
            if ($request->type == 'yesterday') {
                $yesterdayRecords = Invoices::leftjoin('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                $yesterdayRecords = $yesterdayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $yesterday = [];
                foreach ($yesterdayRecords as $yesterdayRecord) {
                    $parent_services = Services::with('parent')->where('id', $yesterdayRecord->service_id)->first();
                    $yesterday[0] = ['Task', 'Hours per Day'];
                    $yesterday[$yesterdayRecord->service_id] = [
                        $parent_services->parent->name,
                        $yesterdayRecord->total_price,
                    ];
                    $colors[] = $parent_services->color;
                    $total += $yesterdayRecord->total_price;
                }
                if (count($yesterday) > 0) {
                    foreach ($yesterday as $record) {
                        $data['yesterday'][] = $record;
                    }
                }
            }
            if ($request->type == 'week') {
                $last7DaysRecords = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7DaysRecords = $last7DaysRecords->where('invoices.created_by', Auth::User()->id);
                }
                $last7DaysRecords = $last7DaysRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $last7days = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $child_services = Services::where('parent_id', $service->id)->get();
                        $last7days[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($last7DaysRecords) {
                            foreach ($child_services as $child) {
                                foreach ($last7DaysRecords as $last7DaysRecord) {
                                    if ($last7DaysRecord->service_id == $child->id) {
                                        $last7days[$service->id] = [
                                            $service->name,
                                            $last7DaysRecord->total_price,
                                        ];
                                        $colors[] = $service->color;
                                        $total += $last7DaysRecord->total_price;
                                    }
                                }
                            }
                        }
                    }
                }
                if (count($last7days)) {
                    foreach ($last7days as $record) {
                        $data['week'][] = $record;
                    }
                }
            }
            if ($request->type == 'month') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where('invoices.created_by', Auth::User()->id);
                }
                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $thisMonth = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $child_services = Services::where('parent_id', $service->id)->get();
                        $thisMonth[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($thisMonthRecords) {
                            foreach ($child_services as $child) {
                                foreach ($thisMonthRecords as $thisMonthRecord) {
                                    if ($thisMonthRecord->service_id == $child->id) {
                                        $thisMonth[$service->id] = [
                                            $service->name,
                                            $thisMonthRecord->total_price,
                                        ];
                                        $colors[] = $service->color;
                                        $total += $thisMonthRecord->total_price;
                                    }
                                }
                            }
                        }
                    }
                }
                if (count($thisMonth)) {
                    foreach ($thisMonth as $record) {
                        $data['month'][] = $record;
                    }
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function CollectionByServiceCategory(Request $request)
    {
        $data = [
            'today' => [],
            'yesterday' => [],
            'last7days' => [],
            'thismonth' => [],
            'lastmonth' => [],
        ];
        $services = Services::where([
            'account_id' => Auth::User()->account_id,
            'active' => '1',
            'parent_id' => '0',
        ])->get();
        if ($request->type == 'today') {
            $total = 0;
            $today[0] = [
                'Task',
                'Hours per Day',
            ];
            foreach ($services as $service) {
                $childServices = Services::where('parent_id', $service->id)->get();
                foreach ($childServices as $child) {
                    $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')->whereDate('package_advances.created_at', '=', Carbon::now()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id' => Auth::User()->account_id,
                            'appointments.service_id' => $child->id,
                        ])->get();
                    if ($packagesadvances) {
                        $balance = 0;
                        $total_revenue_cash_in = 0;
                        $total_revenue_card_in = 0;
                        $total_refund_out = 0;
                        foreach ($packagesadvances as $packagesadvance) {
                            if (
                                $packagesadvance->cash_flow == 'in' &&
                                    $packagesadvance->is_adjustment == '0' &&
                                    $packagesadvance->is_tax == '0' &&
                                    $packagesadvance->is_cancel == '0'
                            ) {
                                switch ($packagesadvance->cash_flow) {
                                    case 'in':
                                        $balance = $balance + $packagesadvance->cash_amount;
                                        break;
                                    case 'out':
                                        $balance = $balance - $packagesadvance->cash_amount;
                                        break;
                                    default:
                                        break;
                                }
                                if ($packagesadvance->cash_amount != 0) {
                                    if ($packagesadvance->package_id) {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->is_adjustment == '1') {
                                        $transtype = Config::get('constants.trans_type.adjustment');
                                    }
                                    if ($packagesadvance->is_cancel == '1') {
                                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                        $transtype = Config::get('constants.trans_type.invoice_create');
                                    }
                                    if ($packagesadvance->is_refund == '1') {
                                        $transtype = Config::get('constants.trans_type.refund_in');
                                    }
                                    if ($packagesadvance->is_tax == '1') {
                                        $transtype = Config::get('constants.trans_type.tax_out');
                                    }
                                    if ($packagesadvance->cash_flow == 'in') {
                                        if ($packagesadvance->paymentmode->name == 'Cash') {
                                            $revenue_cash_in = $packagesadvance->cash_amount;
                                            $revenue_card_in = '';
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Card') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = $packagesadvance->cash_amount;
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = '';
                                            $revenue_bank_in = $packagesadvance->cash_amount;
                                            $refund_out = '';
                                        }
                                    } else {
                                        $revenue_cash_in = '';
                                        $revenue_card_in = '';
                                        $revenue_bank_in = '';
                                        $refund_out = $packagesadvance->cash_amount;
                                    }

                                    if ($revenue_cash_in) {
                                        $total_revenue_cash_in += $revenue_cash_in;
                                    }
                                    if ($revenue_card_in) {
                                        $total_revenue_card_in += $revenue_card_in;
                                    }
                                    if ($revenue_bank_in) {
                                        $total_revenue_card_in += $revenue_bank_in;
                                    }
                                    if ($refund_out) {
                                        $total_refund_out += $refund_out;
                                    }

                                }
                            }
                        }
                    }
                    $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
                    $In_hand_balance = $total_revenue - $total_refund_out;
                    if ($In_hand_balance > 0) {
                        $today[$service->id] = [
                            $service->name,
                            $In_hand_balance,
                        ];
                        $colors[] = $service->color;
                        $total += $In_hand_balance;
                    }
                }
            }
            if (count($today)) {
                foreach ($today as $record) {
                    $data['today'][] = $record;
                }
            }
        }
        if ($request->type == 'yesterday') {
            $total = 0;
            $yesterday[0] = [
                'Task',
                'Hours per Day',
            ];
            foreach ($services as $service) {
                $childServices = Services::where('parent_id', $service->id)->get();
                foreach ($childServices as $child) {
                    $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')->whereDate('package_advances.created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id' => Auth::User()->account_id,
                            'appointments.service_id' => $child->id,
                        ])->get();
                    if ($packagesadvances) {
                        $balance = 0;
                        $total_revenue_cash_in = 0;
                        $total_revenue_card_in = 0;
                        $total_refund_out = 0;
                        foreach ($packagesadvances as $packagesadvance) {
                            if (
                                $packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            ) {
                                switch ($packagesadvance->cash_flow) {
                                    case 'in':
                                        $balance = $balance + $packagesadvance->cash_amount;
                                        break;
                                    case 'out':
                                        $balance = $balance - $packagesadvance->cash_amount;
                                        break;
                                    default:
                                        break;
                                }
                                if ($packagesadvance->cash_amount != 0) {
                                    if ($packagesadvance->package_id) {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->is_adjustment == '1') {
                                        $transtype = Config::get('constants.trans_type.adjustment');
                                    }
                                    if ($packagesadvance->is_cancel == '1') {
                                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                        $transtype = Config::get('constants.trans_type.invoice_create');
                                    }
                                    if ($packagesadvance->is_refund == '1') {
                                        $transtype = Config::get('constants.trans_type.refund_in');
                                    }
                                    if ($packagesadvance->is_tax == '1') {
                                        $transtype = Config::get('constants.trans_type.tax_out');
                                    }
                                    if ($packagesadvance->cash_flow == 'in') {
                                        if ($packagesadvance->paymentmode->name == 'Cash') {
                                            $revenue_cash_in = $packagesadvance->cash_amount;
                                            $revenue_card_in = '';
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Card') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = $packagesadvance->cash_amount;
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = '';
                                            $revenue_bank_in = $packagesadvance->cash_amount;
                                            $refund_out = '';
                                        }
                                    } else {
                                        $revenue_cash_in = '';
                                        $revenue_card_in = '';
                                        $revenue_bank_in = '';
                                        $refund_out = $packagesadvance->cash_amount;
                                    }

                                    if ($revenue_cash_in) {
                                        $total_revenue_cash_in += $revenue_cash_in;
                                    }
                                    if ($revenue_card_in) {
                                        $total_revenue_card_in += $revenue_card_in;
                                    }
                                    if ($revenue_bank_in) {
                                        $total_revenue_card_in += $revenue_bank_in;
                                    }
                                    if ($refund_out) {
                                        $total_refund_out += $refund_out;
                                    }
                                }
                            }
                        }
                    }
                    $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
                    $In_hand_balance = $total_revenue - $total_refund_out;
                    if ($In_hand_balance > 0) {
                        $yesterday[$service->id] = [
                            $service->name,
                            $In_hand_balance,
                        ];
                        $colors[] = $service->color;
                        $total += $In_hand_balance;
                    }
                }
            }
            if (count($yesterday)) {
                foreach ($yesterday as $record) {
                    $data['yesterday'][] = $record;
                }
            }
        }
        if ($request->type == 'last7days') {
            $total = 0;
            $last7days[0] = [
                'Task',
                'Hours per Day',
            ];
            foreach ($services as $service) {
                $childServices = Services::where('parent_id', $service->id)->get();
                foreach ($childServices as $child) {
                    $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')->whereDate('package_advances.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                        ->whereDate('package_advances.created_at', '<=', Carbon::now()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id' => Auth::User()->account_id,
                            'appointments.service_id' => $child->id,
                        ])->get();
                    if ($packagesadvances) {
                        $balance = 0;
                        $total_balance = 0;
                        $total_revenue_cash_in = 0;
                        $total_revenue_card_in = 0;
                        $total_refund_out = 0;
                        foreach ($packagesadvances as $packagesadvance) {
                            if (
                                $packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            ) {
                                switch ($packagesadvance->cash_flow) {
                                    case 'in':
                                        $balance = $balance + $packagesadvance->cash_amount;
                                        break;
                                    case 'out':
                                        $balance = $balance - $packagesadvance->cash_amount;
                                        break;
                                    default:
                                        break;
                                }
                                if ($packagesadvance->cash_amount != 0) {
                                    if ($packagesadvance->package_id) {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->is_adjustment == '1') {
                                        $transtype = Config::get('constants.trans_type.adjustment');
                                    }
                                    if ($packagesadvance->is_cancel == '1') {
                                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                        $transtype = Config::get('constants.trans_type.invoice_create');
                                    }
                                    if ($packagesadvance->is_refund == '1') {
                                        $transtype = Config::get('constants.trans_type.refund_in');
                                    }
                                    if ($packagesadvance->is_tax == '1') {
                                        $transtype = Config::get('constants.trans_type.tax_out');
                                    }
                                    if ($packagesadvance->cash_flow == 'in') {
                                        if ($packagesadvance->paymentmode->name == 'Cash') {
                                            $revenue_cash_in = $packagesadvance->cash_amount;
                                            $revenue_card_in = '';
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Card') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = $packagesadvance->cash_amount;
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = '';
                                            $revenue_bank_in = $packagesadvance->cash_amount;
                                            $refund_out = '';
                                        }
                                    } else {
                                        $revenue_cash_in = '';
                                        $revenue_card_in = '';
                                        $revenue_bank_in = '';
                                        $refund_out = $packagesadvance->cash_amount;
                                    }

                                    if ($revenue_cash_in) {
                                        $total_revenue_cash_in += $revenue_cash_in;
                                    }
                                    if ($revenue_card_in) {
                                        $total_revenue_card_in += $revenue_card_in;
                                    }
                                    if ($revenue_bank_in) {
                                        $total_revenue_card_in += $revenue_bank_in;
                                    }
                                    if ($refund_out) {
                                        $total_refund_out += $refund_out;
                                    }
                                }
                            }
                        }
                    }
                    $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
                    $In_hand_balance = $total_revenue - $total_refund_out;
                    if ($In_hand_balance > 0) {
                        $last7days[$service->id] = [
                            $service->name,
                            $In_hand_balance,
                        ];
                        $colors[] = $service->color;
                        $total += $In_hand_balance;
                    }
                }
            }
            if (count($last7days)) {
                foreach ($last7days as $record) {
                    $data['last7days'][] = $record;
                }
            }
        }
        if ($request->type == 'thismonth') {
            $total = 0;
            $thismonth[0] = [
                'Task',
                'Hours per Day',
            ];
            foreach ($services as $service) {
                $childServices = Services::where('parent_id', $service->id)->get();
                foreach ($childServices as $child) {
                    $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')->whereDate('package_advances.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                        ->whereDate('package_advances.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id' => Auth::User()->account_id,
                            'appointments.service_id' => $child->id,
                        ])->get();
                    if ($packagesadvances) {
                        $balance = 0;
                        $total_balance = 0;
                        $total_revenue_cash_in = 0;
                        $total_revenue_card_in = 0;
                        $total_refund_out = 0;
                        foreach ($packagesadvances as $packagesadvance) {
                            if (
                                $packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            ) {
                                switch ($packagesadvance->cash_flow) {
                                    case 'in':
                                        $balance = $balance + $packagesadvance->cash_amount;
                                        break;
                                    case 'out':
                                        $balance = $balance - $packagesadvance->cash_amount;
                                        break;
                                    default:
                                        break;
                                }
                                $total_balance = $balance;
                                if ($packagesadvance->cash_amount != 0) {
                                    if ($packagesadvance->package_id) {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->is_adjustment == '1') {
                                        $transtype = Config::get('constants.trans_type.adjustment');
                                    }
                                    if ($packagesadvance->is_cancel == '1') {
                                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                        $transtype = Config::get('constants.trans_type.invoice_create');
                                    }
                                    if ($packagesadvance->is_refund == '1') {
                                        $transtype = Config::get('constants.trans_type.refund_in');
                                    }
                                    if ($packagesadvance->is_tax == '1') {
                                        $transtype = Config::get('constants.trans_type.tax_out');
                                    }
                                    if ($packagesadvance->cash_flow == 'in') {
                                        if ($packagesadvance->paymentmode->name == 'Cash') {
                                            $revenue_cash_in = $packagesadvance->cash_amount;
                                            $revenue_card_in = '';
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Card') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = $packagesadvance->cash_amount;
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = '';
                                            $revenue_bank_in = $packagesadvance->cash_amount;
                                            $refund_out = '';
                                        }
                                    } else {
                                        $revenue_cash_in = '';
                                        $revenue_card_in = '';
                                        $revenue_bank_in = '';
                                        $refund_out = $packagesadvance->cash_amount;
                                    }

                                    if ($revenue_cash_in) {
                                        $total_revenue_cash_in += $revenue_cash_in;
                                    }
                                    if ($revenue_card_in) {
                                        $total_revenue_card_in += $revenue_card_in;
                                    }
                                    if ($revenue_bank_in) {
                                        $total_revenue_card_in += $revenue_bank_in;
                                    }
                                    if ($refund_out) {
                                        $total_refund_out += $refund_out;
                                    }

                                }
                            }
                        }
                    }
                    $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
                    $In_hand_balance = $total_revenue - $total_refund_out;
                    if ($In_hand_balance > 0) {
                        $thismonth[$service->id] = [
                            $service->name,
                            $In_hand_balance,
                        ];
                        $colors[] = $service->color;
                        $total += $In_hand_balance;
                    }
                }
            }
            if (count($thismonth)) {
                foreach ($thismonth as $record) {
                    $data['thismonth'][] = $record;
                }
            }
        }
        if ($request->type == 'lastmonth') {
            $total = 0;
            $lastmonth[0] = [
                'Task',
                'Hours per Day',
            ];
            foreach ($services as $service) {
                $childServices = Services::where('parent_id', $service->id)->get();
                foreach ($childServices as $child) {
                    $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')->whereDate('package_advances.created_at', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
                        ->whereDate('package_advances.created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id' => Auth::User()->account_id,
                            'appointments.service_id' => $child->id,
                        ])->get();
                    if ($packagesadvances) {
                        $balance = 0;
                        $total_balance = 0;
                        $total_revenue_cash_in = 0;
                        $total_revenue_card_in = 0;
                        $total_refund_out = 0;
                        foreach ($packagesadvances as $packagesadvance) {
                            if (
                                $packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            ) {
                                switch ($packagesadvance->cash_flow) {
                                    case 'in':
                                        $balance = $balance + $packagesadvance->cash_amount;
                                        break;
                                    case 'out':
                                        $balance = $balance - $packagesadvance->cash_amount;
                                        break;
                                    default:
                                        break;
                                }
                                $total_balance = $balance;
                                if ($packagesadvance->cash_amount != 0) {
                                    if ($packagesadvance->package_id) {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->is_adjustment == '1') {
                                        $transtype = Config::get('constants.trans_type.adjustment');
                                    }
                                    if ($packagesadvance->is_cancel == '1') {
                                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                        $transtype = Config::get('constants.trans_type.invoice_create');
                                    }
                                    if ($packagesadvance->is_refund == '1') {
                                        $transtype = Config::get('constants.trans_type.refund_in');
                                    }
                                    if ($packagesadvance->is_tax == '1') {
                                        $transtype = Config::get('constants.trans_type.tax_out');
                                    }
                                    if ($packagesadvance->cash_flow == 'in') {
                                        if ($packagesadvance->paymentmode->name == 'Cash') {
                                            $revenue_cash_in = $packagesadvance->cash_amount;
                                            $revenue_card_in = '';
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Card') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = $packagesadvance->cash_amount;
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = '';
                                            $revenue_bank_in = $packagesadvance->cash_amount;
                                            $refund_out = '';
                                        }
                                    } else {
                                        $revenue_cash_in = '';
                                        $revenue_card_in = '';
                                        $revenue_bank_in = '';
                                        $refund_out = $packagesadvance->cash_amount;
                                    }

                                    if ($revenue_cash_in) {
                                        $total_revenue_cash_in += $revenue_cash_in;
                                    }
                                    if ($revenue_card_in) {
                                        $total_revenue_card_in += $revenue_card_in;
                                    }
                                    if ($revenue_bank_in) {
                                        $total_revenue_card_in += $revenue_bank_in;
                                    }
                                    if ($refund_out) {
                                        $total_refund_out += $refund_out;
                                    }

                                }
                            }
                        }
                    }
                    $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
                    $In_hand_balance = $total_revenue - $total_refund_out;
                    if ($In_hand_balance > 0) {
                        $lastmonth[$service->id] = [
                            $service->name,
                            $In_hand_balance,
                        ];
                        $colors[] = $service->color;
                        $total += $In_hand_balance;
                    }
                }
            }
            if (count($lastmonth)) {
                foreach ($lastmonth as $record) {
                    $data['lastmonth'][] = $record;
                }
            }
        }
        if ($request->type == '') {
            $total = 0;
            $today[0] = [
                'Task',
                'Hours per Day',
            ];
            foreach ($services as $service) {
                $childServices = Services::where('parent_id', $service->id)->get();
                foreach ($childServices as $child) {
                    $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')
                        ->whereDate('package_advances.created_at', '=', Carbon::now()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id' => Auth::User()->account_id,
                            'appointments.service_id' => $child->id,
                        ])->get();
                    if ($packagesadvances) {
                        $balance = 0;
                        $total_revenue_cash_in = 0;
                        $total_revenue_card_in = 0;
                        $total_refund_out = 0;
                        foreach ($packagesadvances as $packagesadvance) {
                            if (
                                $packagesadvance->cash_flow == 'in' &&
                                    $packagesadvance->is_adjustment == '0' &&
                                    $packagesadvance->is_tax == '0' &&
                                    $packagesadvance->is_cancel == '0'
                            ) {
                                switch ($packagesadvance->cash_flow) {
                                    case 'in':
                                        $balance = $balance + $packagesadvance->cash_amount;
                                        break;
                                    case 'out':
                                        $balance = $balance - $packagesadvance->cash_amount;
                                        break;
                                    default:
                                        break;
                                }
                                if ($packagesadvance->cash_amount != 0) {
                                    if ($packagesadvance->package_id) {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                        $transtype = Config::get('constants.trans_type.advance_in');
                                    }
                                    if ($packagesadvance->is_adjustment == '1') {
                                        $transtype = Config::get('constants.trans_type.adjustment');
                                    }
                                    if ($packagesadvance->is_cancel == '1') {
                                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                                    }
                                    if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                        $transtype = Config::get('constants.trans_type.invoice_create');
                                    }
                                    if ($packagesadvance->is_refund == '1') {
                                        $transtype = Config::get('constants.trans_type.refund_in');
                                    }
                                    if ($packagesadvance->is_tax == '1') {
                                        $transtype = Config::get('constants.trans_type.tax_out');
                                    }
                                    if ($packagesadvance->cash_flow == 'in') {
                                        if ($packagesadvance->paymentmode->name == 'Cash') {
                                            $revenue_cash_in = $packagesadvance->cash_amount;
                                            $revenue_card_in = '';
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Card') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = $packagesadvance->cash_amount;
                                            $revenue_bank_in = '';
                                            $refund_out = '';
                                        }
                                        if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                            $revenue_cash_in = '';
                                            $revenue_card_in = '';
                                            $revenue_bank_in = $packagesadvance->cash_amount;
                                            $refund_out = '';
                                        }
                                    } else {
                                        $revenue_cash_in = '';
                                        $revenue_card_in = '';
                                        $revenue_bank_in = '';
                                        $refund_out = $packagesadvance->cash_amount;
                                    }

                                    if ($revenue_cash_in) {
                                        $total_revenue_cash_in += $revenue_cash_in;
                                    }
                                    if ($revenue_card_in) {
                                        $total_revenue_card_in += $revenue_card_in;
                                    }
                                    if ($revenue_bank_in) {
                                        $total_revenue_card_in += $revenue_bank_in;
                                    }
                                    if ($refund_out) {
                                        $total_refund_out += $refund_out;
                                    }

                                }
                            }
                        }
                    }
                    $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
                    $In_hand_balance = $total_revenue - $total_refund_out;
                    if ($In_hand_balance > 0) {
                        $today[$service->id] = [
                            $service->name,
                            $In_hand_balance,
                        ];
                        $colors[] = $service->color;
                        $total += $In_hand_balance;
                    }
                }
            }
            if (count($today)) {
                foreach ($today as $record) {
                    $data['today'][] = $record;
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors ?? '',
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function revenueByCentre(Request $request)
    {
        $data = [];

        if (Gate::allows('dashboard_revenue_by_centre')) {

            $locations = Locations::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
            ])->get();

            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

            [$start_date, $end_date] = $this->getDates($request);
            $todayRecords = \App\Models\Invoices::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where('invoice_status_id', '=', $invoicestatus->id);

            if ($request->get('performance') == '1') {
                $todayRecords = $todayRecords->where('created_by', '=', Auth::User()->id);
            }

            $todayRecords = $todayRecords->select('location_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                ->groupBy('location_id')
                ->get();

            $total = 0;
            $data[0] = [
                'Task',
                'Hours per Day',
            ];

            if ($locations) {
                foreach ($locations as $counter => $location) {
                    if ($counter == 0) {
                        $data[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                    }
                    if ($todayRecords) {
                        foreach ($todayRecords as $todayRecord) {
                            if ($todayRecord->location_id == $location->id) {
                                $data[] = [
                                    $location->city->name.' - '.$location->name,
                                    $todayRecord->total_price,
                                ];
                                $total += $todayRecord->total_price;
                            }
                        }
                    }
                }
            }

            return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
                'pie' => $data,
                'total' => number_format($total ?? 0, 2),
            ]);
        }
    }

    public function myRevenueByCentre(Request $request)
    {
        $data = [];

        if (Gate::allows('dashboard_my_revenue_by_centre')) {

            $locations = Locations::getActiveSortedLocations(ACL::getUserCentres());

            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

            [$start_date, $end_date] = $this->getDates($request);

            $todayRecords = \App\Models\Invoices::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where('invoice_status_id', '=', $invoicestatus->id);

            if ($request->get('performance') == '1') {
                $todayRecords = $todayRecords->where('created_by', '=', Auth::User()->id);
            }

            $todayRecords = $todayRecords->select('location_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                ->groupBy('location_id')
                ->get();

            $total = 0;
            $data[0] = [
                'Task',
                'Hours per Day',
            ];

            if ($locations) {
                foreach ($locations as $counter => $location) {
                    if ($counter == 0) {
                        $data[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                    }
                    if ($todayRecords) {
                        foreach ($todayRecords as $todayRecord) {
                            if ($todayRecord->location_id == $location->id) {
                                $data[] = [
                                    $location->city->name.' - '.$location->name,
                                    $todayRecord->total_price,
                                ];
                                $total += $todayRecord->total_price;
                            }
                        }
                    }
                }
            }

            return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
                'pie' => $data,
                'total' => number_format($total ?? 0, 2),
            ]);
        }
    }

    public function revenueByService(Request $request)
    {
        $data = [];
        $total = 0;
        $today = [];
        $colors = [];

        if (Gate::allows('dashboard_revenue_by_service')) {

            $services = Services::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
            ])->get();

            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            if ($request->type == '') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($todayRecords) {
                            foreach ($todayRecords as $todayRecord) {
                                if ($todayRecord->service_id == $service->id) {
                                    $today[$service->id] = [
                                        $service->name,
                                        $todayRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $todayRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($today)) {
                    foreach ($today as $record) {
                        $data['today'][] = $record;
                    }
                }
            }
            if ($request->type == 'today') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($todayRecords) {
                            foreach ($todayRecords as $todayRecord) {
                                if ($todayRecord->service_id == $service->id) {
                                    $today[$service->id] = [
                                        $service->name,
                                        $todayRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $todayRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($today)) {
                    foreach ($today as $record) {
                        $data['today'][] = $record;
                    }
                }
            }

            if ($request->type == 'yesterday') {
                $yesterdayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $yesterdayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $yesterdayRecords = $yesterdayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $yesterday = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $yesterday[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($yesterdayRecords) {
                            foreach ($yesterdayRecords as $yesterdayRecord) {
                                if ($yesterdayRecord->service_id == $service->id) {
                                    $yesterday[$service->id] = [
                                        $service->name,
                                        $yesterdayRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $yesterdayRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($yesterday)) {
                    foreach ($yesterday as $record) {
                        $data['yesterday'][] = $record;
                    }
                }

            }

            if ($request->type == 'week') {

                $last7DaysRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $last7DaysRecords = $last7DaysRecords->where('invoices.created_by', Auth::User()->id);
                }

                $last7DaysRecords = $last7DaysRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $last7days = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $last7days[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($last7DaysRecords) {
                            foreach ($last7DaysRecords as $last7DaysRecord) {
                                if ($last7DaysRecord->service_id == $service->id) {
                                    $last7days[$service->id] = [
                                        $service->name,
                                        $last7DaysRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $last7DaysRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($last7days)) {
                    foreach ($last7days as $record) {
                        $data['week'][] = $record;
                    }
                }
            }

            if ($request->type == 'month') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where('invoices.created_by', Auth::User()->id);
                }

                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $thisMonth = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $thisMonth[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($thisMonthRecords) {
                            foreach ($thisMonthRecords as $thisMonthRecord) {
                                if ($thisMonthRecord->service_id == $service->id) {
                                    $thisMonth[$service->id] = [
                                        $service->name,
                                        $thisMonthRecord->total_price,
                                    ];

                                    $colors[] = $service->color;

                                    $total += $thisMonthRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($thisMonth)) {
                    foreach ($thisMonth as $record) {
                        $data['month'][] = $record;
                    }
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function myRevenueByService(Request $request)
    {
        $data = [];
        $total = 0;
        $today = [];
        $colors = [];

        if (Gate::allows('dashboard_my_revenue_by_service')) {

            $services = Services::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
            ])->get();
            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            if ($request->type == '') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($todayRecords) {
                            foreach ($todayRecords as $todayRecord) {
                                if ($todayRecord->service_id == $service->id) {
                                    $today[$service->id] = [
                                        $service->name,
                                        $todayRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $todayRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($today)) {
                    foreach ($today as $record) {
                        $data['today'][] = $record;
                    }
                }
            }
            if ($request->type == 'today') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($todayRecords) {
                            foreach ($todayRecords as $todayRecord) {
                                if ($todayRecord->service_id == $service->id) {
                                    $today[$service->id] = [
                                        $service->name,
                                        $todayRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $todayRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($today)) {
                    foreach ($today as $record) {
                        $data['today'][] = $record;
                    }
                }
            }

            if ($request->type == 'yesterday') {
                $yesterdayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $yesterdayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $yesterdayRecords = $yesterdayRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $yesterday = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $yesterday[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($yesterdayRecords) {
                            foreach ($yesterdayRecords as $yesterdayRecord) {
                                if ($yesterdayRecord->service_id == $service->id) {
                                    $yesterday[$service->id] = [
                                        $service->name,
                                        $yesterdayRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $yesterdayRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($yesterday)) {
                    foreach ($yesterday as $record) {
                        $data['yesterday'][] = $record;
                    }
                }

            }

            if ($request->type == 'week') {

                $last7DaysRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $last7DaysRecords = $last7DaysRecords->where('invoices.created_by', Auth::User()->id);
                }

                $last7DaysRecords = $last7DaysRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $last7days = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $last7days[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($last7DaysRecords) {
                            foreach ($last7DaysRecords as $last7DaysRecord) {
                                if ($last7DaysRecord->service_id == $service->id) {
                                    $last7days[$service->id] = [
                                        $service->name,
                                        $last7DaysRecord->total_price,
                                    ];
                                    $colors[] = $service->color;

                                    $total += $last7DaysRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($last7days)) {
                    foreach ($last7days as $record) {
                        $data['week'][] = $record;
                    }
                }
            }

            if ($request->type == 'month') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where('invoices.created_by', Auth::User()->id);
                }

                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $thisMonth = [];
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $thisMonth[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($thisMonthRecords) {
                            foreach ($thisMonthRecords as $thisMonthRecord) {
                                if ($thisMonthRecord->service_id == $service->id) {
                                    $thisMonth[$service->id] = [
                                        $service->name,
                                        $thisMonthRecord->total_price,
                                    ];

                                    $colors[] = $service->color;

                                    $total += $thisMonthRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($thisMonth)) {
                    foreach ($thisMonth as $record) {
                        $data['month'][] = $record;
                    }
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    public function getDates($request)
    {

        switch ($request->type) {
            case 'today':
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'yesterday':
                $start_date = Carbon::now()->subDay(1)->format('Y-m-d');
                $end_date = Carbon::now()->subDay(1)->format('Y-m-d');
                break;
            case 'last7days':
                $start_date = Carbon::now()->subDay(6)->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'week':
                $start_date = Carbon::now()->startOfWeek()->format('Y-m-d');
                $end_date = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                $end_date = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'lastmonth':
                $start_date = Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d');
                $end_date = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            default:
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
        }

        return [
            $start_date,
            $end_date,
        ];
    }

    private function getTableFilter($filters)
    {
        if (isset($filters['query']) && isset($filters['query']['filter'])) {
            return $filters['query']['filter'];
        }

        return [];
    }

    private function consultancies($data, $start_date, $end_date)
    {

        if (! Gate::allows('dashboard_states')) {
            $data['all_consultancies'] = null;
            $data['done_consultancies'] = null;

            return $data;
        }
        $query = Appointments::where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereBetween('scheduled_date', [$start_date, $end_date])
            ->whereIn('location_id', ACL::getUserCentres());
        $data['all_consultancies'] = $query->count();
        $query->where('appointment_status_id', config('constants.appointment_status_arrived'));
        $data['done_consultancies'] = $query->count();

        return $data;
    }

    private function treatments($data, $start_date, $end_date)
    {

        if (! Gate::allows('dashboard_states')) {
            $data['all_treatments'] = null;
            $data['done_treatments'] = null;

            return $data;
        }

        $query = Appointments::where('appointment_type_id', config('constants.appointment_type_service'))
            ->whereBetween('scheduled_date', [$start_date, $end_date])
            ->whereIn('location_id', ACL::getUserCentres());

        $data['all_treatments'] = $query->count();

        $query->where('appointment_status_id', config('constants.appointment_status_arrived'));
        $data['done_treatments'] = $query->count();

        return $data;
    }

    private function leads($data, $location_id, $start, $end)
    {

        if (! Gate::allows('dashboard_states')) {
            $data['leads'] = false;
            $data['totalLeads'] = false;

            return $data;
        }

        $where[] = [
            'leads.created_at',
            '>=',
            $start.' 00:00:00',
        ];
        $where[] = [
            'leads.created_at',
            '<=',
            $end.' 23:59:59',
        ];

        $query = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->where('leads.active', 1);
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            });

        $query->where($where);

        $data['leads'] = $query->count();

        $data['totalLeads'] = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->where('leads.active', 1);
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })->count();

        return $data;
    }

    private function getUserLocation()
    {
        return UserHasLocations::where('user_id', auth()->id())->value('location_id');
    }

    private function salesByCentre(Request $request, $data)
    {
        $data['revenue'] = 0;
        if (! Gate::allows('dashboard_states')) {
            $data['revenue'] = null;

            return $data;
        }
        $locations = ACL::getUserCentres();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        [$start_date, $end_date] = $this->getDates($request);
        
        $where[] = [
            'created_at',
            '>=',
            $start_date.' 00:00:00',
        ];
        $where[] = [
            'created_at',
            '<=',
            $end_date.' 23:59:59',
        ];
        $todayRecords = \App\Models\Invoices::where($where)
            ->whereIn('location_id', ACL::getUserCentres())
            ->where('invoice_status_id', '=', $invoicestatus->id);
        if ($request->get('performance') == '1') {
            $todayRecords = $todayRecords->where('created_by', '=', Auth::User()->id);
        }
        $todayRecords = $todayRecords->select('location_id', DB::raw('SUM(invoices.total_price) AS total_price'))
            ->groupBy('location_id')->get();
        if ($locations) {
            foreach ($locations as $location) {
                if ($todayRecords) {
                    foreach ($todayRecords as $todayRecord) {
                        if ($todayRecord->location_id == $location) {
                            $data['revenue'] += $todayRecord->total_price;
                        }
                    }
                }
            }
        }

        return $data;
    }

    private function recentActivities($data)
    {

        if (! Gate::allows('dashboard_recent_activities')) {
            return $data['recent_activities'] = [
                'finance_log' => [],
                'appointment_log' => [],
                'unauthorized' => true,
            ];
        }
        $centres = ACL::getUserCentres();

        $center_names = Locations::whereIn('id', $centres)->pluck('name')->toArray();
        $activities = Activity::whereIn('location', $center_names)->whereDate('created_at', Carbon::now()->format('Y-m-d'))->latest()->get();

        return $data['recent_activities'] = [
            'finance_log' => $activities,
        ];
    }

    private function viewAppointmentLog()
    {

        try {

            [$start, $end] = $this->getDates(request());

            $where[] = [
                'date',
                '>=',
                $start,
            ];
            $where[] = [
                'date',
                '<=',
                $end,
            ];

            $query = AppointmentLog::where('date', [$start, $end]);

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

                if (! isset($data[$audit_trail->id]['scheduled_date'])) {

                    unset($data[$audit_trail->id]);
                }

                if (! isset($data[$audit_trail->id]['appointment_type_id'])) {

                    unset($data[$audit_trail->id]);
                }

            }
        }

        return $data;

    }
}
