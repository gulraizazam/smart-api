<?php

declare(strict_types=1);

namespace App\Reports\Finance;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\DoctorHasLocations;
use App\Models\InvoiceDetails;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Services\Conversion\ConversionService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceConversionReport
{
    use ParsesDateRange;

    /**
     * @deprecated Use App\Services\Reports\Revenue\ConversionReport instead.
     */
    public static function conversion_report($data, $account_id)
    {
        $where = [];

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);

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

        $centerWise = Appointments::select('appointments.location_id', DB::raw('count(appointments.id) as count'))
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
                if (! in_array($appointment->id, $appointmentss, true)) {
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
                        'doi' => Carbon::parse($appointment->created_at)->format('M d Y'),
                        'converted' => '',
                        'conversion_spend' => '',
                        'conversion_date' => '',
                    ];
                }
                $appointmentss[] = $appointment->id;

                $package_info = Packages::where('appointment_id', '=', $appointment->id)->pluck('id')->toArray();

                if (count($package_info)) {

                    $actual = 0;
                    $revenue_in = 0;
                    $out = 0;

                    $packagesadvances = PackageAdvances::whereIn('package_id', $package_info)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->where('cash_amount', '>', 0)
                        ->get();

                    if (! empty($packagesadvances)) {

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

                                $child = FinanceStaffReport::genericfunctionforstaffwiserevenue($packagesadvance);

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
            /* case 1 end */
        }

        /* case 2 start */
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

                $in_appointment_info = Appointments::where('appointment_id', '=', $appointment->id)->pluck('id')->toArray();

                if (count($in_appointment_info)) {

                    $packageadvance_info = PackageAdvances::whereIn('appointment_id', $in_appointment_info)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->get();

                    if (! empty($packageadvance_info)) {

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
                                $child = FinanceStaffReport::genericfunctionforstaffwiserevenue($packagesadvance);
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

                if (! in_array($appointment->id, $appointmentss2, true)) {
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
                        'doi' => Carbon::parse($appointment->created_at)->format('M d Y'),
                        'converted' => '',
                        'conversion_spend' => '',
                        'conversion_date' => '',
                    ];

                    $package_info = Packages::where('appointment_id', '=', $appointment->id)->pluck('id')->toArray();

                    if (empty($package_info)) {
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
        $total_apts = [];
        $converted_apts = [];
        $locationData = [];
        $returnCategoryData = [];

        $conversionService = app(ConversionService::class);

        $data['location_id'] = ($data['location_id'][0] == null) ? 'all' : $data['location_id'];
        $locations = $data['location_id'] == 'all' ? ACL::getUserCentres() : $data['location_id'];

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);

        $extraWhere = [];
        if (! empty($data['service_id'])) {
            $extraWhere[] = ['appointments.service_id', '=', $data['service_id']];
        }

        // Use is_allocated=1 to match dashboard logic
        $consultants = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locations)
            ->when(! empty($data['doctor_id']), fn ($query) => $query->where('user_id', $data['doctor_id']))
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        // Get arrived and converted appointment status IDs
        $arrivedStatus = AppointmentStatuses::where(['account_id' => $account_id, 'is_arrived' => 1])->first();
        $convertedStatus = AppointmentStatuses::where(['account_id' => $account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : config('constants.appointment_status_arrived');
        $convertedStatusId = $convertedStatus?->id;

        // Service-wise arrivals (for category breakdown)
        $total_arrived_appointments = $conversionService->getTotalArrivedByService(
            $consultants, $locations, $arrivedStatusId, $convertedStatusId, $start_date, $end_date, $extraWhere
        );

        // Fetch candidate appointments and validate conversions using shared service
        $eagerLoad = ['location:id,name', 'patient:id,name,phone', 'doctor:id,name', 'service:id,name', 'region:id,name', 'city:id,name'];
        $candidateAppointments = $conversionService->fetchCandidateAppointments(
            $consultants, $locations, $arrivedStatusId, $convertedStatusId, $start_date, $end_date, $extraWhere, $eagerLoad
        );

        $conversionResult = $conversionService->getValidatedConversions(
            $candidateAppointments, $start_date, $end_date, true
        );

        $appointments_info = $conversionResult['conversions'];
        $byService = $conversionResult['by_service'];
        $total = $conversionResult['total_spend'];

        // Build location data from validated conversions
        $count = [];
        $arrived_count = [];
        foreach ($appointments_info as $aptInfo) {
            if (($aptInfo['conversion_spend'] ?? '') !== '' && $aptInfo['conversion_spend'] > 0) {
                $centreName = $aptInfo['centre'] ?? '';
                if ($centreName) {
                    $count[$centreName] = ($count[$centreName] ?? 0) + 1;
                    $locationData[$centreName]['total_count'] = $count[$centreName];
                    $locationData[$centreName]['total'] = ($locationData[$centreName]['total'] ?? 0) + $aptInfo['conversion_spend'];
                }
            }
        }

        // Total arrived appointments (for conversion ratio)
        $filterDoctorIds = ! empty($data['doctor_id']) ? $consultants : null;
        $total_appointments = $conversionService->getTotalArrivedCount(
            $locations, $arrivedStatusId, $convertedStatusId, $start_date, $end_date, $filterDoctorIds, $extraWhere
        );

        $converted_Records = collect($appointments_info)->where('conversion_spend', '!=', '')->where('conversion_spend', '>', 0)->count();
        $converted_apts[] = $converted_Records;
        $total_apts[] = $total_appointments;

        // Build service-wise category data
        $new_array = [];
        foreach ($byService as $serviceId => $svcData) {
            $new_array[$svcData['name']] = [
                'service' => $svcData['name'],
                'total_conversion' => $svcData['count'],
                'avg' => $svcData['count'] > 0 ? ($svcData['spend'] / $svcData['count']) : 0,
                'sum' => $svcData['spend'],
            ];
        }

        // Build category breakdown with total arrivals per service
        foreach ($total_arrived_appointments as $key => $arrive_category) {
            $name = $arrive_category->name;

            // Get category total records (arrivals per service)
            $category_total_records = $conversionService->getTotalArrivedCount(
                $locations, $arrivedStatusId, $convertedStatusId, $start_date, $end_date,
                $filterDoctorIds,
                array_merge($extraWhere, [['service_id', '=', $arrive_category->service_id]])
            );

            if (isset($new_array[$name])) {
                $returnCategoryData[$key] = [
                    'service' => $name,
                    'total_arrival' => $category_total_records,
                    'total_conversion' => $new_array[$name]['total_conversion'],
                    'avg' => $new_array[$name]['avg'],
                    'sum' => $new_array[$name]['sum'],
                ];
            } else {
                $returnCategoryData[$key] = [
                    'service' => $name,
                    'total_arrival' => $category_total_records,
                    'total_conversion' => 0,
                    'avg' => 0,
                    'sum' => 0,
                ];
            }
        }

        // Add categories with conversions but no arrivals
        $processedCategories = $total_arrived_appointments->pluck('name')->toArray();
        foreach ($new_array as $category_name => $category_data) {
            if (! in_array($category_name, $processedCategories, true)) {
                $returnCategoryData[] = [
                    'service' => $category_name,
                    'total_arrival' => 0,
                    'total_conversion' => $category_data['total_conversion'],
                    'avg' => $category_data['avg'],
                    'sum' => $category_data['sum'],
                ];
            }
        }

        $maxConversion = collect($appointments_info)->max('conversion_spend');
        $minConversion = collect($appointments_info)->where('conversion_spend', '!=', '')->where('conversion_spend', '>', 0)->min('conversion_spend');

        $totalamount = collect($appointments_info)->where('conversion_spend', '!=', '')->sum('conversion_spend');

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
        $conversionsByPatient = collect($appointments_info)->where('conversion_spend', '!=', '')->groupBy('patient_id')
            ->map(fn ($items) => $items->sum('conversion_spend'));
        // $conversionsByPatient is an Illuminate Collection — `empty()` on an
        // object is ALWAYS false, so the old guard never tripped and an empty
        // period divided sum() by count()=0 → DivisionByZeroError. Guard on the
        // collection's own count instead.
        if ($conversionsByPatient->count() > 0) {
            $avg_cxlient_value = $conversionsByPatient->sum() / $conversionsByPatient->count();
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
            $avg_cxlient_value,
        ];
    }

    private static function centerWiseData($appointments_info, $appointment, $centerWise, $count, $arrived_count, $total, $locationData)
    {
        $count[$appointment->location->id][] = 1;

        $locationData[$appointment->location->name]['total_count'] = count($count[$appointment->location->id]);
        if ($appointment['converted'] != '') {
            $arrived_count[$appointment->location->id][] = 1;
            $locationData[$appointment->location->name]['total_count'] = count($arrived_count[$appointment->location->id]);
        }
        $total += $appointments_info['conversion_spend'] ? $appointments_info['conversion_spend'] : 0;

        $locationData[$appointment->location->name]['total'] = $total;

        return $locationData;
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

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);

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
                            'tax' => $invoice->tax_percentage,
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
                'phone' => GeneralFunctions::prepareNumber4Call($packagerow->user->phone),
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
}
