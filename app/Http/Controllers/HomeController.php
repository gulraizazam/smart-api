<?php

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Reports\dashboardreport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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

        $location_id = $this->getUserLocation();

        list($start_date, $end_date) = $this->getDates($request);

        $data = $this->consultancies($data, $start_date, $end_date);
        $data = $this->treatments($data, $start_date, $end_date);
        $data = $this->leads($data);
        $data = $this->salesByCentre($request, $data);

        $data['today'] = Carbon::now()->timezone("Asia/Karachi")->format("Y-m-d");
        $data['startWeek'] = Carbon::now()->timezone("Asia/Karachi")->startOfWeek()->format("Y-m-d");
        $data['month'] = Carbon::now()->timezone("Asia/Karachi")->format("Y-m-d");
        $data['currentTime'] = Carbon::now()->timezone("Asia/Karachi")->format("H:i:s");
        $data['location_id'] = $location_id;
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        return view('admin.home', $data);
    }

    public function datatable(Request $request)
    {

        $where = array();

        $filter = $this->getTableFilter($request->all());

        $today = Carbon::now()->format("Y-m-d");
        $todayTime = Carbon::now()->timezone("Asia/Karachi")->format("H:i:s");

        if ($request->has('sort')) {

            list($orderBy, $order) = getSortBy($request, 'scheduled_date', 'DESC', 'appointments');

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

        $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();

        if (Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services') && Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_services') && !Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }

        if (count($where)) {
            $countQuery->where($where);
        }

        if (auth()->id() != 1) {
            $countQuery->where('location_id', $this->getUserLocation());
        }

        if (hasFilter($filter, 'type') && $filter['type'] == 'week') {
            $start_week = $filter['date'];
            $end_week = Carbon::parse($start_week)->endOfWeek()->format('Y-m-d');
            $time = $filter['time'];
            $countQuery->whereBetween('appointments.scheduled_date', [$start_week, $end_week]);

        } else if (hasFilter($filter, 'type') && $filter['type'] == 'month') {

            $start_month = Carbon::parse($filter['date'])->startOfMonth()->format('Y-m-d');
            $end_month = Carbon::parse($filter['date'])->endOfMonth()->format('Y-m-d');
            $time = $filter['time'];
            $countQuery->whereBetween('appointments.scheduled_date', [$start_month, $end_month]);
        } else {
            $countQuery->whereDate('appointments.scheduled_date', $today);
            $countQuery->where('appointments.scheduled_time', '>=', $todayTime);
        }

        $iTotalRecords = $countQuery->count();

        list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $records = array();
        $records["data"] = array();

        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_consultancy') && !Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }


        if ($orderBy == 'name') { /* Need to append appropriate table name to order by, it was missing before*/
            $orderBy = 'appointments.name';
        }

        if (auth()->id() != 1) {
            $resultQuery->where('appointments.location_id', $this->getUserLocation());
        }

        if (hasFilter($filter, 'type') && $filter['type'] == 'week') {
            $start_week = $filter['date'];
            $end_week = Carbon::parse($start_week)->endOfWeek()->format('Y-m-d');
            $time = $filter['time'];
            $resultQuery->whereBetween('appointments.scheduled_date', [$start_week, $end_week]);

        } else if (hasFilter($filter, 'type') && $filter['type'] == 'month') {

            $start_month = Carbon::parse($filter['date'])->startOfMonth()->format('Y-m-d');
            $end_month = Carbon::parse($filter['date'])->endOfMonth()->format('Y-m-d');
            $time = $filter['time'];

            $resultQuery->whereBetween('appointments.scheduled_date', [$start_month, $end_month]);
        } else {
            $resultQuery->whereDate('appointments.scheduled_date', $today);

            $resultQuery->where('appointments.scheduled_time', '>=', $todayTime);
        }

        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->orderBy('appointments.scheduled_time', "DESC")
            ->get();

        $invoicearray = array();

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
                    ['invoice_status_id', '=', $invoice_status->id]
                ])->first();
                $invoicearray[] = $invoice;
                if ($invoice) {
                    $invoiceid = $invoice->id;
                }
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } else if ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $records["data"][$index] = array(
                    'id' => $appointment->app_id,
                    'patient_id' => $appointment->patient_id,
                    'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
                    'name' => ($appointment->patient_name) ? $appointment->patient_name : $appointment->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($appointment->phone),
                    'scheduled_date' => ($appointment->scheduled_date) ? \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
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
                );

                $index++;
            }

            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records["permissions"] = [
            'status' => Gate::allows('appointments_appointment_status'),
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function getTableFilter($filters) {
        if (isset($filters['query']) && isset($filters['query']['filter'])) {
            return $filters['query']['filter'];
        }
        return [];
    }

    private function consultancies($data, $start_date, $end_date) {

        $query = Appointments::where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereBetween('scheduled_date', [$start_date, $end_date]);
        if (auth()->id() != 1) {
            $query->whereIn('location_id', ACL::getUserCentres());
        }

        $data['all_consultancies'] = $query->count();

        $query->where('appointment_status_id', config('constants.appointment_status_arrived'));
        $data['done_consultancies'] = $query->count();

        return $data;
    }

    private function treatments($data, $start_date, $end_date) {

        $query = Appointments::where('appointment_type_id', config('constants.appointment_type_service'))
            ->whereBetween('scheduled_date', [$start_date, $end_date]);
        if (auth()->id() != 1) {
            $query->whereIn('location_id', ACL::getUserCentres());
        }

        $data['all_treatments'] = $query->count();

        $query->where('appointment_status_id', config('constants.appointment_status_arrived'));
        $data['done_treatments'] = $query->count();

        return $data;
    }

    private function leads($data) {

        $data['leads'] = Leads::where('active', 1)->count();

        return $data;
    }

    private function getUserLocation() {
        return UserHasLocations::where('user_id', auth()->id())->value('location_id');
    }

    private function salesByCentre(Request $request, $data)
    {
        $data['revenue'] = 0;
        // if (Gate::allows('dashboard_revenue_by_centre') || Gate::allows('dashboard_my_revenue_by_centre')) {

        $locations = Locations::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1']
        ])->whereIn('id', ACL::getUserCentres())->get();

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        list($start_date, $end_date) = $this->getDates($request);


        $todayRecords = \App\Models\Invoices::whereBetween('created_at',  [$start_date, $end_date])
            ->whereIn('location_id', ACL::getUserCentres())
            ->where('invoice_status_id', '=', $invoicestatus->id);

        if ($request->get('performance') == '1') {
            $todayRecords = $todayRecords->where('created_by', '=', Auth::User()->id);
        }

        $todayRecords = $todayRecords->select('location_id', DB::raw("SUM(invoices.total_price) AS total_price"))
            ->groupBy('location_id')
            ->get();

        if ($locations) {
            foreach ($locations as $location) {
                if ($todayRecords) {
                    foreach ($todayRecords as $todayRecord) {
                        if ($todayRecord->location_id == $location->id) {
                            $data['revenue'] += $todayRecord->total_price;
                        }
                    }
                }
            }
        }

        //}

        return $data;
    }

    public function collectionByCentre(Request $request)
    {
        $data = array(
            'today' => array(),
            'yesterday' => array(),
            'week' => array(),
            'month' => array(),
        );

        if (Gate::allows('dashboard_collection_by_centre') || Gate::allows('dashboard_my_collection_by_centre')) {

            $location_information = Locations::getActiveSorted(ACL::getUserCentres());



            switch ($request->type) {
                case 'today':
                    list( $report_data, $total) = dashboardreport::collectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'today', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;

                case 'yesterday':
                    list( $report_data, $total) = dashboardreport::collectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['yesterday'][] = $record;
                        }
                    }
                    break;

                case 'week':
                    list( $report_data, $total) = dashboardreport::collectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['week'][] = $record;
                        }
                    }
                    break;

                case 'month':
                    list( $report_data, $total) = dashboardreport::collectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['month'][] = $record;
                        }
                    }
                    break;
                default:
                    list( $report_data, $total) = dashboardreport::collectionbyrevenuewidgets($location_information, Auth::User()->account_id, 'today', $request);
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
            'total' => $total ?? 0
        ]);
    }

    public function revenueByCentre(Request $request)
    {
        $data = array();

        if (Gate::allows('dashboard_revenue_by_centre') || Gate::allows('dashboard_my_revenue_by_centre')) {

            $locations = Locations::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1']
            ])->get();

            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

            switch ($request->type) {
                case 'today':
                    $start_date = Carbon::now()->format('Y-m-d');
                    $end_date = Carbon::now()->format('Y-m-d');
                    break;

                case 'yesterday':
                    $start_date = Carbon::now()->subDay(1)->format('Y-m-d');
                    $end_date = Carbon::now()->subDay(1)->format('Y-m-d');
                    break;

                case 'week':
                    $start_date = Carbon::now()->subDay(6)->format('Y-m-d');
                    $end_date = Carbon::now()->format('Y-m-d');
                    break;

                case 'month':
                    $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                    $end_date = Carbon::now()->endOfMonth()->format('Y-m-d');
                    break;
                default:
                    $start_date = Carbon::now()->format('Y-m-d');
                    $end_date = Carbon::now()->format('Y-m-d');
                    break;
            }


            $todayRecords = \App\Models\Invoices::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where('invoice_status_id', '=', $invoicestatus->id);

            if ($request->get('performance') == '1') {
                $todayRecords = $todayRecords->where('created_by', '=', Auth::User()->id);
            }

            $todayRecords = $todayRecords->select('location_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                ->groupBy('location_id')
                ->get();

            $total = 0;
            $data[0] = array(
                'Task',
                'Hours per Day'
            );

            if ($locations) {
                foreach ($locations as $counter => $location) {
                    if ($counter == 0) {
                        $data[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                    }
                    if ($todayRecords) {
                        foreach ($todayRecords as $todayRecord) {
                            if ($todayRecord->location_id == $location->id) {
                                $data[] = [
                                    $location->city->name . ' - ' . $location->name,
                                    $todayRecord->total_price
                                ];
                                $total += $todayRecord->total_price;
                            }
                        }
                    }
                }
            }

            return ApiHelper::apiResponse($this->success, 'Bar chart data', true, [
                'pie' => $data,
                'total' => $total
            ]);
        }
    }

    public function revenueByService(Request $request)
    {
        $data = array();
        $total = 0;

        if (Gate::allows('dashboard_revenue_by_service') || Gate::allows('dashboard_my_revenue_by_service')) {

            $services = Services::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1']
            ])->get();

            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            if ($request->type == 'today') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', \Carbon\Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }

                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $today = array();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($todayRecords) {
                            foreach ($todayRecords as $todayRecord) {
                                if ($todayRecord->service_id == $service->id) {
                                    $today[$service->id] = [
                                        $service->name,
                                        $todayRecord->total_price
                                    ];

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

                $yesterdayRecords = $yesterdayRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $yesterday = array();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $yesterday[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($yesterdayRecords) {
                            foreach ($yesterdayRecords as $yesterdayRecord) {
                                if ($yesterdayRecord->service_id == $service->id) {
                                    $yesterday[$service->id] = [
                                        $service->name,
                                        $yesterdayRecord->total_price
                                    ];
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

                $last7DaysRecords = $last7DaysRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $last7days = array();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $last7days[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($last7DaysRecords) {
                            foreach ($last7DaysRecords as $last7DaysRecord) {
                                if ($last7DaysRecord->service_id == $service->id) {
                                    $last7days[$service->id] = [
                                        $service->name,
                                        $last7DaysRecord->total_price
                                    ];
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
            if ($request->type ==  'month') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where('invoices.created_by', Auth::User()->id);
                }

                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();

                $thisMonth = array();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $thisMonth[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($thisMonthRecords) {
                            foreach ($thisMonthRecords as $thisMonthRecord) {
                                if ($thisMonthRecord->service_id == $service->id) {
                                    $thisMonth[$service->id] = [
                                        $service->name,
                                        $thisMonthRecord->total_price
                                    ];

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
            'total' => $total,
        ]);
    }

    public function appointmentByStatus(Request $request)
    {

        if (Gate::allows('dashboard_appointment_by_status') || Gate::allows('dashboard_my_appointment_by_status')) {
            $appointment_statuses = AppointmentStatuses::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['parent_id', '=', '0'],
            ])->get();

            switch ($request->type) {
                case 'today':
                    $start_date = \Carbon\Carbon::now()->format('Y-m-d');
                    $end_date = Carbon::now()->format('Y-m-d');
                    break;

                case 'yesterday':
                    $start_date = Carbon::now()->subDay(1)->format('Y-m-d');
                    $end_date = Carbon::now()->subDay(1)->format('Y-m-d');
                    break;

                case 'week':
                    $start_date = Carbon::now()->subDay(6)->format('Y-m-d');
                    $end_date = Carbon::now()->format('Y-m-d');
                    break;

                case 'month':
                    $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                    $end_date = Carbon::now()->endOfMonth()->format('Y-m-d');
                    break;
                default:
                    $start_date = Carbon::now()->format('Y-m-d');
                    $end_date = Carbon::now()->format('Y-m-d');
                    break;
            }


            $todayRecords = Appointments::whereDate('created_at', '>=', $start_date)->whereDate('created_at', '<=', $end_date)->whereIn('location_id', ACL::getUserCentres());

            if ($request->get('performance')) {
                $todayRecords = $todayRecords->where('created_by', Auth::User()->id);
            }

            $todayRecords = $todayRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();

            $total = 0;
            $data = array();
            if ($appointment_statuses) {
                foreach ($appointment_statuses as $key => $appointment_status) {
                    if ($key === 0) {
                        $data[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                    }

                    if ($todayRecords) {
                        foreach ($todayRecords as $count => $todayRecord) {
                            if ($todayRecord->appointment_status_id == $appointment_status->id) {

                                $data[] = [
                                    $appointment_status->name,
                                    $todayRecord->total
                                ];

                                $total += $todayRecord->total;

                            }
                        }
                    }
                }
            }

            return ApiHelper::apiResponse($this->success, 'Appointment data', true, [
                'appointment' => $data,
                'total' => $total
            ]);
        }
    }

    public function getDates($request) {

        switch ($request->type) {
            case 'today':
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;

            case 'yesterday':
                $start_date = Carbon::now()->subDay(1)->format('Y-m-d');
                $end_date = Carbon::now()->subDay(1)->format('Y-m-d');
                break;

            case 'week':
                $start_date = Carbon::now()->subDay(6)->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;

            case 'month':
                $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                $end_date = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            default:
                $start_date = \Carbon\Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
        }

        return [
            $start_date,
            $end_date
        ];
    }

}
