<?php

declare(strict_types=1);
namespace App\Reports;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Centertarget;
use App\Models\CentertargetMeta;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Services;
use App\Models\StaffTargets;
use App\Models\StaffTargetServices;
use App\User;
use Auth;
use Carbon\Carbon;
use Config;
use DB;

class Operations
{
    /*
     * Center target report
     */
    public static function centertargetreport($data, $account_id)
    {

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
        if ((isset($data['days_count']) && $data['days_count'])) {
            $end_date = Carbon::createFromDate($data['year'], $data['month'], $data['days_count'])->toDateString();
        } else {
            $end_date = Carbon::createFromDate($data['year'], $data['month'], $data['days_count'])->endOfMonth()->toDateString();
        }
        $start_date = Carbon::createFromDate($data['year'], $data['month'], '1')->toDateString();

        $location_target_data = [];

        foreach ($where as $locationid) {

            $centergetlocationdata = CentertargetMeta::where([
                ['year', '=', $data['year']],
                ['month', '=', $data['month']],
                ['location_id', '=', $locationid],
            ])->first();

            if ($centergetlocationdata && $centergetlocationdata->target_amount > 0) {

                $achieved_amount = self::Monthlyachievedamount($start_date, $end_date, $locationid, $account_id);
                $location_target_data[$locationid] = [
                    'id' => $locationid,
                    'name' => $centergetlocationdata->location->name,
                    'region' => $centergetlocationdata->location->region->name,
                    'city' => $centergetlocationdata->location->city->name,
                    'monthly_target' => $centergetlocationdata->target_amount,
                    'target_achieved' => $achieved_amount,
                    'Pecentage' => ($achieved_amount / $centergetlocationdata->target_amount) * 100,
                ];
            }
        }

        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location_target_data' => $location_target_data,
        ];
    }

    /**
     * Company Health Status Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function companyHealthReport($data, $account_id)
    {
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

        if ((isset($data['days_count']) && $data['days_count'])) {
            $end_date = Carbon::createFromDate($data['year'], $data['month'], $data['days_count'])->toDateString();
        } else {
            $end_date = Carbon::createFromDate($data['year'], $data['month'], $data['days_count'])->endOfMonth()->toDateString();
        }
        $start_date = Carbon::createFromDate($data['year'], $data['month'], '1')->toDateString();

        $record = Centertarget::where(['month' => $data['month'], 'year' => $data['year'], 'account_id' => Auth::user()->account_id])->first();

        if (isset($data['completed_working_days'])) {
            if ($data['completed_working_days'] <= $data['days_count']) {
                $remainingDays = $record->working_days - $data['completed_working_days'];
            } else {
                $remainingDays = 0;
            }
        } else {
            $remainingDays = 0;
        }

        $location_target_data = [];
        $regions = [];

        foreach ($where as $locationid) {

            $centergetlocationdata = CentertargetMeta::where([
                ['year', '=', $data['year']],
                ['month', '=', $data['month']],
                ['location_id', '=', $locationid],
            ])->first();

            if ($centergetlocationdata && $centergetlocationdata->target_amount > 0) {

                $achieved_amount = self::Monthlyachievedamount($start_date, $end_date, $locationid, $account_id);
                $revenue_outstanding = $centergetlocationdata->target_amount - $achieved_amount;

                $location_target_data[$locationid] = [
                    'region_id' => $centergetlocationdata->location->region->id,
                    'name' => $centergetlocationdata->location->name,
                    'monthly_target' => $centergetlocationdata->target_amount,
                    'target_achieved' => $achieved_amount,
                    'perDayRequired' => ($remainingDays != 0) ? $revenue_outstanding / $remainingDays : $revenue_outstanding / 1,
                    'revenue_outstanding' => $revenue_outstanding,
                    'Pecentage' => ($achieved_amount / $centergetlocationdata->target_amount) * 100,
                ];

                if (! in_array($centergetlocationdata->location->region->id, $regions, true)) {
                    $regions[$centergetlocationdata->location->region->id] = [
                        'region_id' => $centergetlocationdata->location->region->id,
                        'region_name' => $centergetlocationdata->location->region->name,
                    ];
                }
            }
        }

        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location_target_data' => $location_target_data,
            'regions' => $regions,
            'remainingDays' => $remainingDays,
        ];
    }

    /**
     * Hihest paid client Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function highestpaidclient($data, $filters, $account_id)
    {
        $where = [];
        if (isset($data['region_id']) && $data['region_id']) {
            $where[] = [
                'region_id',
                '=',
                $data['region_id'],
            ];
        }
        if (isset($data['city_id']) && $data['city_id']) {
            $where[] = [
                'city_id',
                '=',
                $data['city_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'id',
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
            'active',
            '=',
            '1',
        ];
        $where[] = [
            'slug',
            '=',
            'custom',
        ];
        $locationdf = Locations::where($where)->whereIn('id', ACL::getUserCentres())->get()->toArray();

        $locationclient = [];
        $invoicesstatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        // Build month range once (replaces per-row whereYear+whereMonth which can't use index)
        $monthStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonth();

        $locationIds = array_column($locationdf, 'id');

        // Pre-aggregate per-patient revenue across ALL locations in one query.
        // Replaces N+1 (one sum query per patient × per location).
        $revenueRows = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.created_at', '>=', $monthStart)
            ->where('invoices.created_at', '<', $monthEnd)
            ->where('invoices.invoice_status_id', '=', $invoicesstatus->id)
            ->whereIn('invoices.location_id', $locationIds)
            ->select(
                'invoices.location_id',
                'invoices.patient_id',
                DB::raw('SUM(invoice_details.net_amount) as revenue')
            )
            ->groupBy('invoices.location_id', 'invoices.patient_id')
            ->get();

        // Index by [location_id][patient_id] => revenue for O(1) lookup in the loop.
        $revenueByLocPatient = [];
        foreach ($revenueRows as $row) {
            $revenueByLocPatient[$row->location_id][$row->patient_id] = $row->revenue;
        }

        // Pre-fetch all distinct (location_id, patient_id) pairs from appointments in one query.
        $appointmentClients = Appointments::whereIn('location_id', $locationIds)
            ->select('location_id', 'patient_id')
            ->distinct()
            ->get();

        // Group patient IDs by location.
        $patientIdsByLocation = [];
        foreach ($appointmentClients as $apt) {
            $patientIdsByLocation[$apt->location_id][] = $apt->patient_id;
        }

        // Batch-load all unique patient records (replaces N+1 User::find).
        $allPatientIds = array_unique($appointmentClients->pluck('patient_id')->all());
        $patientMap = User::whereIn('id', $allPatientIds)
            ->select('id', 'name', 'email', 'phone', 'gender', 'dob')
            ->get()
            ->keyBy('id');

        foreach ($locationdf as $location) {
            $locationclient[$location['id']] = [
                'id' => $location['id'],
                'name' => $location['name'],
                'region' => $filters['regions'][$location['region_id']]->name,
                'city' => $filters['cities'][$location['city_id']]->name,
                'clients' => [],
            ];

            $clientIds = $patientIdsByLocation[$location['id']] ?? [];
            if (empty($clientIds)) {
                unset($locationclient[$location['id']]);
                continue;
            }

            $clients = [];
            foreach ($clientIds as $patientId) {
                $patient = $patientMap->get($patientId);
                if (!$patient) {
                    continue;
                }
                $clients[] = [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'phone' => GeneralFunctions::prepareNumber4Call($patient->phone),
                    'gender' => ($patient->gender == '1') ? 'Male' : 'Female',
                    'dob' => $patient->dob,
                    'Revenue' => $revenueByLocPatient[$location['id']][$patientId] ?? 0,
                ];
            }

            // Sort by Revenue descending (replaces O(n²) bubble sort).
            usort($clients, fn($a, $b) => $b['Revenue'] <=> $a['Revenue']);

            $locationclient[$location['id']]['clients'] = $clients;
        }

        return $locationclient;
    }

    /**
     * List of service that can be offer as complimentory
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function listofservicecanoffercomplimentory($data, $account_id)
    {
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        $where[] = [
            'active',
            '=',
            '1',
        ];
        $where[] = [
            'complimentory',
            '=',
            '1',
        ];
        $monthStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonth();
        $serviceinformation = Services::where($where)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $monthEnd)
            ->get();

        return $serviceinformation;
    }

    /**
     * List of service that can not be offer as complimentory
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function listofservicecannotoffercomplimentory($data, $account_id)
    {
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        $where[] = [
            'active',
            '=',
            '1',
        ];
        $where[] = [
            'complimentory',
            '=',
            '0',
        ];
        $where[] = [
            'end_node',
            '=',
            '1',
        ];
        $monthStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonth();
        $serviceinformation = Services::where($where)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $monthEnd)
            ->get();

        return $serviceinformation;
    }

    /**
     * Conversion report for consultancy
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function conversionreportconsultancy($data, $account_id)
    {
        $reportdata = [];
        $where = [];
        $practit_where = [];

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
        if (isset($data['user_id']) && $data['user_id']) {
            $practit_where[] = [
                'doctor_id',
                '=',
                $data['user_id'],
            ];
        }
        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $practit_where[] = [
                'consultancy_type',
                '=',
                $data['consultancy_type'],
            ];
        }

        $locations = Locations::whereIn('id', $where)->get();

        $appointmentconsultancy = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $arrivedstatus = AppointmentStatuses::where('is_arrived', '=', '1')->first();

        foreach ($locations as $location) {
            $appointment_booked = Appointments::where([
                ['location_id', '=', $location->id],
                ['appointment_type_id', '=', $appointmentconsultancy->id],
            ])
                ->where($practit_where)
                ->whereDate($data['date_range_by_first'], '>=', $start_date)
                ->whereDate($data['date_range_by_first'], '<=', $end_date)
                ->count();
            if ($appointment_booked > 0) {
                if (! array_key_exists($location->region_id, $reportdata)) {
                    $reportdata[$location->region_id] = [
                        'region_id' => $location->region_id,
                        'name' => $location->region->name,
                        'location' => [],
                    ];
                }
                $appointment_arrvied = Appointments::where([
                    ['base_appointment_status_id', '=', $arrivedstatus->id],
                    ['location_id', '=', $location->id],
                    ['appointment_type_id', '=', $appointmentconsultancy->id],
                ])
                    ->where($practit_where)
                    ->whereDate($data['date_range_by_first'], '>=', $start_date)
                    ->whereDate($data['date_range_by_first'], '<=', $end_date)
                    ->count();
                if ($appointment_arrvied > 0) {

                    $arrival_ratio = ($appointment_arrvied / $appointment_booked) * 100;

                    $reportdata[$location->region_id]['location'][$location->id] = [
                        'location_id' => $location->id,
                        'location_name' => $location->name,
                        'booked' => $appointment_booked,
                        'arrived' => $appointment_arrvied,
                        'arrival_ratio' => $arrival_ratio,
                        'converted' => '',
                        'conversion_ratio' => '',
                    ];

                    $converted_count = self::conversion_count($data['date_range_by_first'], $start_date, $end_date, $appointmentconsultancy->id, $location->id, $arrivedstatus->id, $practit_where);

                    if ($converted_count > 0) {

                        $conversion_ratio = ($converted_count / $appointment_arrvied) * 100;
                        $reportdata[$location->region_id]['location'][$location->id]['conversion_ratio'] = $conversion_ratio;

                    } else {

                        $reportdata[$location->region_id]['location'][$location->id]['conversion_ratio'] = 0;

                    }

                    $reportdata[$location->region_id]['location'][$location->id]['converted'] = $converted_count;
                }
            }
        }

        return $reportdata;
    }

    /*
     * Function that return the count of conversion in Book,GC report
     *
     * Was: 1 appointment fetch + 2 sum queries PER appointment (could be 100s
     * per location). Refactored to 1 appointment fetch + 2 grouped aggregate
     * queries total — runs in O(1) DB roundtrips regardless of appointment count.
     */
    public static function conversion_count($date_range_by, $start_date, $end_date, $appointment_type, $location, $arrived, $practit_where)
    {
        $appointments = Appointments::where([
            ['location_id', '=', $location],
            ['appointment_type_id', '=', $appointment_type],
            ['base_appointment_status_id', '=', $arrived],
        ])
            ->where($practit_where)
            ->whereDate($date_range_by, '>=', $start_date)
            ->whereDate($date_range_by, '<=', $end_date)
            ->select('id')
            ->get();

        if ($appointments->isEmpty()) {
            return 0;
        }

        $appointmentIds = $appointments->pluck('id')->all();

        // Direct: appointments that have payments via Packages.appointment_id
        // (was per-row sum query in the loop)
        $directSums = Packages::join('package_advances', 'packages.id', '=', 'package_advances.package_id')
            ->whereIn('packages.appointment_id', $appointmentIds)
            ->whereDate('package_advances.created_at', '>=', $start_date)
            ->whereDate('package_advances.created_at', '<=', $end_date)
            ->where('package_advances.cash_amount', '>', 0)
            ->select('packages.appointment_id', DB::raw('SUM(package_advances.cash_amount) as total'))
            ->groupBy('packages.appointment_id')
            ->pluck('total', 'packages.appointment_id');

        // Indirect: appointments whose child appointments have payments
        // (was per-row sum query in the loop)
        $childSums = Appointments::from('appointments as parent_appt')
            ->join('appointments as appoint_2', 'parent_appt.id', '=', 'appoint_2.appointment_id')
            ->join('package_advances', 'appoint_2.id', '=', 'package_advances.appointment_id')
            ->whereIn('parent_appt.id', $appointmentIds)
            ->whereDate('package_advances.created_at', '>=', $start_date)
            ->whereDate('package_advances.created_at', '<=', $end_date)
            ->where('package_advances.cash_amount', '>', 0)
            ->select('parent_appt.id as parent_id', DB::raw('SUM(package_advances.cash_amount) as total'))
            ->groupBy('parent_appt.id')
            ->pluck('total', 'parent_id');

        $count = 0;
        foreach ($appointments as $appointment) {
            $direct = (float) ($directSums[$appointment->id] ?? 0);
            if ($direct > 0) {
                $count++;
                continue;
            }
            $child = (float) ($childSums[$appointment->id] ?? 0);
            if ($child > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Conversion report for Treatment
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function conversionreporttreatment($data, $account_id)
    {
        $reportdata = [];
        $where = [];
        $practit_where = [];

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
        if (isset($data['user_id']) && $data['user_id']) {
            $practit_where[] = [
                'doctor_id',
                '=',
                $data['user_id'],
            ];
        }

        $locations = Locations::whereIn('id', $where)->get();

        $appointmenttreatment = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $arrivedstatus = AppointmentStatuses::where('is_arrived', '=', '1')->first();

        $reportdata = [];

        foreach ($locations as $location) {
            $appointment_booked = Appointments::where([
                ['location_id', '=', $location->id],
                ['appointment_type_id', '=', $appointmenttreatment->id],
            ])
                ->where($practit_where)
                ->whereDate($data['date_range_by'], '>=', $start_date)
                ->whereDate($data['date_range_by'], '<=', $end_date)
                ->count();

            if ($appointment_booked > 0) {
                if (! array_key_exists($location->region_id, $reportdata)) {
                    $reportdata[$location->region_id] = [
                        'region_id' => $location->region_id,
                        'name' => $location->region->name,
                        'location' => [],
                    ];
                }
                $appointment_arrvied = Appointments::where([
                    ['base_appointment_status_id', '=', $arrivedstatus->id],
                    ['location_id', '=', $location->id],
                    ['appointment_type_id', '=', $appointmenttreatment->id],
                ])
                    ->where($practit_where)
                    ->whereDate($data['date_range_by'], '>=', $start_date)
                    ->whereDate($data['date_range_by'], '<=', $end_date)
                    ->count();
                $arrival_ratio = ($appointment_arrvied / $appointment_booked) * 100;
                $reportdata[$location->region_id]['location'][$location->id] = [
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'booked' => $appointment_booked,
                    'arrived' => $appointment_arrvied,
                    'arrival_ratio' => $arrival_ratio,
                ];
            }
        }

        return $reportdata;
    }

    /**
     * DAR Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    /** @deprecated Called via App\Services\Reports\Operations\DarReport. */
    public static function dar_report($data, $account_id)
    {
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];

        $location_ids = GeneralFunctions::getLocationIds($data['location_id']);

        // Eager load every relationship the foreach below touches.
        // Without this, each row triggers 7 lazy-load queries (patient,
        // appointment_type, doctor, service, appointment_status_base,
        // appointment_status, location). At 1000 rows that's ~7000 queries.
        $appointment_info = Appointments::with([
                'patient:id,name',
                'appointment_type:id,name,slug',
                'doctor:id,name',
                'service:id,name',
                'appointment_status_base:id,name',
                'appointment_status:id,name,is_arrived,is_converted',
                'location:id,name',
            ])
            ->where($where)
            ->whereNotNull('scheduled_date')
            ->when($location_ids, fn ($q) => $q->whereIn('location_id', $location_ids))
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->orderBy('appointment_type_id', 'asc')
            ->get();

        $walkinAppointment = Appointments::select('location_id', DB::raw('count(id) as count'))
            ->where($where)
            ->whereNotNull('scheduled_date')
            ->whereIn('created_by', GeneralFunctions::getFDM($location_ids))
            ->when($location_ids, fn ($q) => $q->whereIn('location_id', $location_ids))
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->groupBy('location_id')
            ->pluck('count', 'location_id');

        $appointment_data = [];
        $locationData = [];
        foreach ($appointment_info as $appointment) {

            $appointment_data[$appointment->id] = [
                'schedule_date' => Carbon::parse($appointment->scheduled_date, null)->format('M j, Y'),
                'id' => $appointment->patient_id,
                'client_name' => $appointment->patient->name,
                'appointment_type' => $appointment->appointment_type->name,
                'appointment_slug' => $appointment->appointment_type->slug,
                'doctor_name' => $appointment->doctor->name,
                'service' => $appointment->service->name,
                'appointment_status_parent' => $appointment->appointment_status_base->name,
                'appointment_status_child' => $appointment->appointment_status->name,
                'appointment_status_isarrived' => $appointment->appointment_status->is_arrived,
            ];

            $count[$appointment->location->id][] = 1;

            $locationData[$appointment->location->name]['consultantbooked'] = count($count[$appointment->location->id]);

            if ($appointment->appointment_type->slug == 'consultancy' && ($appointment->appointment_status->is_arrived == '1' || $appointment->appointment_status->is_converted == '1')) {
                $arrived_count[$appointment->location->id][] = 1;
                $locationData[$appointment->location->name]['consultantarrived'] = count($arrived_count[$appointment->location->id]);
                $locationData[$appointment->location->name]['walking'] = $walkinAppointment[$appointment->location_id] ?? 0;
            }

        }

        return [$appointment_data, $locationData];
    }

    /** @deprecated Called via App\Services\Reports\Operations\WalkingReport. */
    public static function walking_report($data, $account_id)
    {
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];

        $location_ids = GeneralFunctions::getLocationIds($data['location_id']);

        // Eager load every relationship the foreach below touches to avoid
        // 7 lazy-load queries per row (patient/type/doctor/service/status_base/
        // status/location).
        $appointment_info = Appointments::with([
                'patient:id,name',
                'appointment_type:id,name,slug',
                'doctor:id,name',
                'service:id,name',
                'appointment_status_base:id,name',
                'appointment_status:id,name,is_arrived,is_converted',
                'location:id,name',
            ])
            ->where($where)
            ->when($location_ids, fn ($q) => $q->whereIn('location_id', $location_ids))
            ->whereIn('created_by', GeneralFunctions::getFDM($location_ids))
            ->whereIn('location_id', ACL::getUserCentres())
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->orderBy('appointment_type_id', 'asc')
            ->get();

        $walkinAppointment = Appointments::select('location_id', DB::raw('count(id) as walkin'))
            ->where($where)
            ->whereNotNull('scheduled_date')
            ->whereIn('created_by', GeneralFunctions::getFDM($location_ids))
            ->when($location_ids, fn ($q) => $q->whereIn('location_id', $location_ids))
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->groupBy('location_id')
            ->pluck('walkin', 'location_id');

        $appointment_data = [];
        $locationData = [];
        foreach ($appointment_info as $appointment) {

            $appointment_data[$appointment->id] = [
                'schedule_date' => Carbon::parse($appointment->scheduled_date, null)->format('M j, Y'),
                'id' => $appointment->patient_id,
                'client_name' => $appointment->patient->name,
                'appointment_type' => $appointment->appointment_type->name,
                'appointment_slug' => $appointment->appointment_type->slug,
                'doctor_name' => $appointment->doctor->name,
                'service' => $appointment->service->name,
                'appointment_status_parent' => $appointment->appointment_status_base->name,
                'appointment_status_child' => $appointment->appointment_status->name,
                'appointment_status_isarrived' => $appointment->appointment_status->is_arrived,
            ];

            $locationData[$appointment->location->name]['walkin'] = $walkinAppointment[$appointment->location_id] ?? 0;

        }

        return [
            $appointment_data,
            $locationData,
        ];
    }

    /** @deprecated Called via App\Services\Reports\Operations\AgentReport. */
    public static function agent_report($data, $account_id)
    {
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];

        if (isset($data['agent_id']) && $data['agent_id'] != '') {
            $agent_id = [$data['agent_id']];
        } else {
            $agent_id = GeneralFunctions::getCSR();
        }

        $location_ids = GeneralFunctions::getLocationIds($data['location_id']);

        $appointment_info = Appointments::where($where)->whereIn('created_by', $agent_id)
            ->whereIn('location_id', ACL::getUserCentres())
            ->whereNotNull('scheduled_date')
            ->when($location_ids, fn ($q) => $q->whereIn('location_id', $location_ids))
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->orderBy('appointment_type_id', 'asc')
            ->get();

        $appointment_data = [];
        $locationData = [];
        foreach ($appointment_info as $appointment) {

            $appointment_data[$appointment->id] = [
                'schedule_date' => Carbon::parse($appointment->scheduled_date, null)->format('M j, Y'),
                'id' => $appointment->patient_id,
                'client_name' => $appointment->patient->name,
                'appointment_type' => $appointment->appointment_type->name,
                'appointment_slug' => $appointment->appointment_type->slug,
                'doctor_name' => $appointment->doctor->name,
                'service' => $appointment->service->name,
                'appointment_status_parent' => $appointment->appointment_status_base->name,
                'appointment_status_child' => $appointment->appointment_status->name,
                'appointment_status_isarrived' => $appointment->appointment_status->is_arrived,
            ];

            $count[$appointment->location->id][] = 1;

            $locationData[$appointment->location->name]['consultantbooked'] = count($count[$appointment->location->id]);

            if ($appointment->appointment_type->slug == 'consultancy' && ($appointment->appointment_status->is_arrived == '1' || $appointment->appointment_status->is_converted == '1')) {
                $arrived_count[$appointment->location->id][] = 1;
                $locationData[$appointment->location->name]['consultantarrived'] = count($arrived_count[$appointment->location->id]);
            }

        }

        return [$appointment_data, $locationData];
    }

    /**
     * Complimentory Treatment Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function complimentoryreport($data, $account_id)
    {

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];

        if (isset($data['location_id']) && $data['location_id']) {
            $appointmentinfo = Services::join('appointments', 'services.id', '=', 'appointments.service_id')
                ->where('services.complimentory', '=', '1')
                ->whereDate('appointments.created_at', '>=', $start_date)
                ->whereDate('appointments.created_at', '<=', $end_date)
                ->where('appointments.location_id', $data['location_id'])
                ->select('appointments.*', 'services.name as servicename')
                ->get();
        } else {
            $appointmentinfo = Services::join('appointments', 'services.id', '=', 'appointments.service_id')
                ->where('services.complimentory', '=', '1')
                ->whereDate('appointments.created_at', '>=', $start_date)
                ->whereDate('appointments.created_at', '<=', $end_date)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('appointments.*', 'services.name as servicename')
                ->get();
        }

        $appointmentdata = [];

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($appointmentinfo as $appointment) {

            $invoicechecked = Invoices::where([
                ['appointment_id', '=', $appointment->id],
                ['invoice_status_id', '=', $invoicestatus->id],
            ])->first();

            if ($invoicechecked) {
                $appointment['invoices'] = 'Paid';
            } else {
                $appointment['invoices'] = 'Not Paid';
            }
        }

        return $appointmentinfo;
    }

    /**
     * DTR report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function dtrreport($data, $account_id, $filters)
    {
        $locaitonwhere = [];

        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        $where[] = [
            'month',
            '=',
            $data['month'],
        ];
        $where[] = [
            'year',
            '=',
            $data['year'],
        ];
        if ($data['location_id']) {
            $locaitonwhere[] = [
                'id',
                '=',
                $data['location_id'],
            ];
        }
        $locations = Locations::where([
            [$locaitonwhere],
            ['account_id', '=', $account_id],
            ['active', '=', '1'],
        ])->whereIn('id', ACL::getUserCentres())->get();

        $location_staff = [];

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($locations as $location) {
            $staff_target = StaffTargets::where([
                [$where],
                ['location_id', '=', $location->id],
            ])->get();
            if (!empty($staff_target)) {
                $location_staff[$location->id] = [
                    'id' => $location->id,
                    'location' => $location->name,
                    'region' => $location->region->name,
                    'city' => $location->city->name,
                    'doctors' => [],
                ];
                $monthStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
                $monthEnd = $monthStart->copy()->addMonth();
                foreach ($staff_target as $staff) {
                    $staff_target_service = StaffTargetServices::where('staff_target_id', '=', $staff->id)->get();
                    if (!empty($staff_target_service)) {
                        foreach ($staff_target_service as $staffservice) {
                            $appointmentinformtion = count(Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                                ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                                ->where([
                                    ['appointments.location_id', '=', $location->id],
                                    ['appointments.service_id', '=', $staffservice->service_id],
                                    ['appointments.doctor_id', '=', $staffservice->staff_id],
                                ])->where('appointments.created_at', '>=', $monthStart)
                                ->where('appointments.created_at', '<', $monthEnd)
                                ->get());
                            $d = cal_days_in_month(CAL_GREGORIAN, $data['month'], $data['year']);

                            $date = \Carbon\Carbon::now();
                            $curentmonth = $date->month;
                            $currentyear = $date->year;

                            if ($curentmonth == $data['month'] && $currentyear == $data['year']) {
                                $currentday = $date->day;
                                $re_days = $d - $currentday;
                            } else {
                                $re_days = 0;
                            }

                            $location_staff[$location->id]['doctors'][$staffservice->id] = [
                                'doctor' => $filters['doctors'][$staffservice->staff_id]->name,
                                'service' => $filters['services'][$staffservice->service_id]->name,
                                'target_service_count' => $staffservice->target_services,
                                'target_service_done' => $appointmentinformtion,
                                'target_complete_ratio' => ($appointmentinformtion / $staffservice->target_services) * 100,
                                'remaining_day' => $re_days,
                            ];
                        }
                    }
                }
            }
        }

        return $location_staff;
    }

    /*
     * Get he center wise collection
     */
    public static function Monthlyachievedamount($start_date, $end_date, $locationid, $account_id)
    {

        $packagesadvances = PackageAdvances::whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->where([
                ['account_id', '=', $account_id],
                ['location_id', '=', $locationid],
            ])->get();

        $location_single_info = Locations::find($locationid);

        if ($packagesadvances) {
            $balance = 0;
            $total_balance = 0;
            $total_revenue_in = 0;
            $total_refund_out = 0;

            foreach ($packagesadvances as $packagesadvance) {
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
                    $balance += match ($packagesadvance->cash_flow) {
                        'in' => $packagesadvance->cash_amount,
                        'out' => -$packagesadvance->cash_amount,
                        default => 0,
                    };
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
                            $revenue_in = $packagesadvance->cash_amount;
                            $refund_out = 0;
                        } else {
                            $revenue_in = 0;
                            $refund_out = $packagesadvance->cash_amount;
                        }

                        if ($revenue_in) {
                            $total_revenue_in += $revenue_in;
                        }
                        if ($refund_out) {
                            $total_refund_out += $refund_out;
                        }
                    }
                }
            }
        }
        $achieved = $total_revenue_in - $total_refund_out;

        return $achieved;
    }
}
