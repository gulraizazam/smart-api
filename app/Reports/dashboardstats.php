<?php

namespace App\Reports;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Services;
use App\User;
use Auth;
use Carbon\Carbon;
use Config;
use DB;
use Illuminate\Support\Facades\Gate;

class dashboardreport
{
    public static function getRevenueByCenter($start_date, $end_date, $performance, $account_id)
    {

        if (! Gate::allows('dashboard_revenue_by_centre')) {
            return abort(404);
        }

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        $invoices = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
            ->where('invoices.account_id', '=', $account_id)
            ->whereDate('invoices.created_at', '>=', $start_date)
            ->whereDate('invoices.created_at', '<=', $end_date)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('invoice_details.*', 'invoices.*');

        if ($performance == '1') {
            $invoices = $invoices->where('invoices.created_by', '=', Auth::User()->id);
        }

        $invoices = $invoices->get();

        return $invoices;
    }

    private static function calculate_revenue_by_centre($packageAdvances, $locations)
    {
        $report_data = [];

        if ($packageAdvances && $locations) {

            $balance = 0;
            $total_balance = 0;

            foreach ($locations as $key => $location) {

                $location_information = Locations::find($key);

                $report_data[$location_information->id] = [
                    'id' => $location_information->id,
                    'name' => $location_information->name,
                    'city' => $location_information->city->name,
                    'region' => $location_information->region->name,
                    'revenue_data' => [],
                ];

                foreach ($packageAdvances as $packagesadvance) {
                    if (
                        (
                            $packagesadvance->cash_flow == 'in' &&
                            $packagesadvance->is_adjustment == '0' &&
                            $packagesadvance->is_tax == '0' &&
                            $packagesadvance->is_cancel == '0'
                        )
                        ||
                        (
                            $packagesadvance->cash_flow == 'out' &&
                            $packagesadvance->is_refund == '1'
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
                                    $revenue_card_in = 0;
                                    $revenue_bank_in = 0;
                                    $refund_out = 0;
                                }
                                if ($packagesadvance->paymentmode->name == 'Card') {
                                    $revenue_cash_in = 0;
                                    $revenue_card_in = $packagesadvance->cash_amount;
                                    $revenue_bank_in = 0;
                                    $refund_out = 0;
                                }
                                if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                    $revenue_cash_in = 0;
                                    $revenue_card_in = 0;
                                    $revenue_bank_in = $packagesadvance->cash_amount;
                                    $refund_out = '';
                                }
                            } else {
                                $revenue_cash_in = 0;
                                $revenue_card_in = 0;
                                $revenue_bank_in = 0;
                                $refund_out = $packagesadvance->cash_amount;
                            }

                            if ($location_information->id == $packagesadvance->location_id) {
                                $report_data[$location_information->id]['revenue_data'][$packagesadvance->id] = [
                                    'patient' => $packagesadvance->user->name,
                                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvance->user->phone),
                                    'transtype' => $transtype,
                                    'payment_mode_id' => $packagesadvance->payment_mode_id,
                                    'payment_mode' => $packagesadvance->paymentmode->name,
                                    'cash_flow' => $packagesadvance->cash_flow,
                                    'revenue_cash_in' => $revenue_cash_in,
                                    'revenue_card_in' => $revenue_card_in,
                                    'revenue_bank_in' => $revenue_bank_in,
                                    'refund_out' => $refund_out,
                                    'Balance' => $balance,
                                    'created_at' => Carbon::parse($packagesadvance->created_at)->format('F j,Y h:i A'),
                                ];
                            }

                        }
                    }
                }
            }
        }

        return $report_data;
    }

    /*
     * Collection by centre widgets calculation
     */

    public static function CollectionByRevenueWidgets($location_information, $account_id, $where, $request)
    {
        $report_data = [];
        $wherecondtion = [];
        if ($request->performance) {
            $wherecondtion[] = [
                'created_by',
                '=',
                Auth::User()->id,
            ];
        }
        foreach ($location_information as $key => $location_infomation) {
            if ($where == 'today') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'yesterday') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'last7day') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'thisMonth') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }

            $location_single_info = Locations::find($key);

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

            $report_data[$location_single_info->id] = [
                'centre' => $location_single_info->city->name.' - '.$location_single_info->name,
                'value' => $In_hand_balance,
            ];
        }

        return $report_data;
    }

    /*
     * Collection revenue centre wise report without performance
     */
    public static function getcollectionrevenue($start_date, $end_date, $performance, $account_id)
    {
        $where = [];

        if ($performance == 'true') {
            $where[] = [
                'created_by',
                '=',
                Auth::User()->id,
            ];

        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres());

        $packageAdvances = PackageAdvances::whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->whereIn('location_id', ACL::getUserCentres())
            ->where('account_id', '=', $account_id)
            ->where($where)
            ->get();

        $report_data = dashboardreport::calculate_revenue_by_centre($packageAdvances, $locations);

        foreach ($report_data as $data) {
            if (empty($data['revenue_data'])) {
                unset($report_data[$data['id']]);
            }
        }

        return $report_data;

    }

    public static function getAppointmentsByStatus($start_date, $end_date, $performance, $account_id)
    {
        $appointments = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.created_at', '>=', $start_date)
            ->whereDate('appointments.created_at', '<=', $end_date)
            ->where('appointments.account_id', '=', $account_id)
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        if ($performance === 'true') {
            $appointments = $appointments->where('appointments.created_by', '=', Auth::user()->id);
        }

        $appointments = $appointments->select('appointments.*', 'users.name as username', 'users.email', 'users.phone', 'users.referred_by')->get();

        $report_data = [];
        $statuses = [];

        $count = 0;
        if (count($appointments)) {
            foreach ($appointments as $appointment) {
                if (! in_array($appointment->base_appointment_status_id, $statuses)) {

                    $report_data[$appointment->base_appointment_status_id] = [
                        'status_name' => $appointment->appointment_status_base->name,
                        'appointment_data' => [],
                    ];

                    $statuses[] = $appointment->base_appointment_status_id;

                }

                $user_info = User::find($appointment->referred_by);

                $report_data[$appointment->base_appointment_status_id]['appointment_data'][$count++] = [
                    'appointment_id' => $appointment->id,
                    'patient_name' => $appointment->username,
                    'patient_phone' => $appointment->phone,
                    'patient_email' => $appointment->email,
                    'scheduled_at' => ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($appointment->scheduled_time, null)->format('h A') : '-',
                    'doctor_name' => $appointment->doctor->name,
                    'city' => $appointment->city->name,
                    'centre' => $appointment->location->name,
                    'consultancy' => $appointment->service->name,
                    'status' => $appointment->appointment_status->name,
                    'type' => $appointment->appointment_type->name,
                    'created_at' => Carbon::parse($appointment->created_at)->format('M j, Y H:i A'),
                    'created_by' => $appointment->user->name,
                    'converted_by' => $appointment->user_converted_by->name,
                    'rescheduled_by' => $appointment->user_updated_by->name,
                    'referred_by' => $user_info ? $user_info->name : '',
                ];

            }
        }

        return $report_data;
    }

    /*
     * Revenue By service report data calculation
     */
    public static function revenuebyservicesales($start_date, $end_date, $performance, $account_id)
    {

        $where = [];
        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();

        if ($performance == 'true') {
            $where[] = [
                'invoices.created_by',
                '=',
                Auth::User()->id,
            ];
            $where[] = [
                'invoices.invoice_status_id',
                '=',
                $invoice_status->id,
            ];
        } else {
            $where[] = [
                'invoices.invoice_status_id',
                '=',
                $invoice_status->id,
            ];
        }

        $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereDate('invoices.created_at', '>=', $start_date)
            ->whereDate('invoices.created_at', '<=', $end_date)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
            ->groupBy('invoice_details.service_id')
            ->get();

        $reportdata = [];
        $services = [];

        foreach ($records as $record) {
            if (! in_array($record->service_id, $services)) {
                $serviceinfo = Services::find($record->service_id);
                $reportdata[$record->service_id] = [
                    'id' => $record->service_id,
                    'name' => $serviceinfo->name,
                    'amount' => 0.00,
                ];
            }
            $services[] = $record->service_id;

            $reportdata[$record->service_id]['amount'] += $record->total_price;
        }

        return $reportdata;
    }

    /*
     * Appointment by type
     */
    public static function Appointmentbytype($start_date, $end_date, $performance, $account_id)
    {
        $where = [];
        if ($performance == 'true') {
            $where[] = [
                'appointments.created_by',
                '=',
                Auth::User()->id,
            ];
        }
        $appointments = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.created_at', '>=', $start_date)
            ->whereDate('appointments.created_at', '<=', $end_date)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->where($where);

        $appointments = $appointments->select('appointments.*', 'users.name as username', 'users.email', 'users.phone', 'users.referred_by')->get();
        $report_data = [];
        $types = [];

        $count = 0;
        if (count($appointments)) {
            foreach ($appointments as $appointment) {
                if (! in_array($appointment->appointment_type_id, $types)) {
                    $report_data[$appointment->appointment_type_id] = [
                        'type_name' => $appointment->appointment_type->name,
                        'appointment_data' => [],
                    ];
                    $types[] = $appointment->appointment_type_id;
                }
                $user_info = User::find($appointment->referred_by);
                $report_data[$appointment->appointment_type_id]['appointment_data'][$count++] = [
                    'appointment_id' => $appointment->id,
                    'patient_name' => $appointment->username,
                    'patient_phone' => $appointment->phone,
                    'patient_email' => $appointment->email,
                    'scheduled_at' => ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
                    'doctor_name' => $appointment->doctor->name,
                    'city' => $appointment->city->name,
                    'centre' => $appointment->location->name,
                    'consultancy' => $appointment->service->name,
                    'status' => $appointment->appointment_status->name,
                    'type' => $appointment->appointment_type->name,
                    'created_at' => Carbon::parse($appointment->created_at)->format('M j, Y H:i A'),
                    'created_by' => $appointment->user->name,
                    'converted_by' => $appointment->user_converted_by->name,
                    'rescheduled_by' => $appointment->user_updated_by->name,
                    'referred_by' => $user_info ? $user_info->name : '',
                ];
            }
        }

        return $report_data;
    }
}
