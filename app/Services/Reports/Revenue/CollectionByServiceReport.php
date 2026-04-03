<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionByServiceReport
{
    /**
     * Generate collection by service report.
     *
     * Calculates service-wise revenue from package_advances where cash_flow='out'
     * (service consumption records). Each record is linked to an appointment
     * which contains the service_id.
     */
    public function generate(
        ?string $startDate,
        ?string $endDate,
        ?array $locationIds,
        ?string $regionId,
        int $accountId,
    ): array {
        $resolvedLocationIds = $this->resolveLocationIds($locationIds, $regionId);

        $results = PackageAdvances::join('appointments', 'appointments.id', '=', 'package_advances.appointment_id')
            ->whereDate('package_advances.created_at', '>=', $startDate)
            ->whereDate('package_advances.created_at', '<=', $endDate)
            ->where('package_advances.account_id', $accountId)
            ->whereIn('package_advances.location_id', $resolvedLocationIds)
            ->where('package_advances.cash_amount', '>', 0)
            ->where('package_advances.cash_flow', 'out')
            ->select(
                'appointments.service_id',
                DB::raw('SUM(package_advances.cash_amount) as total_amount'),
            )
            ->groupBy('appointments.service_id')
            ->get();

        // Batch-load service names
        $serviceIds = $results->pluck('service_id')->unique()->filter()->values()->toArray();
        $services = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

        $reportData = [];

        foreach ($results as $row) {
            $service = $services->get($row->service_id);

            if (! $service) {
                continue;
            }

            $reportData[$service->id] = [
                'id' => $service->id,
                'name' => $service->name,
                'amount' => (float) $row->total_amount,
            ];
        }

        return $this->buildResult($reportData, $startDate, $endDate);
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
        $sheet->setCellValue('A3', 'Service')->getStyle('A3')->getFont()->setBold(true);
        $sheet->setCellValue('B3', 'Total')->getStyle('B3')->getFont()->setBold(true);

        $counter = 4;
        $total = 0;

        foreach ($reportData as $row) {
            if ($row['amount'] > 0) {
                $total += $row['amount'];
                $sheet->setCellValue("A{$counter}", $row['name']);
                $sheet->setCellValue("B{$counter}", number_format($row['amount'], 2));
                $counter++;
            }
        }

        $sheet->setCellValue("A{$counter}", '');
        $sheet->setCellValue("B{$counter}", '');
        $counter++;
        $sheet->setCellValue("A{$counter}", 'Grand Total')->getStyle("A{$counter}")->getFont()->setBold(true);
        $sheet->setCellValue("B{$counter}", number_format($total, 2))->getStyle("B{$counter}")->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Collectionbyservice.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    private function resolveLocationIds(?array $locationIds, ?string $regionId): array
    {
        $where = [];

        if ($regionId) {
            $locations = ! empty($locationIds)
                ? Locations::generalrevenuegetActiveSorted($locationIds, $regionId)
                : Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $regionId);

            foreach ($locations as $key => $location) {
                $where[] = $key;
            }
        } else {
            if (! empty($locationIds)) {
                if (is_array($locationIds)) {
                    $filtered = array_filter($locationIds, fn ($val) => $val !== '' && $val !== null);
                    if (! empty($filtered)) {
                        $where = array_values($filtered);
                    }
                } else {
                    $where[] = $locationIds;
                }
            }

            if (empty($where)) {
                $locations = Locations::getActiveSorted(ACL::getUserCentres());
                foreach ($locations as $key => $location) {
                    $where[] = $key;
                }
            }
        }

        return $where;
    }

    private function buildResult(array $reportData, ?string $startDate, ?string $endDate): array
    {
        return [
            'reportData' => $reportData,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
