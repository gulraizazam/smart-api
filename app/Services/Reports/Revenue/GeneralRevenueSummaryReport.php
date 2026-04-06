<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\PackageAdvances;
use Carbon\Carbon;

class GeneralRevenueSummaryReport
{
    public function generate(
        ?string $startDate,
        ?string $endDate,
        ?array $locationIds,
        ?string $regionId,
        int $accountId,
    ): array {
        $locationInfo = $this->resolveLocations($locationIds, $regionId);
        $reportData = [];

        $locationKeys = array_keys(is_array($locationInfo) ? $locationInfo : $locationInfo->toArray());

        // Preload all advances grouped by location to eliminate N+1
        $allAdvances = count($locationKeys)
            ? PackageAdvances::with('user:id,gender', 'paymentmode')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('account_id', $accountId)
                ->whereIn('location_id', $locationKeys)
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('location_id')
            : collect();

        // Preload all locations with city and region
        $allLocations = count($locationKeys)
            ? Locations::with('city:id,name', 'region:id,name')
                ->whereIn('id', $locationKeys)
                ->get()
                ->keyBy('id')
            : collect();

        foreach ($locationInfo as $key => $locationName) {
            $advances = $allAdvances[$key] ?? collect();
            $location = $allLocations[$key] ?? null;

            if (! $location) {
                continue;
            }

            $totalCash = 0;
            $totalCard = 0;
            $totalBank = 0;
            $totalRefund = 0;
            $maleTotal = 0;
            $femaleTotal = 0;
            $unknownGenderTotal = 0;

            foreach ($advances as $advance) {
                if (! $this->isRevenueTransaction($advance)) {
                    continue;
                }

                if ($advance->cash_amount == 0) {
                    continue;
                }

                if ($advance->cash_flow === 'in') {
                    $paymentName = $advance->paymentmode->name ?? 'Cash';

                    match (true) {
                        $paymentName === 'Cash' => $totalCash += $advance->cash_amount,
                        $paymentName === 'Card' => $totalCard += $advance->cash_amount,
                        $paymentName === 'Bank/Wire Transfer' => $totalBank += $advance->cash_amount,
                        default => $totalCash += $advance->cash_amount,
                    };

                    // Gender-wise calculation
                    $amount = $advance->cash_amount;
                    if ($advance->user?->gender == 1) {
                        $maleTotal += $amount;
                    } elseif ($advance->user?->gender == 2) {
                        $femaleTotal += $amount;
                    } else {
                        $unknownGenderTotal += $amount;
                    }
                } else {
                    $totalRefund += $advance->cash_amount;
                }
            }

            $reportData[$location->id] = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city->name,
                'region' => $location->region->name,
                'revenue_cash_in' => $totalCash,
                'revenue_card_in' => $totalCard,
                'revenue_bank_in' => $totalBank,
                'refund_out' => $totalRefund,
                'in_hand' => ($totalCash + $totalCard + $totalBank) - $totalRefund,
                'male_revenue' => $maleTotal,
                'female_revenue' => $femaleTotal,
                'unknown_gender_revenue' => $unknownGenderTotal,
            ];
        }

