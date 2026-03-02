<?php

namespace App\Reports;

use Auth;
use Config;
use App\User;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\Packages;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\MachineType;
use App\Models\Appointments;
use App\Models\PabaoRecords;
use App\Models\InvoiceDetails;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\InvoiceStatuses;
use App\Models\PackageAdvances;
use App\Models\ResourceHasRota;
use App\Models\AppointmentTypes;
use App\Helpers\GeneralFunctions;
use App\Models\DoctorHasLocations;
use Illuminate\Support\Facades\DB;
use App\Models\PabaoRecordPayments;
use App\Helpers\Widgets\AppointmentEditWidget;

class Finanaces
{
    /**
     * Centre performance stats by revenue
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function centerperformancestatsbyrevenue($data, $filters = [])
    {
        $where = [];
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        if (isset($data['service_id']) && $data['service_id']) {
            $where[] = [
                'service_id',
                '=',
                $data['service_id'],
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'created_by',
                '=',
                $data['user_id'],
            ];
        }
        if (count($where)) {
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        } else {
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (!in_array($recod->location_id, $created_byArray)) {
                    $created_byArray[] = $recod->location_id;
                    $locationinfo = Locations::where('id', '=', $recod->location_id)->first();
                    $data[$recod->location_id] = [
                        'id' => $recod->location_id,
                        'name' => $locationinfo->name,
                        'region' => (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$recod->region_id]->name : '',
                        'city' => (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$recod->city_id]->name : '',
                    ];
                    $data[$recod->location_id]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->location_id]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }

    /**
     * Centre performance stats by service type
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function centerperformancestatsbyservices($data, $filters = [])
    {
        $where = [];
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        if (isset($data['service_id']) && $data['service_id']) {
            $where[] = [
                'service_id',
                '=',
                $data['service_id'],
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'created_by',
                '=',
                $data['user_id'],
            ];
        }
        if (count($where)) {
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where($where)
                ->get();
        } else {
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (!in_array($recod->appointment_type_id, $created_byArray)) {
                    $created_byArray[] = $recod->appointment_type_id;
                    $appointmenttype = AppointmentTypes::find($recod->appointment_type_id);
                    $data[$recod->appointment_type_id] = [
                        'name' => $appointmenttype->name,
                    ];
                    $data[$recod->appointment_type_id]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->appointment_type_id]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }

    /**
     * Customer Payment Ledger Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function Customerpaymentledgerallentries($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];

        $packagesadvances = PackageAdvances::whereDate('created_at', '=', $start_date)
            ->whereDate('created_at', '=', $end_date);

        if (count($where)) {
            $packagesadvances = $packagesadvances->where($where);
        }

        $packagesadvances = $packagesadvances->orderBy('created_at', 'asc')
            ->get();

        $records = [];
        if ($packagesadvances) {
            $balance = 0;
            foreach ($packagesadvances as $packagesadvances) {

                switch ($packagesadvances->cash_flow) {
                    case 'in':
                        $balance = $balance + $packagesadvances->cash_amount;
                        break;
                    case 'out':
                        $balance = $balance - $packagesadvances->cash_amount;
                        break;
                    default:
                        break;
                }
                if ($packagesadvances->cash_amount != 0) {

                    if ($packagesadvances->package_id) {
                        $transtype = Config::get('constants.trans_type.advance_in');
                    }
                    if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'in') {
                        $transtype = Config::get('constants.trans_type.advance_in');
                    }
                    if ($packagesadvances->is_adjustment == '1') {
                        $transtype = Config::get('constants.trans_type.adjustment');
                    }
                    if ($packagesadvances->is_cancel == '1') {
                        $transtype = Config::get('constants.trans_type.invoice_cancel');
                    }
                    if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'out') {
                        $transtype = Config::get('constants.trans_type.invoice_create');
                    }
                    if ($packagesadvances->is_refund == '1') {
                        $transtype = Config::get('constants.trans_type.refund_in');
                    }
                    if ($packagesadvances->is_tax == '1') {
                        $transtype = Config::get('constants.trans_type.tax_out');
                    }
                    if ($packagesadvances->cash_flow == 'in') {
                        $cash_in = number_format($packagesadvances->cash_amount);
                        $cash_out = '-';
                    } else {
                        $cash_out = number_format($packagesadvances->cash_amount);
                        $cash_in = '-';
                    }
                    $records[] = [
                        'patient_id' => $packagesadvances->patient_id,
                        'patient' => $packagesadvances->user->name,
                        'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone),
                        'centre' => $packagesadvances->location->name,
                        'transtype' => $transtype,
                        'cash_in' => $cash_in,
                        'cash_out' => $cash_out,
                        'balance' => number_format($balance),
                        'cash_amount' => '1',
                        'created_at' => Carbon::parse($packagesadvances->created_at)->format('F j,Y h:i A'),
                    ];
                }
            }

            return $records;
        }
    }

    /**
     * Customer Treatment Package ledger
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function customertreatmentpackageledger($data, $account_id)
    {
        $where = [];
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['location_id'])) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        if (count($where)) {
            $packageinfo = Packages::where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::whereIn('location_id', ACL::getUserCentres())->get();
        }
        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'total_price' => $packagerow->total_price,
                'children' => [],
            ];
            $packagesadvances = PackageAdvances::whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->where('package_id', '=', $packagerow->id)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($packagesadvances) {
                $balance = 0;
                $count = 0;
                foreach ($packagesadvances as $packagesadvances) {

                    switch ($packagesadvances->cash_flow) {
                        case 'in':
                            $balance = $balance + $packagesadvances->cash_amount;
                            break;
                        case 'out':
                            $balance = $balance - $packagesadvances->cash_amount;
                            break;
                        default:
                            break;
                    }
                    if ($packagesadvances->cash_amount != 0) {
                        $count++;
                        if ($packagesadvances->package_id) {
                            $transtype = Config::get('constants.trans_type.advance_in');
                        }

                        if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'in') {
                            $transtype = Config::get('constants.trans_type.advance_in');
                        }

                        if ($packagesadvances->is_adjustment == '1') {
                            $transtype = Config::get('constants.trans_type.adjustment');
                        }

                        if ($packagesadvances->is_cancel == '1') {
                            $transtype = Config::get('constants.trans_type.invoice_cancel');
                        }
                        if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'out') {
                            $transtype = Config::get('constants.trans_type.invoice_create');
                        }

                        if ($packagesadvances->is_refund == '1') {
                            $transtype = Config::get('constants.trans_type.refund_in');
                        }
                        if ($packagesadvances->is_tax == '1') {
                            $transtype = Config::get('constants.trans_type.tax_out');
                        }
                        if ($packagesadvances->cash_flow == 'in') {
                            $cash_in = number_format($packagesadvances->cash_amount);
                            $cash_out = '-';
                        } else {
                            $cash_out = number_format($packagesadvances->cash_amount);
                            $cash_in = '-';
                        }
                        $records = [
                            'patient_id' => $packagesadvances->patient_id,
                            'patient' => $packagesadvances->user->name,
                            'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone),
                            'transtype' => $transtype,
                            'cash_in' => $cash_in,
                            'cash_out' => $cash_out,
                            'balance' => number_format($balance),
                            'cash_amount' => '1',
                            'created_at' => Carbon::parse($packagesadvances->created_at)->format('F j,Y h:i A'),
                        ];
                        $packagetrans[$packagerow->id]['children'][$packagesadvances->id] = $records;
                    }
                }
            }
            if ($count == 0) {
                unset($packagetrans[$packagerow->id]);
            }
        }

        return $packagetrans;
    }

    /**
     * List of advances as of today for plans
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function lsitofadvanacesoftodayplan($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        if (count($where)) {
            $packageinfo = Packages::where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::whereIn('location_id', ACL::getUserCentres())->get();
        }
        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'total_price' => $packagerow->total_price,
                'is_refund' => $packagerow->is_refund ? 'Yes' : 'NO',
                'advancebalance' => '',
                'outstandingbalance' => '',
                'usedbalance' => '',
                'unusedbalance' => '',
            ];
            $advancebalance = PackageAdvances::whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->where([
                    ['package_id', '=', $packagerow->id],
                    ['cash_flow', '=', 'in'],
                    ['is_refund', '=', 0],
                    ['is_adjustment', '=', 0],
                    ['is_cancel', '=', 0],
                ])->whereNull('appointment_id')->sum('cash_amount');

            if ($advancebalance !== 0) {

                $packagetrans[$packagerow->id]['advancebalance'] = $advancebalance;

                $outstandingbalance = $packagerow->total_price - $advancebalance;

                $packagetrans[$packagerow->id]['outstandingbalance'] = $outstandingbalance;

                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', $start_date)
                    ->whereDate('created_at', '<=', $end_date)
                    ->where('package_id', '=', $packagerow->id)
                    ->get();

                $balance = 0;
                $refund_balance = 0;

                foreach ($packagesadvances as $packagesadvances) {
                    if ($packagesadvances->cash_flow == 'out' & ($packagesadvances->is_refund == 1 || $packagesadvances->is_adjustment == 1)) {
                        $refund_balance += $packagesadvances->cash_amount;
                    }
                    if ($packagesadvances->is_refund == 0 && $packagesadvances->is_adjustment == 0) {
                        switch ($packagesadvances->cash_flow) {
                            case 'in':
                                $balance = $balance + $packagesadvances->cash_amount;
                                break;
                            case 'out':
                                $balance = $balance - $packagesadvances->cash_amount;
                                break;
                            default:
                                break;
                        }
                    }
                }
                $usedbalance = $advancebalance - $balance;

                $packagetrans[$packagerow->id]['usedbalance'] = $usedbalance;

                $packagetrans[$packagerow->id]['unusedbalance'] = $balance - $refund_balance;
            } else {
                unset($packagetrans[$packagerow->id]);
            }
        }

        return $packagetrans;
    }

    /**
     * List of advances as of today for non plans
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function lsitofadvanacesoftodaynonplan($data, $filters, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        if (count($where)) {
            $appointmentinfo = Appointments::where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $appointmentinfo = Appointments::whereIn('location_id', ACL::getUserCentres())->get();
        }
        $appointmenttrans = [];
        foreach ($appointmentinfo as $appointment) {
            $advancebalance = PackageAdvances::whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->where([
                    ['appointment_id', '=', $appointment->id],
                    ['cash_flow', '=', 'in'],
                    ['is_refund', '=', 0],
                    ['is_adjustment', '=', 0],
                    ['is_cancel', '=', 0],
                ])->whereNull('package_id')->sum('cash_amount');
            if ($advancebalance) {
                $appointmenttrans[$appointment->id] = [
                    'id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'patient_name' => $appointment->name,
                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone),
                    'email' => $appointment->patient->email,
                    'schedule' => ($appointment->scheduled_date) ? \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
                    'doctor' => (array_key_exists($appointment->doctor_id, $filters['doctors'])) ? $filters['doctors'][$appointment->doctor_id]->name : '',
                    'city' => (array_key_exists($appointment->city_id, $filters['cities'])) ? $filters['cities'][$appointment->city_id]->name : '',
                    'location' => (array_key_exists($appointment->location_id, $filters['locations'])) ? $filters['locations'][$appointment->location_id]->name : '',
                    'total_price' => '',
                    'advancebalance' => '',
                    'outstandingbalance' => '',
                    'usedbalance' => '',
                    'unusedbalance' => '',
                ];

                $appointmenttrans[$appointment->id]['total_price'] = $advancebalance;

                $appointmenttrans[$appointment->id]['advancebalance'] = $advancebalance;

                $outstandingbalance = $appointmenttrans[$appointment->id]['total_price'] - $advancebalance;

                $appointmenttrans[$appointment->id]['outstandingbalance'] = $outstandingbalance;

                $packagesadvances = PackageAdvances::whereDate('package_advances.created_at', '>=', $start_date)
                    ->whereDate('package_advances.created_at', '<=', $end_date)
                    ->where('appointment_id', '=', $appointment->id)
                    ->get();

                $balance = 0;
                foreach ($packagesadvances as $packagesadvances) {
                    switch ($packagesadvances->cash_flow) {
                        case 'in':
                            $balance = $balance + $packagesadvances->cash_amount;
                            break;
                        case 'out':
                            $balance = $balance - $packagesadvances->cash_amount;
                            break;
                        default:
                            break;
                    }
                }
                $appointmenttrans[$appointment->id]['unusedbalance'] = $balance;

                $usedbalance = $advancebalance - $balance;

                $appointmenttrans[$appointment->id]['usedbalance'] = $usedbalance;
            }
        }

        return $appointmenttrans;
    }

    /**
     * Summarized data of discounts given to customer
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function SummarizeddataofDiscountsgiventothecustomer($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'packages.patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'packages.account_id',
            '=',
            $account_id,
        ];
        if (count($where)) {
            $packageinfo = Packages::whereDate('packages.created_at', '>=', $start_date)
                ->whereDate('packages.created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        } else {
            $packageinfo = Packages::whereDate('packages.created_at', '>=', $start_date)
                ->whereDate('packages.created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        }

        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'is_refund' => $packagerow->is_refund ? 'Yes' : 'NO',
                'orignal_price' => '',
                'discount_price' => '',
                'tax_amt' => '',
            ];

            $originalprice = PackageBundles::where('package_id', '=', $packagerow->id)->sum('service_price');
            $discountedprice = PackageBundles::where('package_id', '=', $packagerow->id)->sum('tax_exclusive_net_amount');
            $tax_amt_price = PackageBundles::where('package_id', '=', $packagerow->id)->sum('tax_including_price');

            $packagetrans[$packagerow->id]['orignal_price'] = $originalprice;

            $packagetrans[$packagerow->id]['discount_price'] = $discountedprice;

            $packagetrans[$packagerow->id]['tax_amt'] = $tax_amt_price;
        }

        return $packagetrans;
    }

    /**
     * List of clients who claimed refunds for plans
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function ListofClientswhoclaimedrefunds($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        $where[] = [
            'is_refund',
            '=',
            '1',
        ];
        if (count($where)) {
            $packageinfo = Packages::where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::whereIn('location_id', ACL::getUserCentres())->get();
        }
        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'is_refund' => $packagerow->is_refund ? 'Yes' : 'NO',
                'total_price' => '',
                'refund_amount' => '',
            ];

            $total_price = PackageBundles::where('package_id', '=', $packagerow->id)->sum('tax_including_price');

            $refund_amount = PackageAdvances::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where([
                    ['package_id', '=', $packagerow->id],
                    ['is_refund', '=', '1'],
                ])
                ->sum('cash_amount');

            $packagetrans[$packagerow->id]['total_price'] = $total_price;

            $packagetrans[$packagerow->id]['refund_amount'] = $refund_amount;
        }

        return $packagetrans;
    }

    /**
     * List of clients who claimed refunds for non plans
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function ListofClientswhoclaimedrefundsnonplans($data, $filters, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'appointments.patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        $where[] = [
            'appointments.account_id',
            '=',
            $account_id,
        ];

        $package_advances = PackageAdvances::with(['appointment' => function ($query) use ($where) {
            $query->where($where);
            $query->whereIn('location_id', ACL::getUserCentres());
        }])->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->where('is_refund', '=', 1)
            ->whereNull('package_id')->get();

        $appointmentrefund = [];
        $appointmentids = [];
        $count = 0;
        if ($package_advances) {
            foreach ($package_advances as $packageadvance) {
                if (!in_array($packageadvance->appointment->id, $appointmentids)) {
                    $appointmentrefund[$packageadvance->appointment->id] = [
                        'id' => $packageadvance->appointment->id,
                        'patient_id' => $packageadvance->appointment->patient_id,
                        'patient_name' => $packageadvance->appointment->name,
                        'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packageadvance->appointment->patient->phone),
                        'email' => $packageadvance->appointment->patient->email,
                        'schedule' => ($packageadvance->appointment->scheduled_date) ? \Carbon\Carbon::parse($packageadvance->appointment->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($packageadvance->appointment->scheduled_time, null)->format('h:i A') : '-',
                        'service' => (array_key_exists($packageadvance->appointment->service_id, $filters['services'])) ? $filters['services'][$packageadvance->appointment->service_id]->name : '',
                        'doctor' => (array_key_exists($packageadvance->appointment->doctor_id, $filters['doctors'])) ? $filters['doctors'][$packageadvance->appointment->doctor_id]->name : '',
                        'city' => (array_key_exists($packageadvance->appointment->city_id, $filters['cities'])) ? $filters['cities'][$packageadvance->appointment->city_id]->name : '',
                        'location' => (array_key_exists($packageadvance->appointment->location_id, $filters['locations'])) ? $filters['locations'][$packageadvance->appointment->location_id]->name : '',
                        'total_price' => (array_key_exists($packageadvance->appointment->service_id, $filters['services'])) ? $filters['services'][$packageadvance->appointment->service_id]->price : '',
                        'refund_amount' => $packageadvance->cash_amount,
                    ];
                    $appointmentids = [$packageadvance->appointment->id];
                } else {
                    $appointmentrefund[$packageadvance->appointment->id]['refund_amount'] = $appointmentrefund[$packageadvance->appointment->id]['refund_amount'] + $packageadvance->cash_amount;
                }
            }
        }

        return $appointmentrefund;
    }

    /**
     * List of clients who claimed refunds for plans days wise
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function ListofClientswhoclaimedrefundsdaywise($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        $where[] = [
            'is_refund',
            '=',
            '1',
        ];

        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }

        $packageinfo = Packages::where($where)->whereIn('location_id', ACL::getUserCentres())->get();

        $packagetrans = [];

        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'total_price' => '',
                'refunds' => [],
            ];
            $total_price = PackageBundles::where('package_id', '=', $packagerow->id)->sum('net_amount');

            $packagetrans[$packagerow->id]['total_price'] = $total_price;

            $refunds_info = PackageAdvances::whereYear('created_at', '=', $data['year'])
                ->whereMonth('created_at', '=', $data['month'])
                ->where([
                    ['package_id', '=', $packagerow->id],
                    ['is_refund', '=', '1'],
                ])->get();

            foreach ($refunds_info as $refunds) {
                $packagetrans[$packagerow->id]['refunds'][$refunds->id] = [
                    'patient_id' => $packagerow->patient_id,
                    'id' => $refunds->id,
                    'patient' => $packagerow->user->name,
                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                    'location' => $packagerow->location->name,
                    'cash_flow' => $refunds->cash_flow,
                    'refund_note' => $refunds->refund_note,
                    'cash_amount' => $refunds->cash_amount,
                    'created_at' => $refunds->created_at,
                ];
            }
        }

        return $packagetrans;
    }

    /**
     * List of clients who claimed refunds days base for non plans
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function ListofClientswhoclaimedrefundsdaysbasenonplans($data, $filters, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'appointments.patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        $where[] = [
            'appointments.account_id',
            '=',
            $account_id,
        ];

        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'appointments.location_id',
                '=',
                $data['location_id'],
            ];
        }

        $package_advances = PackageAdvances::with(['appointment' => function ($query) use ($where) {
            $query->where($where);
        }])
            ->whereYear('created_at', '=', $data['year'])
            ->whereMonth('created_at', '=', $data['month'])
            ->where('is_refund', '=', 1)
            ->whereNull('package_id')
            ->get();

        $appointmentrefund = [];
        $appointmentids = [];

        foreach ($package_advances as $refunds) {

            if (!in_array($refunds->appointment->id, $appointmentids)) {

                $appointmentrefund[$refunds->appointment->id] = [
                    'id' => $refunds->appointment->id,
                    'name' => $refunds->appointment->name,
                    'total_price' => (array_key_exists($refunds->appointment->service_id, $filters['services'])) ? $filters['services'][$refunds->appointment->service_id]->price : '',
                    'refunds' => [],
                ];

                $appointmentrefund[$refunds->appointment->id]['refunds'][$refunds->id] = [
                    'id' => $refunds->id,
                    'patient_id' => $refunds->appointment->patient_id,
                    'patient_name' => $refunds->appointment->name,
                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($refunds->appointment->patient->phone),
                    'email' => $refunds->appointment->patient->email,
                    'schedule' => ($refunds->appointment->scheduled_date) ? \Carbon\Carbon::parse($refunds->appointment->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($refunds->appointment->scheduled_time, null)->format('h:i A') : '-',
                    'service' => (array_key_exists($refunds->appointment->service_id, $filters['services'])) ? $filters['services'][$refunds->appointment->service_id]->name : '',
                    'doctor' => (array_key_exists($refunds->appointment->doctor_id, $filters['doctors'])) ? $filters['doctors'][$refunds->appointment->doctor_id]->name : '',
                    'city' => (array_key_exists($refunds->appointment->city_id, $filters['cities'])) ? $filters['cities'][$refunds->appointment->city_id]->name : '',
                    'location' => (array_key_exists($refunds->appointment->location_id, $filters['locations'])) ? $filters['locations'][$refunds->appointment->location_id]->name : '',
                    'cash_flow' => $refunds->cash_flow,
                    'refund_note' => $refunds->refund_note,
                    'cash_amount' => $refunds->cash_amount,
                    'created_at' => $refunds->created_at,
                ];

                $appointmentids = [$refunds->appointment->id];
            } else {

                $appointmentrefund[$refunds->appointment->id]['refunds'][$refunds->id] = [
                    'id' => $refunds->id,
                    'patient_id' => $refunds->appointment->patient_id,
                    'patient_name' => $refunds->appointment->name,
                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($refunds->appointment->patient->phone),
                    'email' => $refunds->appointment->patient->email,
                    'schedule' => ($refunds->appointment->scheduled_date) ? \Carbon\Carbon::parse($refunds->appointment->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($refunds->appointment->scheduled_time, null)->format('h:i A') : '-',
                    'service' => (array_key_exists($refunds->appointment->service_id, $filters['services'])) ? $filters['services'][$refunds->appointment->service_id]->name : '',
                    'doctor' => (array_key_exists($refunds->appointment->doctor_id, $filters['doctors'])) ? $filters['doctors'][$refunds->appointment->doctor_id]->name : '',
                    'city' => (array_key_exists($refunds->appointment->city_id, $filters['cities'])) ? $filters['cities'][$refunds->appointment->city_id]->name : '',
                    'location' => (array_key_exists($refunds->appointment->location_id, $filters['locations'])) ? $filters['locations'][$refunds->appointment->location_id]->name : '',
                    'cash_flow' => $refunds->cash_flow,
                    'refund_note' => $refunds->refund_note,
                    'cash_amount' => $refunds->cash_amount,
                    'created_at' => $refunds->created_at,
                ];
            }
        }

        return $appointmentrefund;
    }

    /**
     * General Reveneue report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function generalrevenuereportdetail($data, $account_id)
{
    $where = [];

    if (isset($data['date_range']) && $data['date_range']) {
        $date_range = explode(' - ', $data['date_range']);
        $start_date = date('Y-m-d', strtotime($date_range[0]));
        $end_date = date('Y-m-d', strtotime($date_range[1]));
    } else {
        $start_date = null;
        $end_date = null;
    }
    $gender = $data['gender_id'];
    $where[] = [
        'account_id',
        '=',
        $account_id,
    ];
    $location_information = ACL::getUserCentres();
    $report_data = [];
    foreach ($data['location_id_com'] as $location) {
        $query = PackageAdvances::whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->where('location_id', '=', $location)
            ->where($where)
            ->orderBy('created_at', 'asc');

        // Add gender filter condition
        if ($gender !== 'all') {
            $query->whereHas('user', function ($q) use ($gender) {
                $q->where('gender', $gender);
            });
        }

        $packagesadvances = $query->get();

        $location_information = Locations::find($location);

        if ($packagesadvances) {
            $balance = 0;
            $total_balance = 0;
            $report_data[$location_information->id] = [
                'id' => $location_information->id,
                'name' => $location_information->name,
                'city' => $location_information->city->name,
                'region' => $location_information->region->name,
                'revenue_data' => [],
            ];

            foreach ($packagesadvances as $packagesadvance) {
                if (
                    ($packagesadvance->cash_flow == 'in' &&
                        $packagesadvance->is_adjustment == '0' &&
                        $packagesadvance->is_tax == '0' &&
                        $packagesadvance->is_cancel == '0'
                    )
                    ||
                    ($packagesadvance->cash_flow == 'out' &&
                        $packagesadvance->is_refund == '1' &&
                        $packagesadvance->is_tax == '0'
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
                            if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer' || $packagesadvance->paymentmode->name == 'Bank') {
                                $revenue_cash_in = 0;
                                $revenue_card_in = 0;
                                $revenue_bank_in = $packagesadvance->cash_amount;
                                $refund_out = 0;
                            }
                        } else {
                            $revenue_cash_in = 0;
                            $revenue_card_in = 0;
                            $revenue_bank_in = 0;
                            $refund_out = $packagesadvance->cash_amount;
                        }
                        if ($packagesadvance->cash_flow == 'out') {

                            if ($packagesadvance->paymentmode->name == 'Cash') {
                                $refund_cash_in = $packagesadvance->cash_amount;
                                $refund_card_in = 0;
                                $refund_bank_in = 0;
                                $refund_out = 0;
                            }
                            if ($packagesadvance->paymentmode->name == 'Card') {
                                $refund_cash_in = 0;
                                $refund_card_in = $packagesadvance->cash_amount;
                                $refund_bank_in = 0;
                                $refund_out = 0;
                            }
                            if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer' || $packagesadvance->paymentmode->name == 'Bank') {
                                $refund_cash_in = 0;
                                $refund_card_in = 0;
                                $refund_bank_in = $packagesadvance->cash_amount;
                                $refund_out = 0;
                            }
                        } else {
                            $refund_cash_in = 0;
                            $refund_card_in = 0;
                            $refund_bank_in = 0;
                            $refund_out = $packagesadvance->cash_amount;
                        }
                        $gender = $packagesadvance->user->gender == 1 ? 'Male' : 'Female';
                        $report_data[$location_information->id]['revenue_data'][$packagesadvance->id] = [
                            'patient_id' => $packagesadvance->patient_id,
                            'patient' => $packagesadvance->user->name,
                            'gender' => $gender,
                            'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvance->user->phone),
                            'transtype' => $transtype,
                            'payment_mode_id' => $packagesadvance->payment_mode_id,
                            'payment_mode' => $packagesadvance->paymentmode->name ?? 'Cash',
                            'cash_flow' => $packagesadvance->cash_flow,
                            'revenue_cash_in' => $revenue_cash_in,
                            'revenue_card_in' => $revenue_card_in,
                            'revenue_bank_in' => $revenue_bank_in,
                            'refund_cash_in' => $refund_cash_in,
                            'refund_card_in' =>  $refund_card_in,
                            'refund_bank_in' => $refund_bank_in,
                            'refund_out' => $refund_cash_in + $refund_card_in + $refund_bank_in,
                            'Balance' => $balance,
                            'created_at' => Carbon::parse($packagesadvance->created_at)->format('F j,Y h:i A'),
                        ];
                    }
                }
            }
        }
    }

    return $report_data;
}

    /**
     * General Reveneue report summary
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function generalrevenuereportsummary($data, $account_id)
{
    if (isset($data['date_range']) && $data['date_range']) {
        $date_range = explode(' - ', $data['date_range']);
        $start_date = date('Y-m-d', strtotime($date_range[0]));
        $end_date = date('Y-m-d', strtotime($date_range[1]));
    } else {
        $start_date = null;
        $end_date = null;
    }
    
    // Filter by selected locations first, then by region if provided
    $userCentres = ACL::getUserCentres();
    
    if (!empty($data['location_id']) && is_array($data['location_id'])) {
        // Filter user centres to only selected locations
        $selectedLocations = array_intersect($data['location_id'], is_array($userCentres) ? $userCentres : [$userCentres]);
        if (empty($selectedLocations)) {
            $selectedLocations = $data['location_id'];
        }
        if (isset($data['region_id']) && $data['region_id']) {
            $location_information = Locations::generalrevenuegetActiveSorted($selectedLocations, $data['region_id']);
        } else {
            $location_information = Locations::getActiveSorted($selectedLocations);
        }
    } elseif (isset($data['region_id']) && $data['region_id']) {
        $location_information = Locations::generalrevenuegetActiveSorted($userCentres, $data['region_id']);
    } else {
        $location_information = Locations::getActiveSorted($userCentres);
    }

    $report_data = [];

    foreach ($location_information as $key => $location_infomation) {

        $packagesadvances = PackageAdvances::with('user:id,gender') // Add relationship to get user gender
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->where([
                ['account_id', '=', $account_id],
                ['location_id', '=', $key],
            ])->orderBy('created_at', 'asc')->get();

        $location_single_info = Locations::find($key);

        if ($packagesadvances) {
            $balance = 0;
            $total_balance = 0;
            $total_revenue_cash_in = 0;
            $total_revenue_card_in = 0;
            $total_revenue_bank_in = 0;
            $total_refund_out = 0;
            
            // Gender-wise totals
            $male_total = 0;
            $female_total = 0;
            $unknown_gender_total = 0;

            foreach ($packagesadvances as $packagesadvance) {
                if (
                    ($packagesadvance->cash_flow == 'in' &&
                        $packagesadvance->is_adjustment == '0' &&
                        $packagesadvance->is_tax == '0' &&
                        $packagesadvance->is_cancel == '0'
                    )
                    ||
                    ($packagesadvance->cash_flow == 'out' &&
                        $packagesadvance->is_refund == '1' &&
                        $packagesadvance->is_tax == '0'
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
                            $total_revenue_bank_in += $revenue_bank_in;
                        }
                        if ($refund_out) {
                            $total_refund_out += $refund_out;
                        }
                        
                        // Add gender-wise calculation
                        if ($packagesadvance->cash_flow == 'in') {
                            $amount = $packagesadvance->cash_amount;
                            
                            if ($packagesadvance->user && isset($packagesadvance->user->gender)) {
                                if ($packagesadvance->user->gender == 1) {
                                    $male_total += $amount;
                                } elseif ($packagesadvance->user->gender == 2) {
                                    $female_total += $amount;
                                } else {
                                    $unknown_gender_total += $amount;
                                }
                            } else {
                                $unknown_gender_total += $amount;
                            }
                        }
                    }
                }
            }
        }

        $report_data[$location_single_info->id] = [
            'id' => $location_single_info->id,
            'name' => $location_single_info->name,
            'city' => $location_single_info->city->name,
            'region' => $location_single_info->region->name,
            'revenue_cash_in' => $total_revenue_cash_in,
            'revenue_card_in' => $total_revenue_card_in,
            'revenue_bank_in' => $total_revenue_bank_in,
            'refund_out' => $total_refund_out,
            'in_hand' => ($total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in) - $total_refund_out,
            // Gender-wise breakdown
            'male_revenue' => $male_total,
            'female_revenue' => $female_total,
            'unknown_gender_revenue' => $unknown_gender_total,
        ];
    }

    return $report_data;
}

    /**
     * General Reveneue report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function pabaurecordrevenuereport($data, $account_id)
    {

        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $location_info = Locations::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
                ['id', '=', $data['location_id']],
            ])->get()->pluck('name', 'id');
        } else {
            $location_info = Locations::getActiveSorted(ACL::getUserCentres());
        }

        $report_data = [];

        foreach ($location_info as $key => $location) {

            $loc_inform = Locations::find($key);

            $report_data[$loc_inform->id] = [
                'id' => $key,
                'name' => $location,
                'region' => $loc_inform->region->name,
                'city' => $loc_inform->city->name,
                'pabau_rocord' => [],
            ];

            $pabau_record = PabaoRecords::where('location_id', '=', $key)->get();

            if (count($pabau_record) > 0) {
                $count = 0;
                foreach ($pabau_record as $pabau) {

                    $report_data[$loc_inform->id]['pabau_rocord'][$pabau->id] = [
                        'id' => $pabau->id,
                        'name' => $pabau->client,
                        'phone' => $pabau->phone,
                        'invoice_no' => $pabau->invoice_no,
                        'total_amount' => $pabau->total_amount,
                        'paid_amount' => '',
                        'outstanding_amount' => '',
                        'issue_date' => $pabau->issue_date,
                        'pabau_record_payment' => [],
                    ];

                    $pabau_record_payment = PabaoRecordPayments::whereDate('date_paid', '>=', $start_date)
                        ->whereDate('date_paid', '<=', $end_date)
                        ->where('pabao_record_id', '=', $pabau->id)
                        ->get();

                    if (count($pabau_record_payment) > 0) {
                        $count++;
                        $sum_amount = 0;
                        foreach ($pabau_record_payment as $pabau_payment) {
                            $report_data[$loc_inform->id]['pabau_rocord'][$pabau->id]['pabau_record_payment'][$pabau_payment->id] = [
                                'id' => $pabau_payment->id,
                                'amount' => $pabau_payment->amount,
                                'Date' => $pabau_payment->date_paid,
                            ];
                            $sum_amount += $pabau_payment->amount;
                        }
                        $report_data[$loc_inform->id]['pabau_rocord'][$pabau->id]['paid_amount'] = $pabau->paid_amount + $sum_amount;
                        $report_data[$loc_inform->id]['pabau_rocord'][$pabau->id]['outstanding_amount'] = $pabau->outstanding_amount - $sum_amount;
                    } else {
                        unset($report_data[$loc_inform->id]['pabau_rocord'][$pabau->id]);
                    }
                }
                if ($count == 0) {
                    unset($report_data[$loc_inform->id]);
                }
            } else {
                unset($report_data[$loc_inform->id]);
            }
        }

        return $report_data;
    }

    /**
     * Machine Wise Revenue Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function machinewiseinvoicerevenuereport($data, $account_id)
    {
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $where = [];

        if (isset($data['region_id']) && $data['region_id']) {
            /*
             * If region selected
             * case1: If location is selected
             * case2: If location is not selected
             */
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $Locations = Locations::generalrevenuegetActiveSorted($data['location_id'], $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            } else {
                $Locations = Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        } else {
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $where[] = $data['location_id'];
            } else {
                $Locations = Locations::getActiveSorted(ACL::getUserCentres());
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        }

