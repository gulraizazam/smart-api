<?php

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App;
use App\Helpers\GeneralFunctions;
use App\Models\RoleHasUsers;
use App\Models\Appointments;
use App\Models\AppointmentsDailyStats;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\DoctorHasLocations;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\Models\User;
use App\Reports\dashboardreport;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Routing\Generator\Dumper\GeneratorDumper;

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
        $data = [
            'today' => [],
            'yesterday' => [],
            'last7days' => [],
            'week' => [],
            'thismonth' => [],
            'lastmonth' => [],
        ];
        $location_information = ACL::getUserCentres();
        if (Gate::allows('dashboard_collection_by_centre') || Gate::allows('dashboard_my_collection_by_centre')) {
            if ($request->get('today') != '') {
                [$today_records, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
                if (count($today_records)) {
                    foreach ($today_records as $record) {
                        $data['today'][] = $record;
                    }
                }
            }
            if ($request->get('yesterday') != '') {
                [$yesterdayRecords, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                if (count($yesterdayRecords)) {
                    foreach ($yesterdayRecords as $record) {
                        $data['yesterday'][] = $record;
                    }
                }
            }
            if ($request->get('last7days') != '') {
                [$last7dayRecords, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                if (count($last7dayRecords)) {
                    foreach ($last7dayRecords as $record) {
                        $data['last7days'][] = $record;
                    }
                }

            }
            if ($request->get('week') != '') {
                [$weekRecords, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'week', $request);
                if (count($weekRecords)) {
                    foreach ($weekRecords as $record) {
                        $data['week'][] = $record;
                    }
                }
            }
            if ($request->get('thismonth') != '') {
                [$thisMonthRecords, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                if (count($thisMonthRecords)) {
                    foreach ($thisMonthRecords as $record) {
                        $data['thismonth'][] = $record;
                    }
                }
            }
            if ($request->get('lastmonth') != '') {
                [$thisMonthRecords, $total] = dashboardreport::CollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'lastMonth', $request);
                if (count($thisMonthRecords)) {
                    foreach ($thisMonthRecords as $record) {
                        $data['lastmonth'][] = $record;
                    }
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'pie chart data', true, [
            'pie' => $data,
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
        if (Gate::allows('dashboard_collection_by_centre') || Gate::allows('dashboard_my_collection_by_centre')) {
            if ($request->today) {
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
            if ($request->yesterday) {
                $total = 0;
                $yesterday[0] = [
                    'Task',
                    'Hours per Day',
                ];
                foreach ($services as $service) {
                    $childServices = Services::where('parent_id', $service->id)->get();
                    foreach ($childServices as $child) {
                        $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')
                            ->whereDate('package_advances.created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
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
            if ($request->last7days) {
                $total = 0;
                $last7days[0] = [
                    'Task',
                    'Hours per Day',
                ];
                foreach ($services as $service) {
                    $childServices = Services::where('parent_id', $service->id)->get();
                    foreach ($childServices as $child) {
                        $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')
                            ->whereDate('package_advances.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
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
            if ($request->thismonth) {
                $total = 0;
                $thismonth[0] = [
                    'Task',
                    'Hours per Day',
                ];
                foreach ($services as $service) {
                    $childServices = Services::where(['parent_id' => $service->id])->get();
                    foreach ($childServices as $child) {
                        $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')
                            ->whereDate('package_advances.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
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
            if ($request->lastmonth) {

                $total = 0;
                $lastmonth[0] = [
                    'Task',
                    'Hours per Day',
                ];
                foreach ($services as $service) {
                    $childServices = Services::where(['parent_id' => $service->id])->get();
                    foreach ($childServices as $child) {
                        $packagesadvances = PackageAdvances::join('appointments', 'appointments.id', 'package_advances.appointment_id')
                            ->whereDate('package_advances.created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                            ->whereDate('package_advances.created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
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
        }

        return ApiHelper::apiResponse($this->success, 'service data', true, [
            'pie' => $data,
            'colors' => $colors ?? '',
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
            $services = Services::where([
                'account_id' => Auth::User()->account_id,
                'active' => '1',
                'parent_id' => '0',
            ])->get();
            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
            if ($request->today) {
                $today_records = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $today_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $today_records = $today_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $prepareData = [];
                foreach ($today_records as $key => $todayRecord) {
                    $parent_services = Services::with('parent')->where(['id' => $todayRecord->service_id])->first();
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
            if ($request->yesterday) {
                $yesterdayRecords = Invoices::leftjoin('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $yesterdayRecords->where(['invoices.created_by' => Auth::User()->id]);
                }
                $yesterdayRecords = $yesterdayRecords->select('invoices.id', 'invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $yesterday = [];
                $prepareData = [];
                foreach ($yesterdayRecords as $key => $todayRecord) {
                    $parent_services = Services::with('parent')->where(['id' => $todayRecord->service_id])->first();
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
                $yesterday[0] = ['Task', 'Hours per Day'];
                foreach ($prepareData as $todayRecord) {
                    $yesterday[$todayRecord['id']] = [
                        $todayRecord['name'],
                        $todayRecord['total'],
                    ];
                }
                if (count($yesterday) > 0) {
                    foreach ($yesterday as $record) {
                        $data['yesterday'][] = $record;
                    }
                }

            }
            if ($request->last7days) {
                $last7_days_records = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7_days_records = $last7_days_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $last7_days_records = $last7_days_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $last7days = [];
                $prepareData = [];
                foreach ($last7_days_records as $key => $todayRecord) {
                    $parent_services = Services::with('parent')->where(['id' => $todayRecord->service_id])->first();
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
                $last7days[0] = ['Task', 'Hours per Day'];
                foreach ($prepareData as $todayRecord) {
                    $last7days[$todayRecord['id']] = [
                        $todayRecord['name'],
                        $todayRecord['total'],
                    ];
                }
                if (count($last7days) > 0) {
                    foreach ($last7days as $record) {
                        $data['week'][] = $record;
                    }
                }

            }
            if ($request->thismonth) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where(['invoices.created_by' => Auth::User()->id]);
                }
                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $thisMonth = [];
                $prepareData = [];
                foreach ($thisMonthRecords as $key => $todayRecord) {
                    $parent_services = Services::with('parent')->where(['id' => $todayRecord->service_id])->first();
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
                $thisMonth[0] = ['Task', 'Hours per Day'];
                foreach ($prepareData as $todayRecord) {
                    $thisMonth[$todayRecord['id']] = [
                        $todayRecord['name'],
                        $todayRecord['total'],
                    ];
                }
                if (count($thisMonth) > 0) {
                    foreach ($thisMonth as $record) {
                        $data['month'][] = $record;
                    }
                }

            }
            if ($request->lastmonth) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where(['invoices.created_by' => Auth::User()->id]);
                }
                $thisMonthRecords = $thisMonthRecords->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                $thisMonth = [];
                $prepareData = [];
                foreach ($thisMonthRecords as $key => $todayRecord) {
                    $parent_services = Services::with('parent')->where(['id' => $todayRecord->service_id])->first();
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
                $thisMonth[0] = ['Task', 'Hours per Day'];
                foreach ($prepareData as $todayRecord) {
                    $thisMonth[$todayRecord['id']] = [
                        $todayRecord['name'],
                        $todayRecord['total'],
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
                    [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['today'][] = $record;
                        }
                    }
                    break;
                case 'yesterday':
                    [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'yesterday', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['yesterday'][] = $record;
                        }
                    }
                    break;
                case 'week':
                    [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'last7day', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['week'][] = $record;
                        }
                    }
                    break;
                case 'month':
                    [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'thisMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['month'][] = $record;
                        }
                    }
                    break;
                case 'lastmonth':
                    [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'lastMonth', $request);
                    if (count($report_data)) {
                        foreach ($report_data as $record) {
                            $data['lastmonth'][] = $record;
                        }
                    }
                    break;
                default:
                    [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($location_information, Auth::User()->account_id, 'today', $request);
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

    public function revenueByCentre(Request $request)
    {
        $data = [];
        if (Gate::allows('dashboard_revenue_by_centre')) {
            $locations = ACL::getUserCentres();

            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
            [$start_date, $end_date] = $this->getDates($request);
            $today_records = \App\Models\Invoices::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where(['invoice_status_id' => $invoicestatus->id]);
            if ($request->get('performance') == '1') {
                $today_records = $today_records->where(['created_by' => Auth::User()->id]);
            }
            $today_records = $today_records->select('location_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                ->groupBy('location_id')
                ->get();
            $total = 0;
            $data[0] = [
                'Task',
                'Hours per Day',
            ];
            if ($locations) {
                foreach ($locations as $counter => $location) {
                    $location_detail = Locations::find($location);
                    if ($counter == 0) {
                        $data[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                    }
                    if ($today_records) {
                        foreach ($today_records as $todayRecord) {
                            if ($todayRecord->location_id == $location_detail->id) {
                                $data[] = [
                                    $location_detail->city->name.' - '.$location_detail->name,
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
            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
            [$start_date, $end_date] = $this->getDates($request);
            $today_records = \App\Models\Invoices::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where(['invoice_status_id' => $invoicestatus->id]);
            if ($request->get('performance') == '1') {
                $today_records = $today_records->where(['created_by' => Auth::User()->id]);
            }
            $today_records = $today_records->select('location_id', DB::raw('SUM(invoices.total_price) AS total_price'))
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
                    if ($today_records) {
                        foreach ($today_records as $todayRecord) {
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
            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
            if ($request->get('today')) {
                $today_records = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $today_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $today_records = $today_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($today_records) {
                            foreach ($today_records as $todayRecord) {
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
            if ($request->get('yesterday')) {
                $yesterdayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $yesterdayRecords->where(['invoices.created_by' => Auth::User()->id]);
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
            if ($request->get('last7days')) {
                $last7_days_records = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7_days_records = $last7_days_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $last7_days_records = $last7_days_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
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
                        if ($last7_days_records) {
                            foreach ($last7_days_records as $last7DaysRecord) {
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
                        $data['last7days'][] = $record;
                    }
                }
            }
            if ($request->get('week')) {
                $last7_days_records = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7_days_records = $last7_days_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $last7_days_records = $last7_days_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
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
                        if ($last7_days_records) {
                            foreach ($last7_days_records as $last7DaysRecord) {
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
            if ($request->get('thismonth')) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where(['invoices.created_by' => Auth::User()->id]);
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
            if ($request->get('lastmonth')) {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where(['invoices.created_by' => Auth::User()->id]);
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
                        $data['lastmonth'][] = $record;
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
            $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
            if ($request->period == '') {
                $today_records = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $today_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $today_records = $today_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($today_records) {
                            foreach ($today_records as $todayRecord) {
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
            if ($request->period == 'today') {
                $today_records = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $today_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $today_records = $today_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
                    ->groupBy('invoice_details.service_id')
                    ->get();
                if ($services) {
                    $total = 0;
                    foreach ($services as $service) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($today_records) {
                            foreach ($today_records as $todayRecord) {
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
            if ($request->period == 'yesterday') {
                $yesterdayRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $yesterdayRecords->where(['invoices.created_by' => Auth::User()->id]);
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
            if ($request->period == 'last7days') {
                $last7_days_records = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7_days_records = $last7_days_records->where(['invoices.created_by' => Auth::User()->id]);
                }
                $last7_days_records = $last7_days_records->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
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
                        if ($last7_days_records) {
                            foreach ($last7_days_records as $last7DaysRecord) {
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
            if ($request->period == 'thismonth') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where(['invoices.created_by' => Auth::User()->id]);
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
            if ($request->period == 'thismonth') {
                $thisMonthRecords = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->whereDate('invoices.created_at', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
                    ->whereDate('invoices.created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
                    ->where(['invoices.invoice_status_id' => $invoicestatus->id])
                    ->whereIn('invoices.location_id', ACL::getUserCentres());

                if ($request->get('performance')) {
                    $thisMonthRecords = $thisMonthRecords->where(['invoices.created_by' => Auth::User()->id]);
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
                        $data['lastmonth'][] = $record;
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
            $end_date,
        ];
    }

    public function AppointmentByStatus(Request $request)
    {

        $data = [];
        $total = 0;
        $today = [];
        $colors = [];
        if (Gate::allows('dashboard_appointment_by_status')) {
            $appointment_statuses = AppointmentStatuses::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['parent_id', '=', '0'],
            ])->get();
            if ($request->period == '') {
                $today_records = Appointments::whereDate('scheduled_date', '=', Carbon::now()->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $today_records = $today_records->where(['created_by' => Auth::User()->id]);
                }
                $today_records = $today_records->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($today_records) {
                            foreach ($today_records as $todayRecord) {
                                if ($todayRecord->appointment_status_id == $appointment_status->id) {
                                    $today[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $todayRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            if ($request->period == 'today') {
                $today_records = Appointments::whereDate('scheduled_date', '=', Carbon::now()->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $today_records = $today_records->where(['created_by' => Auth::User()->id]);
                }
                $today_records = $today_records->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $today[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($today_records) {
                            foreach ($today_records as $todayRecord) {
                                if ($todayRecord->appointment_status_id == $appointment_status->id) {
                                    $today[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $todayRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            if ($request->period == 'yesterday') {
                $yesterdayRecords = Appointments::whereDate('scheduled_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                $yesterdayRecords = $yesterdayRecords->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $yesterday[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($yesterdayRecords) {
                            foreach ($yesterdayRecords as $yestersdayRecord) {
                                if ($yestersdayRecord->appointment_status_id == $appointment_status->id) {
                                    $yesterday[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $yestersdayRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            if ($request->period == 'last7days') {
                $last7_days_records = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('scheduled_date', '<=', Carbon::now()->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7_days_records = $last7_days_records->where(['created_by' => Auth::User()->id]);
                }
                $last7_days_records = $last7_days_records->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $last7days[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($last7_days_records) {
                            foreach ($last7_days_records as $last7DayRecord) {
                                if ($last7DayRecord->appointment_status_id == $appointment_status->id) {
                                    $last7days[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $last7DayRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            if ($request->period == 'week') {
                $last7_days_records = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                    ->whereDate('scheduled_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $last7_days_records = $last7_days_records->where(['created_by' => Auth::User()->id]);
                }
                $last7_days_records = $last7_days_records->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $last7days[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($last7_days_records) {
                            foreach ($last7_days_records as $last7DayRecord) {
                                if ($last7DayRecord->appointment_status_id == $appointment_status->id) {
                                    $last7days[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $last7DayRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            if ($request->period == 'thismonth') {
                $monthlyRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('scheduled_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $monthlyRecords = $monthlyRecords->where(['created_by' => Auth::User()->id]);
                }
                $monthlyRecords = $monthlyRecords->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $monthlyRecord[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($monthlyRecords) {
                            foreach ($monthlyRecords as $monthRecord) {
                                if ($monthRecord->appointment_status_id == $appointment_status->id) {
                                    $monthlyRecord[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $monthRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            if ($request->period == 'lastmonth') {
                $monthlyRecords = Appointments::whereDate('scheduled_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                    ->whereDate('scheduled_date', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                    ->where(['appointment_type_id' => $request->type])
                    ->whereIn('location_id', ACL::getUserCentres());
                if ($request->get('performance')) {
                    $monthlyRecords = $monthlyRecords->where(['created_by' => Auth::User()->id]);
                }
                $monthlyRecords = $monthlyRecords->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
                    ->groupBy('base_appointment_status_id')
                    ->get();
                if ($appointment_statuses) {
                    $total = 0;
                    foreach ($appointment_statuses as $appointment_status) {
                        $monthlyRecord[0] = [
                            'Task',
                            'Hours per Day',
                        ];
                        if ($monthlyRecords) {
                            foreach ($monthlyRecords as $monthRecord) {
                                if ($monthRecord->appointment_status_id == $appointment_status->id) {
                                    $monthlyRecord[$appointment_status->id] = [
                                        $appointment_status->name,
                                        $monthRecord->total,

                                    ];

                                    $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            'total' => 0,
        ]);
    }

    public function AppointmentByType(Request $request)
    {
        $data = [];
        $total = 0;
        $today = [];
        $colors = [];
        $appointment_types = AppointmentTypes::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->get();
        if ($request->period == '') {
            $today_records = Appointments::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $today_records = $today_records->where(['created_by' => Auth::User()->id]);
            }
            $today_records = $today_records->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
                ->groupBy('appointment_type_id')
                ->get();
            $today = [];
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $today[0] = [
                        'Task',
                        'Hours per Day',
                    ];
                    if ($today_records) {
                        foreach ($today_records as $todayRecord) {
                            if ($todayRecord->appointment_type_id == $appointment_type->id) {
                                $today[$appointment_type->id] = [
                                    $appointment_type->name,
                                    $todayRecord->total,
                                ];
                                $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
        if ($request->period == 'today') {
            $today_records = Appointments::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $today_records = $today_records->where(['created_by' => Auth::User()->id]);
            }
            $today_records = $today_records->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
                ->groupBy('appointment_type_id')
                ->get();
            $today = [];
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $today[0] = [
                        'Task',
                        'Hours per Day',
                    ];
                    if ($today_records) {
                        foreach ($today_records as $todayRecord) {
                            if ($todayRecord->appointment_type_id == $appointment_type->id) {
                                $today[$appointment_type->id] = [
                                    $appointment_type->name,
                                    $todayRecord->total,

                                ];

                                $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
        if ($request->period == 'yesterday') {
            $yesterdayRecords = Appointments::whereDate('created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());

            if ($request->get('performance')) {
                $yesterdayRecords = $yesterdayRecords->where(['created_by' => Auth::User()->id]);
            }

            $yesterdayRecords = $yesterdayRecords->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
                ->groupBy('appointment_type_id')
                ->get();

            $today = [];
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $yesterday[0] = [
                        'Task',
                        'Hours per Day',
                    ];
                    if ($yesterdayRecords) {
                        foreach ($yesterdayRecords as $yesterdayRecord) {
                            if ($yesterdayRecord->appointment_type_id == $appointment_type->id) {
                                $yesterday[$appointment_type->id] = [
                                    $appointment_type->name,
                                    $yesterdayRecord->total,

                                ];

                                $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
        if ($request->period == 'last7days') {
            $weeklyRecords = Appointments::whereDate('created_at', '=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('created_at', '<=', Carbon::now()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());

            if ($request->get('performance')) {
                $weeklyRecords = $weeklyRecords->where(['created_by' => Auth::User()->id]);
            }
            $weeklyRecords = $weeklyRecords->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
                ->groupBy('appointment_type_id')
                ->get();
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $last7days[0] = [
                        'Task',
                        'Hours per Day',
                    ];
                    if ($weeklyRecords) {
                        foreach ($weeklyRecords as $weeklyRecord) {
                            if ($weeklyRecord->appointment_type_id == $appointment_type->id) {
                                $last7days[$appointment_type->id] = [
                                    $appointment_type->name,
                                    $weeklyRecord->total,

                                ];

                                $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
        if ($request->period == 'thismonth') {
            $monthlyRecords = Appointments::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $monthlyRecords = $monthlyRecords->where(['created_by' => Auth::User()->id]);
            }
            $monthlyRecords = $monthlyRecords->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
                ->groupBy('appointment_type_id')
                ->get();
            $today = [];
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $month[0] = [
                        'Task',
                        'Hours per Day',
                    ];
                    if ($monthlyRecords) {
                        foreach ($monthlyRecords as $monthlyRecord) {
                            if ($monthlyRecord->appointment_type_id == $appointment_type->id) {
                                $month[$appointment_type->id] = [
                                    $appointment_type->name,
                                    $monthlyRecord->total,

                                ];
                                $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
        if ($request->period == 'lastmonth') {
            $monthlyRecords = Appointments::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->whereIn('location_id', ACL::getUserCentres());
            if ($request->get('performance')) {
                $monthlyRecords = $monthlyRecords->where(['created_by' => Auth::User()->id]);
            }
            $monthlyRecords = $monthlyRecords->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
                ->groupBy('appointment_type_id')
                ->get();
            $today = [];
            if ($appointment_types) {
                $total = 0;
                foreach ($appointment_types as $appointment_type) {
                    $month[0] = [
                        'Task',
                        'Hours per Day',
                    ];
                    if ($monthlyRecords) {
                        foreach ($monthlyRecords as $monthlyRecord) {
                            if ($monthlyRecord->appointment_type_id == $appointment_type->id) {
                                $month[$appointment_type->id] = [
                                    $appointment_type->name,
                                    $monthlyRecord->total,

                                ];
                                $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
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
            'total' => 0,
        ]);
    }

    public function getChild(Request $request)
    {
        if ($request->child_id) {
            $service = Services::find($request->child_id);

            return ApiHelper::apiResponse($this->success, 'service data', true, [
                'child' => $service->name,
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

        $period = $request->period == '' ? 'thismonth' : $request->period;
        $fdm_users = RoleHasUsers::where(['role_id' => 4])->pluck('user_id')->toArray();
        $center_id = $request->centre_id == 'All' ? ACL::getUserCentres() : [$request->centre_id];

        $periods = [
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfWeek()->format('Y-m-d'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
        ];

        $stats = AppointmentsDailyStats::select('centre_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('SUM(CASE WHEN appointment_status_id = 2 THEN 1 ELSE 0 END) as arrived')
            ->selectRaw('SUM(CASE WHEN appointment_status_id = 2 AND user_id IN ('.implode(',', $fdm_users).') THEN 1 ELSE 0 END) as walkin')
            ->whereBetween('cron_current_date', [$periods[$period]['start_date'], $periods[$period]['end_date']])
            ->whereIn('centre_id', $center_id)
            ->groupBy('centre_id')
            ->get()->toArray();

        foreach ($stats as $stat) {
            $centre = Locations::where(['id' => $stat['centre_id']])->first();
            array_push($lables, $centre['name']);
            array_push($total_apts, $stat['total']);
            array_push($arrived_apts, (int) $stat['arrived']);
            array_push($walkin_apts, (int) $stat['walkin']);
        }

        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total' => $total_apts,
            'arrived' => $arrived_apts,
            'walkin' => $walkin_apts,
        ]);
    }

    public function CSRWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $lables = [];

        $data = ($request->user_id == 'All') ? 'user_id' : 'cron_current_date';
        $csr_users = RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id')->toArray();
        $csr = User::whereIn('id', $csr_users)->where(['active' => 1])->pluck('id')->toArray();
        $period = $request->period == '' ? 'thismonth' : $request->period;
        $user_id = ($request->user_id == 'All') ? $csr : [$request->user_id];

        $periods = [
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfWeek()->format('Y-m-d'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
        ];
        $stats = AppointmentsDailyStats::select($data)
            ->selectRaw('count(*) as total')
            ->selectRaw('SUM(CASE WHEN appointment_status_id = 2 THEN 1 ELSE 0 END) as arrived')
            ->whereBetween('cron_current_date', [$periods[$period]['start_date'], $periods[$period]['end_date']])
            ->whereIn('user_id', $user_id)
            ->orderBy('user_id', 'ASC')
            ->groupBy($data)
            ->get()->toArray();

        foreach ($stats as $stat) {
            if ($data == 'user_id') {
                $username = User::whereId($stat['user_id'])->where(['active' => 1])->first();
                if ($username) {
                    array_push($lables, $username->name);
                }
            } else {
                array_push($lables, $stat['cron_current_date']);
            }
            array_push($total_apts, $stat['total']);
            array_push($arrived_apts, (int) $stat['arrived']);
        }

        return ApiHelper::apiResponse($this->success, 'centre wise arrival data', true, [
            'bar' => $lables,
            'total' => $total_apts,
            'arrived' => $arrived_apts,

        ]);
    }

    public function CallWiseArrival(Request $request)
    {
        $total_apts = [];
        $arrived_apts = [];
        $lables = [];
        if ($request->period == '') {
            $fdm_users = RoleHasUsers::where(['role_id' => 4])->pluck('user_id');
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereNotIn('user_id', $fdm_users)
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))->whereDate('cron_current_date', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                ->whereNotIn('user_id', $fdm_users)
                ->where(['appointment_status_id' => 2])
                ->groupBy('user_id')->get()->toArray();
            foreach ($yesterday_total_appointments as $loc) {
                $user = User::find($loc['user_id']);
                if ($user) {
                    array_push($lables, $user->name);
                }
                array_push($total_apts, $loc['total']);
            }
            foreach ($yesterday_arrived_appointments as $apt) {
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period == 'yesterday') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->where(['user_id' => $request->user_id])
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->where(['user_id' => $request->user_id, 'appointment_status_id' => 2])
                ->groupBy('user_id')->get()->toArray();
            foreach ($yesterday_total_appointments as $loc) {
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach ($yesterday_arrived_appointments as $apt) {
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period == 'last7days') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id])
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id, 'appointment_status_id' => 2])
                ->groupBy('user_id')->get()->toArray();
            foreach ($yesterday_total_appointments as $loc) {
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach ($yesterday_arrived_appointments as $apt) {
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period == 'week') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id])
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfWeek()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id])
                ->where(['appointment_status_id' => 2])
                ->groupBy('user_id')->get()->toArray();
            foreach ($yesterday_total_appointments as $loc) {
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach ($yesterday_arrived_appointments as $apt) {
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period == 'thismonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id])
                ->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id, 'appointment_status_id' => 2])
                ->groupBy('user_id')->get()->toArray();
            foreach ($yesterday_total_appointments as $loc) {
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach ($yesterday_arrived_appointments as $apt) {
                array_push($arrived_apts, $apt['arrived']);
            }
        }
        if ($request->period == 'lastmonth') {
            $yesterday_total_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as total'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id])->groupBy('user_id')->get()->toArray();
            $yesterday_arrived_appointments = AppointmentsDailyStats::select('user_id', DB::raw('count(*) as arrived'))
                ->whereDate('cron_current_date', '>=', Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d'))
                ->whereDate('cron_current_date', '<=', Carbon::now()->endOfMonth()->subMonth()->format('Y-m-d'))
                ->where(['user_id' => $request->user_id, 'appointment_status_id' => 2])->groupBy('user_id')->get()->toArray();
            foreach ($yesterday_total_appointments as $loc) {
                $user = User::findOrFail($loc['user_id']);
                array_push($lables, $user->name);
                array_push($total_apts, $loc['total']);
            }
            foreach ($yesterday_arrived_appointments as $apt) {
                array_push($arrived_apts, $apt['arrived']);
            }
        }

        return ApiHelper::apiResponse($this->success, 'csr wise arrival data', true, [
            'bar' => $lables,
            'total' => $total_apts,
            'arrived' => $arrived_apts,

        ]);
    }
    public function DoctoreWiseConversion(Request $request)
    {
       
        $total_apts = [];
        $converted_apts = [];
        $lables = [];
        $appointmentss = array();
        $total = 0;
        $appointments_info = array();
        $period = $request->period;
        $periods = GeneralFunctions::GetPeriods();
        $centre_doctors = DoctorHasLocations::when($request->centre_id, function ($query) use ($request) {
            return $query->where(['location_id' => $request->centre_id]);
        })
        ->when($request->doc_id, function ($query) use ($request) {
            return $query->where(['user_id' => $request->doc_id]);
        })
        ->groupBy('user_id')
        ->pluck('user_id');
       
        $role = DB::table('roles')->where(['name' => 'Aesthetic Consultant'])->pluck('id');
        
        $consultants = RoleHasUsers::join('users','users.id','role_has_users.user_id')
        ->select('users.name','users.id')
        ->whereIn('users.id' , $centre_doctors)
        ->where('role_id',$role)
        ->where('users.active',1)
        ->get();
       
        foreach($consultants as $consultant){
            
            array_push($lables , $consultant->name);

            $converted_appointments = Appointments::with('location:id,name')->join('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
                ->where('appointments.base_appointment_status_id', config('constants.appointment_status_arrived'))
                ->where('appointments.appointment_type_id', 1)
                ->where('package_advances.cash_amount', '>', 0)
                ->where('appointments.doctor_id', $consultant->id)
                ->select('appointments.*')
                ->when($period == 'today', function ($query) use ($periods ,$period) {
                    $query->whereDate('package_advances.created_at', $periods[$period]['start_date']);
                })
                ->when($period != 'today', function ($query) use ($periods ,$period) {
                    $query->whereBetween('package_advances.created_at', [
                        $periods[$period]['start_date'],
                        $periods[$period]['end_date']
                    ]);
                })
            ->get();
            
            if (count($converted_appointments)) {
                foreach ($converted_appointments as $appointment) {
                    if (!in_array($appointment->id, $appointmentss)) {
                        $appointments_info[$appointment->id] = array(
                            'patient_id' => $appointment->patient_id,
                            'appointment_id' => $appointment->id,
                            'doctor_id' => $appointment->doctor_id,
                            'doctor' => $appointment->doctor->name,
                            'client' => $appointment->patient->name,
                            'phone' => $appointment->patient->phone,
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
                    $appointmentss[] = $appointment->id;
                    $package_info = PackageAdvances::where(['appointment_id' => $appointment->id])->get()->pluck('id')->toArray();
                    if (count($package_info)) {
                        $actual = 0;
                        $revenue_in = 0;
                        $out = 0;
                        $packagesadvances = PackageAdvances::whereIn('id', $package_info)
                            ->where(['cash_flow' => "in"])
                            ->where('cash_amount', '>', 0)
                            ->get();
                        if (count($packagesadvances) > 0) {
                            $check = 0;
                            $first_advance = PackageAdvances::whereIn('id', $package_info)
                                ->where('cash_amount', '>', 0)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            $date = Carbon::parse($first_advance->updated_at)->format('Y-m-d');
                            if (($date >= $periods[$period]['start_date']) && ($date <= $periods[$period]['end_date'])) {
                                $check = 1;
                            }
                            if ($check == 1) {
                                $appointments_info[$appointment->id]['converted'] = 'Yes';
                                foreach ($packagesadvances as $packagesadvance) {
                                    $child = GeneralFunctions::genericfunctionforstaffwiserevenue($packagesadvance);
                                    if ($child) {
                                        $revenue_in += $child['revenue'] ? $child['revenue'] : 0;
                                        $out += $child['refund_out'] ? $child['refund_out'] : 0;
                                    }
                                }
                                $actual = $revenue_in - $out;
                                $appointments_info[$appointment->id]['conversion_spend'] = $actual;
                                $appointments_info[$appointment->id]['converted'] = 'Yes';
                                $appointments_info[$appointment->id]['conversion_date'] = $first_advance->created_at;
                                $count[$appointment->location->id][] = 1;
                                $locationData[$appointment->location->name]['total_count'] = count($count[$appointment->location->id]);
                                if($appointment['converted'] != '') {
                                    $arrived_count[$appointment->location->id][] = 1;
                                    $locationData[$appointment->location->name]['total_count'] = count($arrived_count[$appointment->location->id]);
                                }
                                $total += $appointments_info[$appointment->id]['conversion_spend'] ? $appointments_info[$appointment->id]['conversion_spend'] : 0;
                                $locationData[$appointment->location->name]['total'] = $total;
                               
                            }
                        }
                    }
                }
    
                /*case 1 end*/
            }

            $total_appointments = Appointments::whereBetween('scheduled_date',[$periods[$period]['start_date'], $periods[$period]['end_date']])
            ->where(['appointment_type_id' => 1, 'base_appointment_status_id' => 2 , 'doctor_id' => $consultant->id])
            ->count();
           
            array_push($converted_apts, collect($appointments_info)->where('conversion_spend', "!=","")->count());
            array_push($total_apts, $total_appointments);
            $maxConversion1 = collect($appointments_info)->filter(function($appointment){
                if($appointment['conversion_spend'] > 0){
                    return $appointment;
                }
            });
            $maxConversion1 = $maxConversion1->groupBy('service_id');
            $returnCategoryData = [];
            foreach($maxConversion1 as $key => $app){
                $sum_conversion_spend = 0;
                $sum_conversion_total = 0;
                foreach($app as $value) {
                    $name = $value['service'];
                    $sum_conversion_spend += $value['conversion_spend'];
                    $sum_conversion_total += 1;
                }
               $category_total_records = Appointments::where(['service_id' => $value['service_id'], 'base_appointment_status_id' => 2, 'appointment_type_id' => 1 , 'doctor_id' =>$consultant->id])
                ->whereBetween('scheduled_date',[$periods[$period]['start_date'], $periods[$period]['end_date']])
                ->count();
                $returnCategoryData[$key] = [
                    'service' => $name,
                    'total_arrival' => $category_total_records,
                    'total_conversion' => $sum_conversion_total
                ];
                
            }
        }
       
        return ApiHelper::apiResponse($this->success, 'doctor wise conversion data', true, [
            'labels' => $lables,
            'total_appointments'=>$total_apts,
            'converted_appointments'=>$converted_apts,
            'categories'=>$returnCategoryData

        ]);
           
    }
    public function GetCentreDoctors(Request $request)
    {
        $role = DB::table('roles')->where(['name' => 'Aesthetic Consultant'])->pluck('id');
        $centre_doctors = DoctorHasLocations::where(['location_id' => $request->centre_id])
        ->groupBy('user_id')
        ->pluck('user_id');
        $consultants = RoleHasUsers::join('users','users.id','role_has_users.user_id')
        ->select('users.name','users.id')
        ->whereIn('users.id' , $centre_doctors)
        ->where('role_id',$role)
        ->where('users.active',1)
        ->get();
        return response()->json(['status' => 1 , 'doctors' => $consultants]);
    }
}
