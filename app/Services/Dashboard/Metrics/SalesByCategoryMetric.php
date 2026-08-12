<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\DashboardHelper;
use App\Models\Invoices;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sales by top-level service category for an arbitrary date range +
 * branch scope. Queries `invoices` + `invoice_details` so it includes
 * ALL paid services in the period — one-off treatments billed directly,
 * treatments delivered from packages, memberships, everything — unlike
 * ServiceCategoryTrendMetric which only sees payments tied to a package
 * via `package_advances`.
 *
 * Grouping: each invoice_details.service_id is resolved to its top-level
 * category (ancestor whose parent_id is NULL). Sales sum by parent.
 * Invoice status filter uses DashboardHelper::getPaidInvoiceStatusId(),
 * matching the legacy DashboardRevenueService::getRevenueByServiceCategory.
 *
 * Return shape (flat, no time bucketing):
 *   categories: list of { id, name, total }, sorted total desc
 *   total:      grand total across all categories
 */
final class SalesByCategoryMetric implements Metric
{
    private const CACHE_TTL = 300;

    /**
     * @return array{
     *   categories: list<array{id: int, name: string, total: float}>,
     *   total: float
     * }
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['categories' => [], 'total' => 0.0];
        }

        $cacheKey = 'mgmt_dash:sales_by_category:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope, $range));
    }

    /**
     * @return array{
     *   categories: list<array{id: int, name: string, total: float}>,
     *   total: float
     * }
     */
    private function build(MetricScope $scope, DateRange $range): array
    {
        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();

        // Branch scoping: MetricScope narrows to the caller's assigned
        // centres by default; the picker on the top-bar may narrow
        // further via `branchIds`.
        $branchIds = $scope->isBranchScoped() && $scope->branchIds !== null
            ? $scope->branchIds
            : null;

        $query = Invoices::query()
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereBetween('invoices.created_at', [
                $range->startString().' 00:00:00',
                $range->endString().' 23:59:59',
            ])
            ->where('invoices.invoice_status_id', $invoiceStatusId)
            ->where('invoices.account_id', $scope->accountId);

        if ($branchIds !== null && $branchIds !== []) {
            $query->whereIn('invoices.location_id', $branchIds);
        }

        $records = $query
            ->select(
                'invoice_details.service_id',
                DB::raw('SUM(invoices.total_price) AS total_price'),
            )
            ->groupBy('invoice_details.service_id')
            ->get();

        if ($records->isEmpty()) {
            return ['categories' => [], 'total' => 0.0];
        }

        // Resolve each service_id → its top-level parent. Loads only the
        // services referenced by the aggregated rows (bounded fanout).
        $serviceIds = $records->pluck('service_id')->filter()->unique()->values()->all();
        if ($serviceIds === []) {
            return ['categories' => [], 'total' => 0.0];
        }

        [$topLevel, $serviceToParent] = $this->buildCategoryMap($scope->accountId, $serviceIds);

        $grouped = [];
        $grandTotal = 0.0;

        foreach ($records as $record) {
            $sid = (int) ($record->service_id ?? 0);
            if ($sid === 0) {
                continue;
            }
            $parentId = $serviceToParent[$sid] ?? null;
            if ($parentId === null || ! isset($topLevel[$parentId])) {
                continue;
            }
            $amount = (float) $record->total_price;
            $grouped[$parentId] ??= 0.0;
            $grouped[$parentId] += $amount;
            $grandTotal += $amount;
        }

        arsort($grouped);

        $categories = [];
        foreach ($grouped as $catId => $total) {
            if ($total <= 0) {
                continue;
            }
            $categories[] = [
                'id' => (int) $catId,
                'name' => $topLevel[$catId] ?? "Category #{$catId}",
                'total' => round($total, 2),
            ];
        }

        return [
            'categories' => $categories,
            'total' => round($grandTotal, 2),
        ];
    }

    /**
     * Load every service in the account, walk each service's parent chain
     * to the top-level ancestor (parent_id IS NULL), and produce both:
     *   - the top-level id → name map (only ancestors that ARE top-level)
     *   - the service id → top-level parent id map (for grouping)
     *
     * Walks the full services table (bounded per account) so subsequent
     * ancestor lookups don't trigger N+1 queries.
     *
     * @param  list<int>  $referencedServiceIds
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    private function buildCategoryMap(int $accountId, array $referencedServiceIds): array
    {
        $rows = DB::table('services')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'parent_id')
            ->get();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->id] = $row;
        }

        $topLevel = [];
        $serviceToParent = [];

        foreach ($referencedServiceIds as $sid) {
            if (! isset($byId[$sid])) {
                continue;
            }
            $ancestor = $byId[$sid];
            $visited = [];
            while ($ancestor->parent_id !== null && isset($byId[(int) $ancestor->parent_id])) {
                if (isset($visited[(int) $ancestor->id])) {
                    break;
                }
                $visited[(int) $ancestor->id] = true;
                $ancestor = $byId[(int) $ancestor->parent_id];
            }

            $topId = (int) $ancestor->id;
            $serviceToParent[$sid] = $topId;

            // Only surface as a top-level category when the ancestor
            // itself has no parent. Broken parent chains fall through
            // silently (revenue is dropped from the chart) rather than
            // showing a mid-tree node as a category.
            if ($ancestor->parent_id === null) {
                $topLevel[$topId] = (string) $ancestor->name;
            }
        }

        return [$topLevel, $serviceToParent];
    }
}
