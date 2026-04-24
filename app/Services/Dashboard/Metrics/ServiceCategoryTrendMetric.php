<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\ConsumptionAwareCollectionQuery;
use Illuminate\Support\Facades\Cache;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Net-cash collections by top-level service category over a trailing-months
 * window. Drives the "category collections trend" table on the Overview.
 *
 * Each month column is the net cash collected in that month (by
 * `package_advances.created_at`) attributed to each top-level category via
 * pro-rata of each service line's `tax_including_price` share of its package.
 * Summing a category's row across months equals that category's slice of
 * SalesLedgerQuery::totalNet over the same window — reconcilable with the
 * Stats "Sales" tile.
 *
 * Collection definition matches SalesLedgerQuery::netCollectionSelectRaw:
 * cash-in via Cash/Card/Bank-Wire minus refund-outs, excluding adjustments,
 * tax rows, cancellations, and soft-deleted rows. Branch scoping uses
 * `pa.location_id` (payment branch), same as the Sales Momentum tile.
 *
 * Top-level category = services.parent_id IS NULL. Services with a parent
 * roll up to the nearest ancestor whose parent_id is NULL. $range is used
 * only to anchor the end month for the trailing-N window.
 *
 * Return shape:
 *   months:     list of "YYYY-MM" labels, oldest first
 *   categories: list of { id, name }
 *   series:     { [categoryId]: list<float> }  (values align with months)
 */
final class ServiceCategoryTrendMetric implements Metric
{
    private const CACHE_TTL = 300;

    /**
     * @return array{months: list<string>, categories: list<array{id: int|string, name: string}>, series: array<int|string, list<float>>, other_members: list<array{id: int, name: string, total: float}>}
     */
    public function compute(MetricScope $scope, DateRange $range, int $months = 12): array
    {
        if ($scope->isDenyAll()) {
            return ['months' => [], 'categories' => [], 'series' => [], 'other_members' => []];
        }

        $cacheKey = 'mgmt_dash:service_category_trend:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString()
            .'|m='.$months;

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope, $range, $months));
    }

    /**
     * @return array{months: list<string>, categories: list<array{id: int|string, name: string}>, series: array<int|string, list<float>>, other_members: list<array{id: int, name: string, total: float}>}
     */
    private function build(MetricScope $scope, DateRange $range, int $months): array
    {
        $end = CarbonImmutable::parse($range->endString())->endOfMonth();
        $first = $end->subMonthsNoOverflow($months - 1)->startOfMonth();
        $monthLabels = [];
        $labelToIdx = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $label = $end->subMonthsNoOverflow($i)->format('Y-m');
            $labelToIdx[$label] = count($monthLabels);
            $monthLabels[] = $label;
        }

        $categoryMap = $this->topLevelCategoryMap($scope->accountId);

        $rawSeries = [];
        foreach ($categoryMap['categories'] as $category) {
            $rawSeries[$category['id']] = array_fill(0, count($monthLabels), 0.0);
        }

        // Two queries (direct + pro-rated) cover the whole window, bucketed
        // by month in SQL — then rolled up from service to parent category.
        $byMonth = ConsumptionAwareCollectionQuery::sumByMonthAndService(
            $scope,
            $first->format('Y-m-d'),
            $end->format('Y-m-d'),
        );

        foreach ($byMonth as $label => $services) {
            $idx = $labelToIdx[$label] ?? null;
            if ($idx === null) {
                continue;
            }
            foreach ($services as $serviceId => $value) {
                $parentId = $categoryMap['service_to_parent'][$serviceId] ?? null;
                if ($parentId === null || ! isset($rawSeries[$parentId])) {
                    continue;
                }
                $rawSeries[$parentId][$idx] = round($rawSeries[$parentId][$idx] + (float) $value, 2);
            }
        }

        return $this->assembleAll($rawSeries, $categoryMap['categories'], $monthLabels);
    }

    /**
     * Return every category that collected non-zero revenue in the window,
     * sorted by total revenue descending. No top-N cap, no "Other" bucket —
     * the view-layer scrolls.
     *
     * @param  array<int, list<float>>  $rawSeries
     * @param  list<array{id: int, name: string}>  $allCategories
     * @param  list<string>  $monthLabels
     * @return array{
     *   months: list<string>,
     *   categories: list<array{id: int, name: string}>,
     *   series: array<int, list<float>>,
     *   other_members: list<array{id: int, name: string, total: float}>
     * }
     */
    private function assembleAll(array $rawSeries, array $allCategories, array $monthLabels): array
    {
        $totals = [];
        foreach ($rawSeries as $categoryId => $values) {
            $totals[$categoryId] = array_sum($values);
        }
        arsort($totals);

        $namesById = [];
        foreach ($allCategories as $cat) {
            $namesById[$cat['id']] = $cat['name'];
        }

        $categories = [];
        $series = [];
        foreach ($totals as $categoryId => $total) {
            if ($total <= 0) {
                continue;
            }
            $id = (int) $categoryId;
            $categories[] = ['id' => $id, 'name' => $namesById[$id] ?? "Category #{$id}"];
            $series[$id] = $rawSeries[$categoryId];
        }

        return [
            'months' => $monthLabels,
            'categories' => $categories,
            'series' => $series,
            'other_members' => [],
        ];
    }

    /**
     * @return array{categories: list<array{id: int, name: string}>, service_to_parent: array<int, int>}
     */
    private function topLevelCategoryMap(int $accountId): array
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

        foreach ($byId as $id => $row) {
            $ancestor = $row;
            $visited = [];
            while ($ancestor->parent_id !== null && isset($byId[(int) $ancestor->parent_id])) {
                if (isset($visited[(int) $ancestor->id])) {
                    break;
                }
                $visited[(int) $ancestor->id] = true;
                $ancestor = $byId[(int) $ancestor->parent_id];
            }

            $topId = (int) $ancestor->id;
            $serviceToParent[$id] = $topId;

            if ($ancestor->parent_id === null && ! isset($topLevel[$topId])) {
                $topLevel[$topId] = ['id' => $topId, 'name' => (string) $ancestor->name];
            }
        }

        return [
            'categories' => array_values($topLevel),
            'service_to_parent' => $serviceToParent,
        ];
    }

    /**
     * Net collection attributed to each top-level category for a single
     * calendar month. Pro-rates each package_advance net-cash amount across
     * the package's service lines by tax_including_price share, then rolls
     * each service into its top-level parent via $serviceToParent.
     *
     * @param  array<int, int>  $serviceToParent
     * @return array<int, float> [categoryId => collection]
     */
}
