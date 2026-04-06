<?php

declare(strict_types=1);
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

            match ($reportType) {
                'cashflow-statement' => $this->writeCashFlowStatementCsv($handle, $data),
                'branch-comparison' => $this->writeBranchComparisonCsv($handle, $data),
                'category-trend' => $this->writeCategoryTrendCsv($handle, $data),
                'vendor-outstanding' => $this->writeVendorOutstandingCsv($handle, $data),
                'staff-advance' => $this->writeStaffAdvanceCsv($handle, $data),
                'transfer-log' => $this->writeTransferLogCsv($handle, $data),
                'flagged-entries' => $this->writeFlaggedEntriesCsv($handle, $data),
                'daily-movement' => $this->writeDailyMovementCsv($handle, $data),
                'dormant-vendors' => $this->writeDormantVendorsCsv($handle, $data),
                default => $this->writeGenericCsv($handle, $data),
            };

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
        return match ($reportType) {
            'cashflow-statement' => $this->reportService->cashFlowStatement($accountId, $filters),
            'branch-comparison' => $this->reportService->branchComparison($accountId, $filters),
            'category-trend' => $this->reportService->categoryTrend($accountId, $filters),
            'vendor-outstanding' => $this->reportService->vendorOutstanding($accountId),
            'staff-advance' => $this->reportService->staffAdvanceSummary($accountId),
            'transfer-log' => $this->reportService->transferLog($accountId, $filters),
            'flagged-entries' => $this->reportService->flaggedEntries($accountId, $filters),
            'daily-movement' => $this->reportService->dailyMovement($accountId, $filters),
            'dormant-vendors' => $this->reportService->dormantVendors($accountId),
            default => [],
        };
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
        fputcsv($handle, ['Pool', 'Branch', 'Type', 'Opening Balance', 'Current Balance']);
        foreach (($data['pool_breakdown'] ?? []) as $pool) {
            $branchName = $pool['location']['name'] ?? '—';
            fputcsv($handle, [$pool['name'] ?? '', $branchName, $pool['type'] ?? '', number_format($pool['opening_balance'] ?? 0), number_format($pool['cached_balance'] ?? 0)]);
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
        fputcsv($handle, ['Date', 'Amount', 'From Pool', 'To Pool', 'Method', 'Reference', 'Created By', 'Status']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['transfer_date'] ?? '',
                number_format($row['amount'] ?? 0),
                $row['from_pool']['name'] ?? '',
                $row['to_pool']['name'] ?? '',
                $row['method'] ?? '',
                $row['reference_no'] ?? '',
                $row['creator']['name'] ?? '',
                ($row['is_voided'] ?? false) ? 'Voided' : 'Active',
            ]);
        }
    }

    private function writeFlaggedEntriesCsv($handle, array $data): void
    {
        fputcsv($handle, ['Date', 'Description', 'Amount', 'Category', 'Branch', 'Pool', 'Vendor', 'Flag Reason', 'Status', 'Created By']);
        foreach ($data as $row) {
            $branchName = $row['for_branch']['name'] ?? (($row['is_for_general'] ?? false) ? 'General / Company-wide' : '');
            fputcsv($handle, [
                $row['expense_date'] ?? '',
                $row['description'] ?? '',
                number_format($row['amount'] ?? 0),
                $row['category']['name'] ?? '',
                $branchName,
                $row['paid_from_pool']['name'] ?? '',
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

    private function writeDailyMovementCsv($handle, array $data): void
    {
        fputcsv($handle, ['Date', 'Pool', 'Type', 'Amount']);
        foreach (($data['expenses'] ?? []) as $row) {
            fputcsv($handle, [$row['date'] ?? '', $row['pool_name'] ?? '', 'Expense', number_format($row['total'] ?? 0)]);
        }
        foreach (($data['transfers_out'] ?? []) as $row) {
            fputcsv($handle, [$row['date'] ?? '', $row['pool_name'] ?? '', 'Transfer Out', number_format($row['total'] ?? 0)]);
        }
        foreach (($data['transfers_in'] ?? []) as $row) {
            fputcsv($handle, [$row['date'] ?? '', $row['pool_name'] ?? '', 'Transfer In', number_format($row['total'] ?? 0)]);
        }
        foreach (($data['staff_advances'] ?? []) as $row) {
            fputcsv($handle, [$row['date'] ?? '', $row['pool_name'] ?? '', 'Staff Advance', number_format($row['total'] ?? 0)]);
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