        $location_info = Locations::whereIn('id', $where)->get()->pluck('name', 'id');

        $report_data = [];

        foreach ($location_info as $key => $location) {

            $loc_inform = Locations::find($key);

            $report_data[$loc_inform->id] = [
                'id' => $key,
                'name' => $location,
                'region' => $loc_inform->region->name,
                'city' => $loc_inform->city->name,
                'machine' => [],
            ];

            $invoice_paid = InvoiceStatuses::where('slug', '=', 'paid')->first();

            /*Find resouce location wise*/
            $resource_location = Resources::where('location_id', '=', $loc_inform->id)->get();
            $count = 0;

            if (count($resource_location) > 0) {

                foreach ($resource_location as $recource) {

                    $report_data[$loc_inform->id]['machine'][$recource->id] = [
                        'id' => $recource->id,
                        'name' => $recource->name,
                        'machine_array' => [],
                    ];

                    $appointment_info = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                        ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                        ->where([
                            ['appointments.resource_id', '=', $recource->id],
                            ['invoices.invoice_status_id', '=', $invoice_paid->id],
                        ])->whereDate('invoices.created_at', '>=', $start_date)
                        ->whereDate('invoices.created_at', '<=', $end_date)
                        ->select('invoices.created_at as Invoice_created_at', 'invoices.id as Invoice_id', 'invoice_details.*', 'appointments.name', 'appointments.id as appointmentid')
                        ->get();

                    if (count($appointment_info) > 0) {
                        $count++;
                        foreach ($appointment_info as $appointment) {
                            $report_data[$loc_inform->id]['machine'][$recource->id]['machine_array'][$appointment->appointmentid] = [
                                'id' => $appointment->Invoice_id,
                                'client' => $appointment->name,
                                'service_price' => $appointment->service_price,
                                'discount_name' => $appointment->discount_name,
                                'discount_type' => $appointment->discount_type,
                                'discount_price' => $appointment->discount_price,
                                'amount' => $appointment->tax_exclusive_serviceprice,
                                'tax_value' => $appointment->tax_price,
                                'net_amount' => $appointment->tax_including_price,
                                'is_exclusive' => $appointment->is_exclusive,
                                'created_at' => $appointment->Invoice_created_at,
                            ];
                        }
                    } else {
                        unset($report_data[$loc_inform->id]['machine'][$recource->id]);
                    }
                }
            }
            if ($count == 0) {
                unset($report_data[$loc_inform->id]);
            }
        }

