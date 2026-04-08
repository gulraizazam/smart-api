<?php

declare(strict_types=1);

namespace App\Reports\Finance;

use App\Enums\AppointmentType;
use Config;
use Carbon\Carbon;
use App\Models\User;
use App\Helpers\ACL;
use App\Models\Packages;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\MachineType;
use App\Models\Appointments;
use App\Models\PackageService;
use App\Models\PackageAdvances;
use App\Helpers\Widgets\AppointmentEditWidget;

class FinanceStaffReport
{
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
            ])->pluck('id')->toArray();
        } else {
            $location_info = Locations::getActiveSortedStaffwisereport(ACL::getUserCentres())->pluck('id')->toArray();
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $doctor_info = User::where([
                ['account_id', '=', $account_id],
                ['active', '=', '1'],
                ['id', '=', $data['doctor_id']],
            ])->pluck('id')->toArray();
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

        // Preload lookup maps to prevent N+1 on join results
        $allLocationIds = $revenue_plan_information->pluck('location_id')->unique();
        $allDoctorIds = $revenue_plan_information->pluck('doctor_id')->unique();
        $locationsMap = Locations::with(['city', 'region'])->whereIn('id', $allLocationIds)->get()->keyBy('id');
        $doctorsMap = User::whereIn('id', $allDoctorIds)->pluck('name', 'id');

        if ($revenue_plan_information) {

            $doctors = [];
            $locations = [];

            foreach ($revenue_plan_information as $revenueinformation) {
                $loc = $locationsMap[$revenueinformation->location_id] ?? null;
                if (!in_array($revenueinformation->location_id, $locations, true) && $loc) {
                    $report[$revenueinformation->location_id] = [
                        'centre' => $loc->name,
                        'city' => $loc->city->name,
                        'region' => $loc->region->name,
                        'doctor_info' => [],
                    ];

                    $locations[] = $revenueinformation->location_id;
                }

                if (!in_array($revenueinformation->doctor_id, $doctors, true) && $loc) {
                    $report[$revenueinformation->location_id]['doctor_info'][$revenueinformation->doctor_id] = [
                        'doctor' => $doctorsMap[$revenueinformation->doctor_id] ?? '',
                        'centre' => $loc->name,
                        'city' => $loc->city->name,
                        'region' => $loc->region->name,
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

            // Preload linked appointments to prevent N+1
            $linkedIds = $revenue_treatment_information->pluck('appointmentlinkid')->unique();
            $linkedAppointments = Appointments::whereIn('id', $linkedIds)->get()->keyBy('id');
            // Merge any new location/doctor IDs into the maps
            $newLocIds = $linkedAppointments->pluck('location_id')->unique()->diff($locationsMap->keys());
            if ($newLocIds->isNotEmpty()) {
                $locationsMap = $locationsMap->merge(Locations::with(['city', 'region'])->whereIn('id', $newLocIds)->get()->keyBy('id'));
            }
            $newDocIds = $linkedAppointments->pluck('doctor_id')->unique()->filter()->diff($doctorsMap->keys());
            if ($newDocIds->isNotEmpty()) {
                $doctorsMap = $doctorsMap->merge(User::whereIn('id', $newDocIds)->pluck('name', 'id'));
            }

            $doctors_2 = $doctors;
            $locations_2 = $locations;
            $count = 0;

            foreach ($revenue_treatment_information as $revenueinformation_treat) {

                $link_doctor_id = $linkedAppointments[$revenueinformation_treat->appointmentlinkid] ?? null;
                if (!$link_doctor_id) {
                    continue;
                }
                $loc = $locationsMap[$link_doctor_id->location_id] ?? null;
                $docName = $doctorsMap[$link_doctor_id->doctor_id] ?? '';

                if (!in_array($link_doctor_id->location_id, $locations_2, true) && $loc) {
                    $report[$link_doctor_id->location_id] = [
                        'centre' => $loc->name,
                        'city' => $loc->city->name,
                        'region' => $loc->region->name,
                        'doctor_info' => [],
                    ];

                    $locations_2[] = $link_doctor_id->location_id;
                } elseif ($loc) {
                    $report[$link_doctor_id->location_id]['centre'] = $loc->name;
                    $report[$link_doctor_id->location_id]['city'] = $loc->city->name;
                    $report[$link_doctor_id->location_id]['region'] = $loc->region->name;
                }

                if (!in_array($link_doctor_id->doctor_id, $doctors_2, true) && $loc) {
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id] = [
                        'doctor' => $docName,
                        'centre' => $loc->name,
                        'city' => $loc->city->name,
                        'region' => $loc->region->name,
                        'doctor_revenue' => [],
                    ];
                    $doctors_2[] = $link_doctor_id->doctor_id;
                } elseif ($loc) {

                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['doctor'] = $docName;
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['centre'] = $loc->name;
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['city'] = $loc->city->name;
                    $report[$link_doctor_id->location_id]['doctor_info'][$link_doctor_id->doctor_id]['region'] = $loc->region->name;
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

                    $loc = $locationsMap[$location_id] ?? null;

                    $report[$location_id]['doctor_info'][$doctor_id] = [
                        'doctor' => $doctorsMap[$doctor_id] ?? '',
                        'centre' => $loc?->name ?? '',
                        'city' => $loc?->city?->name ?? '',
                        'region' => $loc?->region?->name ?? '',
                        'doctor_revenue' => $report[$location_id]['doctor_info'][$doctor_id]['doctor_revenue'],
                    ];
                }
                if (!array_key_exists('doctor_revenue', $report[$location_id]['doctor_info'][$doctor_id]) || empty($report[$location_id]['doctor_info'][$doctor_id]['doctor_revenue'])) {
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

            if (!empty($packagesadvances)) {

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
                                        ['appointment_type_id', '=', AppointmentType::Treatment->value],
                                    ])->first();

                                    $tax_percentage = $appointinfor->location->tax_percentage;

                                    if ($appointinfor) {

                                        $resourceinfor = Resources::find($appointinfor->resource_id);

                                        if (!in_array($resourceinfor->machine_type_id, $machines, true)) {

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

                                        if (!empty($total_consume_service)) {
                                            $total_consume_packageservice_ids = PackageService::whereDate('updated_at', '>=', $start_date)
                                                ->whereDate('updated_at', '<=', $end_date)
                                                ->where([
                                                    ['is_consumed', '=', '1'],
                                                    ['package_id', '=', $packageinfo->id],
                                                ])->whereNotNull('package_id')->pluck('id')->toArray();

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

                                        if (!empty($total_consume_service)) {

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
                                                                ['appointments.appointment_type_id', '=', AppointmentType::Treatment->value],
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

                                                                    if (!in_array($resource_info->machine_type_id, $machines, true)) {

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

                                            if (!in_array($machine_type->id, $machines, true)) {

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

                                    if (!in_array($resourceinfo->machine_type_id, $machines, true)) {

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

                                        if (!in_array($machine_type->id, $machines, true)) {

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

            if (!empty($packagesadvances)) {

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
                                        ['appointment_type_id', '=', AppointmentType::Treatment->value],
                                    ])->first();

                                    if ($appointinfor) {

                                        $resourceinfor = Resources::find($appointinfor->resource_id);

                                        if (!in_array($resourceinfor->machine_type_id, $machines, true)) {

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

                                        if (!empty($total_consume_service)) {
                                            $total_consume_packageservice_ids = PackageService::whereDate('updated_at', '>=', $start_date)
                                                ->whereDate('updated_at', '<=', $end_date)
                                                ->where([
                                                    ['is_consumed', '=', '1'],
                                                    ['package_id', '=', $packageinfo->id],
                                                ])->whereNotNull('package_id')->pluck('id')->toArray();

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

                                        if (!empty($total_consume_service)) {
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
                                                                ['appointments.appointment_type_id', '=', AppointmentType::Treatment->value],
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

                                                                    if (!in_array($resource_info->machine_type_id, $machines, true)) {

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

                                            if (!in_array($machine_type->id, $machines, true)) {

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

                                    if (!in_array($resourceinfo->machine_type_id, $machines, true)) {

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

                                        if (!in_array($machine_type->id, $machines, true)) {

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
}
