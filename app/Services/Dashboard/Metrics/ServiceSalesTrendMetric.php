<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\ConsumptionAwareCollectionQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Net-cash collections per INDIVIDUAL service over a trailing-months window
 * — sibling to ServiceCategoryTrendMetric but without the parent-rollup.
 * The Overview table uses this to drill from "top-level categories" into
 * "named services" when management wants the granular breakdown.
 *
 * Same net-cash semantic as ServiceCategoryTrendMetric: each month column
 * is the net collection recorded in that month (by pa.created_at), pro-
 * rated across each package's service lines by tax_including_price share.
 * Collection filter = SalesLedgerQuery::netCollectionSelectRaw (cash-in
 * via Cash/Card/Bank-Wire minus refund-outs, adjustments/tax/cancels/soft-
 * deletes excluded). Branch filter on pa.location_id.
 *
 * Same top-N + Other bucketing and same response shape as the category
 * metric so the frontend can reuse its table renderer unchanged.
 *
 * Return shape:
 *   months:     list of "YYYY-MM" labels, oldest first
 *   categories: list of { id, name } — named "categories" for response
 *               compatibility; these are service IDs/names here
 *   series:     { [serviceId]: list<float> }  (values align with months)
 *   other_members: list<{ id, name, total }> — long tail rolled up into "Other"
 */
final class ServiceSalesTrendMetric implements Metric
{
    /**
     * Label used for the one edge-case row where revenue could not be
     * attributed to a specific service (package has a package_services
     * row with a NULL service_id — a data bug that still carries money).
     * Shown as its own line so the total stays reconcilable.
     */
    private const UNASSIGNED_LABEL = 'Unassigned';

    private const CACHE_TTL = 300;

    /**
     * @return array{months: list<string>, categories: list<array{id: int|string, name: string}>, series: array<int|string, list<float>>, other_members: list<array{id: int, name: string, total: float}>}
     */
    public function compute(MetricScope $scope, DateRange $range, int $months = 12): array
    {
        if ($scope->isDenyAll()) {
            return ['months' => [], 'categories' => [], 'series' => [], 'other_members' => []];
        }

        $cacheKey = 'mgmt_dash:service_sales_trend:'
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

        // Two queries (direct + pro-rated) cover the whole window and
        // bucket by YYYY-MM in SQL. 12-month trend = 2 queries, not 24.
        $byMonth = ConsumptionAwareCollectionQuery::sumByMonthAndService(
            $scope,
            $first->format('Y-m-d'),
            $end->format('Y-m-d'),
        );

        $rawSeries = [];
        foreach ($byMonth as $label => $services) {
            $idx = $labelToIdx[$label] ?? null;
            if ($idx === null) {
                continue;
            }
            foreach ($services as $serviceId => $value) {
                if (! isset($rawSeries[$serviceId])) {
                    $rawSeries[$serviceId] = array_fill(0, count($monthLabels), 0.0);
                }
                $rawSeries[$serviceId][$idx] = round((float) $value, 2);
            }
        }

        $serviceInfo = $this->serviceInfo(array_keys($rawSeries), $scope->accountId);

        return $this->assembleAll($rawSeries, $serviceInfo, $monthLabels);
    }

    /**
     * Return every service that collected non-zero revenue in the window,
     * sorted by total revenue descending. No top-N cap, no "Other"
     * bucket — the view-layer scrolls.
     *
     * @param  array<int, list<float>>  $rawSeries
     * @param  array<int, array{name: string, parent_id: int|null}>  $infoById
     * @param  list<string>  $monthLabels
     * @return array{
     *   months: list<string>,
     *   categories: list<array{id: int, name: string}>,
     *   series: array<int, list<float>>,
     *   other_members: list<array{id: int, name: string, total: float}>
     * }
     */
    private function assembleAll(array $rawSeries, array $infoById, array $monthLabels): array
    {
        $totals = [];
        foreach ($rawSeries as $serviceId => $values) {
            $totals[$serviceId] = array_sum($values);
        }
        arsort($totals);

        $categories = [];
        $series = [];
        foreach ($totals as $serviceId => $total) {
            if ($total <= 0) {
                continue;
            }
            $id = (int) $serviceId;
            $name = $id === 0
                ? self::UNASSIGNED_LABEL
                : ($infoById[$id]['name'] ?? "Service #{$id}");
            $categories[] = ['id' => $id, 'name' => $name];
            $series[$id] = $rawSeries[$serviceId];
        }

        return [
            'months' => $monthLabels,
            'categories' => $categories,
            'series' => $series,
            'other_members' => [],
        ];
    }

    /**
     * Resolve name + parent_id for the service IDs that appeared in the
     * window. One lookup for the whole window.
     *
     * @param  list<int>  $serviceIds
     * @return array<int, array{name: string, parent_id: int|null}>
     */
    private function serviceInfo(array $serviceIds, int $accountId): array
    {
        if ($serviceIds === []) {
            return [];
        }
        $rows = DB::table('services')
            ->where('account_id', $accountId)
            ->whereIn('id', $serviceIds)
            ->select('id', 'name', 'parent_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = [
                'name' => (string) $row->name,
                'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
            ];
        }

        return $out;
    }
}
