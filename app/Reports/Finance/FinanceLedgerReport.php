<?php

declare(strict_types=1);

namespace App\Reports\Finance;

use App\Enums\PlanType;
use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Config;

class FinanceLedgerReport
{
    use ParsesDateRange;

    /**
     * Customer Payment Ledger Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function Customerpaymentledgerallentries($data, $account_id)
    {
        $where = [];

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);

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

        $packagesadvances = PackageAdvances::with(['user:id,name,phone', 'location:id,name'])
            ->whereDate('created_at', '=', $start_date)
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

                $balance += match ($packagesadvances->cash_flow) {
                    'in' => $packagesadvances->cash_amount,
                    'out' => -$packagesadvances->cash_amount,
                    default => 0,
                };
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
                        'phone' => GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone),
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
        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->whereIn('location_id', ACL::getUserCentres())->get();
        }

        // Pre-fetch ALL package advances for ALL packages in one query, grouped
        // by package_id. Replaces the previous N+1 (one query per package).
        $packageIds = $packageinfo->pluck('id')->all();
        $advancesByPackage = collect();
        if (! empty($packageIds)) {
            $advancesByPackage = PackageAdvances::with(['user:id,name,phone'])
                ->whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->whereIn('package_id', $packageIds)
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('package_id');
        }

        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'total_price' => $packagerow->total_price,
                'children' => [],
            ];
            $packagesadvances = $advancesByPackage->get($packagerow->id, collect());

            if ($packagesadvances) {
                $balance = 0;
                $count = 0;
                foreach ($packagesadvances as $packagesadvances) {

                    $balance += match ($packagesadvances->cash_flow) {
                        'in' => $packagesadvances->cash_amount,
                        'out' => -$packagesadvances->cash_amount,
                        default => 0,
                    };
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
                            'phone' => GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone),
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->whereIn('location_id', ACL::getUserCentres())->get();
        }

        // Pre-fetch ALL package advances for ALL packages in one query, grouped
        // by package_id. Replaces the previous N+1 (one sum() + one get() per
        // package). Per-package aggregations are now computed in PHP.
        $packageIds = $packageinfo->pluck('id')->all();
        $advancesByPackage = collect();
        if (! empty($packageIds)) {
            $advancesByPackage = PackageAdvances::whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->whereIn('package_id', $packageIds)
                ->get()
                ->groupBy('package_id');
        }

        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
                'location' => $packagerow->location->name,
                'total_price' => $packagerow->total_price,
                'is_refund' => $packagerow->is_refund ? 'Yes' : 'NO',
                'advancebalance' => '',
                'outstandingbalance' => '',
                'usedbalance' => '',
                'unusedbalance' => '',
            ];

            $packageAdvances = $advancesByPackage->get($packagerow->id, collect());

            // Compute the same filtered sum the SQL did:
            //   cash_flow='in' AND is_refund=0 AND is_adjustment=0 AND is_cancel=0 AND appointment_id IS NULL
            // (int) cast handles tinyint columns whether driver returns int or string;
            // never matches NULL because NULL cast becomes 0 only after the !==null guard.
            $advancebalance = $packageAdvances
                ->filter(fn ($a) => $a->cash_flow === 'in'
                    && $a->appointment_id === null
                    && (int) $a->is_refund === 0
                    && (int) $a->is_adjustment === 0
                    && (int) $a->is_cancel === 0)
                ->sum('cash_amount');

            if ($advancebalance !== 0) {

                $packagetrans[$packagerow->id]['advancebalance'] = $advancebalance;

                $outstandingbalance = $packagerow->total_price - $advancebalance;

                $packagetrans[$packagerow->id]['outstandingbalance'] = $outstandingbalance;

                $balance = 0;
                $refund_balance = 0;

                foreach ($packageAdvances as $packagesadvances) {
                    if ($packagesadvances->cash_flow == 'out' & ($packagesadvances->is_refund == 1 || $packagesadvances->is_adjustment == 1)) {
                        $refund_balance += $packagesadvances->cash_amount;
                    }
                    if ($packagesadvances->is_refund == 0 && $packagesadvances->is_adjustment == 0) {
                        $balance += match ($packagesadvances->cash_flow) {
                            'in' => $packagesadvances->cash_amount,
                            'out' => -$packagesadvances->cash_amount,
                            default => 0,
                        };
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $appointmentinfo = Appointments::with(['patient:id,phone,email'])->where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $appointmentinfo = Appointments::with(['patient:id,phone,email'])->whereIn('location_id', ACL::getUserCentres())->get();
        }

        // Pre-fetch ALL package advances for ALL appointments in one query,
        // grouped by appointment_id. Replaces 2 queries per appointment
        // (sum + get) — at 1000 appointments that was 2000+ queries.
        $appointmentIds = $appointmentinfo->pluck('id')->all();
        $advancesByAppointment = collect();
        if (! empty($appointmentIds)) {
            $advancesByAppointment = PackageAdvances::whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->whereIn('appointment_id', $appointmentIds)
                ->get()
                ->groupBy('appointment_id');
        }

        $appointmenttrans = [];
        foreach ($appointmentinfo as $appointment) {
            $apptAdvances = $advancesByAppointment->get($appointment->id, collect());

            // Compute the same filtered sum the SQL did:
            //   cash_flow='in' AND is_refund=0 AND is_adjustment=0 AND is_cancel=0 AND package_id IS NULL
            $advancebalance = $apptAdvances
                ->filter(fn ($a) => $a->cash_flow === 'in'
                    && $a->package_id === null
                    && (int) $a->is_refund === 0
                    && (int) $a->is_adjustment === 0
                    && (int) $a->is_cancel === 0)
                ->sum('cash_amount');

            if ($advancebalance) {
                $appointmenttrans[$appointment->id] = [
                    'id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'patient_name' => $appointment->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($appointment->patient->phone),
                    'email' => $appointment->patient->email,
                    'schedule' => ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
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

                // Reuse the already-fetched batch (was a per-appointment query).
                $balance = 0;
                foreach ($apptAdvances as $packagesadvances) {
                    $balance += match ($packagesadvances->cash_flow) {
                        'in' => $packagesadvances->cash_amount,
                        'out' => -$packagesadvances->cash_amount,
                        default => 0,
                    };
                }
                $appointmenttrans[$appointment->id]['unusedbalance'] = $balance;

                $usedbalance = $advancebalance - $balance;

                $appointmenttrans[$appointment->id]['usedbalance'] = $usedbalance;
            }
        }

        return $appointmenttrans;
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->where($where)->whereIn('location_id', ACL::getUserCentres())->get();
        } else {
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->whereIn('location_id', ACL::getUserCentres())->get();
        }
        $packagetrans = [];
        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'patient_id' => $packagerow->patient_id,
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'patient' => $packagerow->user->name,
                'phone' => GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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

        $package_advances = PackageAdvances::with([
            'appointment' => function ($query) use ($where) {
                $query->where($where);
                $query->whereIn('location_id', ACL::getUserCentres());
            },
            // Eager load nested patient too — was lazy-loaded per row in the
            // foreach below (->appointment->patient->phone / email).
            'appointment.patient:id,phone,email',
        ])->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->where('is_refund', '=', 1)
            ->whereNull('package_id')->get();

        $appointmentrefund = [];
        $appointmentids = [];
        $count = 0;
        if ($package_advances) {
            foreach ($package_advances as $packageadvance) {
                if (! in_array($packageadvance->appointment->id, $appointmentids, true)) {
                    $appointmentrefund[$packageadvance->appointment->id] = [
                        'id' => $packageadvance->appointment->id,
                        'patient_id' => $packageadvance->appointment->patient_id,
                        'patient_name' => $packageadvance->appointment->name,
                        'phone' => GeneralFunctions::prepareNumber4Call($packageadvance->appointment->patient->phone),
                        'email' => $packageadvance->appointment->patient->email,
                        'schedule' => ($packageadvance->appointment->scheduled_date) ? Carbon::parse($packageadvance->appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($packageadvance->appointment->scheduled_time, null)->format('h:i A') : '-',
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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

        $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])->where($where)->whereIn('location_id', ACL::getUserCentres())->get();

        $packagetrans = [];

        foreach ($packageinfo as $packagerow) {
            $packagetrans[$packagerow->id] = [
                'id' => $packagerow->id,
                'name' => $packagerow->name,
                'total_price' => '',
                'refunds' => [],
            ];
            // Plan value MUST match what the View dialog shows (the
            // confirmed-correct source) — getDisplayData computes it as
            // SUM(package_services.price) for plans and SUM(package_bundles
            // .tax_including_price) for memberships. The old SUM(net_amount)
            // here read the PRE-discount price on discounted rows, so the
            // ledger overstated discounted plans by the discount amount
            // (~342M across ~19.3k plans on the prod mirror). Mirroring
            // getDisplayData keeps the report and the dialogs on one number.
            $total_price = $packagerow->plan_type === PlanType::Membership
                ? PackageBundles::where('package_id', '=', $packagerow->id)->sum('tax_including_price')
                : PackageService::where('package_id', '=', $packagerow->id)->sum('price');

            $packagetrans[$packagerow->id]['total_price'] = $total_price;

            $monthStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->addMonth();
            $refunds_info = PackageAdvances::where('created_at', '>=', $monthStart)
                ->where('created_at', '<', $monthEnd)
                ->where([
                    ['package_id', '=', $packagerow->id],
                    ['is_refund', '=', '1'],
                ])->get();

            foreach ($refunds_info as $refunds) {
                $packagetrans[$packagerow->id]['refunds'][$refunds->id] = [
                    'patient_id' => $packagerow->patient_id,
                    'id' => $refunds->id,
                    'patient' => $packagerow->user->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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

        $monthStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonth();
        $package_advances = PackageAdvances::with([
            'appointment' => function ($query) use ($where) {
                $query->where($where);
            },
            // Eager load nested patient too — was lazy-loaded per row in the
            // foreach below (->appointment->patient->phone / email).
            'appointment.patient:id,phone,email',
        ])
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $monthEnd)
            ->where('is_refund', '=', 1)
            ->whereNull('package_id')
            ->get();

        $appointmentrefund = [];
        $appointmentids = [];

        foreach ($package_advances as $refunds) {

            if (! in_array($refunds->appointment->id, $appointmentids, true)) {

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
                    'phone' => GeneralFunctions::prepareNumber4Call($refunds->appointment->patient->phone),
                    'email' => $refunds->appointment->patient->email,
                    'schedule' => ($refunds->appointment->scheduled_date) ? Carbon::parse($refunds->appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($refunds->appointment->scheduled_time, null)->format('h:i A') : '-',
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
                    'phone' => GeneralFunctions::prepareNumber4Call($refunds->appointment->patient->phone),
                    'email' => $refunds->appointment->patient->email,
                    'schedule' => ($refunds->appointment->scheduled_date) ? Carbon::parse($refunds->appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($refunds->appointment->scheduled_time, null)->format('h:i A') : '-',
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
     * Summarized data of discounts given to customer
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function SummarizeddataofDiscountsgiventothecustomer($data, $account_id)
    {
        $where = [];

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])
                ->whereDate('packages.created_at', '>=', $start_date)
                ->whereDate('packages.created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        } else {
            $packageinfo = Packages::with(['user:id,name,phone', 'location:id,name'])
                ->whereDate('packages.created_at', '>=', $start_date)
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
                'phone' => GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
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
}