        return $report_data;
    }

    /**
     * patner Collection Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function partnercollectionreport($data, $account_id)
    {

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $where = [];

        if (isset($data['region_id']) && $data['region_id']) {
            /*
             * If region selected
             * case1: If location is selected
             * case2: If location is not selected
             */
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $Locations = Locations::generalrevenuegetActiveSorted($data['location_id'], $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            } else {
                $Locations = Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        } else {
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $where[] = $data['location_id'];
            } else {
                $Locations = Locations::getActiveSorted(ACL::getUserCentres());
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        }

        $location_info = Locations::whereIn('id', $where)->get();

        $report_data = [];

        foreach ($location_info as $location) {

            $packagesadvances = PackageAdvances::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where([
                    ['account_id', '=', $account_id],
                    ['location_id', '=', $location->id],
                ])->orderBy('created_at', 'asc')->get();

            if (count($packagesadvances) > 0) {

                $report_data[$location->id] = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'region' => $location->region->name,
                    'city' => $location->city->name,
                    'machine' => [],
                ];

                if ($packagesadvances) {

                    $machines = [];
                    $packageids = [];
                    $count = 0;

                    foreach ($packagesadvances as $key => $packagesadvance) {

                        $tax = 0;
                        $net_amount = 0;

                        if (
                            ($packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->cash_amount != '0' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            )
                            ||
                            ($packagesadvance->cash_flow == 'out' &&
                                $packagesadvance->is_refund == '1' &&
                                $packagesadvance->is_tax == '0'
                            )
                        ) {
                            if ($packagesadvance->cash_flow == 'in') {
                                if (isset($packagesadvance->appointment_id)) {

                                    $appointinfor = Appointments::where([
                                        ['id', '=', $packagesadvance->appointment_id],
                                        ['appointment_type_id', '=', '2'],
                                    ])->first();

                                    $tax_percentage = $appointinfor->location->tax_percentage;

                                    if ($appointinfor) {

                                        $resourceinfor = Resources::find($appointinfor->resource_id);

                                        if (!in_array($resourceinfor->machine_type_id, $machines)) {

                                            $machinetype = MachineType::find($resourceinfor->machine_type_id);

                                            $report_data[$location->id]['machine'][$resourceinfor->machine_type_id] = [
                                                'id' => $machinetype->id,
                                                'name' => $machinetype->name,
                                                'transaction' => [],
                                            ];

                                            $machines[] = $resourceinfor->machine_type_id;
                                        }

                                        $package_tax_info = \App\Models\Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                                            ->where('invoices.appointment_id', '=', $packagesadvance->appointment_id)
                                            ->where('tax_including_price', '=', $packagesadvance->cash_amount)
                                            ->select('invoice_details.tax_exclusive_serviceprice', 'invoice_details.is_exclusive', 'invoice_details.tax_price', 'invoice_details.tax_including_price')
                                            ->first();

                                        $remaining_amount = $package_tax_info['tax_including_price'];
                                        $remaining_amount_tax = $package_tax_info['tax_price'];
                                        $remaining_net_amount = $package_tax_info['tax_exclusive_serviceprice'];

                                        $refund = '';

                                        $report_data[$location->id]['machine'][$resourceinfor->machine_type_id]['transaction'][$count++] = [
                                            'id' => $appointinfor->patient_id,
                                            'data_id' => $appointinfor->id,
                                            'name' => $appointinfor->patient->name,
                                            'flow' => $packagesadvance->cash_flow,
                                            'amount' => $remaining_amount,
                                            'tax' => $remaining_amount_tax,
                                            'net_amount' => $remaining_net_amount,
                                            'amount_out' => 0,
                                        ];
                                    }
                                } else {

                                    if (!in_array($packagesadvance->package_id, $packageids, true)) {

                                        $packageids[] = $packagesadvance->package_id;

                                        $packageinfo = Packages::find($packagesadvance->package_id);

                                        $tax_percentage = $packageinfo->location->tax_percentage;

                                        $total_consume_service = PackageService::whereDate('updated_at', '>=', $start_date)
                                            ->whereDate('updated_at', '<=', $end_date)
                                            ->where([
                                                ['is_consumed', '=', '1'],
                                                ['package_id', '=', $packageinfo->id],
                                            ])->whereNotNull('package_id')->get();

                                        if (count($total_consume_service) > 0) {
                                            $total_consume_packageservice_ids = PackageService::whereDate('updated_at', '>=', $start_date)
                                                ->whereDate('updated_at', '<=', $end_date)
                                                ->where([
                                                    ['is_consumed', '=', '1'],
                                                    ['package_id', '=', $packageinfo->id],
                                                ])->whereNotNull('package_id')->get()->pluck('id')->toArray();

                                            $total_consume = PackageService::whereIn('id', $total_consume_packageservice_ids)->where([
                                                ['is_consumed', '=', '1'],
                                                ['package_id', '=', $packageinfo->id],
                                            ])->whereNotNull('package_id')->sum('tax_including_price');
                                        } else {
                                            $total_consume_packageservice_ids = [];
                                            $total_consume = 0;
                                        }

                                        $machine_types = AppointmentEditWidget::LoadMachineType_machinewisecollection_report($packageinfo, $total_consume_packageservice_ids); //$package_machine

                                        $machine_type_count = count($machine_types); //$package_machine_count

                                        if (count($total_consume_service) > 0) {

                                            $package_info_consume = PackageAdvances::whereDate('created_at', '>=', $start_date)
                                                ->whereDate('created_at', '<=', $end_date)
                                                ->where([
                                                    ['package_id', '=', $packageinfo->id],
                                                    ['is_cancel', '=', '0'],
                                                    ['is_refund', '=', '0'],
                                                    ['is_adjustment', '=', '0'],
                                                    ['cash_flow', '=', 'out'],
                                                ])->get();

                                            $appointmentids = [];

                                            $tax = 0;
                                            $net_amount = 0;

                                            foreach ($total_consume_service as $consume_service) {

                                                foreach ($package_info_consume as $consume_package) {

                                                    if ($consume_package->appointment_id) {

                                                        $appointment_for = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                                                            ->whereDate('invoices.created_at', '>=', $start_date)
                                                            ->whereDate('invoices.created_at', '<=', $end_date)
                                                            ->where([
                                                                ['appointments.id', '=', $consume_package->appointment_id],
                                                                ['appointments.appointment_type_id', '=', '2'],
                                                                ['invoices.invoice_status_id', '=', '3'],
                                                            ])->select('appointments.*')->first();

                                                        if ($appointment_for) {

                                                            if ($appointment_for->service_id == $consume_service->service_id) {

                                                                if (!in_array($consume_package->appointment_id, $appointmentids, true)) {

                                                                    $appointmentids[] = $consume_package->appointment_id;

                                                                    $package_tax_info = \App\Models\Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                                                                        ->where('invoices.appointment_id', '=', $consume_package->appointment_id)
                                                                        ->select('invoice_details.tax_exclusive_serviceprice', 'invoice_details.is_exclusive', 'invoice_details.tax_price', 'invoice_details.tax_including_price')->first();

                                                                    $remaining_amount = $package_tax_info['tax_including_price'];
                                                                    $remaining_amount_tax = $package_tax_info['tax_price'];
                                                                    $remaining_net_amount = $package_tax_info['tax_exclusive_serviceprice'];
                                                                    $tax += $remaining_amount_tax;
                                                                    $net_amount += $remaining_net_amount;

                                                                    $resource_info = Resources::find($appointment_for->resource_id);

                                                                    if (!in_array($resource_info->machine_type_id, $machines)) {

                                                                        $machinetype = MachineType::find($resource_info->machine_type_id);

                                                                        $report_data[$location->id]['machine'][$resource_info->machine_type_id] = [
                                                                            'id' => $machinetype->id,
                                                                            'name' => $machinetype->name,
                                                                            'transaction' => [],
                                                                        ];

                                                                        $machines[] = $resource_info->machine_type_id;
                                                                    }
                                                                    if ($remaining_amount > 0) {
                                                                        $report_data[$location->id]['machine'][$resource_info->machine_type_id]['transaction'][$count++] = [
                                                                            'id' => $packageinfo->patient_id,
                                                                            'data_id' => $packageinfo->id,
                                                                            'name' => $packageinfo->user->name,
                                                                            'flow' => 'in',
                                                                            'amount' => $remaining_amount,
                                                                            'tax' => $remaining_amount_tax,
                                                                            'net_amount' => $remaining_net_amount,
                                                                            'amount_out' => 0,
                                                                        ];
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $cash_receive = PackageAdvances::whereDate('created_at', '>=', $start_date)
                                            ->whereDate('created_at', '<=', $end_date)
                                            ->where([
                                                ['package_id', '=', $packageinfo->id],
                                                ['is_cancel', '=', '0'],
                                                ['cash_flow', '=', 'in'],
                                            ])->sum('cash_amount');

                                        $package_service_ids = [];

                                        foreach ($machine_types['machine_types'] as $machine_type) {

                                            foreach ($machine_types['machine_service_allocation'] as $allocate_service) {
                                                if ($machine_type->id == $allocate_service['Machine_type_id']) {
                                                    $package_service_ids[] = $allocate_service['Package_service_id'];
                                                }
                                            }

                                            if (!in_array($machine_type->id, $machines)) {

                                                $report_data[$location->id]['machine'][$machine_type->id] = [
                                                    'id' => $machine_type->id,
                                                    'name' => $machine_type->name,
                                                    'transaction' => [],
                                                ];

                                                $machines[] = $machine_type->id;
                                            }
                                            $machine_service_amount = PackageService::whereIn('id', $package_service_ids)->sum('tax_including_price');

                                            $machine_service_amount_exclusive = PackageService::whereIn('id', $package_service_ids)->sum('tax_exclusive_price');

                                            $total_amount = $packageinfo->total_price - $total_consume;

                                            $divide_amount = 0;
                                            $divide_tax = 0;
                                            $divide_net_amount = 0;

                                            if ($total_amount > 0) {
                                                $remaining_amount = $cash_receive - $total_consume;
                                                if ($tax > 0 && $net_amount > 0) {
                                                    $divide_amount = ($machine_service_amount * $remaining_amount) / $total_amount;
                                                    $divide_net_amount = $divide_amount / (($tax_percentage / 100) + 1);
                                                    $divide_tax = $divide_amount - $divide_net_amount;
                                                } else {
                                                    $divide_net_amount = ($machine_service_amount_exclusive * $remaining_amount) / $total_amount;
                                                    $divide_amount = ($machine_service_amount * $remaining_amount) / $total_amount;
                                                    $divide_tax = $divide_amount - $divide_net_amount;
                                                }
                                            }

                                            if ($divide_amount > 0) {
                                                $report_data[$location->id]['machine'][$machine_type->id]['transaction'][$count++] = [
                                                    'id' => $packageinfo->patient_id,
                                                    'data_id' => $packageinfo->id,
                                                    'name' => $packageinfo->user->name,
                                                    'flow' => 'in',
                                                    'amount' => $divide_amount,
                                                    'tax' => $divide_tax,
                                                    'net_amount' => $divide_net_amount,
                                                    'amount_out' => 0,
                                                ];
                                            }
                                            $package_service_ids = [];
                                        }
                                    }
                                }
                            } else {

                                if (isset($packagesadvance->appointment_id)) {

                                    $appointinfor = Appointments::find($packagesadvance->appointment_id);

                                    $resourceinfo = Resources::find($appointinfor->resource_id);

                                    if (!in_array($resourceinfo->machine_type_id, $machines)) {

                                        $machinetype = MachineType::find($resourceinfo->machine_type_id);

                                        $report_data[$location->id]['machine'][$machinetype->id] = [
                                            'id' => $machinetype->id,
                                            'name' => $machinetype->name,
                                            'transaction' => [],
                                        ];
                                    }
                                    $services[] = $appointinfor->service_id;

                                    $report_data[$location->id]['machine'][$resourceinfo->machine_type_id]['transaction'][$count++] = [
                                        'id' => $appointinfor->patient_id,
                                        'data_id' => $appointinfor->id,
                                        'name' => $appointinfor->patient->name,
                                        'flow' => 'out',
                                        'amount' => 0,
                                        'tax' => 0,
                                        'net_amount' => 0,
                                        'amount_out' => $packagesadvance->cash_amount,
                                    ];
                                } else {

                                    $packageinfo = Packages::find($packagesadvance->package_id);

                                    $machine_types = AppointmentEditWidget::LoadMachineType_machinewisecollection_report($packageinfo); //$package_machine

                                    $package_service_ids = [];

                                    $package_machine = $package_machine_count = Resources::where([
                                        ['location_id', '=', $packageinfo->location_id],
                                        ['active', '=', '1'],
                                    ])->get();

                                    $package_machine_count = count($package_machine_count);

                                    $cash_receive = $packagesadvance->cash_amount;

                                    foreach ($machine_types['machine_types'] as $machine_type) {

                                        foreach ($machine_types['machine_service_allocation'] as $allocate_service) {
                                            if ($machine_type->id == $allocate_service['Machine_type_id']) {
                                                $package_service_ids[] = $allocate_service['Package_service_id'];
                                            }
                                        }

                                        if (!in_array($machine_type->id, $machines)) {

                                            $report_data[$location->id]['machine'][$machine_type->id] = [
                                                'id' => $machine_type->id,
                                                'name' => $machine_type->name,
                                                'transaction' => [],
                                            ];

                                            $machines[] = $machine_type->id;
                                        }

                                        $machine_service_amount = PackageService::whereIn('id', $package_service_ids)->sum('tax_including_price');

                                        $divide_amount = 0;

                                        $remaining_amount = $packagesadvance->cash_amount;

                                        $total_amount = $packageinfo->total_price;

                                        $divide_amount = ($machine_service_amount * $remaining_amount) / $total_amount;

                                        if ($divide_amount > 0) {
                                            $report_data[$location->id]['machine'][$machine_type->id]['transaction'][$count++] = [
                                                'id' => $packageinfo->patient_id,
                                                'data_id' => $packageinfo->id,
                                                'name' => $packageinfo->user->name,
                                                'flow' => 'out',
                                                'amount' => 0,
                                                'tax' => 0,
                                                'net_amount' => 0,
                                                'amount_out' => $divide_amount,
                                            ];
                                        }
                                        $package_service_ids = [];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $report_data;
    }

    /**
     * Staff Wise Revenue Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function staffwiserevenue($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $location_info = Locations::where([
                ['account_id', '=', $account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
                ['id', '=', $data['location_id']],
            ])->get()->pluck('id')->toArray();
        } else {
            $location_info = Locations::getActiveSortedStaffwisereport(ACL::getUserCentres())->pluck('id')->toArray();
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $doctor_info = User::where([
                ['account_id', '=', $account_id],
                ['active', '=', '1'],
                ['id', '=', $data['doctor_id']],
            ])->get()->pluck('id')->toArray();
        } else {
            $doctor_info = User::getAllActivePractionersRecords($account_id, ACL::getUserCentres())->pluck('id')->toArray();
        }

        $report = [];

        /*case 1*/
        $revenue_plan_information = Appointments::join('packages', 'appointments.id', '=', 'packages.appointment_id')
            ->join('package_advances', 'packages.id', '=', 'package_advances.package_id')
            ->whereDate('package_advances.created_at', '>=', $start_date)
            ->whereDate('package_advances.created_at', '<=', $end_date)
            ->whereIn('packages.location_id', $location_info)
            ->whereIn('appointments.doctor_id', $doctor_info)
            ->whereNotNull('packages.appointment_id')
            ->where('package_advances.cash_amount', '>', 0)
            ->select('appointments.doctor_id', 'appointments.location_id', 'packages.id', 'package_advances.*')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($revenue_plan_information) {

            $doctors = [];
            $locations = [];

            foreach ($revenue_plan_information as $revenueinformation) {
                if (!in_array($revenueinformation->location_id, $locations)) {
                    $report[$revenueinformation->location_id] = [
                        'centre' => $revenueinformation->location->name,
                        'city' => $revenueinformation->location->city->name,
                        'region' => $revenueinformation->location->region->name,
                        'doctor_info' => [],
                    ];

                    $locations[] = $revenueinformation->location_id;
                }

                if (!in_array($revenueinformation->doctor_id, $doctors)) {
                    $report[$revenueinformation->location_id]['doctor_info'][$revenueinformation->doctor_id] = [
                        'doctor' => $revenueinformation->doctor->name,
                        'centre' => $revenueinformation->location->name,
                        'city' => $revenueinformation->location->city->name,
                        'region' => $revenueinformation->location->region->name,
                        'doctor_revenue' => [],
                    ];
                    $doctors[] = $revenueinformation->doctor_id;
                }

                $child_array = [];

                $child_array = self::genericfunctionforstaffwiserevenue($revenueinformation);

                if ($child_array) {
                    $report[$revenueinformation->location_id]['doctor_info'][$revenueinformation->doctor_id]['doctor_revenue'][$revenueinformation->id] = $child_array;
                }
            }
        }
        /*end*/

        /*case 2*/
        $revenue_treatment_information = Appointments::join('package_advances', 'appointments.id', '=', 'package_advances.appointment_id')
            ->whereDate('package_advances.created_at', '>=', $start_date)
            ->whereDate('package_advances.created_at', '<=', $end_date)
            ->whereIn('appointments.location_id', $location_info)
            ->whereIn('appointments.doctor_id', $doctor_info)
            ->whereNotNull('appointments.appointment_id')
            ->whereNull('package_advances.package_id')
            ->select('appointments.doctor_id', 'appointments.location_id', 'package_advances.*', 'appointments.appointment_id as appointmentlinkid')->get();

        if ($revenue_treatment_information) {

            $doctors_2 = $doctors;
            $locations_2 = $locations;
            $count = 0;

            foreach ($revenue_treatment_information as $revenueinformation_treat) {

                $link_doctor_id = Appointments::where('id', '=', $revenueinformation_treat->appointmentlinkid)->first();

                if (!in_array($link_doctor_id->location_id, $locations_2)) {
                    $report[$link_doctor_id->location_id] = [
                        'centre' => $link_doctor_id->location->name,
                        'city' => $link_doctor_id->location->city->name,
                        'region' => $link_doctor_id->location->region->name,
                        'doctor_info' => [],
                    ];

                    $locations_2[] = $link_doctor_id->location_id;
                } else {
                    $report[$link_doctor_id->location_id]['centre'] = $link_doctor_id->location->name;
                    $report[$link_doctor_id->location_id]['city'] = $link_doctor_id->location->city->name;
                    $report[$link_doctor_id->location_id]['region'] = $link_doctor_id->location->region->name;
                }

                if (!in_array($link_doctor_id->doctor_id, $doctors_2)) {
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id] = [
                        'doctor' => $link_doctor_id->doctor->name,
                        'centre' => $link_doctor_id->location->name,
                        'city' => $link_doctor_id->location->city->name,
                        'region' => $link_doctor_id->location->region->name,
                        'doctor_revenue' => [],
                    ];
                    $doctors_2[] = $link_doctor_id->doctor_id;
                } else {

                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['doctor'] = $link_doctor_id->doctor->name;
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['centre'] = $link_doctor_id->location->name;
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['city'] = $link_doctor_id->location->city->name;
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['region'] = $link_doctor_id->location->region->name;
                }

                $child_array = [];

                $child_array = self::genericfunctionforstaffwiserevenue($revenueinformation_treat);

                if ($child_array) {
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['doctor_revenue'][$count++] = $child_array;
                }
            }
        }

        foreach ($report as $location_id => $data) {
            foreach ($data['doctor_info'] as $doctor_id => $value) {

                if (!isset($value['doctor'])) {

                    $doctor_latest = User::find($doctor_id);

                    $location_latest = Locations::find($location_id);

                    $report[$location_id]['doctor_info'][$doctor_id] = [
                        'doctor' => $doctor_latest->name,
                        'centre' => $location_latest->name,
                        'city' => $location_latest->city->name,
                        'region' => $location_latest->region->name,
                        'doctor_revenue' => $report[$location_id]['doctor_info'][$doctor_id]['doctor_revenue'],
                    ];
                }
                if (!array_key_exists('doctor_revenue', $report[$location_id]['doctor_info'][$doctor_id]) || count($report[$location_id]['doctor_info'][$doctor_id]['doctor_revenue']) == 0) {
                    unset($report[$location_id]['doctor_info'][$doctor_id]);
                }
            }
        }
        /*end*/
        return $report;
    }

    public static function genericfunctionforstaffwiserevenue($packagesadvance)
    {
        $balance = 0;
        $total_balance = 0;
        if (
            ($packagesadvance->cash_flow == 'in' &&
                $packagesadvance->is_adjustment == '0' &&
                $packagesadvance->is_tax == '0' &&
                $packagesadvance->is_cancel == '0'
            )
            ||
            ($packagesadvance->cash_flow == 'out' &&
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
                    $revenue = $packagesadvance->cash_amount;
                    $refund_out = '';
                } else {
                    $revenue = '';
                    $refund_out = $packagesadvance->cash_amount;
                }
                $report_data = [
                    'patient' => $packagesadvance->user->name,
                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvance->user->phone),
                    'transtype' => $transtype,
                    'payment_mode_id' => $packagesadvance->payment_mode_id,
                    'cash_flow' => $packagesadvance->cash_flow,
                    'revenue' => $revenue,
                    'refund_out' => $refund_out,
                    'Balance' => $balance,
                    'created_at' => Carbon::parse($packagesadvance->created_at)->format('F j,Y h:i A'),
                ];

                return $report_data;
            }
        }
    }

    public static function conversion_report($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where[] = [
                'appointments.region_id',
                '=',
                $data['region_id'],
            ];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where[] = [
                'appointments.city_id',
                '=',
                $data['city_id'],
            ];
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'appointments.patient_id',
                '=',
                $data['patient_id'],
            ];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where[] = [
                'appointments.service_id',
                '=',
                $data['service_id'],
            ];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where[] = [
                'appointments.doctor_id',
                '=',
                $data['doctor_id'],
            ];
        }

        $appointment_type = AppointmentTypes::whereSlug('consultancy')->first();

        $where[] = [
            'appointments.appointment_type_id',
            '=',
            $appointment_type->id,
        ];
        $where[] = [
            'package_advances.cash_amount',
            '>',
            0,
        ];

        $location_ids = GeneralFunctions::getLocationIds($data['location_id']);
        $appointments = Appointments::with('location:id,name')
            ->join('packages', 'appointments.id', '=', 'packages.appointment_id')
            ->join('package_advances', 'packages.id', '=', 'package_advances.package_id')
            ->when($location_ids, fn ($q) => $q->whereIn('appointments.location_id', $location_ids))
            ->where('appointments.base_appointment_status_id', config('constants.appointment_status_arrived'))
            ->whereDate('package_advances.created_at', '>=', $start_date)
            ->whereDate('package_advances.created_at', '<=', $end_date)
            ->where($where)
            ->whereNotNull('packages.appointment_id')
            ->whereNull('packages.deleted_at')
            ->select('appointments.*')
            ->orderBy('appointments.created_at', 'desc')
            ->get();

        $centerWise = Appointments::select('appointments.id', 'appointments.location_id', DB::raw('count(appointments.id) as count'))
            ->join('packages', 'appointments.id', '=', 'packages.appointment_id')
            ->join('package_advances', 'packages.id', '=', 'package_advances.package_id')
            ->where($where)
            ->whereNotNull('scheduled_date')
            ->when($location_ids, fn ($q) => $q->whereIn('appointments.location_id', $location_ids))
            ->where('appointments.appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->groupBy('appointments.location_id')
            ->pluck('count', 'appointments.location_id');

        $total = 0;
        $count = [];
        $arrived_count = [];
        $centerWiseData = [];
        $appointmentss = [];
        $appointments_info = [];
        $locationData = [];
        if (count($appointments)) {
            foreach ($appointments as $appointment) {
                if (!in_array($appointment->id, $appointmentss)) {
                    $appointments_info[$appointment->id] = [
                        'patient_id' => $appointment->patient_id,
                        'appointment_id' => $appointment->id,
                        'doctor_id' => $appointment->doctor_id,
                        'doctor' => $appointment->doctor->name,
                        'client' => $appointment->patient->name,
                        'phone' => $appointment->patient->phone,
                        'service' => $appointment->service->name,
                        'region' => $appointment->region->name,
                        'city' => $appointment->city->name,
                        'centre' => $appointment->location->name,
                        'doi' => \Carbon\Carbon::parse($appointment->created_at)->format('M d Y'),
                        'converted' => '',
                        'conversion_spend' => '',
                        'conversion_date' => '',
                    ];
                }
                $appointmentss[] = $appointment->id;

                $package_info = Packages::where('appointment_id', '=', $appointment->id)->get()->pluck('id')->toArray();

                if (count($package_info)) {

                    $actual = 0;
                    $revenue_in = 0;
                    $out = 0;

                    $packagesadvances = PackageAdvances::whereIn('package_id', $package_info)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->where('cash_amount', '>', 0)
                        ->get();

                    if (count($packagesadvances) > 0) {

                        $check = 0;

                        $first_advance = PackageAdvances::whereIn('package_id', $package_info)
                            ->where('cash_amount', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->first();

                        $date = ($first_advance->updated_at)->format('Y-m-d');

                        if (($date >= $start_date) && ($date <= $end_date)) {
                            $check = 1;
                        }
                        if ($check == 1) {
                            $appointments_info[$appointment->id]['converted'] = 'Yes';

                            foreach ($packagesadvances as $packagesadvance) {

                                $child = self::genericfunctionforstaffwiserevenue($packagesadvance);

                                if ($child) {
                                    $revenue_in += $child['revenue'] ? $child['revenue'] : 0;
                                    $out += $child['refund_out'] ? $child['refund_out'] : 0;
                                }
                            }
                            $actual = $revenue_in - $out;

                            $appointments_info[$appointment->id]['conversion_spend'] = $actual;
                            $appointments_info[$appointment->id]['converted'] = 'Yes';

                            $appointments_info[$appointment->id]['conversion_date'] = $first_advance->created_at;

                            /*$centerWiseData = self::centerWiseData(
                                $appointments_info[$appointment->id],
                                $appointment,
                                $centerWise,
                                $count,
                                $arrived_count,
                                $total,
                                $locationData
                            );*/

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
            /*case 1 end*/
        }

        /*case 2 start*/
        $records = Appointments::with('location:id,name')
            ->join('appointments as appoint_2', 'appointments.id', '=', 'appoint_2.appointment_id')
            ->join('package_advances', 'appoint_2.id', '=', 'package_advances.appointment_id')
            ->when($location_ids, fn ($q) => $q->whereIn('appointments.location_id', $location_ids))
            ->whereDate('package_advances.created_at', '>=', $start_date)
            ->whereDate('package_advances.created_at', '<=', $end_date)
            ->where($where)
            ->select('appointments.*', 'package_advances.cash_amount');
        $records = $records->select(DB::raw('DISTINCT appointments.id as ABC,appointments.*'))->get();

        if (count($records)) {

            $appointmentss2 = $appointmentss;

            foreach ($records as $appointment) {

                $revenue_in = 0;
                $out = 0;
                $status = false;
                $conversion_spend = 0;
                $converted = '';

                $in_appointment_info = Appointments::where('appointment_id', '=', $appointment->id)->get()->pluck('id')->toArray();

                if (count($in_appointment_info)) {

                    $packageadvance_info = PackageAdvances::whereIn('appointment_id', $in_appointment_info)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->get();

                    if (count($packageadvance_info) > 0) {

                        $check = 0;

                        $first_advance = PackageAdvances::whereIn('appointment_id', $in_appointment_info)
                            ->where('cash_amount', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->first();

                        $date = ($first_advance->updated_at)->format('Y-m-d');

                        if (($date >= $start_date) && ($date <= $end_date)) {
                            $check = 1;
                        }
                        if ($check == 1) {
                            foreach ($packageadvance_info as $packagesadvance) {
                                $child = self::genericfunctionforstaffwiserevenue($packagesadvance);
                                if ($child) {
                                    $revenue_in += $child['revenue'] ? $child['revenue'] : 0;
                                    $out += $child['refund_out'] ? $child['refund_out'] : 0;
                                }
                            }
                            $conversion_spend = $revenue_in - $out;
                            $converted = 'Yes';
                            $status = true;
                        } else {
                            $conversion_spend = '0';
                            $status = false;
                        }
                    }
                } else {
                    $conversion_spend = '0';
                    $status = false;
                }

                if (!in_array($appointment->id, $appointmentss2)) {
                    $appointments_info[$appointment->id] = [
                        'patient_id' => $appointment->patient_id,
                        'appointment_id' => $appointment->id,
                        'doctor_id' => $appointment->doctor_id,
                        'doctor' => $appointment->doctor->name,
                        'client' => $appointment->patient->name,
                        'phone' => $appointment->patient->phone,
                        'service' => $appointment->service->name,
                        'region' => $appointment->region->name,
                        'city' => $appointment->city->name,
                        'centre' => $appointment->location->name,
                        'doi' => \Carbon\Carbon::parse($appointment->created_at)->format('M d Y'),
                        'converted' => '',
                        'conversion_spend' => '',
                        'conversion_date' => '',
                    ];

                    $package_info = Packages::where('appointment_id', '=', $appointment->id)->get()->pluck('id')->toArray();

                    if (count($package_info) == 0) {
                        $appointmentss2[] = $appointment->id;
                        $appointments_info[$appointment->id]['converted'] = $converted;
                        $appointments_info[$appointment->id]['conversion_spend'] = $conversion_spend;
                        $appointments_info[$appointment->id]['conversion_date'] = $first_advance->created_at;
                    }
                } else {
                    if ($appointments_info[$appointment->id]['converted'] == 'Yes' && $status) {

                        $previouse_actual = $appointments_info[$appointment->id]['conversion_spend'];
                        $appointments_info[$appointment->id]['conversion_spend'] = $previouse_actual + $conversion_spend;
                    } elseif ($appointments_info[$appointment->id]['converted'] == 'No' && $status) {

                        $appointments_info[$appointment->id]['conversion_spend'] = $conversion_spend;
                        $appointments_info[$appointment->id]['conversion_date'] = $first_advance->created_at;
                        $appointments_info[$appointment->id]['converted'] = 'Yes';
                    }
                }

                /*$centerWiseData = self::centerWiseData(
                    $appointments_info[$appointment->id],
                    $appointment,
                    $centerWise,
                    $count,
                    $arrived_count,
                    $total,
                    $locationData
                );*/

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

        return [
            $appointments_info,
            $locationData,
        ];
    }

    public static function LoadConversionReport($data, $account_id)
    {
        $where = [];
        $total_apts = [];
        $converted_apts = [];
        $locationData = [];
        $appointments = array();
        $total = 0;
        $appointments_info = array();
        $returnCategoryData = [];
        $total_arrived_appointments = 0;
        $sum_conversion_spend2 = 0;

        $data['location_id'] = ($data['location_id'][0] == null) ? 'all' : $data['location_id'];

        $locations = $data['location_id'] == 'all' ? ACL::getUserCentres() : $data['location_id'];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }


        if (!empty($data['service_id'])) {
            $where[] = [['appointments.service_id' => $data['service_id']]];
        }


        $consultants = DoctorHasLocations::whereIn('location_id', $locations)->when(!empty($data['doctor_id']), function ($query) use ($data) {
            return $query->where('user_id', $data['doctor_id']);
        })
            ->distinct('user_id')
            ->pluck('user_id');


        // Get arrived and converted appointment status IDs
        $arrivedStatus = \App\Models\AppointmentStatuses::where(['account_id' => $account_id, 'is_arrived' => 1])->first();
        $convertedStatus = \App\Models\AppointmentStatuses::where(['account_id' => $account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : config('constants.appointment_status_arrived');
        $convertedStatusId = $convertedStatus ? $convertedStatus->id : null;

        $total_arrived_appointments = Appointments::with('location:id,name')
            ->join('services', 'appointments.service_id', 'services.id')
            ->where('appointments.appointment_type_id', 1)
            ->where(function($query) use ($arrivedStatusId, $convertedStatusId) {
                $query->where('appointments.base_appointment_status_id', $arrivedStatusId);
                if ($convertedStatusId) {
                    $query->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                }
            })
            ->whereIn('doctor_id', $consultants)
            ->whereIn('appointments.location_id', $locations)
            ->where($where)
            ->selectRaw('count(*) as arrived, service_id,services.name')
            ->where('appointments.scheduled_date', '>=', $start_date)
            ->where('appointments.scheduled_date', '<=', $end_date)

            ->groupBy('service_id')
            ->get();

        $converted_appointments =  Appointments::with('location:id,name')
            ->leftjoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->where(function($query) use ($arrivedStatusId, $convertedStatusId) {
                $query->where('appointments.base_appointment_status_id', $arrivedStatusId);
                if ($convertedStatusId) {
                    $query->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                }
            })
            ->whereIn('appointments.doctor_id', $consultants)
            ->whereIn('appointments.location_id', $locations)
            ->where($where)
            ->where('package_advances.cash_amount', '>', 0)
            ->select('appointments.*')
            ->where('package_advances.created_at', '>=', $start_date . ' 00:00:00')
            ->where('package_advances.created_at', '<=', $end_date . ' 23:59:59')

            ->get();

        if (count($converted_appointments)) {
            foreach ($converted_appointments as $appointment) {
                if (!in_array($appointment->id, $appointments)) {
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
                $appointments[] = $appointment->id;
                
                // Get invoice creation date for this appointment
                $invoice = \App\Models\Invoices::where('appointment_id', $appointment->id)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'asc')
                    ->first();

                if (!$invoice) {
                    continue;
                }

                $invoiceCreatedAt = Carbon::parse($invoice->created_at);
                $invoiceDate = $invoiceCreatedAt->format('Y-m-d');

                // Get all packages linked to this appointment
                $packages = Packages::where('appointment_id', $appointment->id)->get();

                if ($packages->isEmpty()) {
                    continue;
                }

                // Collect all package IDs
                $packageIds = $packages->pluck('id')->toArray();

                // Get package bundle IDs for all packages
                $packagebundleIds = PackageBundles::whereIn('package_id', $packageIds)->pluck('id');

                // Check if there's at least one service added in package on same day or after invoice creation date
                $serviceAfterInvoice = PackageService::whereIn('package_bundle_id', $packagebundleIds)
                    ->whereDate('created_at', '>=', $invoiceDate)
                    ->exists();

                if (!$serviceAfterInvoice) {
                    continue;
                }

                // Check if there's at least one payment on same day or after invoice creation date (across all packages)
                $firstPayment = PackageAdvances::whereIn('package_id', $packageIds)
                    ->where('cash_flow', 'in')
                    ->where('cash_amount', '>', 0)
                    ->whereNull('deleted_at')
                    ->whereDate('created_at', '>=', $invoiceDate)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if (!$firstPayment) {
                    continue;
                }

                // Check if the FIRST payment date falls within the report date range
                $firstPaymentDate = Carbon::parse($firstPayment->created_at)->format('Y-m-d');
                if ($firstPaymentDate < $start_date || $firstPaymentDate > $end_date) {
                    continue;
                }

                // Get all payments for conversion spend calculation (from invoice date, within report range) across all packages
                $packagesadvances = PackageAdvances::whereIn('package_id', $packageIds)
                    ->where('cash_amount', '>', 0)
                    ->whereNull('deleted_at')
                    ->whereDate('created_at', '>=', $invoiceDate)
                    ->where('package_advances.created_at', '>=', $start_date . ' 00:00:00')
                    ->where('package_advances.created_at', '<=', $end_date . ' 23:59:59')
                    ->get();

                if (count($packagesadvances) > 0) {
                    $actual = 0;
                    $revenue_in = 0;
                    $out = 0;

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
                    $appointments_info[$appointment->id]['conversion_date'] = $firstPayment->created_at;
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
        if (isset($data['doctor_id'])) {
            $total_appointments = Appointments::whereBetween('scheduled_date', [$start_date, $end_date])
                ->where('appointment_type_id' , 1)
                ->where(function ($query) use ($arrivedStatusId, $convertedStatusId) {
                    $query->where('appointments.base_appointment_status_id', $arrivedStatusId)
                        ->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                })
                
                ->whereIn('appointments.doctor_id', $consultants)
                ->whereIn('appointments.location_id', $locations)
                ->where($where)
                ->count();
        } else {
            $total_appointments = Appointments::whereBetween('scheduled_date', [$start_date, $end_date])
                ->where('appointment_type_id' , 1)
                ->where(function ($query) use ($arrivedStatusId, $convertedStatusId) {
                    $query->where('appointments.base_appointment_status_id', $arrivedStatusId)
                        ->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                })
                //->whereIn('appointments.doctor_id', $consultants)
                ->whereIn('appointments.location_id', $locations)
                ->where($where)
                ->count();
        }

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
        foreach ($maxConversion as $key => $conversions) {
            $sum_conversion_total = 0;
            $sum_conversion_spend = 0;
            foreach ($conversions as $conversion) {
                $name = $conversion['service'];
                $sum_conversion_spend += $conversion['conversion_spend'];
                $sum_conversion_total++;
            }
            $avg_by_category = ($sum_conversion_spend / count($conversions));
            $new_array[$name] = [
                'service' => $name,
                'total_conversion' => $sum_conversion_total,
                'avg' => $avg_by_category,
                'sum' => $sum_conversion_spend,
            ];
        }

        foreach ($total_arrived_appointments->toArray() as $key => $arrive_category) {
            if (array_key_exists($arrive_category['name'], $new_array)) {
                $name = [$arrive_category['name']][0];

                $sum_conversion_total = $new_array[$arrive_category['name']]['total_conversion'];
                $avg_valu = $new_array[$arrive_category['name']]['avg'];
                $sum_valu = $new_array[$arrive_category['name']]['sum'];
                if (isset($data['doctor_id'])) {
                    $category_total_records = Appointments::where(['service_id' => $arrive_category['service_id'], 'appointment_type_id' => 1])
                    ->where(function ($query) use ($arrivedStatusId, $convertedStatusId) {
                            $query->where('appointments.base_appointment_status_id', $arrivedStatusId)
                                ->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                        })
                        ->whereIn('doctor_id', $consultants)
                        ->whereIn('appointments.location_id', $locations)
                        ->where('scheduled_date', '>=', $start_date)
                        ->where('scheduled_date', '<=', $end_date)

                        ->count();
                } else {
                    $category_total_records = Appointments::where(['service_id' => $arrive_category['service_id'],  'appointment_type_id' => 1])
                    ->where(function ($query) use ($arrivedStatusId, $convertedStatusId) {
                            $query->where('appointments.base_appointment_status_id', $arrivedStatusId)
                                ->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                        })
                        //->whereIn('doctor_id', $consultants)
                        ->whereIn('appointments.location_id', $locations)
                        ->where('scheduled_date', '>=', $start_date)
                        ->where('scheduled_date', '<=', $end_date)
                        ->count();
                }
            } else {
                $name = [$arrive_category['name']][0];
                $sum_conversion_total = 0;
                $avg_valu = 0;
                $sum_valu = 0;

                if (isset($data['doctor_id'])) {
                    $category_total_records = Appointments::where(['service_id' => $arrive_category['service_id'],  'appointment_type_id' => 1])
                    ->where(function ($query) use ($arrivedStatusId, $convertedStatusId) {
                            $query->where('appointments.base_appointment_status_id', $arrivedStatusId)
                                ->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                        })
                        ->whereIn('doctor_id', $consultants)
                        ->whereIn('appointments.location_id', $locations)
                        ->where('scheduled_date', '>=', $start_date)
                        ->where('scheduled_date', '<=', $end_date)
                        ->count();
                } else {
                    $category_total_records = Appointments::where(['service_id' => $arrive_category['service_id'],  'appointment_type_id' => 1])
                    ->where(function ($query) use ($arrivedStatusId, $convertedStatusId) {
                            $query->where('appointments.base_appointment_status_id', $arrivedStatusId)
                                ->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                        })
                        //->whereIn('doctor_id', $consultants)
                        ->whereIn('appointments.location_id', $locations)
                        ->where('scheduled_date', '>=', $start_date)
                        ->where('scheduled_date', '<=', $end_date)
                        ->count();
                }
            }

            $returnCategoryData[$key] = [
                'service' => $name,
                'total_arrival' => $category_total_records,
                'total_conversion' => $sum_conversion_total,
                'avg' => $avg_valu,
                'sum' => $sum_valu
            ];
        }
        $maxConversion = collect($appointments_info)->max('conversion_spend');
        $minConversion = collect($appointments_info)->where('conversion_spend', '!=', '')->where('conversion_spend', '>', 0)->min('conversion_spend');
        $converted_Records = collect($appointments_info)->where('conversion_spend', '!=', '')->count();

        $totalamount = collect($appointments_info)->where('conversion_spend', '!=', '')->sum('conversion_spend');
        $total_appointments = Appointments::where('scheduled_date', '>=', $start_date)
            ->where(['appointment_type_id' => 1, 'base_appointment_status_id' => 2])
            ->where($where)
            //->whereIn('doctor_id', $consultants)
            ->whereIn('location_id', $locations)

            ->where('scheduled_date', '>=', $start_date)
            ->where('scheduled_date', '<=', $end_date)
            ->count();

        if ($total_appointments > 0) {
            $arrival_to_conversion_ratio = ($converted_Records / $total_appointments) * 100;
        } else {
            $arrival_to_conversion_ratio = 0;
        }
        if ($converted_Records > 0) {
            $average_client_coversion = $totalamount / $converted_Records;
        } else {
            $average_client_coversion = 0;
        }
        $conversionsByPatient = collect($appointments_info)->where('conversion_spend', "!=", "")->groupBy('patient_id')
            ->map(function ($appointments_info) {
                return $appointments_info->sum('conversion_spend');
            });
        if (count($conversionsByPatient) > 0) {
            $avg_cxlient_value = $conversionsByPatient->sum() / count($conversionsByPatient);
        } else {
            $avg_cxlient_value = 0;
        }

        return [
            $appointments_info,
            $locationData,
            $maxConversion,
            $minConversion,
            $returnCategoryData,
            $arrival_to_conversion_ratio,
            $average_client_coversion,
            $conversionsByPatient,
            $converted_Records,
            array_sum($total_apts),
            $avg_cxlient_value
        ];
    }

    private static function centerWiseData($appointments_info, $appointment, $centerWise, $count, $arrived_count, $total, $locationData)
    {
        $count[$appointment->location->id][] = 1;
        dump(count($count[$appointment->location->id]));

        $locationData[$appointment->location->name]['total_count'] = count($count[$appointment->location->id]);
        if ($appointment['converted'] != '') {
            $arrived_count[$appointment->location->id][] = 1;
            $locationData[$appointment->location->name]['total_count'] = count($arrived_count[$appointment->location->id]);
        }
        $total += $appointments_info['conversion_spend'] ? $appointments_info['conversion_spend'] : 0;

        $locationData[$appointment->location->name]['total'] = $total;

        return $locationData;
    }

    /*
     * Machine wise Collection Report
     */
    public static function machinewisecollectionreport($data, $account_id)
    {
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $where = [];

        if (isset($data['region_id']) && $data['region_id']) {
            /*
             * If region selected
             * case1: If location is selected
             * case2: If location is not selected
             */
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $Locations = Locations::generalrevenuegetActiveSorted($data['location_id'], $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            } else {
                $Locations = Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        } else {
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $where[] = $data['location_id'];
            } else {
                $Locations = Locations::getActiveSorted(ACL::getUserCentres());
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        }

        $location_info = Locations::whereIn('id', $where)->get();

        $report_data = [];

        foreach ($location_info as $location) {

            $packagesadvances = PackageAdvances::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where([
                    ['account_id', '=', $account_id],
                    ['location_id', '=', $location->id],
                ])->orderBy('created_at', 'asc')->get();

            if (count($packagesadvances) > 0) {

                $report_data[$location->id] = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'region' => $location->region->name,
                    'city' => $location->city->name,
                    'machine_types' => [],
                ];

                if ($packagesadvances) {

                    $packageids = [];
                    $count = 0;
                    $machines = [];

                    foreach ($packagesadvances as $key => $packagesadvance) {
                        if (
                            ($packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->cash_amount != '0' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            )
                            ||
                            ($packagesadvance->cash_flow == 'out' &&
                                $packagesadvance->is_refund == '1' &&
                                $packagesadvance->is_tax == '0'
                            )
                        ) {
                            if ($packagesadvance->cash_flow == 'in') {

                                if (isset($packagesadvance->appointment_id)) {

                                    $appointinfor = Appointments::where([
                                        ['id', '=', $packagesadvance->appointment_id],
                                        ['appointment_type_id', '=', '2'],
                                    ])->first();

                                    if ($appointinfor) {

                                        $resourceinfor = Resources::find($appointinfor->resource_id);

                                        if (!in_array($resourceinfor->machine_type_id, $machines)) {

                                            $machinetype = MachineType::find($resourceinfor->machine_type_id);

                                            $report_data[$location->id]['machine_types'][$resourceinfor->machine_type_id] = [
                                                'id' => $machinetype->id,
                                                'name' => $machinetype->name,
                                                'transaction' => [],
                                            ];
                                            $machines[] = $resourceinfor->machine_type_id;
                                        }

                                        $report_data[$location->id]['machine_types'][$resourceinfor->machine_type_id]['transaction'][$count++] = [
                                            'id' => $appointinfor->patient_id,
                                            'data_id' => $appointinfor->id,
                                            'name' => $appointinfor->patient->name,
                                            'flow' => $packagesadvance->cash_flow,
                                            'amount_in' => $packagesadvance->cash_amount,
                                            'amount_out' => 0,
                                        ];
                                    }
                                } else {
                                    if (!in_array($packagesadvance->package_id, $packageids, true)) {

                                        $packageids[] = $packagesadvance->package_id;

                                        $packageinfo = Packages::find($packagesadvance->package_id);

                                        $total_consume_service = PackageService::whereDate('updated_at', '>=', $start_date)
                                            ->whereDate('updated_at', '<=', $end_date)
                                            ->where([
                                                ['is_consumed', '=', '1'],
                                                ['package_id', '=', $packageinfo->id],
                                            ])->whereNotNull('package_id')->get();

                                        if (count($total_consume_service) > 0) {
                                            $total_consume_packageservice_ids = PackageService::whereDate('updated_at', '>=', $start_date)
                                                ->whereDate('updated_at', '<=', $end_date)
                                                ->where([
                                                    ['is_consumed', '=', '1'],
                                                    ['package_id', '=', $packageinfo->id],
                                                ])->whereNotNull('package_id')->get()->pluck('id')->toArray();

                                            $total_consume = PackageService::whereIn('id', $total_consume_packageservice_ids)->where([
                                                ['is_consumed', '=', '1'],
                                                ['package_id', '=', $packageinfo->id],
                                            ])->whereNotNull('package_id')->sum('tax_including_price');
                                        } else {
                                            $total_consume_packageservice_ids = [];
                                            $total_consume = 0;
                                        }

                                        $machine_types = AppointmentEditWidget::LoadMachineType_machinewisecollection_report($packageinfo, $total_consume_packageservice_ids); //$package_machine

                                        $machine_type_count = count($machine_types); //$package_machine_count

                                        if (count($total_consume_service) > 0) {
                                            $package_info_consume = PackageAdvances::whereDate('created_at', '>=', $start_date)
                                                ->whereDate('created_at', '<=', $end_date)
                                                ->where([
                                                    ['package_id', '=', $packageinfo->id],
                                                    ['is_cancel', '=', '0'],
                                                    ['is_refund', '=', '0'],
                                                    ['is_adjustment', '=', '0'],
                                                    ['cash_flow', '=', 'out'],
                                                ])->get();

                                            $appointmentids = [];
                                            $coun = 0;
                                            $ids_a = [];

                                            foreach ($total_consume_service as $consume_service) {

                                                foreach ($package_info_consume as $consume_package) {

                                                    if ($consume_package->appointment_id) {

                                                        $appointment_for = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                                                            ->whereDate('invoices.created_at', '>=', $start_date)
                                                            ->whereDate('invoices.created_at', '<=', $end_date)
                                                            ->where([
                                                                ['appointments.id', '=', $consume_package->appointment_id],
                                                                ['appointments.appointment_type_id', '=', '2'],
                                                                ['invoices.invoice_status_id', '=', '3'],
                                                            ])->select('appointments.*')->first();

                                                        if ($appointment_for) {

                                                            if ($appointment_for->service_id == $consume_service->service_id) {

                                                                if (!in_array($consume_package->appointment_id, $appointmentids, true)) {
                                                                    $coun++;
                                                                    $appointmentids[] = $consume_package->appointment_id;

                                                                    $divide_amount_consume = PackageAdvances::where([
                                                                        ['package_id', '=', $packageinfo->id],
                                                                        ['appointment_id', '=', $consume_package->appointment_id],
                                                                        ['is_cancel', '=', '0'],
                                                                        ['is_refund', '=', '0'],
                                                                        ['is_adjustment', '=', '0'],
                                                                        ['cash_flow', '=', 'out'],
                                                                    ])->distinct('cash_amount')->orderBy('created_at', 'desc')->limit('2')->sum('cash_amount');

                                                                    $resource_info = Resources::find($appointment_for->resource_id);

                                                                    if (!in_array($resource_info->machine_type_id, $machines)) {

                                                                        $machinetype = MachineType::find($resource_info->machine_type_id);

                                                                        $report_data[$location->id]['machine_types'][$resource_info->machine_type_id] = [
                                                                            'id' => $machinetype->id,
                                                                            'name' => $machinetype->name,
                                                                            'transaction' => [],
                                                                        ];

                                                                        $machines[] = $resource_info->machine_type_id;
                                                                    }
                                                                    if ($divide_amount_consume > 0) {
                                                                        $report_data[$location->id]['machine_types'][$resource_info->machine_type_id]['transaction'][$count++] = [
                                                                            'id' => $packageinfo->patient_id,
                                                                            'data_id' => $packageinfo->id,
                                                                            'name' => $packageinfo->user->name,
                                                                            'flow' => 'in',
                                                                            'amount_in' => $divide_amount_consume,
                                                                            'amount_out' => 0,
                                                                        ];
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $cash_receive = PackageAdvances::whereDate('created_at', '>=', $start_date)
                                            ->whereDate('created_at', '<=', $end_date)
                                            ->where([
                                                ['package_id', '=', $packageinfo->id],
                                                ['is_cancel', '=', '0'],
                                                ['cash_flow', '=', 'in'],
                                            ])->sum('cash_amount');

                                        $package_service_ids = [];

                                        foreach ($machine_types['machine_types'] as $machine_type) {

                                            foreach ($machine_types['machine_service_allocation'] as $allocate_service) {
                                                if ($machine_type->id == $allocate_service['Machine_type_id']) {
                                                    $package_service_ids[] = $allocate_service['Package_service_id'];
                                                }
                                            }

                                            if (!in_array($machine_type->id, $machines)) {

                                                $report_data[$location->id]['machine_types'][$machine_type->id] = [
                                                    'id' => $machine_type->id,
                                                    'name' => $machine_type->name,
                                                    'transaction' => [],
                                                ];

                                                $machines[] = $machine_type->id;
                                            }
                                            $machine_service_amount = PackageService::whereIn('id', $package_service_ids)->sum('tax_including_price');

                                            $divide_amount = 0;

                                            $remaining_amount = $cash_receive - $total_consume;

                                            $total_amount = $packageinfo->total_price - $total_consume;
                                            if ($total_amount > 0) {
                                                $divide_amount = ($machine_service_amount * $remaining_amount) / $total_amount;
                                            }
                                            if ($divide_amount > 0) {
                                                $report_data[$location->id]['machine_types'][$machine_type->id]['transaction'][$count++] = [
                                                    'id' => $packageinfo->patient_id,
                                                    'data_id' => $packageinfo->id,
                                                    'name' => $packageinfo->user->name,
                                                    'flow' => 'in',
                                                    'amount_in' => $divide_amount,
                                                    'amount_out' => 0,
                                                ];
                                            }
                                            $package_service_ids = [];
                                        }
                                    }
                                }
                            } else {

                                if (isset($packagesadvance->appointment_id)) {

                                    $appointinfor = Appointments::find($packagesadvance->appointment_id);

                                    $resourceinfo = Resources::find($appointinfor->resource_id);

                                    if (!in_array($resourceinfo->machine_type_id, $machines)) {

                                        $machinetype = MachineType::find($resourceinfo->machine_type_id);

                                        $report_data[$location->id]['machine_types'][$machinetype->id] = [
                                            'id' => $machinetype->id,
                                            'name' => $machinetype->name,
                                            'transaction' => [],
                                        ];

                                        $machines[] = $resourceinfo->machine_type_id;
                                    }

                                    $report_data[$location->id]['machine_types'][$resourceinfo->machine_type_id]['transaction'][$count++] = [
                                        'id' => $appointinfor->patient_id,
                                        'data_id' => $appointinfor->id,
                                        'name' => $appointinfor->patient->name,
                                        'flow' => 'out',
                                        'amount_in' => 0,
                                        'amount_out' => $packagesadvance->cash_amount,
                                    ];
                                } else {

                                    $packageinfo = Packages::find($packagesadvance->package_id);

                                    $machine_types = AppointmentEditWidget::LoadMachineType_machinewisecollection_report($packageinfo); //$package_machine

                                    $package_service_ids = [];

                                    foreach ($machine_types['machine_types'] as $machine_type) {

                                        foreach ($machine_types['machine_service_allocation'] as $allocate_service) {
                                            if ($machine_type->id == $allocate_service['Machine_type_id']) {
                                                $package_service_ids[] = $allocate_service['Package_service_id'];
                                            }
                                        }

                                        if (!in_array($machine_type->id, $machines)) {

                                            $report_data[$location->id]['machine_types'][$machine_type->id] = [
                                                'id' => $machine_type->id,
                                                'name' => $machine_type->name,
                                                'transaction' => [],
                                            ];

                                            $machines[] = $machine_type->id;
                                        }

                                        $machine_service_amount = PackageService::whereIn('id', $package_service_ids)->sum('tax_including_price');

                                        $divide_amount = 0;

                                        $remaining_amount = $packagesadvance->cash_amount;

                                        $total_amount = $packageinfo->total_price;

                                        $divide_amount = ($machine_service_amount * $remaining_amount) / $total_amount;

                                        if ($divide_amount > 0) {
                                            $report_data[$location->id]['machine_types'][$machine_type->id]['transaction'][$count++] = [
                                                'id' => $packageinfo->patient_id,
                                                'data_id' => $packageinfo->id,
                                                'name' => $packageinfo->user->name,
                                                'flow' => 'out',
                                                'amount_in' => 0,
                                                'amount_out' => $divide_amount,
                                            ];
                                        }
                                        $package_service_ids = [];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $report_data;
    }

    /**
     * Consume Revenue Plan Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function consumeplanrevenue($data, $account)
    {
        $reportdata = [];
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        if (isset($data['region_id']) && $data['region_id']) {
            /*
             * If region selected
             * case1: If location is selected
             * case2: If location is not selected
             */
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $Locations = Locations::generalrevenuegetActiveSorted($data['location_id'], $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            } else {
                $Locations = Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        } else {
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $where[] = $data['location_id'];
            } else {
                $Locations = Locations::getActiveSorted(ACL::getUserCentres());
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        }
        $locations = Locations::whereIn('id', $where)->get();
        foreach ($locations as $location) {
            $plan_information = Packages::with('packageservice', 'location')->whereDate('created_at', '>=', $start_date)->whereDate('created_at', '<=', $end_date)->where('location_id', '=', $location->id)->get();
            foreach ($plan_information as $plan) {
                $t_count = count($plan->packageservice);
                $c_count = count($plan->packageservice->where('is_consumed', '=', '1'));
                if ($t_count == $c_count) {
                    $invoice_information = InvoiceDetails::where('package_id', '=', $plan->id)->orderBy('created_at', 'asc')->get();
                    foreach ($invoice_information as $invoice) {
                        $reportdata[] = [
                            'plan_id' => $plan->id,
                            'service' => $invoice->service->name,
                            'location' => $plan->location->name,
                            'service_price' => $invoice->service->price,
                            'disocunt_name' => $invoice->discount_name,
                            'discount_type' => $invoice->discount_type,
                            'discount_amount' => $invoice->discount_price,
                            'amount' => $invoice->tax_exclusive_serviceprice,
                            'tax' => $invoice->tax_percenatage,
                            'tax_value' => $invoice->tax_price,
                            'tax_amount' => $invoice->tax_including_price,
                            'is_exclusive' => $invoice->is_exclusive,
                        ];
                    }
                } else {
                    continue;
                }
            }
        }

        return $reportdata;
    }

    /**
     * Plan Maturity Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function planmaturityreport($data, $account_id)
    {
        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        if (count($where)) {
            $packageinfo = Packages::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())->get();
        }
        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {

            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'total_price' => $packagerow->total_price,
                'is_refund' => $packagerow->is_refund ? 'Yes' : 'NO',
                'advancebalance' => '',
                'outstandingbalance' => '',
                'usedbalance' => '',
                'unusedbalance' => '',
            ];
            $advancebalance = PackageAdvances::where([
                ['package_id', '=', $packagerow->id],
                ['cash_flow', '=', 'in'],
                ['is_refund', '=', 0],
                ['is_adjustment', '=', 0],
                ['is_cancel', '=', 0],
            ])->whereNull('appointment_id')->sum('cash_amount');

            if ($advancebalance !== 0) {

                $packagetrans[$packagerow->id]['advancebalance'] = $advancebalance;

                $outstandingbalance = $packagerow->total_price - $advancebalance;

                $packagetrans[$packagerow->id]['outstandingbalance'] = $outstandingbalance;

                $packagesadvances = PackageAdvances::where('package_id', '=', $packagerow->id)->get();

                $balance = 0;
                $refund_balance = 0;

                foreach ($packagesadvances as $packagesadvances) {
                    if ($packagesadvances->cash_flow == 'out' & ($packagesadvances->is_refund == 1 || $packagesadvances->is_adjustment == 1)) {
                        $refund_balance += $packagesadvances->cash_amount;
                    }
                    if ($packagesadvances->is_refund == 0 && $packagesadvances->is_adjustment == 0) {
                        switch ($packagesadvances->cash_flow) {
                            case 'in':
                                $balance = $balance + $packagesadvances->cash_amount;
                                break;
                            case 'out':
                                $balance = $balance - $packagesadvances->cash_amount;
                                break;
                            default:
                                break;
                        }
                    }
                }

                $usedbalance = $advancebalance - $balance;

                $packagetrans[$packagerow->id]['usedbalance'] = $usedbalance;

                $packagetrans[$packagerow->id]['unusedbalance'] = $balance - $refund_balance;
            } else {
                unset($packagetrans[$packagerow->id]);
            }
        }

        return $packagetrans;
    }
}
