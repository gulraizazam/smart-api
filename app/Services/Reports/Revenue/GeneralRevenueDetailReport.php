<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\GeneralFunctions;
use App\Models\Locations;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class GeneralRevenueDetailReport
{
    /**
     * Generate the general revenue detail report.
     *
     * Returns data keyed to match existing Blade view expectations.
     */
    public function generate(
        ?string $startDate,
        ?string $endDate,
        array $locationIds,
        int $accountId,
        string $genderId = 'all',
    ): array {
        $reportData = [];

        foreach ($locationIds as $locationId) {
            $query = PackageAdvances::with(['user:id,name,gender,phone', 'paymentmode'])
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('location_id', $locationId)
                ->where('account_id', $accountId)
                ->orderBy('created_at', 'asc');

            if ($genderId !== 'all') {
                $query->whereHas('user', fn ($q) => $q->where('gender', $genderId));
            }

            $advances = $query->get();
            $location = Locations::find($locationId);

            if (! $location) {
                continue;
            }

            $balance = 0;
            $reportData[$location->id] = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city->name,
                'region' => $location->region->name,
                'revenue_data' => [],
            ];

            foreach ($advances as $advance) {
                if (! $this->isRevenueTransaction($advance)) {
                    continue;
                }

                $balance = match ($advance->cash_flow) {
                    'in' => $balance + $advance->cash_amount,
                    'out' => $balance - $advance->cash_amount,
                    default => $balance,
                };

                if ($advance->cash_amount == 0) {
                    continue;
                }

                $transtype = $this->resolveTransactionType($advance);
                [$revenueCash, $revenueCard, $revenueBank, $refundOut] = $this->categorizeByPaymentMode($advance);
                [$refundCash, $refundCard, $refundBank] = $this->categorizeRefundByPaymentMode($advance);

                $genderLabel = $advance->user->gender == 1 ? 'Male' : 'Female';

                $reportData[$location->id]['revenue_data'][$advance->id] = [
                    'patient_id' => $advance->patient_id,
                    'patient' => $advance->user->name,
                    'gender' => $genderLabel,
                    'phone' => GeneralFunctions::prepareNumber4Call($advance->user->phone),
                    'transtype' => $transtype,
                    'payment_mode_id' => $advance->payment_mode_id,
                    'payment_mode' => $advance->paymentmode->name ?? 'Cash',
                    'cash_flow' => $advance->cash_flow,
                    'revenue_cash_in' => $revenueCash,
                    'revenue_card_in' => $revenueCard,
                    'revenue_bank_in' => $revenueBank,
                    'refund_cash_in' => $refundCash,
                    'refund_card_in' => $refundCard,
                    'refund_bank_in' => $refundBank,
                    'refund_out' => $refundCash + $refundCard + $refundBank,
                    'Balance' => $balance,
                    'created_at' => Carbon::parse($advance->created_at)->format('F j,Y h:i A'),
                ];
            }
        }

        return $this->buildResult($reportData, $startDate, $endDate);
    }

    /**
     * Generate Excel export for this report.
     * Preserves exact same output as the original GeneralRevenueReportExcel method.
     */
    public function generateExcel(array $result): void
    {
        $reportData = $result['report_data'];
        $totalRevenueCashIn = $result['total_revenue_cash_in'];
        $totalRevenueCardIn = $result['total_revenue_card_in'];
        $totalRevenueBankIn = $result['total_revenue_bank_in'];
        $totalRefund = $result['total_refund'];
        $totalRevenue = $result['total_revenue'];
        $startDate = $result['start_date'];
        $endDate = $result['end_date'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $sheet->setCellValue('B1', "From {$startDate} to {$endDate}");
        $sheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $sheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));
        $sheet->setCellValue('A3', '');

        $sheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $sheet->setCellValue('B4', 'Patient Name')->getStyle('B4')->getFont()->setBold(true);
        $sheet->setCellValue('C4', 'Gender')->getStyle('C4')->getFont()->setBold(true);
        $sheet->setCellValue('D4', 'Transaction Type')->getStyle('D4')->getFont()->setBold(true);
        $sheet->setCellValue('E4', 'Revenue Cash In')->getStyle('E4')->getFont()->setBold(true);
        $sheet->setCellValue('F4', 'Revenue Card In')->getStyle('F4')->getFont()->setBold(true);
        $sheet->setCellValue('G4', 'Revenue Bank/Wire In')->getStyle('G4')->getFont()->setBold(true);
        $sheet->setCellValue('H4', 'Refund/Out')->getStyle('H4')->getFont()->setBold(true);
        $sheet->setCellValue('I4', 'Created At')->getStyle('I4')->getFont()->setBold(true);
        $sheet->setCellValue('A5', '');

        $counter = 6;

        if ($reportData) {
            foreach ($reportData as $location) {
                $locCash = 0;
                $locCard = 0;
                $locBank = 0;
                $locRefund = 0;

                $sheet->setCellValue("A{$counter}", $location['name'])->getStyle("A{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$counter}", $location['city'])->getStyle("B{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("C{$counter}", $location['region'])->getStyle("C{$counter}")->getFont()->setBold(true);
                $counter++;
                $sheet->setCellValue("A{$counter}", '');
                $counter++;

                foreach ($location['revenue_data'] as $row) {
                    $locCash += $row['revenue_cash_in'] ?: 0;
                    $locCard += $row['revenue_card_in'] ?: 0;
                    $locBank += $row['revenue_bank_in'] ?: 0;
                    $locRefund += $row['refund_out'] ?: 0;

                    $sheet->setCellValue("A{$counter}", $row['patient_id']);
                    $sheet->setCellValue("B{$counter}", $row['patient']);
                    $sheet->setCellValue("C{$counter}", $row['gender']);
                    $sheet->setCellValue("D{$counter}", $row['transtype']);
                    if ($row['revenue_cash_in']) {
                        $sheet->setCellValue("E{$counter}", number_format($row['revenue_cash_in'], 2));
                    }
                    if ($row['revenue_card_in']) {
                        $sheet->setCellValue("F{$counter}", number_format($row['revenue_card_in'], 2));
                    }
                    if ($row['revenue_bank_in']) {
                        $sheet->setCellValue("G{$counter}", number_format($row['revenue_bank_in'], 2));
                    }
                    if ($row['refund_out']) {
                        $sheet->setCellValue("H{$counter}", number_format($row['refund_out'], 2));
                    }
                    $sheet->setCellValue("I{$counter}", $row['created_at']);
                    $counter++;
                }

                $sheet->setCellValue("A{$counter}", '');
                $counter++;
                $sheet->setCellValue("A{$counter}", $location['name'])->getStyle("A{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$counter}", 'Total')->getStyle("B{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("D{$counter}", number_format($locCash, 2))->getStyle("D{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("E{$counter}", number_format($locCard, 2))->getStyle("E{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("F{$counter}", number_format($locBank, 2))->getStyle("F{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("G{$counter}", number_format($locRefund, 2))->getStyle("G{$counter}")->getFont()->setBold(true);
                $sheet->setCellValue("H{$counter}", number_format(($locCash + $locCard + $locBank) - $locRefund, 2))->getStyle("H{$counter}")->getFont()->setBold(true);
                $counter++;
            }

            $sheet->setCellValue("A{$counter}", '');
            $counter++;

            $sheet->setCellValue("A{$counter}", 'Revenue Cash In')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalRevenueCashIn, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Revenue Card In')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalRevenueCardIn, 2));
            $counter++;
            $sheet->setCellValue("A{$counter}", 'Revenue Bank/Wire In')->getStyle("A{$counter}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$counter}", number_format($totalRevenueBankIn, 2));
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

    private function buildResult(array $reportData, ?string $startDate, ?string $endDate): array
    {
        $totalCash = 0;
        $totalCard = 0;
        $totalBank = 0;
        $totalRefund = 0;

        foreach ($reportData as $location) {
            foreach ($location['revenue_data'] as $row) {
                $totalCash += $row['revenue_cash_in'] ?: 0;
                $totalCard += $row['revenue_card_in'] ?: 0;
                $totalBank += $row['revenue_bank_in'] ?: 0;
                $totalRefund += $row['refund_out'] ?: 0;
            }
        }

        $totalRevenue = $totalCash + $totalCard + $totalBank;

        return [
            'report_data' => $reportData,
            'total_revenue_cash_in' => $totalCash,
            'total_revenue_card_in' => $totalCard,
            'total_revenue_bank_in' => $totalBank,
            'total_refund' => $totalRefund,
            'total_revenue' => $totalRevenue,
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

    private function resolveTransactionType(PackageAdvances $advance): string
    {
        $transtype = '';

        if ($advance->package_id) {
            $transtype = Config::get('constants.trans_type.advance_in');
        }
        if ($advance->invoice_id && $advance->cash_flow === 'in') {
            $transtype = Config::get('constants.trans_type.advance_in');
        }
        if ($advance->is_adjustment == '1') {
            $transtype = Config::get('constants.trans_type.adjustment');
        }
        if ($advance->is_cancel == '1') {
            $transtype = Config::get('constants.trans_type.invoice_cancel');
        }
        if ($advance->invoice_id && $advance->cash_flow === 'out') {
            $transtype = Config::get('constants.trans_type.invoice_create');
        }
        if ($advance->is_refund == '1') {
            $transtype = Config::get('constants.trans_type.refund_in');
        }
        if ($advance->is_tax == '1') {
            $transtype = Config::get('constants.trans_type.tax_out');
        }

        // Inventory-module sales: a package_advances row written by
        // OrderService::createOrder / processRefund carries no
        // package_id / invoice_id / appointment_id and no flags besides
        // (optionally) is_refund. Label them so they read as "Product
        // Sale" / "Product Refund" in the report instead of an empty
        // transaction type. Falls through after the legacy checks above
        // so any future package_advances shape with one of those
        // discriminators keeps its existing label.
        if ($transtype === ''
            && empty($advance->package_id)
            && empty($advance->invoice_id)
            && empty($advance->appointment_id)
            && $advance->is_adjustment != '1'
            && $advance->is_cancel != '1'
            && $advance->is_tax != '1'
        ) {
            $transtype = $advance->cash_flow === 'out'
                ? 'Product Refund'
                : 'Product Sale';
        }

        return $transtype;
    }

    private function categorizeByPaymentMode(PackageAdvances $advance): array
    {
        if ($advance->cash_flow === 'in') {
            $paymentName = $advance->paymentmode->name ?? 'Cash';

            return match (true) {
                $paymentName === 'Cash' => [$advance->cash_amount, 0, 0, 0],
                $paymentName === 'Card' => [0, $advance->cash_amount, 0, 0],
                in_array($paymentName, ['Bank/Wire Transfer', 'Bank'], true) => [0, 0, $advance->cash_amount, 0],
                default => [$advance->cash_amount, 0, 0, 0],
            };
        }

        return [0, 0, 0, $advance->cash_amount];
    }

    private function categorizeRefundByPaymentMode(PackageAdvances $advance): array
    {
        if ($advance->cash_flow === 'out') {
            $paymentName = $advance->paymentmode->name ?? 'Cash';

            return match (true) {
                $paymentName === 'Cash' => [$advance->cash_amount, 0, 0],
                $paymentName === 'Card' => [0, $advance->cash_amount, 0],
                in_array($paymentName, ['Bank/Wire Transfer', 'Bank'], true) => [0, 0, $advance->cash_amount],
                default => [$advance->cash_amount, 0, 0],
            };
        }

        return [0, 0, 0];
    }
}
