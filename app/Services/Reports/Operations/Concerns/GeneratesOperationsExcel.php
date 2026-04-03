<?php

declare(strict_types=1);

namespace App\Services\Reports\Operations\Concerns;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait GeneratesOperationsExcel
{
    protected function buildExcel(
        array $reportData,
        string $startDate,
        string $endDate,
        array $locationData,
        string $reportName,
        string $type,
    ): void {
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $sheet->setCellValue('B1', "From {$startDate} to {$endDate}");

        $sheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $sheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $sheet->setCellValue('A3', '');

        $sheet->setCellValue('A5', 'Sr#')->getStyle('A5')->getFont()->setBold(true);
        $sheet->setCellValue('B5', 'Scheduled Date')->getStyle('B5')->getFont()->setBold(true);
        $sheet->setCellValue('C5', 'Client Id')->getStyle('C5')->getFont()->setBold(true);
        $sheet->setCellValue('D5', 'Client Name')->getStyle('D5')->getFont()->setBold(true);
        $sheet->setCellValue('E5', 'Appointment Type')->getStyle('E5')->getFont()->setBold(true);
        $sheet->setCellValue('F5', 'Practitioner')->getStyle('F5')->getFont()->setBold(true);
        $sheet->setCellValue('G5', 'Service')->getStyle('G5')->getFont()->setBold(true);
        $sheet->setCellValue('H5', 'Appointment Status Parent')->getStyle('H5')->getFont()->setBold(true);

        $sheet->setCellValue('A6', '');

        $counter = 6;

        if (count($reportData)) {
            $count = 1;
            $consultantbooked = 0;
            $treatmentbooked = 0;
            $consultantarrived = 0;
            $treatmentarrived = 0;

            foreach ($reportData as $row) {
                if ($row['appointment_slug'] == 'consultancy') {
                    $consultantbooked++;
                } elseif ($row['appointment_slug'] == 'treatment') {
                    $treatmentbooked++;
                }
                if ($row['appointment_slug'] == 'consultancy' && $row['appointment_status_isarrived'] == '1') {
                    $consultantarrived++;
                } elseif ($row['appointment_slug'] == 'treatment' && $row['appointment_status_isarrived'] == '1') {
                    $treatmentarrived++;
                }

                $sheet->setCellValue("A{$counter}", $count++);
                $sheet->setCellValue("B{$counter}", $row['schedule_date']);
                $sheet->setCellValue("C{$counter}", $row['id']);
                $sheet->setCellValue("D{$counter}", $row['client_name']);
                $sheet->setCellValue("E{$counter}", $row['appointment_type']);
                $sheet->setCellValue("F{$counter}", $row['doctor_name']);
                $sheet->setCellValue("G{$counter}", $row['service']);
                $sheet->setCellValue("H{$counter}", $row['appointment_status_parent']);

                $counter++;
            }

            $sheet->setCellValue("A{$counter}", '');
            $counter++;

            if (! empty($locationData)) {
                foreach ($locationData as $key => $location) {
                    if ($type == 'dar') {
                        $counter += 2;
                        $sheet->setCellValue("A{$counter}", $key)->getStyle("A{$counter}")->getFont()->setBold(true);
                        $counter += 2;

                        $sheet->setCellValue("A{$counter}", 'Consultation Booked')->getStyle("A{$counter}")->getFont()->setBold(true);
                        $sheet->setCellValue("B{$counter}", $location['consultantbooked'] ?? '')->getStyle("B{$counter}")->getFont()->setBold(true);
                        $counter++;

                        $sheet->setCellValue("A{$counter}", 'Consultation Arrived')->getStyle("A{$counter}")->getFont()->setBold(true);
                        $sheet->setCellValue("B{$counter}", $location['consultantarrived'] ?? '')->getStyle("B{$counter}")->getFont()->setBold(true);
                        $counter++;

                        $sheet->setCellValue("A{$counter}", 'Total Walkin')->getStyle("A{$counter}")->getFont()->setBold(true);
                        $sheet->setCellValue("B{$counter}", $location['walking'] ?? '')->getStyle("B{$counter}")->getFont()->setBold(true);
                        $counter++;

                        if ($consultantbooked > 0) {
                            $ratio = '00.00 %';
                            if (isset($location['consultantbooked'], $location['consultantarrived'])) {
                                $bookingWithoutWalkin = $location['consultantbooked'] - ($location['walking'] ?? 0);
                                $arrivedWithoutWalkin = $location['consultantarrived'] - ($location['walking'] ?? 0);
                                if ($bookingWithoutWalkin > 0) {
                                    $ratio = number_format(($arrivedWithoutWalkin / $bookingWithoutWalkin) * 100, 2) . '%';
                                }
                            }

                            $sheet->setCellValue("A{$counter}", 'Consultation Arrival Ratio')->getStyle("A{$counter}")->getFont()->setBold(true);
                            $sheet->setCellValue("B{$counter}", $ratio)->getStyle("B{$counter}")->getFont()->setBold(true);
                            $counter++;
                        }
                    } elseif ($type == 'agent') {
                        $counter += 2;
                        $sheet->setCellValue("A{$counter}", $key)->getStyle("A{$counter}")->getFont()->setBold(true);
                        $counter += 2;

                        $sheet->setCellValue("A{$counter}", 'Consultation Booked')->getStyle("A{$counter}")->getFont()->setBold(true);
                        $sheet->setCellValue("B{$counter}", $location['consultantbooked'] ?? '')->getStyle("B{$counter}")->getFont()->setBold(true);
                        $counter++;

                        $sheet->setCellValue("A{$counter}", 'Consultation Arrived')->getStyle("A{$counter}")->getFont()->setBold(true);
                        $sheet->setCellValue("B{$counter}", $location['consultantarrived'] ?? '')->getStyle("B{$counter}")->getFont()->setBold(true);
                        $counter++;

                        if ($consultantbooked > 0) {
                            $ratio = '00.00 %';
                            if (isset($location['consultantbooked'], $location['consultantarrived'])) {
                                if ($location['consultantbooked'] > 0) {
                                    $ratio = number_format(($location['consultantarrived'] / $location['consultantbooked']) * 100, 2) . '%';
                                }
                            }

                            $sheet->setCellValue("A{$counter}", 'Consultation Arrival Ratio')->getStyle("A{$counter}")->getFont()->setBold(true);
                            $sheet->setCellValue("B{$counter}", $ratio)->getStyle("B{$counter}")->getFont()->setBold(true);
                            $counter++;
                        }
                    } elseif ($type == 'walkin') {
                        $counter += 2;
                        $sheet->setCellValue("A{$counter}", $key)->getStyle("A{$counter}")->getFont()->setBold(true);
                        $counter += 2;

                        $sheet->setCellValue("A{$counter}", 'Total Walkin')->getStyle("A{$counter}")->getFont()->setBold(true);
                        $sheet->setCellValue("B{$counter}", $location['walkin'] ?? '')->getStyle("B{$counter}")->getFont()->setBold(true);
                        $counter++;
                    }
                }
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$reportName}.xlsx\"");
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