        return $this->buildResult($reportData, $startDate, $endDate);
    }

    public function generateExcel(array $result): void
    {
        $reportData = $result['report_data'];
        $totalCash = $result['total_revenue_cash_in'];
        $totalCard = $result['total_revenue_card_in'];
        $totalBank = $result['total_revenue_bank_in'];
        $totalRefund = $result['total_refund'];
        $totalRevenue = $result['total_revenue'];
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

        $sheet->setCellValue('A4', 'Centre')->getStyle('A4')->getFont()->setBold(true);
        $sheet->setCellValue('B4', 'City')->getStyle('B4')->getFont()->setBold(true);
        $sheet->setCellValue('C4', 'Region')->getStyle('C4')->getFont()->setBold(true);
        $sheet->setCellValue('D4', 'Revenue Cash In')->getStyle('D4')->getFont()->setBold(true);
        $sheet->setCellValue('E4', 'Revenue Card In')->getStyle('E4')->getFont()->setBold(true);
        $sheet->setCellValue('F4', 'Revenue Bank/Wire In')->getStyle('F4')->getFont()->setBold(true);
        $sheet->setCellValue('G4', 'Refund/Out')->getStyle('G4')->getFont()->setBold(true);
        $sheet->setCellValue('H4', 'In Hand')->getStyle('H4')->getFont()->setBold(true);
        $sheet->setCellValue('A5', '');

        $counter = 6;

        if ($reportData) {
            foreach ($reportData as $row) {
                $sheet->setCellValue("A{$counter}", $row['name']);
                $sheet->setCellValue("B{$counter}", $row['city']);
                $sheet->setCellValue("C{$counter}", $row['region']);
                $sheet->setCellValue("D{$counter}", number_format($row['revenue_cash_in'], 2));
                $sheet->setCellValue("E{$counter}", number_format($row['revenue_card_in'], 2));
                $sheet->setCellValue("F{$counter}", number_format($row['revenue_bank_in'], 2));
                $sheet->setCellValue("G{$counter}", number_format($row['refund_out'], 2));
                $sheet->setCellValue("H{$counter}", number_format($row['in_hand'], 2));
                $counter++;
            }

            $sheet->setCellValue("A{$counter}", '');
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Total')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("D{$counter}", number_format($totalCash, 2))->getStyle("D{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("E{$counter}", number_format($totalCard, 2))->getStyle("E{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("F{$counter}", number_format($totalBank, 2))->getStyle("F{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("G{$counter}", number_format($totalRefund, 2))->getStyle("G{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("H{$counter}", number_format(($totalCash + $totalCard + $totalBank) - $totalRefund, 2))->getStyle("H{$counter}")->getFont()->setBold(true);
            $counter++;

            $sheet->setCellValue("A{$counter}", '');
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Revenue Cash In')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalCash, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Revenue Card In')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalCard, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Revenue Bank/Wire In')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalBank, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Total Revenue')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalRevenue, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Refund')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalRefund, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'In Hand Balance')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalRevenue - $totalRefund, 2));
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="GeneralRevenueReport.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    private function resolveLocations(?array $locationIds, ?string $regionId): mixed
    {
        $userCentres = ACL::getUserCentres();

        // Filter empty values from location IDs
        $filteredIds = $locationIds
            ? array_filter($locationIds, fn ($val) => $val !== '' && $val !== null)
            : [];

        if (! empty($filteredIds)) {
            $selectedLocations = array_intersect($filteredIds, is_array($userCentres) ? $userCentres : [$userCentres]);

            if (empty($selectedLocations)) {
                $selectedLocations = $filteredIds;
            }

            return $regionId
                ? Locations::generalrevenuegetActiveSorted($selectedLocations, $regionId)
                : Locations::getActiveSorted($selectedLocations);
        }

        if ($regionId) {
            return Locations::generalrevenuegetActiveSorted($userCentres, $regionId);
        }

        return Locations::getActiveSorted($userCentres);
    }

    private function buildResult(array $reportData, ?string $startDate, ?string $endDate): array
    {
        $totalCash = 0;
        $totalCard = 0;
        $totalBank = 0;
        $totalRefund = 0;
        $totalMale = 0;
        $totalFemale = 0;

        foreach ($reportData as $row) {
            $totalCash += $row['revenue_cash_in'] ?: 0;
            $totalCard += $row['revenue_card_in'] ?: 0;
            $totalBank += $row['revenue_bank_in'] ?: 0;
            $totalRefund += $row['refund_out'] ?: 0;
            $totalMale += $row['male_revenue'] ?: 0;
            $totalFemale += $row['female_revenue'] ?: 0;
        }

        return [
            'report_data' => $reportData,
            'total_revenue_cash_in' => $totalCash,
            'total_revenue_card_in' => $totalCard,
            'total_revenue_bank_in' => $totalBank,
            'total_refund' => $totalRefund,
            'total_revenue' => $totalCash + $totalCard + $totalBank,
            'total_revenue_male_in' => $totalMale,
            'total_revenue_female_in' => $totalFemale,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function isRevenueTransaction(PackageAdvances $advance): bool
    {
        return ($advance->cash_flow === 'in'
                && $advance->is_adjustment == '0'
                && $advance->is_tax == '0'
                && $advance->is_cancel == '0')
            || ($advance->cash_flow === 'out'
                && $advance->is_refund == '1'
                && $advance->is_tax == '0');
    }
}
