<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyEmployeeStatsReport
{
    public function generate(
        ?string $startDate,
        ?string $endDate,
        array $requestData,
    ): array {
        $where = [];

        if (! empty($requestData['patient_id'])) {
            $where['invoices.patient_id'] = $requestData['patient_id'];
        }
        if (! empty($requestData['appointment_type_id'])) {
            $where['appointments.appointment_type_id'] = $requestData['appointment_type_id'];
        }
        if (! empty($requestData['location_id'])) {
            $where['invoices.location_id'] = $requestData['location_id'];
        }
        if (! empty($requestData['service_id'])) {
            $where['invoice_details.service_id'] = $requestData['service_id'];
        }
        if (! empty($requestData['doctor_id'])) {
            $where['invoices.doctor_id'] = $requestData['doctor_id'];
        }

        $where['invoices.invoice_status_id'] = '3';

        $records = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereDate('invoices.created_at', '>=', $startDate)
            ->whereDate('invoices.created_at', '<=', $endDate)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select(
                'invoice_details.service_id',
                'invoices.doctor_id',
                DB::raw('SUM(invoice_details.tax_including_price) AS total_price')
            )
            ->groupBy('invoice_details.service_id', 'invoices.doctor_id')
            ->get();

        if ($records->isEmpty()) {
            return $this->buildResult([], $startDate, $endDate);
        }

        // Batch-load all doctors and services to avoid N+1
        $doctorIds = $records->pluck('doctor_id')->unique()->filter()->values()->toArray();
        $serviceIds = $records->pluck('service_id')->unique()->filter()->values()->toArray();

        $doctors = User::whereIn('id', $doctorIds)->get(['id', 'name'])->keyBy('id');
        $services = Services::whereIn('id', $serviceIds)->get(['id', 'name'])->keyBy('id');

        $reportData = [];

        foreach ($records as $record) {
            $doctor = $doctors->get($record->doctor_id);

            if (! $doctor) {
                continue;
            }

            if (! isset($reportData[$doctor->id])) {
                $reportData[$doctor->id] = [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'records' => [],
                ];
            }

            $service = $services->get($record->service_id);

            if (! $service || $record->total_price <= 0) {
                continue;
            }

            if (! array_key_exists($service->id, $reportData[$doctor->id]['records'])) {
                $reportData[$doctor->id]['records'][$service->id] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'amount' => $record->total_price,
                ];
            }
        }

        // Build filters for Blade view compatibility
        $accountId = Auth::user()->account_id;
        $filters = [
            'locations' => Locations::getAllRecordsDictionary($accountId),
            'services' => Services::getAllRecordsDictionaryWithoutAll($accountId),
            'users' => User::getAllRecords($accountId)->getDictionary(),
            'doctors' => Doctors::getAll($accountId)->getDictionary(),
        ];

        return $this->buildResult($reportData, $startDate, $endDate, $filters);
    }

    public function generateExcel(array $result): void
    {
        $reportData = $result['reportData'];
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
        $sheet->setCellValue('A3', '');
        $sheet->setCellValue('B3', '');
        $sheet->setCellValue('C3', '');
        $sheet->setCellValue('A4', 'Doctor')->getStyle('A4')->getFont()->setBold(true);
        $sheet->setCellValue('B4', 'Service')->getStyle('B4')->getFont()->setBold(true);
        $sheet->setCellValue('C4', 'Total')->getStyle('C4')->getFont()->setBold(true);

        $counter = 5;
        $serviceGrandTotal = 0;

        if (count($reportData)) {
            foreach ($reportData as $doctorData) {
                $sheet->setCellValue("A{$counter}", $doctorData['name'])->getStyle("A{$counter}")->getFont()->setBold(true);
                $counter++;
                $serviceTotal = 0;

                foreach ($doctorData['records'] as $row) {
                    $serviceTotal += $row['amount'];
                    $sheet->setCellValue("B{$counter}", $row['name']);
                    $sheet->setCellValue("C{$counter}", number_format($row['amount'], 2));
                    $counter++;
                }

                $serviceGrandTotal += $serviceTotal;
                $sheet->setCellValue("A{$counter}", $doctorData['name'])->getStyle("A{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$counter}", 'Total')->getStyle("A{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("C{$counter}", number_format($serviceTotal, 2))->getStyle("A{$counter}")->getFont()->setBold(true);
                $counter++;
            }

            $sheet->setCellValue("A{$counter}", '');
            $sheet->setCellValue("B{$counter}", '');
            $sheet->setCellValue("C{$counter}", '');
            $counter++;
            $sheet->setCellValue("A{$counter}", '');
            $sheet->setCellValue("B{$counter}", 'Grand Total')->getStyle("B{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("C{$counter}", number_format($serviceGrandTotal, 2))->getStyle("C{$counter}")->getFont()->setBold(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="SaleSummaryDoctorsWise.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    private function buildResult(array $reportData, ?string $startDate, ?string $endDate, array $filters = []): array
    {
        return [
            'reportData' => $reportData,
            'filters' => $filters,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
