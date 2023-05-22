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
use App\Models\AppointmentsDailyStats;
use Illuminate\Support\Facades\Config;
use App\Helpers\ACL;
use App;
use App\Models\RoleHasUsers;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\PackageAdvances;
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
            'week' => array(),
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
            if ($request->get('week') != '') {
                list( $weekRecords, $total) = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'week', $request);
                if (count($weekRecords)) {
                    foreach ($weekRecords as $record) {
                        $data['week'][] = $record;
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
    public function CollectionByServiceCategory(Request $request)
    {
        $data = array(
            'today' => array(),
            'yesterday' => array(),
            'last7days' => array(),
            'thismonth' => array(),
            'lastmonth' => array(),
        );
        $services = Services::where([
            'account_id'=> Auth::User()->account_id,
            'active'=>'1',
            'parent_id'=>'0'
        ])->get();
        if (Gate::allows('dashboard_collection_by_centre') || Gate::allows('dashboard_my_collection_by_centre')) {
            if ($request->today) {
                $total = 0;
                $today[0] = array(
                    'Task',
                    'Hours per Day'
                );
                foreach ($services as $service) {
                    $childServices = Services::where('parent_id',$service->id)->get();
                    foreach($childServices as $child){
                        $packagesadvances = PackageAdvances::join('appointments','appointments.id','package_advances.appointment_id')
                        ->whereDate('package_advances.created_at', '=', Carbon::now()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id'=> Auth::User()->account_id,
                            'appointments.service_id'=>$child->id,
                        ])->get();
                        if($packagesadvances ){
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
                            $today[$service->id] = array(
                                $service->name,
                                $In_hand_balance,
                            );
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
            if ($request->yesterday) {
                $total = 0;
                $yesterday[0] = array(
                    'Task',
                    'Hours per Day'
                );
                foreach ($services as $service) {
                    $childServices = Services::where('parent_id',$service->id)->get();
                    foreach($childServices as $child){
                        $packagesadvances = PackageAdvances::join('appointments','appointments.id','package_advances.appointment_id')
                        ->whereDate('package_advances.created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id'=> Auth::User()->account_id,
                            'appointments.service_id'=>$child->id,
                        ])->get();
                        if($packagesadvances ){
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
                            $yesterday[$service->id] = array(
                                $service->name,
                                $In_hand_balance,
                            );
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
            if ($request->last7days) {
                $total = 0;
                $last7days[0] = array(
                    'Task',
                    'Hours per Day'
                );
                foreach ($services as $service) {
                    $childServices = Services::where('parent_id',$service->id)->get();
                    foreach($childServices as $child){
                        $packagesadvances = PackageAdvances::join('appointments','appointments.id','package_advances.appointment_id')
                        ->whereDate('package_advances.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                        ->whereDate('package_advances.created_at', '<=', Carbon::now()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id'=> Auth::User()->account_id,
                            'appointments.service_id'=>$child->id,
                        ])->get();
                        if($packagesadvances ){
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
                            $last7days[$service->id] = array(
                                $service->name,
                                $In_hand_balance,
                            );
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
            if ($request->thismonth) {
                $total = 0;
                $thismonth[0] = array(
                    'Task',
                    'Hours per Day'
                );
                foreach ($services as $service) {
                    $childServices = Services::where(['parent_id'=>$service->id])->get();
                    foreach($childServices as $child){
                        $packagesadvances = PackageAdvances::join('appointments','appointments.id','package_advances.appointment_id')
                        ->whereDate('package_advances.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                        ->whereDate('package_advances.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id'=>Auth::User()->account_id,
                            'appointments.service_id'=>$child->id,
                        ])->get();
                        if($packagesadvances ){
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
                            $thismonth[$service->id] = array(
                                $service->name,
                                $In_hand_balance,
                            );
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
            if ($request->lastmonth) {

                $total = 0;
                $lastmonth[0] = array(
                    'Task',
                    'Hours per Day'
                );
                foreach ($services as $service) {
                    $childServices = Services::where(['parent_id' => $service->id])->get();
                    foreach($childServices as $child){
                        $packagesadvances = PackageAdvances::join('appointments','appointments.id','package_advances.appointment_id')
                        ->whereDate('package_advances.created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                        ->whereDate('package_advances.created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                        ->where([
                            'package_advances.account_id'=> Auth::User()->account_id,
                            'appointments.service_id'=>$child->id,
                        ])->get();
                        if($packagesadvances ){
                            $balance = 0;
                            $total_balance = 0;
                            $total_revenue_cash_in = 0;
                            $total_revenue_card_in = 0;
                            $total_refund_out = 0;
                            foreach ($packagesadvances as $packagesadvance) {
                                if (
                                    (
                                        $packagesadvance->cash_flow == 'in' &&
                                        $packagesadvance->is_adjustment == '0' &&
                                        $packagesadvance->is_tax == '0' &&
                                        $packagesadvance->is_cancel == '0'
                                    )
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
                            $lastmonth[$service->id] = array(
                                $service->name,
                                $In_hand_balance,

                            );
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
        }
        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors ?? '',
            'total' =>  number_format($total ?? 0, 2),
        ]);
    }
    public function RevenueByServiceCategory(Request $request)
    {
        $data = array();
        $total = 0;
        $today = array();
        $colors = array();
        if (Gate::allows('dashboard_revenue_by_service')) {
            $services = Services::where([
                'account_id'=>Auth::User()->account_id,
                'active'=>'1',
                'parent_id'=>'0'
            ])->get();
            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
            if ($request->today) {
                $todayRecords = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $todayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $todayRecords = $todayRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                    $prepareData = [];
                    foreach ($todayRecords as $key => $todayRecord) {
                        $parent_services = Services::with('parent')->where('id',$todayRecord->service_id)->first();
                        $service_name = $parent_services->parent ? $parent_services->parent->name : $parent_services->name;
                        $service_id = $parent_services->parent ? $parent_services->parent->id : $parent_services->id;

                        if(array_key_exists($service_id, $prepareData)){
                            $prepareData[$service_id]['total'] += $todayRecord->total_price;
                        }else{
                            $prepareData[$service_id] = [
                                'id' => $service_id,
                                'name' => $service_name,
                                'total' => $todayRecord->total_price
                            ];
                        }
                    }
                    $today[0] = ['Task','Hours per Day'];
                    foreach ($prepareData as $todayRecord) {
                        $today[$todayRecord['id']] = [
                            $todayRecord['name'],
                            $todayRecord['total']
                        ];
                    }
                    if (count($today) > 0) {
                        foreach ($today as $record) {
                            $data['today'][] = $record;
                        }
                    }
            }
            if ($request->yesterday) {
                $yesterdayRecords = Invoices::leftjoin('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $yesterdayRecords->where('invoices.created_by', Auth::User()->id);
                }
                $yesterdayRecords = $yesterdayRecords->select('invoices.id','invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $yesterday = array();
                $prepareData = [];
                    foreach ($yesterdayRecords as $key => $todayRecord) {
                        $parent_services = Services::with('parent')->where('id',$todayRecord->service_id)->first();
                        $service_name = $parent_services->parent ? $parent_services->parent->name : $parent_services->name;
                        $service_id = $parent_services->parent ? $parent_services->parent->id : $parent_services->id;

                        if(array_key_exists($service_id, $prepareData)){
                            $prepareData[$service_id]['total'] += $todayRecord->total_price;
                        }else{
                            $prepareData[$service_id] = [
                                'id' => $service_id,
                                'name' => $service_name,
                                'total' => $todayRecord->total_price
                            ];
                        }
                    }
                    $yesterday[0] = ['Task','Hours per Day'];
                    foreach ($prepareData as $todayRecord) {
                        $yesterday[$todayRecord['id']] = [
                            $todayRecord['name'],
                            $todayRecord['total']
                        ];
                    }
                if (count($yesterday) > 0) {
                    foreach ($yesterday as $record) {
                        $data['yesterday'][] = $record;
                    }
                }

            }
            if ($request->last7days) {
                $last7DaysRecords = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
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
                $prepareData = [];
                    foreach ($last7DaysRecords as $key => $todayRecord) {
                        $parent_services = Services::with('parent')->where('id',$todayRecord->service_id)->first();
                        $service_name = $parent_services->parent ? $parent_services->parent->name : $parent_services->name;
                        $service_id = $parent_services->parent ? $parent_services->parent->id : $parent_services->id;

                        if(array_key_exists($service_id, $prepareData)){
                            $prepareData[$service_id]['total'] += $todayRecord->total_price;
                        }else{
                            $prepareData[$service_id] = [
                                'id' => $service_id,
                                'name' => $service_name,
                                'total' => $todayRecord->total_price
                            ];
                        }
                    }
                    $last7days[0] = ['Task','Hours per Day'];
                    foreach ($prepareData as $todayRecord) {
                        $last7days[$todayRecord['id']] = [
                            $todayRecord['name'],
                            $todayRecord['total']
                        ];
                    }
                if (count($last7days) > 0) {
                    foreach ($last7days as $record) {
                        $data['week'][] = $record;
                    }
                }

            }
            if ($request->thismonth) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id',  'invoice_details.invoice_id')
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
                $prepareData = [];
                    foreach ($thisMonthRecords as $key => $todayRecord) {
                        $parent_services = Services::with('parent')->where('id',$todayRecord->service_id)->first();
                        $service_name = $parent_services->parent ? $parent_services->parent->name : $parent_services->name;
                        $service_id = $parent_services->parent ? $parent_services->parent->id : $parent_services->id;

                        if(array_key_exists($service_id, $prepareData)){
                            $prepareData[$service_id]['total'] += $todayRecord->total_price;
                        }else{
                            $prepareData[$service_id] = [
                                'id' => $service_id,
                                'name' => $service_name,
                                'total' => $todayRecord->total_price
                            ];
                        }
                    }
                    $thisMonth[0] = ['Task','Hours per Day'];
                    foreach ($prepareData as $todayRecord) {
                        $thisMonth[$todayRecord['id']] = [
                            $todayRecord['name'],
                            $todayRecord['total']
                        ];
                    }
                if (count($thisMonth) > 0) {
                    foreach ($thisMonth as $record) {
                        $data['month'][] = $record;
                    }
                }

            }
            if ($request->lastmonth) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id',  'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=',Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                    ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where('invoices.created_by', Auth::User()->id);
                }
                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw("SUM(invoices.total_price) AS total_price"))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $thisMonth = array();
                $prepareData = [];
                    foreach ($thisMonthRecords as $key => $todayRecord) {
                        $parent_services = Services::with('parent')->where('id',$todayRecord->service_id)->first();
                        $service_name = $parent_services->parent ? $parent_services->parent->name : $parent_services->name;
                        $service_id = $parent_services->parent ? $parent_services->parent->id : $parent_services->id;

                        if(array_key_exists($service_id, $prepareData)){
                            $prepareData[$service_id]['total'] += $todayRecord->total_price;
                        }else{
                            $prepareData[$service_id] = [
                                'id' => $service_id,
                                'name' => $service_name,
                                'total' => $todayRecord->total_price
                            ];
                        }
                    }
                    $thisMonth[0] = ['Task','Hours per Day'];
                    foreach ($prepareData as $todayRecord) {
                        $thisMonth[$todayRecord['id']] = [
                            $todayRecord['name'],
                            $todayRecord['total']
                        ];
                    }
                if (count($thisMonth) > 0) {
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

            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
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
            $invoicestatus = InvoiceStatuses::where(['slug'=>'paid'])->first();
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
            $invoicestatus = InvoiceStatuses::where(['slug'=> 'paid'])->first();
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
                        $data['last7days'][] = $record;
                    }
                }
            }
            if ($request->get('week')) {
                $last7DaysRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
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
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
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
            $invoicestatus = InvoiceStatuses::where(['slug'=> 'paid'])->first();
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
                $start_date = Carbon::now()->subDay(6)->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'week':
                $start_date = Carbon::now()->startOfWeek()->format('Y-m-d');
                $end_date = Carbon::now()->endOfWeek()->format('Y-m-d');
            break;
            case 'thismonth':
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
            if ($request->period=='week') {
                $last7DaysRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('scheduled_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
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
                        $data['week'][] = $record;
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
                $monthlyRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('scheduled_date', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
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
            $monthlyRecords = Appointments::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
            ->whereDate('created_at', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
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
    public function getChild(Request $request)
    {
        if($request->child_id){
            $service = Services::find($request->child_id);
            return ApiHelper::apiResponse($this->success, 'service data', true, [
                'child' => $service->name,
            ]);
        }else{
            return ApiHelper::apiResponse($this->success, 'service data', true, [
                'child' => 'N/A',
            ]);
        }
    }
    public function CentreWiseArrival(Request $request)
    {
        //dd($request->all(), $request->period == '');
        $total_apts = [];
        $arrived_apts = [];
        $walkin_apts = [];
        $lables = [];
        if ($request->period == '') {
            $request['period'] = 'thismonth';
        }
        /* if($request->centre_id != 'all'){
            $where[] = array(['centre_id' => $request->centre_id])
        } */
        if ($request->period=='yesterday') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            if($request->centre_id && $request->centre_id != 'All'){
                $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                    ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['centre_id' => $request->centre_id])->groupBy('centre_id')->get()->toArray();
                $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                    ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['centre_id' => $request->centre_id])->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();

                $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                    ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['appointment_status_id' => 2])
                    ->where('centre_id',$request->centre_id)->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            }else{
                $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                    ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereIn('centre_id', ACL::getUserCentres())->groupBy('centre_id')->get()->toArray();
                $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                    ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereIn('centre_id', ACL::getUserCentres())->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();

                    $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                    ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['appointment_status_id' => 2])
                    ->whereIn('centre_id', ACL::getUserCentres())->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            }
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='last7days') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->whereIn('centre_id', ACL::getUserCentres())->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='week') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->whereIn('centre_id', ACL::getUserCentres())->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='thismonth') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            if($request->centre_id && $request->centre_id == 'All'){
                $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->groupBy('centre_id')->get()->toArray();
                $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                    ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->whereIn('centre_id', ACL::getUserCentres())->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
                $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                    ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where(['appointment_status_id' => 2])
                    ->whereIn('centre_id', ACL::getUserCentres())->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            } else {
                $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['centre_id' => $request->centre_id])->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
            ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
            ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['centre_id' => $request->centre_id])->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();

            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
            ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
            ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->where('centre_id',$request->centre_id)->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            }
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='lastmonth') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->whereIn('centre_id', ACL::getUserCentres())->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->whereIn('centre_id', ACL::getUserCentres())->whereIn('user_id',$fdm_users)->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts,
            'walkin'=>$walkin_apts
        ]);
    }
    public function LocationWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $walkin_apts = [];
        $lables = [];
        if ($request->period == '') {
            $request['period'] = 'thismonth';
        }
        if ($request->period=='yesterday') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1))
                //->whereIn('centre_id', ACL::getUserCentres())
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                return $query->where(['centre_id' => $request->centre_id]);
                })
                ->when($request->centre_id == 'All', function ($query) use ($request) {
                    return $query->whereIn('centre_id', ACL::getUserCentres());
                })
            ->groupBy('centre_id')
            ->get()
            ->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->whereIn('user_id',$fdm_users)
                ->where(['appointment_status_id' => 2])
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='last7days') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->whereIn('user_id',$fdm_users)
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='week') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->whereIn('user_id',$fdm_users)
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='thismonth') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->whereIn('user_id',$fdm_users)
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();

            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        if ($request->period=='lastmonth') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();
            $yesterday_walkin_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as walkin'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->whereIn('user_id',$fdm_users)
                ->when($request->centre_id != 'All', function ($query) use ($request) {
                    return $query->where(['centre_id' => $request->centre_id]);
                    })
                    ->when($request->centre_id == 'All', function ($query) use ($request) {
                        return $query->whereIn('centre_id', ACL::getUserCentres());
                    })
                ->groupBy('centre_id')
                ->get()
                ->toArray();

            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
            foreach($yesterday_walkin_appointments as $apt){
                array_push($walkin_apts, $apt['walkin']);
            }
        }
        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts,
            'walkin'=>$walkin_apts,
            ''
        ]);
    }
    public function UserWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $lables = [];
        if ($request->period == '') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('cron_current_date', DB::raw('count(*) as total'))
                ->where('user_id', $request->user_id)->groupBy('cron_current_date')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('cron_current_date', DB::raw('count(*) as arrived'))->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->where(['user_id' => $request->user_id , 'appointment_status_id' =>2])
                ->groupBy('cron_current_date')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                array_push($lables, $loc['cron_current_date']);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='yesterday') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('cron_current_date', DB::raw('count(*) as total'))
                ->where('user_id', $request->user_id)->groupBy('cron_current_date')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('cron_current_date', DB::raw('count(*) as arrived'))
                ->where(['user_id' => $request->user_id , 'appointment_status_id' =>2])->groupBy('cron_current_date')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                array_push($lables, $loc['cron_current_date']);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='last7days') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);

            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='week') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='thismonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='lastmonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('centre_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('centre_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('centre_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $centre = Locations::where('id', $loc['centre_id'])->first();
                array_push($lables, $centre->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts

        ]);
    }
    public function CSRWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $lables = [];
        $csr_users = RoleHasUsers::where(['role_id' => 2])->pluck('user_id');
        $csr = User::whereIn('id',$csr_users)->where('active',1)->pluck('id');
        if ($request->period=='yesterday') {

            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')
                ->get();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }

        }
        if ($request->period=='last7days') {

            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')
                ->get();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);

            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='week') {

            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }

        }
        if ($request->period=='thismonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
            ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='lastmonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();

            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts

        ]);
    }
    public function AgentWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $lables = [];
        if ($request->period == '') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $csr_users = RoleHasUsers::where(['role_id' => 2])->pluck('user_id');
            $csr = User::whereIn('id',$csr_users)->where('active',1)->pluck('id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->whereIn('user_id', $csr)->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('cron_current_date', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->whereIn('user_id', $csr)->where(['appointment_status_id' =>2])
                ->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }

        return ApiHelper::apiResponse($this->success, 'Agent wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts

        ]);
    }
    public function CsrUserWiseArrival(Request $request)
    {

        $total_apts = [];
        $arrived_apts = [];
        $lables = [];
        $csr_users = RoleHasUsers::where(['role_id' => 2])->pluck('user_id');
        $csr = User::whereIn('id',$csr_users)->where('active',1)->pluck('id');
        if ($request->period=='') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')
                ->get();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }

        }
        if ($request->period=='last7days') {

            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')
                ->get();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['appointment_status_id' => 2])
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);

            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='week') {

            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }

        }
        if ($request->period=='thismonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
            ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='lastmonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->when($request->user_id != 'All', function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when($request->user_id == 'All', function ($query) use ($request,$csr) {
                    return $query->whereIn('user_id',$csr);
                })
                ->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();

            foreach($yesterday_total_appointments as $loc){
                $username = User::whereId($loc['user_id'])->where('active',1)->first();
                if($username){
                    array_push($lables, $username->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }

        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts

        ]);
    }
    public function CallWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $lables = [];
        if ($request->period == '') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereNotIn('user_id',$fdm_users )->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
            ->whereNotIn('user_id',$fdm_users )->where(['appointment_status_id' =>2])
                ->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $user = User::find($loc['user_id']);

                if($user){
                    array_push($lables, $user->name);
                }

                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='yesterday') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->where('user_id', $request->user_id)->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->where(['user_id' => $request->user_id , 'appointment_status_id' =>2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='last7days') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);

            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='week') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='thismonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period=='lastmonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=',Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id ])->where(['appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach($yesterday_total_appointments as $loc){
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach($yesterday_arrived_appointments as $apt){
                array_push($arrived_apts, $apt['arrived']);
            }
        }

        return ApiHelper::apiResponse($this->success, 'csr wise arrival data', true, [
            'bar' => $lables,
            'total'=>$total_apts,
            'arrived'=>$arrived_apts

        ]);
    }
}
