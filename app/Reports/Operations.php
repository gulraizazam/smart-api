<?php

namespace App\Reports;

use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Centertarget;
use App\Models\CentertargetMeta;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\Packages;
use App\Models\Regions;
use App\Models\Services;
use App\Models\StaffTargets;
use App\Models\StaffTargetServices;
use App\User;
use Composer\Package\Package;
use Config;
use DB;
use App\Models\Appointments;
use App\Models\Invoices;
use App\Helpers\GeneralFunctions;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\PackageAdvances;
use Auth;

class Operations
{
    /*
     * Center target report
     */
    public static function centertargetreport($data, $account_id)
    {

        $where = array();

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

        $location_target_data = array();

        foreach ($where as $locationid) {

            $centergetlocationdata = CentertargetMeta::where([
                ['year', '=', $data['year']],
                ['month', '=', $data['month']],
                ['location_id', '=', $locationid]
            ])->first();

            if ($centergetlocationdata && $centergetlocationdata->target_amount > 0) {

                $achieved_amount = self::Monthlyachievedamount($start_date, $end_date, $locationid, $account_id);
                $location_target_data[$locationid] = array(
                    'id' => $locationid,
                    'name' => $centergetlocationdata->location->name,
                    'region' => $centergetlocationdata->location->region->name,
                    'city' => $centergetlocationdata->location->city->name,
                    'monthly_target' => $centergetlocationdata->target_amount,
                    'target_achieved' => $achieved_amount,
                    'Pecentage' => ($achieved_amount / $centergetlocationdata->target_amount) * 100
                );
            }
        }

        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location_target_data' => $location_target_data
        );
    }


    /**
     * Company Health Status Report
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function companyHealthReport($data, $account_id)
    {
        $where = array();

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

        $record = Centertarget::where(array('month' => $data['month'], 'year' => $data['year'], 'account_id' => Auth::User()->account_id))->first();

        if (isset($data['completed_working_days'])) {
            if($data['completed_working_days'] <= $data['days_count']){
                $remainingDays = $record->working_days - $data['completed_working_days'];
            } else {
                $remainingDays = 0;
            }
        } else{
            $remainingDays = 0;
        }


        $location_target_data = array();
        $regions = array();

        foreach ($where as $locationid) {

            $centergetlocationdata = CentertargetMeta::where([
                ['year', '=', $data['year']],
                ['month', '=', $data['month']],
                ['location_id', '=', $locationid]
            ])->first();

            if ($centergetlocationdata && $centergetlocationdata->target_amount > 0) {

                $achieved_amount = self::Monthlyachievedamount($start_date, $end_date, $locationid, $account_id);
                $revenue_outstanding = $centergetlocationdata->target_amount - $achieved_amount;

                $location_target_data[$locationid] = array(
                    'region_id' => $centergetlocationdata->location->region->id,
                    'name' => $centergetlocationdata->location->name,
                    'monthly_target' => $centergetlocationdata->target_amount,
                    'target_achieved' => $achieved_amount,
                    'perDayRequired' => ($remainingDays != 0) ? $revenue_outstanding / $remainingDays : $revenue_outstanding / 1,
                    'revenue_outstanding' => $revenue_outstanding,
                    'Pecentage' => ($achieved_amount / $centergetlocationdata->target_amount) * 100,
                );

                if (!in_array($centergetlocationdata->location->region->id, $regions)) {
                    $regions[$centergetlocationdata->location->region->id] = array(
                        'region_id' => $centergetlocationdata->location->region->id,
                        'region_name' => $centergetlocationdata->location->region->name,
                    );
                }
            }
        }

        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location_target_data' => $location_target_data,
            'regions' => $regions,
            'remainingDays' => $remainingDays,
        );
    }

    /**
     * Hihest paid client Report
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function highestpaidclient($data, $filters = array(), $account_id)
    {
        $where = array();
        if (isset($data['region_id']) && $data['region_id']) {
            $where[] = array(
                'region_id',
                '=',
                $data['region_id']
            );
        }
        if (isset($data['city_id']) && $data['city_id']) {
            $where[] = array(
                'city_id',
                '=',
                $data['city_id']
            );
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = array(
                'id',
                '=',
                $data['location_id']
            );
        }
        $where[] = array(
            'account_id',
            '=',
            $account_id
        );
        $where[] = array(
            'active',
            '=',
            '1'
        );
        $where[] = array(
            'slug',
            '=',
            'custom'
        );
        $locationdf = Locations::where($where)->whereIn('id', ACL::getUserCentres())->get()->toArray();

        $locationclient = array();
        $invoicesstatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($locationdf as $location) {
            $locationclient[$location['id']] = array(
                'id' => $location['id'],
                'name' => $location['name'],
                'region' => $filters['regions'][$location['region_id']]->name,
                'city' => $filters['cities'][$location['city_id']]->name,
                'clients' => array()
            );
            $locationclients = Appointments::where('location_id', '=', $location['id'])->groupBy('patient_id')->select('patient_id')->get();
            if (count($locationclients) > 0) {
                $c = 0;
                foreach ($locationclients as $client) {
                    $clientinformation = User::find($client->patient_id);
                    $revenuesum = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                        ->whereYear('invoices.created_at', '=', $data['year'])
                        ->whereMonth('invoices.created_at', '=', $data['month'])
                        ->where([
                            ['invoices.invoice_status_id', '=', $invoicesstatus->id],
                            ['invoices.location_id', '=', $location['id']],
                            ['invoices.patient_id', '=', $client->patient_id]
                        ])->sum('invoice_details.net_amount');
                    $locationclient[$location['id']]['clients'][$c++] = array(
                        'id' => $clientinformation->id,
                        'name' => $clientinformation->name,
                        'email' => $clientinformation->email,
                        'phone' => GeneralFunctions::prepareNumber4Call($clientinformation->phone),
                        'gender' => ($clientinformation->gender == '1') ? 'Male' : 'Female',
                        'dob' => $clientinformation->dob,
                        'Revenue' => $revenuesum
                    );
                }
                for ($j = 0; $j < count($locationclient[$location['id']]['clients']); $j++) {
                    for ($i = 0; $i < count($locationclient[$location['id']]['clients']) - 1; $i++) {

                        if ($locationclient[$location['id']]['clients'][$i]['Revenue'] < $locationclient[$location['id']]['clients'][$i + 1]['Revenue']) {
                            $temp = $locationclient[$location['id']]['clients'][$i + 1];
                            $locationclient[$location['id']]['clients'][$i + 1] = $locationclient[$location['id']]['clients'][$i];
                            $locationclient[$location['id']]['clients'][$i] = $temp;
                        }
                    }
                }

            } else {
                unset($locationclient[$location['id']]);
            }
        }
        return $locationclient;
    }

    /**
     * List of service that can be offer as complimentory
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function listofservicecanoffercomplimentory($data, $account_id)
    {
        $where[] = array(
            'account_id',
            '=',
            $account_id
        );
        $where[] = array(
            'active',
            '=',
            '1'
        );
        $where[] = array(
            'complimentory',
            '=',
            '1'
        );
        $serviceinformation = Services::where($where)->whereYear('created_at', '=', $data['year'])
            ->whereMonth('created_at', '=', $data['month'])->get();

        return $serviceinformation;
    }

    /**
     * List of service that can not be offer as complimentory
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function listofservicecannotoffercomplimentory($data, $account_id)
    {
        $where[] = array(
            'account_id',
            '=',
            $account_id
        );
        $where[] = array(
            'active',
            '=',
            '1'
        );
        $where[] = array(
            'complimentory',
            '=',
            '0'
        );
        $where[] = array(
            'end_node',
            '=',
            '1'
        );
        $serviceinformation = Services::where($where)->whereYear('created_at', '=', $data['year'])->whereMonth('created_at', '=', $data['month'])->get();

        return $serviceinformation;
    }

    /**
     * Conversion report for consultancy
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function conversionreportconsultancy($data, $account_id)
    {
        $reportdata = array();
        $where = array();
        $practit_where = array();

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
            $practit_where[] = array(
                'doctor_id',
                '=',
                $data['user_id']
            );
        }
        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $practit_where[] = array(
                'consultancy_type',
                '=',
                $data['consultancy_type']
            );
        }

        $locations = Locations::whereIn('id', $where)->get();

        $appointmentconsultancy = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $arrivedstatus = AppointmentStatuses::where('is_arrived', '=', '1')->first();

        foreach ($locations as $location) {
            $appointment_booked = Appointments::where([
                ['location_id', '=', $location->id],
                ['appointment_type_id', '=', $appointmentconsultancy->id]
            ])
                ->where($practit_where)
                ->whereDate($data['date_range_by_first'], '>=', $start_date)
                ->whereDate($data['date_range_by_first'], '<=', $end_date)
                ->count();
            if ($appointment_booked > 0) {
                if (!array_key_exists($location->region_id, $reportdata)) {
                    $reportdata[$location->region_id] = array(
                        'region_id' => $location->region_id,
                        'name' => $location->region->name,
                        'location' => array()
                    );
                }
                $appointment_arrvied = Appointments::where([
                    ['base_appointment_status_id', '=', $arrivedstatus->id],
                    ['location_id', '=', $location->id],
                    ['appointment_type_id', '=', $appointmentconsultancy->id]
                ])
                    ->where($practit_where)
                    ->whereDate($data['date_range_by_first'], '>=', $start_date)
                    ->whereDate($data['date_range_by_first'], '<=', $end_date)
                    ->count();
                if ($appointment_arrvied > 0) {

                    $arrival_ratio = ($appointment_arrvied / $appointment_booked) * 100;

                    $reportdata[$location->region_id]['location'][$location->id] = array(
                        'location_id' => $location->id,
                        'location_name' => $location->name,
                        'booked' => $appointment_booked,
                        'arrived' => $appointment_arrvied,
                        'arrival_ratio' => $arrival_ratio,
                        'converted' => '',
                        'conversion_ratio' => '',
                    );

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
     */
    static public function conversion_count($date_range_by, $start_date, $end_date, $appointment_type, $location, $arrived, $practit_where)
    {
        $count = 0;

        $appointments = Appointments::where([
            ['location_id', '=', $location],
            ['appointment_type_id', '=', $appointment_type],
            ['base_appointment_status_id', '=', $arrived],
        ])
            ->where($practit_where)
            ->whereDate($date_range_by, '>=', $start_date)
            ->whereDate($date_range_by, '<=', $end_date)
            ->get();
        foreach ($appointments as $appointment) {
            $Advance_amount_1 = Packages::join('package_advances', 'packages.id', '=', 'package_advances.package_id')
                ->where('packages.appointment_id', '=', $appointment->id)
                ->whereDate('package_advances.created_at', '>=', $start_date)
                ->whereDate('package_advances.created_at', '<=', $end_date)
                ->where('package_advances.cash_amount', '>', 0)
                ->sum('package_advances.cash_amount');
            if ($Advance_amount_1 > 0) {
                $count++;
            } else {
                $Advance_amount_2 = Appointments::join('appointments as appoint_2', 'appointments.id', '=', 'appoint_2.appointment_id')
                    ->join('package_advances', 'appoint_2.id', '=', 'package_advances.appointment_id')
                    ->where('appointments.id', '=', $appointment->id)
                    ->whereYear('package_advances.created_at', '>=', $start_date)
                    ->whereMonth('package_advances.created_at', '<=', $end_date)
                    ->where('package_advances.cash_amount', '>', 0)
                    ->sum('package_advances.cash_amount');

                if ($Advance_amount_2 > 0) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Conversion report for Treatment
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function conversionreporttreatment($data, $account_id)
    {
        $reportdata = array();
        $where = array();
        $practit_where = array();

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
            $practit_where[] = array(
                'doctor_id',
                '=',
                $data['user_id']
            );
        }

        $locations = Locations::whereIn('id', $where)->get();

        $appointmenttreatment = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $arrivedstatus = AppointmentStatuses::where('is_arrived', '=', '1')->first();

        $reportdata = array();

        foreach ($locations as $location) {
            $appointment_booked = Appointments::where([
                ['location_id', '=', $location->id],
                ['appointment_type_id', '=', $appointmenttreatment->id]
            ])
                ->where($practit_where)
                ->whereDate($data['date_range_by'], '>=', $start_date)
                ->whereDate($data['date_range_by'], '<=', $end_date)
                ->count();

            if ($appointment_booked > 0) {
                if (!array_key_exists($location->region_id, $reportdata)) {
                    $reportdata[$location->region_id] = array(
                        'region_id' => $location->region_id,
                        'name' => $location->region->name,
                        'location' => array()
                    );
                }
                $appointment_arrvied = Appointments::where([
                    ['base_appointment_status_id', '=', $arrivedstatus->id],
                    ['location_id', '=', $location->id],
                    ['appointment_type_id', '=', $appointmenttreatment->id]
                ])
                    ->where($practit_where)
                    ->whereDate($data['date_range_by'], '>=', $start_date)
                    ->whereDate($data['date_range_by'], '<=', $end_date)
                    ->count();
                $arrival_ratio = ($appointment_arrvied / $appointment_booked) * 100;
                $reportdata[$location->region_id]['location'][$location->id] = array(
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'booked' => $appointment_booked,
                    'arrived' => $appointment_arrvied,
                    'arrival_ratio' => $arrival_ratio,
                );
            }
        }
        return $reportdata;
    }

    /**
     * DAR Report
     * @param (mixed) $request
     * @return (mixed)
     */
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
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = array(
                'location_id',
                '=',
                $data['location_id']
            );
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = array(
                'appointment_type_id',
                '=',
                $data['appointment_type_id']
            );
        }
        $where[] = array(
            'account_id',
            '=',
            $account_id
        );

        $appointment_info = Appointments::where($where)->whereIn('location_id', ACL::getUserCentres())
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '>=', $start_date)
            ->whereDate('scheduled_date', '<=', $end_date)
            ->orderBy('appointment_type_id', 'asc')
            ->get();
        $appointment_data = array();
        $count = 1;
        foreach ($appointment_info as $appointment) {
            $scheduled_2 = Carbon::createFromFormat('Y-m-d H:i:s', $end_date . ' ' . $appointment->scheduled_time);

            $next_info = Appointments::where([
                [$where],
                ['patient_id', '=', $appointment->patient_id],
                ['id', '!=', $appointment->id]
            ])->select('*', DB::raw("CONCAT(scheduled_date,' ', scheduled_time) AS scheduled"))->whereIn('location_id',ACL::getUserCentres())->whereNotNull('scheduled_date')->orderBy('scheduled_date','asc')->get();

            if(count($next_info) > 0){
                foreach ($next_info as $next) {
                    if (Carbon::parse($next->scheduled)->format('Y-m-d H:i:s') >= Carbon::parse($scheduled_2)->format('Y-m-d H:i:s')) {
                        $next_first_appointment = $next;
                        break;
                    } else {
                        $next_first_appointment = array();
                    }
                }
            } else {
                $next_first_appointment = array();
            }

            $appointment_data[$appointment->id] = array(
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
                'next_appointment_info' => array(),
            );
            if ($next_first_appointment) {
                $appointment_data[$appointment->id]['next_appointment_info'][$count++] = array(
                    'appointment_id' => $next_first_appointment->id,
                    'appointment_type' => $next_first_appointment->appointment_type->name,
                    'appointment_slug' => $next_first_appointment->appointment_type->slug,
                    'schedule_date' => Carbon::parse($next_first_appointment->scheduled_date, null)->format('M j, Y'),
                    'client_name' => $next_first_appointment->patient->name,
                    'doctor_name' => $next_first_appointment->doctor->name,
                    'service' => $next_first_appointment->service->name,
                    'appointment_status_parent' => $next_first_appointment->appointment_status_base->name,
                    'appointment_status_child' => $next_first_appointment->appointment_status->name,
                    'appointment_status_isarrived' => $next_first_appointment->appointment_status->is_arrived,
                );
            } else {
                $appointment_data[$appointment->id]['next_appointment_info'][$count++] = array(
                    'appointment_id' => 'NULL',
                    'appointment_type' => '-',
                    'appointment_slug' => '-',
                    'schedule_date' => '-',
                    'client_name' => '-',
                    'doctor_name' => '-',
                    'service' => '-',
                    'appointment_status_parent' => '-',
                    'appointment_status_child' => '-',
                    'appointment_status_isarrived' => '-',
                );
            }
        }
        return $appointment_data;
    }

    /**
     * Complimentory Treatment Report
     * @param (mixed) $request
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

        $where[] = array(
            'account_id',
            '=',
            $account_id
        );


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

        $appointmentdata = array();

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($appointmentinfo as $appointment) {

            $invoicechecked = Invoices::where([
                ['appointment_id', '=', $appointment->id],
                ['invoice_status_id', '=', $invoicestatus->id]
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
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function dtrreport($data, $account_id, $filters)
    {
        $locaitonwhere = array();

        $where[] = array(
            'account_id',
            '=',
            $account_id
        );
        $where[] = array(
            'month',
            '=',
            $data['month']
        );
        $where[] = array(
            'year',
            '=',
            $data['year']
        );
        if ($data['location_id']) {
            $locaitonwhere[] = array(
                'id',
                '=',
                $data['location_id']
            );
        }
        $locations = Locations::where([
            [$locaitonwhere],
            ['account_id', '=', $account_id],
            ['active', '=', '1']
        ])->whereIn('id', ACL::getUserCentres())->get();

        $location_staff = array();

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($locations as $location) {
            $staff_target = StaffTargets::where([
                [$where],
                ['location_id', '=', $location->id]
            ])->get();
            if (count($staff_target) > 0) {
                $location_staff[$location->id] = array(
                    'id' => $location->id,
                    'location' => $location->name,
                    'region' => $location->region->name,
                    'city' => $location->city->name,
                    'doctors' => array(),
                );
                foreach ($staff_target as $staff) {
                    $staff_target_service = StaffTargetServices::where('staff_target_id', '=', $staff->id)->get();
                    if (count($staff_target_service) > 0) {
                        foreach ($staff_target_service as $staffservice) {
                            $appointmentinformtion = count(Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                                ->where('invoices.invoice_status_id', '=', $invoicestatus->id)
                                ->where([
                                    ['appointments.location_id', '=', $location->id],
                                    ['appointments.service_id', '=', $staffservice->service_id],
                                    ['appointments.doctor_id', '=', $staffservice->staff_id]
                                ])->whereYear('appointments.created_at', '=', $data['year'])
                                ->whereMonth('appointments.created_at', '=', $data['month'])
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

                            $location_staff[$location->id]['doctors'][$staffservice->id] = array(
                                'doctor' => $filters['doctors'][$staffservice->staff_id]->name,
                                'service' => $filters['services'][$staffservice->service_id]->name,
                                'target_service_count' => $staffservice->target_services,
                                'target_service_done' => $appointmentinformtion,
                                'target_complete_ratio' => ($appointmentinformtion / $staffservice->target_services) * 100,
                                'remaining_day' => $re_days,
                            );
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
