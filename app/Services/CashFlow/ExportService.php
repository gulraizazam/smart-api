<?php

namespace App\Services\CashFlow;

use Illuminate\Support\Facades\Response;

class ExportService
{
    private ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Export a report as CSV (Excel-compatible).
     */
    public function exportCsv(string $reportType, int $accountId, array $filters): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->getReportData($reportType, $accountId, $filters);
        $filename = 'cashflow_' . $reportType . '_' . date('Y-m-d') . '.csv';

        return Response::streamDownload(function () use ($data, $reportType) {
            $handle = fopen('php://output', 'w');

            switch ($reportType) {
                case 'cashflow-statement':
                    $this->writeCashFlowStatementCsv($handle, $data);
                    break;
                case 'branch-comparison':
                    $this->writeBranchComparisonCsv($handle, $data);
                    break;
                case 'category-trend':
                    $this->writeCategoryTrendCsv($handle, $data);
                    break;
                case 'vendor-outstanding':
                    $this->writeVendorOutstandingCsv($handle, $data);
                    break;
                case 'staff-advance':
                    $this->writeStaffAdvanceCsv($handle, $data);
                    break;
                case 'transfer-log':
                    $this->writeTransferLogCsv($handle, $data);
                    break;
                case 'flagged-entries':
                    $this->writeFlaggedEntriesCsv($handle, $data);
                    break;
                case 'dormant-vendors':
                    $this->writeDormantVendorsCsv($handle, $data);
                    break;
                default:
                    $this->writeGenericCsv($handle, $data);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get report data by type.
     */
    private function getReportData(string $reportType, int $accountId, array $filters): array
    {
        switch ($reportType) {
            case 'cashflow-statement':
                return $this->reportService->cashFlowStatement($accountId, $filters);
            case 'branch-comparison':
                return $this->reportService->branchComparison($accountId, $filters);
            case 'category-trend':
                return $this->reportService->categoryTrend($accountId, $filters);
            case 'vendor-outstanding':
                return $this->reportService->vendorOutstanding($accountId);
            case 'staff-advance':
                return $this->reportService->staffAdvanceSummary($accountId);
            case 'transfer-log':
                return $this->reportService->transferLog($accountId, $filters);
            case 'flagged-entries':
                return $this->reportService->flaggedEntries($accountId, $filters);
            case 'dormant-vendors':
                return $this->reportService->dormantVendors($accountId);
            default:
                return [];
        }
    }

    private function writeCashFlowStatementCsv($handle, array $data): void
    {
        fputcsv($handle, ['Cash Flow Statement']);
        fputcsv($handle, ['Period', ($data['period']['from'] ?? '') . ' to ' . ($data['period']['to'] ?? '')]);
        fputcsv($handle, []);

        fputcsv($handle, ['A. Opening Balance', number_format($data['opening_balance'] ?? 0)]);
        fputcsv($handle, []);

        fputcsv($handle, ['B. Inflows']);
        fputcsv($handle, ['Payment Method', 'Amount', 'Count']);
        foreach (($data['inflows'] ?? []) as $row) {
            fputcsv($handle, [$row['method'] ?? '', number_format($row['total'] ?? 0), $row['count'] ?? 0]);
        }
        fputcsv($handle, ['Total Inflows', number_format($data['total_inflows'] ?? 0)]);
        fputcsv($handle, []);

        fputcsv($handle, ['C. Outflows (by Category)']);
        fputcsv($handle, ['Category', 'Amount', 'Count']);
        foreach (($data['outflows'] ?? []) as $row) {
            fputcsv($handle, [$row['category'] ?? '', number_format($row['total'] ?? 0), $row['count'] ?? 0]);
        }
        fputcsv($handle, ['Total Outflows', number_format($data['total_outflows'] ?? 0)]);
        fputcsv($handle, []);

        fputcsv($handle, ['D. Net Cash Flow', number_format($data['net_cash_flow'] ?? 0)]);
        fputcsv($handle, ['E. Closing Balance', number_format($data['closing_balance'] ?? 0)]);
        fputcsv($handle, []);

        fputcsv($handle, ['F. Pool Breakdown']);
        fputcsv($handle, ['Pool', 'Type', 'Balance']);
        foreach (($data['pool_breakdown'] ?? []) as $pool) {
            fputcsv($handle, [$pool['name'] ?? '', $pool['type'] ?? '', number_format($pool['cached_balance'] ?? 0)]);
        }
    }

    private function writeBranchComparisonCsv($handle, array $data): void
    {
        fputcsv($handle, ['Branch', 'Inflows', 'Outflows', 'Expense Count', 'Net']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['branch_name'] ?? '',
                number_format($row['inflows'] ?? 0),
                number_format($row['outflows'] ?? 0),
                $row['expense_count'] ?? 0,
                number_format($row['net'] ?? 0),
            ]);
        }
    }

    private function writeCategoryTrendCsv($handle, array $data): void
    {
        fputcsv($handle, ['Category', 'Month', 'Amount']);
        foreach ($data as $row) {
            fputcsv($handle, [$row['category'] ?? '', $row['month'] ?? '', number_format($row['total'] ?? 0)]);
        }
    }

    private function writeVendorOutstandingCsv($handle, array $data): void
    {
        fputcsv($handle, ['Vendor', 'Opening Balance', 'Current Balance', 'Payment Terms', 'Active']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['name'] ?? '',
                number_format($row['opening_balance'] ?? 0),
                number_format($row['cached_balance'] ?? 0),
                $row['payment_terms'] ?? '',
                ($row['is_active'] ?? false) ? 'Yes' : 'No',
            ]);
        }
    }

    private function writeStaffAdvanceCsv($handle, array $data): void
    {
        fputcsv($handle, ['Staff', 'Total Advances', 'Total Expenses', 'Total Returns', 'Outstanding', 'Last Advance', 'Days Since', 'Aging']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['name'] ?? '',
                number_format($row['total_advances'] ?? 0),
                number_format($row['total_expenses'] ?? 0),
                number_format($row['total_returns'] ?? 0),
                number_format($row['outstanding'] ?? 0),
                $row['last_advance'] ?? '',
                $row['days_since_last'] ?? '',
                $row['aging'] ?? '',
            ]);
        }
    }

    private function writeTransferLogCsv($handle, array $data): void
    {
        fputcsv($handle, ['Date', 'Amount', 'From Pool', 'To Pool', 'Method', 'Reference', 'Created By']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['transfer_date'] ?? '',
                number_format($row['amount'] ?? 0),
                $row['from_pool']['name'] ?? '',
                $row['to_pool']['name'] ?? '',
                $row['method'] ?? '',
                $row['reference_no'] ?? '',
                $row['creator']['name'] ?? '',
            ]);
        }
    }

    private function writeFlaggedEntriesCsv($handle, array $data): void
    {
        fputcsv($handle, ['Date', 'Amount', 'Category', 'Pool', 'Vendor', 'Flag Reason', 'Status', 'Created By']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['expense_date'] ?? '',
                number_format($row['amount'] ?? 0),
                $row['category']['name'] ?? '',
                $row['pool']['name'] ?? '',
                $row['vendor']['name'] ?? '',
                $row['flag_reason'] ?? '',
                $row['status'] ?? '',
                $row['creator']['name'] ?? '',
            ]);
        }
    }

    private function writeDormantVendorsCsv($handle, array $data): void
    {
        fputcsv($handle, ['Vendor', 'Balance', 'Last Activity', 'Days Inactive']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['name'] ?? '',
                number_format($row['cached_balance'] ?? 0),
                $row['last_activity'] ?? 'Never',
                $row['days_inactive'] ?? 'N/A',
            ]);
        }
    }

    private function writeGenericCsv($handle, array $data): void
    {
        if (empty($data)) return;

        $first = reset($data);
        if (is_array($first)) {
            fputcsv($handle, array_keys($first));
            foreach ($data as $row) {
                fputcsv($handle, array_values($row));
            }
        }
    }
}
