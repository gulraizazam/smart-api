<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Reports\Finanaces;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConversionReport
{
    /**
     * Generate conversion report.
     *
     * Extracted from Finanaces::conversion_report()
     * Business logic preserved exactly — this is complex legacy code with
     * two-case appointment processing (direct packages + child appointments).
     */
    public function generate(
        ?string $startDate,
        ?string $endDate,
        array $requestData,
        int $accountId,
    ): array {
        $where = [];

        if (! empty($requestData['region_id'])) {
            $where[] = ['appointments.region_id', '=', $requestData['region_id']];
        }
        if (! empty($requestData['city_id'])) {
            $where[] = ['appointments.city_id', '=', $requestData['city_id']];
        }
        if (! empty($requestData['patient_id'])) {
            $where[] = ['appointments.patient_id', '=', $requestData['patient_id']];
        }
        if (! empty($requestData['service_id'])) {
            $where[] = ['appointments.service_id', '=', $requestData['service_id']];
        }
        if (! empty($requestData['doctor_id'])) {
            $where[] = ['appointments.doctor_id', '=', $requestData['doctor_id']];
        }

        $appointmentType = AppointmentTypes::whereSlug('consultancy')->first();
        $where[] = ['appointments.appointment_type_id', '=', $appointmentType->id];
        $where[] = ['package_advances.cash_amount', '>', 0];

        $locationIds = GeneralFunctions::getLocationIds($requestData['location_id'] ?? null);

        // Case 1: Direct package appointments
        $appointments = Appointments::with([
                'location:id,name',
                'doctor:id,name',
                'patient:id,name,phone',
                'service:id,name',
                'region:id,name',
                'city:id,name',
            ])
            ->join('packages', 'appointments.id', '=', 'packages.appointment_id')
            ->join('package_advances', 'packages.id', '=', 'package_advances.package_id')
            ->when($locationIds, fn ($q) => $q->whereIn('appointments.location_id', $locationIds))
            ->where('appointments.base_appointment_status_id', config('constants.appointment_status_arrived'))
            ->whereDate('package_advances.created_at', '>=', $startDate)
            ->whereDate('package_advances.created_at', '<=', $endDate)
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
            ->when($locationIds, fn ($q) => $q->whereIn('appointments.location_id', $locationIds))
            ->where('appointments.appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereDate('scheduled_date', '>=', $startDate)
            ->whereDate('scheduled_date', '<=', $endDate)
            ->groupBy('appointments.location_id')
            ->pluck('count', 'appointments.location_id');

        $total = 0;
        $count = [];
        $arrivedCount = [];
        $appointmentIds = [];
        $appointmentsInfo = [];
        $locationData = [];

        // Preload packages and advances for Case 1 to eliminate N+1 queries
        $case1AppointmentIds = $appointments->pluck('id')->unique()->toArray();
        $allPackages = count($case1AppointmentIds)
            ? Packages::whereIn('appointment_id', $case1AppointmentIds)
                ->whereNull('deleted_at')
                ->get()
                ->groupBy('appointment_id')
            : collect();

        $allPackageIds = $allPackages->flatten()->pluck('id')->toArray();
        $allAdvancesInRange = count($allPackageIds)
            ? PackageAdvances::whereIn('package_id', $allPackageIds)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('cash_amount', '>', 0)
                ->get()
                ->groupBy('package_id')
            : collect();

        $allFirstAdvances = count($allPackageIds)
            ? PackageAdvances::whereIn('package_id', $allPackageIds)
                ->where('cash_amount', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('package_id')
                ->map(fn ($group) => $group->first())
            : collect();

        if ($appointments->count()) {
            foreach ($appointments as $appointment) {
                if (! in_array($appointment->id, $appointmentIds, true)) {
                    $appointmentsInfo[$appointment->id] = [
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
                $appointmentIds[] = $appointment->id;

                $packageInfo = ($allPackages[$appointment->id] ?? collect())->pluck('id')->toArray();

                if (count($packageInfo)) {
                    $revenueIn = 0;
                    $out = 0;

                    $packageAdvances = collect($packageInfo)
                        ->flatMap(fn ($pkgId) => $allAdvancesInRange[$pkgId] ?? []);

                    if ($packageAdvances->count() > 0) {
                        $firstAdvance = collect($packageInfo)
                            ->map(fn ($pkgId) => $allFirstAdvances[$pkgId] ?? null)
                            ->filter()
                            ->sortBy('created_at')
                            ->first();

                        $date = $firstAdvance->updated_at->format('Y-m-d');

                        if ($date >= $startDate && $date <= $endDate) {
                            $appointmentsInfo[$appointment->id]['converted'] = 'Yes';

                            foreach ($packageAdvances as $advance) {
                                $child = Finanaces::genericfunctionforstaffwiserevenue($advance);

                                if ($child) {
                                    $revenueIn += $child['revenue'] ?: 0;
                                    $out += $child['refund_out'] ?: 0;
                                }
                            }

                            $actual = $revenueIn - $out;
                            $appointmentsInfo[$appointment->id]['conversion_spend'] = $actual;
                            $appointmentsInfo[$appointment->id]['converted'] = 'Yes';
                            $appointmentsInfo[$appointment->id]['conversion_date'] = $firstAdvance->created_at;

                            $count[$appointment->location->id][] = 1;
                            $locationData[$appointment->location->name]['total_count'] = count($count[$appointment->location->id]);

                            if ($appointment['converted'] != '') {
                                $arrivedCount[$appointment->location->id][] = 1;
                                $locationData[$appointment->location->name]['total_count'] = count($arrivedCount[$appointment->location->id]);
                            }

                            $total += $appointmentsInfo[$appointment->id]['conversion_spend'] ?: 0;
                            $locationData[$appointment->location->name]['total'] = $total;
                        }
                    }
                }
            }
        }

        // Case 2: Child appointments
        $records = Appointments::with([
                'location:id,name',
                'doctor:id,name',
                'patient:id,name,phone',
                'service:id,name',
                'region:id,name',
                'city:id,name',
            ])
            ->join('appointments as appoint_2', 'appointments.id', '=', 'appoint_2.appointment_id')
            ->join('package_advances', 'appoint_2.id', '=', 'package_advances.appointment_id')
            ->when($locationIds, fn ($q) => $q->whereIn('appointments.location_id', $locationIds))
            ->whereDate('package_advances.created_at', '>=', $startDate)
            ->whereDate('package_advances.created_at', '<=', $endDate)
            ->where($where)
            ->select(DB::raw('DISTINCT appointments.id as ABC,appointments.*'))
            ->get();

        if ($records->count()) {
            $appointmentIds2 = $appointmentIds;
            $case2Ids = $records->pluck('id')->unique()->toArray();

            // Preload child appointment IDs grouped by parent
            $childAppointmentsByParent = count($case2Ids)
                ? Appointments::whereIn('appointment_id', $case2Ids)
                    ->select('id', 'appointment_id')
                    ->get()
                    ->groupBy('appointment_id')
                : collect();

            // Preload all advances for child appointments
            $allChildAppointmentIds = $childAppointmentsByParent->flatten()->pluck('id')->toArray();
            $childAdvancesInRange = count($allChildAppointmentIds)
                ? PackageAdvances::whereIn('appointment_id', $allChildAppointmentIds)
                    ->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate)
                    ->get()
                    ->groupBy('appointment_id')
                : collect();

            $childFirstAdvances = count($allChildAppointmentIds)
                ? PackageAdvances::whereIn('appointment_id', $allChildAppointmentIds)
                    ->where('cash_amount', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->groupBy('appointment_id')
                    ->map(fn ($group) => $group->first())
                : collect();

            // Preload packages for Case 2 appointments
            $case2Packages = count($case2Ids)
                ? Packages::whereIn('appointment_id', $case2Ids)
                    ->whereNull('deleted_at')
                    ->get()
                    ->groupBy('appointment_id')
                : collect();

            foreach ($records as $appointment) {
                $revenueIn = 0;
                $out = 0;
                $status = false;
                $conversionSpend = 0;
                $converted = '';
                $firstAdvance = null;

                $inAppointmentIds = ($childAppointmentsByParent[$appointment->id] ?? collect())->pluck('id')->toArray();

                if (count($inAppointmentIds)) {
                    $advanceInfo = collect($inAppointmentIds)
                        ->flatMap(fn ($id) => $childAdvancesInRange[$id] ?? []);

                    if ($advanceInfo->count() > 0) {
                        $firstAdvance = collect($inAppointmentIds)
                            ->map(fn ($id) => $childFirstAdvances[$id] ?? null)
                            ->filter()
                            ->sortBy('created_at')
                            ->first();

                        $date = $firstAdvance->updated_at->format('Y-m-d');

                        if ($date >= $startDate && $date <= $endDate) {
                            foreach ($advanceInfo as $advance) {
                                $child = Finanaces::genericfunctionforstaffwiserevenue($advance);
                                if ($child) {
                                    $revenueIn += $child['revenue'] ?: 0;
                                    $out += $child['refund_out'] ?: 0;
                                }
                            }
                            $conversionSpend = $revenueIn - $out;
                            $converted = 'Yes';
                            $status = true;
                        } else {
                            $conversionSpend = '0';
                        }
                    }
                } else {
                    $conversionSpend = '0';
                }

                if (! in_array($appointment->id, $appointmentIds2, true)) {
                    $appointmentsInfo[$appointment->id] = [
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

                    $packageInfo = ($case2Packages[$appointment->id] ?? collect())->pluck('id')->toArray();

                    if (empty($packageInfo)) {
                        $appointmentIds2[] = $appointment->id;
                        $appointmentsInfo[$appointment->id]['converted'] = $converted;
                        $appointmentsInfo[$appointment->id]['conversion_spend'] = $conversionSpend;
                        $appointmentsInfo[$appointment->id]['conversion_date'] = $firstAdvance?->created_at;
                    }
                } else {
                    if (($appointmentsInfo[$appointment->id]['converted'] ?? '') === 'Yes' && $status) {
                        $previous = $appointmentsInfo[$appointment->id]['conversion_spend'];
                        $appointmentsInfo[$appointment->id]['conversion_spend'] = $previous + $conversionSpend;
                    } elseif (($appointmentsInfo[$appointment->id]['converted'] ?? '') === 'No' && $status) {
                        $appointmentsInfo[$appointment->id]['conversion_spend'] = $conversionSpend;
                        $appointmentsInfo[$appointment->id]['conversion_date'] = $firstAdvance?->created_at;
                        $appointmentsInfo[$appointment->id]['converted'] = 'Yes';
                    }
                }

                $count[$appointment->location->id][] = 1;
                $locationData[$appointment->location->name]['total_count'] = count($count[$appointment->location->id]);

                if ($appointment['converted'] != '') {
                    $arrivedCount[$appointment->location->id][] = 1;
                    $locationData[$appointment->location->name]['total_count'] = count($arrivedCount[$appointment->location->id]);
                }

                $total += $appointmentsInfo[$appointment->id]['conversion_spend'] ?: 0;
                $locationData[$appointment->location->name]['total'] = $total;
            }
        }

        return $this->buildResult($appointmentsInfo, $locationData, $startDate, $endDate);
    }

    public function generateExcel(array $result): void
    {
        $reportData = $result['report_data'];
        $startDate = $result['start_date'];
        $endDate = $result['end_date'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $sheet->setCellValue('B1', "From {$startDate} to {$endDate}");
        $sheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $sheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $headers = ['ID', 'Doctor', 'Date of Inquiry', 'Client', 'Appointment Type', 'Service', 'Converted', 'Conversion Spend', 'Conversion Date', 'Region', 'City', 'Location'];
        $cols = range('A', 'L');

        foreach ($headers as $i => $header) {
            $sheet->setCellValue("{$cols[$i]}3", $header)->getStyle("{$cols[$i]}3")->getFont()->setBold(true);
        }

        $sheet->setCellValue('A4', '');
        $counter = 5;
        $total = 0;
        $count = 0;

        if (count($reportData)) {
            foreach ($reportData as $appointment) {
                if ($appointment['converted'] != '') {
                    $sheet->setCellValue("A{$counter}", $appointment['patient_id']);
                    $sheet->setCellValue("B{$counter}", $appointment['doctor']);
                    $sheet->setCellValue("C{$counter}", $appointment['doi']);
                    $sheet->setCellValue("D{$counter}", $appointment['client']);
                    $sheet->setCellValue("E{$counter}", 'Consultancy');
                    $sheet->setCellValue("F{$counter}", $appointment['service']);
                    $sheet->setCellValue("G{$counter}", $appointment['converted']);
                    $sheet->setCellValue("H{$counter}", number_format($appointment['conversion_spend'], 2));
                    $sheet->setCellValue("I{$counter}", Carbon::parse($appointment['conversion_date'])->format('F j,Y'));
                    $sheet->setCellValue("J{$counter}", $appointment['region']);
                    $sheet->setCellValue("K{$counter}", $appointment['city']);
                    $sheet->setCellValue("L{$counter}", $appointment['centre']);

                    $total += $appointment['conversion_spend'] ?: 0;
                    $count++;
                    $counter++;
                }
            }

            $counter++;
            $sheet->setCellValue("A{$counter}", 'Total')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("H{$counter}", number_format($total, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Total Count')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("H{$counter}", count($reportData));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Converted Count')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("H{$counter}", $count);
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Converted Ratio')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("H{$counter}", $count > 0 ? number_format($count / count($reportData) * 100, 2) : 0 . '%');
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Conversion Average')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("H{$counter}", $total > 0 ? number_format($total / $count, 2) : 0);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="conversionreport.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    private function buildResult(array $appointmentsInfo, array $locationData, ?string $startDate, ?string $endDate): array
    {
        return [
            'report_data' => $appointmentsInfo,
            'locationData' => $locationData,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
