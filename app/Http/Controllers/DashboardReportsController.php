<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use Illuminate\Support\Carbon;
use App\Reports\dashboardstats;
use App\Reports\dashboardreport;
use App\Models\Locations;
use App\Models\Services;
use Illuminate\Support\Facades\Auth;
Use App\Models\Patients;
use App\Models\User;
use Gate;
use App\Helpers\ACL;
use App;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use Illuminate\Support\Facades\DB;

class DashboardReportsController extends Controller
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
    
    public function collectionByCentre(Request $request)
    {
       
        $data = array(
            'today' => array(),
            'yesterday' => array(),
            'last7days' => array(),
            'thismonth' => array(),
            'lastmonth' => array(),
        );
        $location_information = ACL::getUserCentres();
        if (Gate::allows('dashboard_collection_by_centre') || Gate::allows('dashboard_my_collection_by_centre')) {
            if ($request->get('today') != '') {
                list( $todayRecords, $total) = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
                if (count($todayRecords)) {
                    foreach ($todayRecords as $record) {
                        $data['today'][] = $record;
                    }
                }
            }
            if ($request->get('yesterday') != '') {
                list( $yesterdayRecords, $total) = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                if (count($yesterdayRecords)) {
                    foreach ($yesterdayRecords as $record) {
                        $data['yesterday'][] = $record;
                    }
                } 
            }
            if ($request->get('last7days') != '') {
                list( $last7dayRecords, $total) = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                if (count($last7dayRecords)) {
                    foreach ($last7dayRecords as $record) {
                        $data['last7days'][] = $record;
                    }
                }
            }
            if ($request->get('thismonth') != '') {
                list( $thisMonthRecords, $total) = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                if (count($thisMonthRecords)) {
                    foreach ($thisMonthRecords as $record) {
                        $data['thismonth'][] = $record;
                    }
                }
            }
            if ($request->get('lastmonth') != '') {
                list( $thisMonthRecords, $total) = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'lastMonth', $request);
                if (count($thisMonthRecords)) {
                    foreach ($thisMonthRecords as $record) {
                        $data['lastmonth'][] = $record;
                    }
                }
            }
        }
        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $data,
            'total' => number_format($total ?? 0, 2)
        ]);
    }
    public function myCollectionByCentre(Request $request)
    {
        $data = array(
            'today' => array(),
            'yesterday' => array(),
            'week' => array(),
            'month' => array(),
        );
        if (Gate::allows('dashboard_my_collection_by_centre')) {
            $location_information = Locations::getActiveSorted(ACL::getUserCentres());
            switch ($request->type) {
                case 'today':
                    list( $report_data, $total) = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;
                case 'yesterday':
                    list( $report_data, $total) = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['yesterday'][] = $record;
                        }
                    }
                    break;
                case 'week':
                    list( $report_data, $total) = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['week'][] = $record;
                        }
                    }
                break;
                case 'month':
                    list( $report_data, $total) = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['month'][] = $record;
                        }
                    }
                break;
                case 'lastmonth':
                    list( $report_data, $total) = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'lastMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['lastmonth'][] = $record;
                        }
                    }
                break;
                default:
                    list( $report_data, $total) = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
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
            'total' => number_format($total ?? 0, 2)
        ]);
    }
    public function revenueByCentre(Request $request)
    {
        $data = array();
        if (Gate::allows('dashboard_revenue_by_centre')) {
            $locations = ACL::getUserCentres();
           
            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            list($start_date, $end_date) =  $this->getDates($request);
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
                    $location_detail = Locations::find($location);
                    if ($counter == 0) {
                        $data[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                    }
                    if ($todayRecords) {
                        foreach ($todayRecords as $todayRecord) {
                            if ($todayRecord->location_id == $location_detail->id) {
                                $data[] = [
                                    $location_detail->city->name . ' - ' . $location_detail->name,
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
                'total' =>  number_format($total ?? 0, 2)
            ]);
        }
    }
    public function myRevenueByCentre(Request $request)
    {
        $data = array();
        if (Gate::allows('dashboard_my_revenue_by_centre')) {
            $locations = Locations::getActiveSortedLocations(ACL::getUserCentres());
            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            list($start_date, $end_date) =  $this->getDates($request);
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
                'total' =>  number_format($total ?? 0, 2)
            ]);
        }
    }
    public function revenueByService(Request $request)
    {
        $data = array();
        $total = 0;
        $today = array();
        $colors = array();
        if (Gate::allows('dashboard_revenue_by_service')) {
            $services = Services::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1']
            ])->get();
            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            if ($request->get('today')) {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();
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
            if ($request->get('yesterday')) {
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
            if ($request->get('last7days')) {
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
            if ($request->get('thismonth')) {
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
            if ($request->get('lastmonth')) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
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

                                    $colors[] = $service->color;

                                    $total += $thisMonthRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($thisMonth)) {
                    foreach ($thisMonth as $record) {
                        $data['lastmonth'][] = $record;
                    }
                }
            }
        }
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' =>  number_format($total ?? 0, 2),
        ]);
    }
    public function myRevenueByService(Request $request)
    {
        $data = array();
        $total = 0;
        $today = array();
        $colors = array();
        if (Gate::allows('dashboard_my_revenue_by_service')) {
            $services = Services::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1']
            ])->get();
            $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
            if ($request->period == '') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();
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
            if ($request->period=='today') {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();
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
            if ($request->period=='yesterday') {
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
            if ($request->period=='last7days') {
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
            if ($request->period=='thismonth') {
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
            if ($request->period=='thismonth') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
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

                                    $colors[] = $service->color;

                                    $total += $thisMonthRecord->total_price;
                                }
                            }
                        }
                    }
                }
                if (count($thisMonth)) {
                    foreach ($thisMonth as $record) {
                        $data['lastmonth'][] = $record;
                    }
                }
            }
        }
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' =>  number_format($total ?? 0, 2),
        ]);
    }
    public function getDates($request) {

        switch ($request->period) {
            case 'today':
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
            break;
            case 'yesterday':
                $start_date = Carbon::now()->subDay(1)->format('Y-m-d');
                $end_date = Carbon::now()->subDay(1)->format('Y-m-d');
            break;
            case 'last7days':
                $start_date = Carbon::now()->startOfWeek()->format('Y-m-d');
                $end_date = Carbon::now()->endOfWeek()->format('Y-m-d');
            break;
            case 'thismonth':
                $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                $end_date = Carbon::now()->endOfMonth()->format('Y-m-d');
            break;
            case 'lastmonth':
                $start_date = Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d');
                $end_date = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
            break;
            default:
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
        }

        return [
            $start_date,
            $end_date
        ];
    }
    public function AppointmentByStatus(Request $request)
    {
       
        $data = array();
        $total = 0;
        $today = array();
        $colors = array();
        if (Gate::allows('dashboard_appointment_by_status')) {
           $appointment_statuses = AppointmentStatuses::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['parent_id', '=', '0'],
            ])->get();
            if ($request->period == '') {
                $todayRecords = Appointments::whereDate('scheduled_date', '=', Carbon::now()->format('Y-m-d'))
                ->where('appointment_type_id',$request->type)
                ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $todayRecords = $todayRecords->where('created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();
                    if ($appointment_statuses) {
                        $total = 0;
                        foreach ($appointment_statuses as $appointment_status) {
                            $today[0] = array(
                                'Task',
                                'Hours per Day'
                            );
                            if ($todayRecords) {
                                foreach ($todayRecords as $todayRecord) {
                                    if ($todayRecord->appointment_status_id == $appointment_status->id) {
                                        $today[$appointment_status->id]= [
                                            $appointment_status->name,
                                            $todayRecord->total
                                            
                                        ];
                                        
                                        $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
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
            if ($request->period=='today') {
                $todayRecords = Appointments::whereDate('scheduled_date', '=', Carbon::now()->format('Y-m-d'))

                ->where('appointment_type_id',$request->type)
                ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $todayRecords = $todayRecords->where('created_by', Auth::User()->id);  
                }
                $todayRecords = $todayRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $today[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($todayRecords) {
                            foreach ($todayRecords as $todayRecord) {
                                if ($todayRecord->appointment_status_id == $appointment_status->id) {
                                    $today[$appointment_status->id]= [
                                        $appointment_status->name,
                                        $todayRecord->total
                                        
                                    ];
                                   
                                    $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
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
            if ($request->period=='yesterday') {
                $yesterdayRecords = Appointments::whereDate('scheduled_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->where('appointment_type_id',$request->type)
                ->whereIn('location_id', ACL::getUserCentres());
                $yesterdayRecords = $yesterdayRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $yesterday[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($yesterdayRecords) {
                            foreach ($yesterdayRecords as $yestersdayRecord) {
                                if ($yestersdayRecord->appointment_status_id == $appointment_status->id) {
                                    $yesterday[$appointment_status->id]= [
                                        $appointment_status->name,
                                        $yestersdayRecord->total
                                        
                                    ];
                                   
                                    $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
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
            if ($request->period=='last7days') {
                $last7DaysRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('scheduled_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where('appointment_type_id',$request->type)
                ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7DaysRecords = $last7DaysRecords->where('created_by', Auth::User()->id); 
                }
                $last7DaysRecords = $last7DaysRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $last7days[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($last7DaysRecords) {
                            foreach ($last7DaysRecords as $last7DayRecord) {
                                if ($last7DayRecord->appointment_status_id == $appointment_status->id) {
                                    $last7days[$appointment_status->id]= [
                                        $appointment_status->name,
                                        $last7DayRecord->total
                                        
                                    ];
                                    
                                    $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
                                }
                            }
                        }
                    }  
                }
                if (count($last7days)) {
                    foreach ($last7days as $record) {
                        $data['last7days'][] = $record;
                    }
                }
            }
            if ($request->period=='thismonth') {
                $monthlyRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('scheduled_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where('appointment_type_id',$request->type)
                ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $monthlyRecords = $monthlyRecords->where('created_by', Auth::User()->id);
                }
                $monthlyRecords = $monthlyRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $monthlyRecord[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($monthlyRecords) {
                            foreach ($monthlyRecords as $monthRecord) {
                                if ($monthRecord->appointment_status_id == $appointment_status->id) {
                                    $monthlyRecord[$appointment_status->id]= [
                                        $appointment_status->name,
                                        $monthRecord->total
                                        
                                    ];
                                    
                                    $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
                                }
                            }
                        }
                    }
                    
                }
                if (count($monthlyRecord)) {
                    foreach ($monthlyRecord as $record) {
                        $data['thismonth'][] = $record;
                    }
                }
            }
            if ($request->period=='lastmonth') {
                $monthlyRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
                ->whereDate('scheduled_date', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
                ->where('appointment_type_id',$request->type)
                ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $monthlyRecords = $monthlyRecords->where('created_by', Auth::User()->id);
                }
                $monthlyRecords = $monthlyRecords->select('base_appointment_status_id as appointment_status_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('base_appointment_status_id')
                ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $monthlyRecord[0] = array(
                            'Task',
                            'Hours per Day'
                        );
                        if ($monthlyRecords) {
                            foreach ($monthlyRecords as $monthRecord) {
                                if ($monthRecord->appointment_status_id == $appointment_status->id) {
                                    $monthlyRecord[$appointment_status->id]= [
                                        $appointment_status->name,
                                        $monthRecord->total
                                        
                                    ];
                                    
                                    $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
                                }
                            }
                        }
                    }
                    
                }
                if (count($monthlyRecord)) {
                    foreach ($monthlyRecord as $record) {
                        $data['lastmonth'][] = $record;
                    }
                }
            }
        }
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' =>  0,
        ]);
    }
    public function AppointmentByType(Request $request)
    {
        $data = array();
        $total = 0;
        $today = array();
        $colors = array();
        $appointment_types = AppointmentTypes::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->get();
        if ($request->period == '') {
            $todayRecords = Appointments::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
            ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $todayRecords = $todayRecords->where('created_by', Auth::User()->id);
            }
            $todayRecords = $todayRecords->select('appointment_type_id', DB::raw("COUNT(id) AS total"))
            ->groupBy('appointment_type_id')
            ->get();
            $today = array();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $today[0] = array(
                        'Task',
                        'Hours per Day'
                    );
                    if ($todayRecords) {
                        foreach ($todayRecords as $todayRecord) {
                            if ($todayRecord->appointment_type_id == $appointment_type->id) {
                                $today[$appointment_type->id]= [
                                    $appointment_type->name,
                                    $todayRecord->total        
                                ]; 
                                $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
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
        if ($request->period=='today') {
            $todayRecords = Appointments::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
            ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $todayRecords = $todayRecords->where('created_by', Auth::User()->id);
            }
            $todayRecords = $todayRecords->select('appointment_type_id', DB::raw("COUNT(id) AS total"))
            ->groupBy('appointment_type_id')
            ->get();
            $today = array();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $today[0] = array(
                        'Task',
                        'Hours per Day'
                    );
                    if ($todayRecords) {
                        foreach ($todayRecords as $todayRecord) {
                            if ($todayRecord->appointment_type_id == $appointment_type->id) {
                                $today[$appointment_type->id]= [
                                    $appointment_type->name,
                                    $todayRecord->total
                                    
                                ];
                                
                                $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
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
        if ($request->period=='yesterday') {
            $yesterdayRecords = Appointments::whereDate('created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereIn('location_id', ACL::getUserCentres());

            if ($request->get('performance')) {
                $yesterdayRecords = $yesterdayRecords->where('created_by', Auth::User()->id);
            }

            $yesterdayRecords = $yesterdayRecords->select('appointment_type_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('appointment_type_id')
                ->get();

            $today = array();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $yesterday[0] = array(
                        'Task',
                        'Hours per Day'
                    );
                    if ($yesterdayRecords) {
                        foreach ($yesterdayRecords as $yesterdayRecord) {
                            if ($yesterdayRecord->appointment_type_id == $appointment_type->id) {
                                $yesterday[$appointment_type->id]= [
                                    $appointment_type->name,
                                    $yesterdayRecord->total
                                    
                                ];
                                
                                $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
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
        if ($request->period=='last7days') {
            $weeklyRecords = Appointments::whereDate('created_at', '=', Carbon::now()->subDay(6)->format('Y-m-d'))
            ->whereDate('created_at', '<=', Carbon::now()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());

            if ($request->get('performance')) {
                $weeklyRecords = $weeklyRecords->where('created_by', Auth::User()->id);
            }
            $weeklyRecords = $weeklyRecords->select('appointment_type_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('appointment_type_id')
                ->get();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $last7days[0] = array(
                        'Task',
                        'Hours per Day'
                    );
                    if ($weeklyRecords) {
                        foreach ($weeklyRecords as $weeklyRecord) {
                            if ($weeklyRecord->appointment_type_id == $appointment_type->id) {
                                $last7days[$appointment_type->id]= [
                                    $appointment_type->name,
                                    $weeklyRecord->total
                                    
                                ];
                                
                                $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
                            }
                        }
                    }
                }
                
            }
            if (count($last7days)) {
                foreach ($last7days as $record) {
                    $data['last7days'][] = $record;
                }
            }
        }
        if ($request->period=='thismonth') {
            $monthlyRecords = Appointments::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
            ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $monthlyRecords = $monthlyRecords->where('created_by', Auth::User()->id);
            }
            $monthlyRecords = $monthlyRecords->select('appointment_type_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('appointment_type_id')
                ->get();
            $today = array();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $month[0] = array(
                        'Task',
                        'Hours per Day'
                    );
                    if ($monthlyRecords) {
                        foreach ($monthlyRecords as $monthlyRecord) {
                            if ($monthlyRecord->appointment_type_id == $appointment_type->id) {
                                $month[$appointment_type->id]= [
                                    $appointment_type->name,
                                    $monthlyRecord->total
                                    
                                ]; 
                                $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
                            }
                        }
                    }
                }
                
            }
            if (count($month)) {
                foreach ($month as $record) {
                    $data['thismonth'][] = $record;
                }
            }
            
        }
        if ($request->period=='lastmonth') {
            $monthlyRecords = Appointments::whereDate('created_at', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
            ->whereDate('created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $monthlyRecords = $monthlyRecords->where('created_by', Auth::User()->id);
            }
            $monthlyRecords = $monthlyRecords->select('appointment_type_id', DB::raw("COUNT(id) AS total"))
                ->groupBy('appointment_type_id')
                ->get();
            $today = array();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $month[0] = array(
                        'Task',
                        'Hours per Day'
                    );
                    if ($monthlyRecords) {
                        foreach ($monthlyRecords as $monthlyRecord) {
                            if ($monthlyRecord->appointment_type_id == $appointment_type->id) {
                                $month[$appointment_type->id]= [
                                    $appointment_type->name,
                                    $monthlyRecord->total
                                    
                                ];
                                $colors=["#3375de","#c8cf19","#cf7a19","#cf1931","#19cf43","#a119cf"];
                            }
                        }
                    }
                }
                
            }
            if (count($month)) {
                foreach ($month as $record) {
                    $data['lastmonth'][] = $record;
                }
            }
            
        }
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' =>  0,
        ]);
    }
}
