<?php

namespace App\Reports;

use App\Helpers\ACL;
use App\Helpers\NodesTree;
use App\Models\Appointments;
use App\Models\Bundles;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Services;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Invoices
{
    /**
     * Generate Account sales Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getAccountSalesReport($data)
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
            $where['invoices.patient_id'] = $data['patient_id'];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where['invoices.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['invoice_details.service_id'] = $data['service_id'];
        }

        if (isset($data['discount_id']) && $data['discount_id'] && $data['discount_id'] != 0) {
            $where['invoice_details.discount_id'] = $data['discount_id'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['invoices.created_by'] = $data['user_id'];
        }
        $where['invoices.invoice_status_id'] = '3';

        $appointments = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereDate('invoices.created_at', '>=', $start_date)
            ->whereDate('invoices.created_at', '<=', $end_date);

        if (count($where)) {
            $appointments = $appointments->where($where);
        }

        if (isset($data['discount_id']) && $data['discount_id'] !== null) {
            $appointments = $appointments->whereNotNull('discount_name')
                ->whereNotNull('discount_type')
                ->whereNotNull('discount_price');
        }

        $appointments = $appointments->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('invoice_details.*', 'invoices.*')
            ->get();

        return $appointments;
    }

    /**
     * Generate Daily Employee Stats Summary
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getDailyEmployeeStatsSummary($data, $filters = [])
    {

        $where = [];
        $flag = 0;
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['invoices.patient_id'] = $data['patient_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['invoices.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $service_data = Services::where('id', '=', $data['service_id'])->where('parent_id', '=', 0)->first();
            if ($service_data) {
                $service_data_ids = Services::where('parent_id', '=', $data['service_id'])->pluck('id')->toArray();
                $flag = 1;
            } else {
                $where['invoice_details.service_id'] = $data['service_id'];
                $flag = 0;
                $service_data_ids = [];
            }

        }

        $where['invoices.invoice_status_id'] = '3';
        if ($flag == 1) {
            $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereDate('invoices.created_at', '>=', $start_date)
                ->whereDate('invoices.created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('invoice_details.service_id', $service_data_ids)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('invoice_details.service_id', DB::raw('SUM(invoice_details.tax_including_price) AS total_price'))
                ->groupBy('invoice_details.service_id')
                ->get();
        } else {
            $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereDate('invoices.created_at', '>=', $start_date)
                ->whereDate('invoices.created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('invoice_details.service_id', DB::raw('SUM(invoice_details.tax_including_price) AS total_price'))
                ->groupBy('invoice_details.service_id')
                ->get();
        }
        $serviceArr = [];
        foreach ($records as $record) {
            if (! in_array($record->id, $service_data_ids)) {
                array_push($serviceArr, $record->service_id);
            }

        }

        $reportdata = [];
        if (isset($data['service_id']) && $data['service_id']) {
            foreach ($filters['services'] as $service) {
                if ($service->id == $data['service_id'] || in_array($service->id, $serviceArr)) {
                    $reportdata[$service->id] = [
                        'id' => $service->id,
                        'name' => $service->name,
                        'amount' => 0.00,
                    ];
                }
                if ($records) {
                    foreach ($records as $record) {
                        if ($service->id == $record->service_id) {
                            $reportdata[$service->id]['amount'] = $record->total_price;
                        }
                    }
                }
            }
        } else {
            foreach ($filters['services'] as $service) {
                $reportdata[$service->id] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'amount' => 0.00,
                ];
                if ($records) {
                    foreach ($records as $record) {
                        if ($service->id == $record->service_id) {
                            $reportdata[$service->id]['amount'] = $record->total_price;
                        }
                    }
                }
            }

        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            if ($data['appointment_type_id'] == Config::get('constants.appointment_type_consultancy')) {
                foreach ($reportdata as $key => $reportd) {
                    $serivceinfo = Services::find($reportd['id']);
                    if ($serivceinfo->end_node == '0') {
                        continue;
                    } else {
                        unset($reportdata[$key]);
                    }
                }
            } else {
                foreach ($reportdata as $key => $reportd) {
                    $serivceinfo = Services::find($reportd['id']);
                    if ($serivceinfo->end_node == '1') {
                        continue;
                    } else {
                        unset($reportdata[$key]);
                    }
                }
            }

        }

        return $reportdata;
    }

    /**
     * Generate Daily Employee Stats
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getDailyEmployeeStats($data, $filters = [])
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
            $where['invoices.patient_id'] = $data['patient_id'];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where['invoices.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['invoice_details.service_id'] = $data['service_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['invoices.doctor_id'] = $data['doctor_id'];
        }

        $where['invoices.invoice_status_id'] = '3';
        if (count($where)) {
            $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereDate('invoices.created_at', '>=', $start_date)
                ->whereDate('invoices.created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('invoice_details.service_id', 'invoices.doctor_id', DB::raw('SUM(invoice_details.tax_including_price) AS total_price'))
                ->groupBy('invoice_details.service_id', 'invoices.doctor_id')
                ->get();
        } else {
            $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereDate('invoices.created_at', '>=', $start_date)
                ->whereDate('invoices.created_at', '<=', $end_date)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('invoice_details.service_id', 'invoices.doctor_id', DB::raw('SUM(invoice_details.tax_including_price) AS total_price'))
                ->groupBy('invoice_details.service_id', 'invoices.doctor_id')
                ->get();
        }

        $reportdata = [];
        $servicedata = [];
        $doctor_Array = [];

        if (count($records)) {

            foreach ($records as $record) {

                $doctor = User::find($record->doctor_id, ['id', 'name']);
                if($doctor){
                    if (! in_array($record->doctor_id, $doctor_Array)) {

                        $reportdata[$doctor->id] = [
                            'id' => $doctor->id,
                            'name' => $doctor->name,
                            'records' => [],
                        ];
    
                        $doctor_Array[] = $doctor->id;
                    }
                }
                

                $service = Services::find($record->service_id, ['id', 'name']);

                if (! in_array($record->service_id, $servicedata) && $record->total_price > 0) {
                    $reportdata[$doctor->id]['records'][$service->id] = [
                        'id' => $service->id,
                        'name' => $service->name,
                        'amount' => $record->total_price,
                    ];

                    $servicedata[] = $service->id;

                }
                if (! array_key_exists($record->service_id, $reportdata[$record->doctor_id]['records'])) {

                    $reportdata[$doctor->id]['records'][$service->id] = [
                        'id' => $service->id,
                        'name' => $service->name,
                        'amount' => $record->total_price,
                    ];
                }
            }
        }

        return $reportdata;
    }

    /**
     * Generate Sales By service category
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getSalesbyServiceCategory($data, $filters = [])
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
            $where['invoices.patient_id'] = $data['patient_id'];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where['invoices.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['invoice_details.service_id'] = $data['service_id'];
        }

        $where['invoices.invoice_status_id'] = '3';

        if (count($where)) {
            $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereDate('invoices.created_at', '>=', $start_date)
                ->whereDate('invoices.created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('invoice_details.service_id', 'invoices.*', 'invoice_details.tax_including_price')
                ->get();
        } else {
            $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereDate('invoices.created_at', '>=', $start_date)
                ->whereDate('invoices.created_at', '<=', $end_date)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('invoice_details.service_id', 'invoices.*', 'invoice_details.tax_including_price')
                ->get();
        }
        $reportdata = [];
        $servicedata = [];

        if (isset($filters['servicesheads'])) {
            foreach ($filters['servicesheads'] as $serviceshead) {
                $reportdata[$serviceshead->id] = [
                    'id' => $serviceshead->id,
                    'name' => $serviceshead->name,
                    'records' => [],
                ];

                $services = self::getNodeServices($serviceshead->id, Auth::User()->account_id, true, true);

                if (count($services) > 0) {
                    if (isset($data['service_id']) && $data['service_id']) {
                        $serviceinfo = Services::find($data['service_id']);
                        foreach ($services as $se) {
                            if ($serviceinfo->name == $se) {
                                $service = Services::where('name', '=', $se)->first();
                                $qty = 0;
                                $total_amount = 0;
                                foreach ($records as $recordQty) {
                                    if ($service->id == $recordQty->service_id) {
                                        $qty++;
                                        $total_amount += $recordQty->tax_including_price;
                                    }
                                }
                                $servicedata[$service->id] = [
                                    'name' => $service->name,
                                    'qty' => $qty,
                                    'amount' => $total_amount,
                                ];
                            }
                        }
                        $reportdata[$serviceshead->id]['records'] = $servicedata;
                        $servicedata = [];
                    } else {
                        foreach ($services as $se) {
                            $service = Services::where('name', '=', $se)->first();
                            $qty = 0;
                            $total_amount = 0;
                            foreach ($records as $recordQty) {
                                if ($service->id == $recordQty->service_id) {
                                    $qty++;
                                    $total_amount += $recordQty->tax_including_price;
                                }
                            }
                            $servicedata[$service->id] = [
                                'name' => $service->name,
                                'qty' => $qty,
                                'amount' => $total_amount,
                            ];
                        }
                        $reportdata[$serviceshead->id]['records'] = $servicedata;
                        $servicedata = [];
                    }

                }
            }
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            if ($data['appointment_type_id'] == Config::get('constants.appointment_type_consultancy')) {
                foreach ($reportdata as $reportd) {
                    $temparray = $reportd['records'];
                    foreach ($temparray as $key => $report) {
                        $serivceinfo = Services::find($key);
                        if ($serivceinfo->end_node == '0') {
                            continue;
                        } else {
                            unset($temparray[$key]);
                        }
                    }
                    $reportdata[$reportd['id']]['records'] = $temparray;
                }
            } else {
                foreach ($reportdata as $reportd) {
                    $temparray = $reportd['records'];
                    foreach ($temparray as $key => $report) {
                        $serivceinfo = Services::find($key);
                        if ($serivceinfo->end_node == '1') {
                            continue;
                        } else {
                            unset($temparray[$key]);
                        }
                    }
                    $reportdata[$reportd['id']]['records'] = $temparray;
                }
            }

        }

        return $reportdata;
    }

    /**
     * Get Node Services
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function getNodeServices($serviceId, $account_id, $drop_down = false, $remove_spaces = false)
    {

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(($serviceId) ? $serviceId : 0, $account_id);
        $parentGroups->toList($parentGroups, -1);
        $services = $parentGroups->nodeList;

        $nodeList = [];

        if (count($services)) {
            foreach ($services as $key => $service) {
                if ($drop_down) {
                    if ($remove_spaces) {
                        $nodeList[$key] = str_replace('&nbsp;', '', trim($service['name']));
                    } else {
                        $nodeList[$key] = trim($service['name']);
                    }
                } else {
                    if ($remove_spaces) {
                        $service['name'] = str_replace('&nbsp;', '', trim($service['name']));
                    }
                    $nodeList[$key] = $service;
                }
            }
        }

        return $nodeList;
    }

    /**
     * Generate Discount Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getdiscountReport($data)
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
            $where['invoices.patient_id'] = $data['patient_id'];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where['invoices.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['invoice_details.service_id'] = $data['service_id'];
        }

        if (isset($data['discount_id']) && $data['discount_id'] && $data['discount_id'] != 0) {
            $where['invoice_details.discount_id'] = $data['discount_id'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['invoices.created_by'] = $data['user_id'];
        }

        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $where['invoices.invoice_status_id'] = $invoicestatus->id;

        $appointments = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereDate('invoices.created_at', '>=', $start_date)
            ->whereDate('invoices.created_at', '<=', $end_date);

        if (count($where)) {
            $appointments = $appointments->where($where);
        }

        if (isset($data['discount_id']) && $data['discount_id'] !== null) {
            $appointments = $appointments->whereNotNull('discount_name')
                ->whereNotNull('discount_type')
                ->whereNotNull('discount_price');
        }

        $appointments = $appointments->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('invoice_details.*', 'invoices.*')
            ->get();

        return $appointments;
    }

    /*
     * Collection by service
     */
    public static function collectionbyservice($data, $account_id)
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

                if ($packagesadvances) {

                    $packageids = [];
                    $count = 0;
                    $machines = [];
                    $bundles = [];

                    foreach ($packagesadvances as $key => $packagesadvance) {
                        if (
                            (
                                $packagesadvance->cash_flow == 'in' &&
                                $packagesadvance->cash_amount != '0' &&
                                $packagesadvance->is_adjustment == '0' &&
                                $packagesadvance->is_tax == '0' &&
                                $packagesadvance->is_cancel == '0'
                            )
                            ||
                            (
                                $packagesadvance->cash_flow == 'out' &&
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

                                        $serviceinfor = Services::find($appointinfor->service_id);

                                        if (! in_array($serviceinfor->id, $bundles)) {

                                            $serviceinfor = Services::find($appointinfor->service_id);

                                            $report_data[$serviceinfor->id] = [
                                                'package_bundle_id' => 0,
                                                'id' => $serviceinfor->id,
                                                'name' => $serviceinfor->name,
                                                'amount' => 0,
                                            ];
                                            $bundles[] = $serviceinfor->id;
                                        }
                                        $report_data[$serviceinfor->id]['amount'] += $packagesadvance->cash_amount;
                                    }
                                } else {
                                    if (! in_array($packagesadvance->package_id, $packageids, true)) {

                                        $packageids[] = $packagesadvance->package_id;

                                        $packageinfo = Packages::find($packagesadvance->package_id);
                                        if ($packageinfo) {
                                            $total_consume_service = PackageService::whereDate('package_services.updated_at', '>=', $start_date)
                                                ->whereDate('package_services.updated_at', '<=', $end_date)
                                                ->where([
                                                    ['is_consumed', '=', '1'],
                                                    ['package_id', '=', $packageinfo->id],
                                                ])->whereNotNull('package_services.package_id')->get();
                                            if (count($total_consume_service) > 0) {
                                                $total_consume_packageservice_ids = PackageService::whereDate('updated_at', '>=', $start_date)
                                                    ->whereDate('updated_at', '<=', $end_date)
                                                    ->where([
                                                        ['is_consumed', '=', '1'],
                                                        ['package_id', '=', $packageinfo->id],
                                                    ])->whereNotNull('package_id')->get()->pluck('id')->toArray();

                                                $total_consume = PackageService::whereDate('updated_at', '>=', $start_date)
                                                    ->whereDate('updated_at', '<=', $end_date)
                                                    ->where([
                                                        ['is_consumed', '=', '1'],
                                                        ['package_id', '=', $packageinfo->id],
                                                    ])->whereNotNull('package_id')->sum('tax_including_price');
                                            } else {
                                                $total_consume_packageservice_ids = [];
                                                $total_consume = 0;
                                            }

                                            if (count($total_consume_service) > 0) {

                                                $package_services = PackageService::whereIn('id', $total_consume_packageservice_ids)
                                                    ->where('package_id', '=', $packageinfo->id)->whereNotNull('package_id')->get();

                                                foreach ($package_services as $package_service) {

                                                    $packagebundle = PackageBundles::find($package_service->package_bundle_id);

                                                    $bundle_info = Bundles::find($packagebundle->bundle_id);

                                                    $divide_amount = $package_service->tax_including_price;

                                                    if (! in_array($bundle_info->id, $bundles)) {

                                                        $report_data[$bundle_info->id] = [
                                                            'package_bundle_id' => $package_service->package_bundle_id,
                                                            'id' => $bundle_info->id,
                                                            'name' => $bundle_info->name,
                                                            'amount' => 0,
                                                        ];
                                                        $bundles[] = $bundle_info->id;
                                                    }
                                                    $report_data[$bundle_info->id]['amount'] += $divide_amount;
                                                }
                                            }
                                            $cash_receive = PackageAdvances::whereDate('created_at', '>=', $start_date)
                                                ->whereDate('created_at', '<=', $end_date)
                                                ->where([
                                                    ['package_id', '=', $packageinfo->id],
                                                    ['is_cancel', '=', '0'],
                                                    ['cash_flow', '=', 'in'],
                                                ])->sum('cash_amount');

                                            $package_services = PackageService::whereNotIn('id', $total_consume_packageservice_ids)
                                                ->where('package_id', '=', $packageinfo->id)->whereNotNull('package_id')->get();

                                            foreach ($package_services as $package_service) {

                                                $packagebundle = PackageBundles::find($package_service->package_bundle_id);

                                                $bundle_info = Bundles::find($packagebundle->bundle_id);

                                                $divide_amount = 0;

                                                $remaining_amount = $cash_receive - $total_consume;

                                                $total_price = $packageinfo->total_price - $total_consume;

                                                $divide_amount = ($package_service->tax_including_price / $total_price) * $remaining_amount;

                                                if (! in_array($bundle_info->id, $bundles)) {

                                                    $report_data[$bundle_info->id] = [
                                                        'id' => $bundle_info->id,
                                                        'name' => $bundle_info->name,
                                                        'amount' => 0,
                                                    ];
                                                    $bundles[] = $bundle_info->id;
                                                }

                                                $report_data[$bundle_info->id]['amount'] += $divide_amount;
                                            }
                                        }

                                    }
                                }
                            } else {

                                if (isset($packagesadvance->appointment_id)) {

                                    $appointinfor = Appointments::find($packagesadvance->appointment_id);

                                    if (! in_array($appointinfor->service_id, $bundles)) {

                                        $serviceinfo = Services::find($appointinfor->service_id);

                                        $report_data[$appointinfor->service_id] = [
                                            'id' => $appointinfor->service_id,
                                            'name' => $serviceinfo->name,
                                            'amount' => 0,
                                        ];
                                        $bundles[] = $serviceinfo->id;
                                    }
                                    $report_data[$appointinfor->service_id]['amount'] -= $packagesadvance->cash_amount;

                                } else {

                                    $packageinfo = Packages::find($packagesadvance->package_id);

                                    $package_services = PackageService::where('package_id', '=', $packageinfo->id)->whereNotNull('package_id')->get();

                                    foreach ($package_services as $package_service) {

                                        $packagebundle = PackageBundles::find($package_service->package_bundle_id);

                                        $bundle_info = Bundles::find($packagebundle->bundle_id);

                                        $divide_amount = 0;

                                        $remaining_amount = $packagesadvance->cash_amount;

                                        $total_amount = $packageinfo->total_price;

                                        $divide_amount = ($package_service->tax_including_price * $remaining_amount) / $total_amount;

                                        if (! in_array($bundle_info->id, $bundles)) {

                                            $report_data[$bundle_info->id] = [
                                                'id' => $bundle_info->id,
                                                'name' => $bundle_info->name,
                                                'amount' => 0,
                                            ];
                                            $bundles[] = $bundle_info->id;
                                        }

                                        $report_data[$bundle_info->id]['amount'] -= $divide_amount;
                                    }
                                }
                            }
                        }

                    }
                    // dd($report_data);
                }
            }
        }

        return $report_data;
    }
}
