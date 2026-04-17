<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Revenue by top-level service category over a trailing-months window. Drives
 * the "service category trend" stacked-area chart on the Branches section.
 *
 * Top-level category = services.parent_id IS NULL. Services with a parent roll
 * up to the nearest ancestor whose parent_id is NULL.
 *
 * The $range parameter is ignored for the trend window (which is always the
 * trailing N months anchored to the range's end date). $range is used only to
 * anchor the "end" month for the trend.
 *
 * Return shape:
 *   months:     list of "YYYY-MM" labels, oldest first
 *   categories: list of { id, name }
 *   series:     { [categoryId]: list<float> }  (values align with months)
 */
final class ServiceCategoryTrendMetric implements Metric
{
    /**
     * Limit displayed series to the top N categories by total revenue across
     * the trend window. The remaining long tail is bucketed into a single
     * "Other" series so the stacked chart stays legible.
     */
    private const TOP_N = 10;

    /**
     * @return array{months: list<string>, categories: list<array{id: int|string, name: string}>, series: array<int|string, list<float>>, other_members: list<array{id: int, name: string, total: float}>}
     */
    public function compute(MetricScope $scope, DateRange $range, int $months = 12): array
    {
        if ($scope->isDenyAll()) {
            return ['months' => [], 'categories' => [], 'series' => [], 'other_members' => []];
        }

        $end = CarbonImmutable::parse($range->endString())->endOfMonth();
        $monthLabels = [];
        $monthBounds = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = $end->subMonthsNoOverflow($i);
            $monthLabels[] = $m->format('Y-m');
            $monthBounds[] = [
                'start' => $m->startOfMonth()->format('Y-m-d'),
                'end' => $m->endOfMonth()->format('Y-m-d'),
                'label' => $m->format('Y-m'),
            ];
        }

        $categoryMap = $this->topLevelCategoryMap($scope->accountId);

        $rawSeries = [];
        foreach ($categoryMap['categories'] as $category) {
            $rawSeries[$category['id']] = array_fill(0, count($monthLabels), 0.0);
        }

        foreach ($monthBounds as $idx => $bounds) {
            $revenueByCategory = $this->revenueByCategory(
                $scope,
                $bounds['start'],
                $bounds['end'],
                $categoryMap['service_to_parent'],
            );

            foreach ($revenueByCategory as $categoryId => $value) {
                if (isset($rawSeries[$categoryId])) {
                    $rawSeries[$categoryId][$idx] = round((float) $value, 2);
                }
            }
        }

        return $this->reduceToTopN($rawSeries, $categoryMap['categories'], $monthLabels);
    }

    /**
     * Keep the TOP_N highest-revenue categories; sum the rest into "Other".
     * Drops categories that contribute zero revenue across the entire window.
     *
     * Returned `other_members` lists what was bucketed into Other (sorted by
     * contribution desc) so the frontend can render an explanatory footnote.
     *
     * @param  array<int, list<float>>  $rawSeries
     * @param  list<array{id: int, name: string}>  $allCategories
     * @param  list<string>  $monthLabels
     * @return array{
     *   months: list<string>,
     *   categories: list<array{id: int|string, name: string}>,
     *   series: array<int|string, list<float>>,
     *   other_members: list<array{id: int, name: string, total: float}>
     * }
     */
    private function reduceToTopN(array $rawSeries, array $allCategories, array $monthLabels): array
    {
        $totals = [];
        foreach ($rawSeries as $categoryId => $values) {
            $totals[$categoryId] = array_sum($values);
        }
        arsort($totals);

        $kept = [];
        $rest = [];
        $rank = 0;
        foreach ($totals as $categoryId => $total) {
            if ($total <= 0) {
                continue;
            }
            if ($rank < self::TOP_N) {
                $kept[] = $categoryId;
            } else {
                $rest[] = $categoryId;
            }
            $rank++;
        }

        $namesById = [];
        foreach ($allCategories as $cat) {
            $namesById[$cat['id']] = $cat['name'];
        }

        $categories = [];
        $series = [];
        foreach ($kept as $categoryId) {
            $categories[] = ['id' => $categoryId, 'name' => $namesById[$categoryId] ?? "Category #{$categoryId}"];
            $series[$categoryId] = $rawSeries[$categoryId];
        }

        $otherMembers = [];
        if ($rest !== []) {
            $other = array_fill(0, count($monthLabels), 0.0);
            foreach ($rest as $categoryId) {
                foreach ($rawSeries[$categoryId] as $i => $v) {
                    $other[$i] += $v;
                }
                $otherMembers[] = [
                    'id' => (int) $categoryId,
                    'name' => $namesById[$categoryId] ?? "Category #{$categoryId}",
                    'total' => round((float) ($totals[$categoryId] ?? 0), 2),
                ];
            }
            $categories[] = ['id' => 'other', 'name' => 'Other'];
            $series['other'] = array_map(static fn (float $v): float => round($v, 2), $other);
        }

        return [
            'months' => $monthLabels,
            'categories' => $categories,
            'series' => $series,
            'other_members' => $otherMembers,
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
     * @param  array<int, int>  $serviceToParent
     * @return array<int, float> [categoryId => revenue]
     */
    private function revenueByCategory(
        MetricScope $scope,
        string $startDate,
        string $endDate,
        array $serviceToParent,
    ): array {
        $query = DB::table('package_services as ps')
            ->join('packages as p', 'ps.package_id', '=', 'p.id')
            ->join('appointments as a', 'p.appointment_id', '=', 'a.id')
            ->where('a.account_id', $scope->accountId)
            ->whereBetween('ps.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('a.location_id', $scope->branchIds);
        }

        $rows = $query
            ->select('ps.service_id', DB::raw('SUM(ps.tax_including_price) as total'))
            ->groupBy('ps.service_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $parentId = $serviceToParent[(int) $row->service_id] ?? null;
            if ($parentId === null) {
                continue;
            }
            $out[$parentId] = ($out[$parentId] ?? 0.0) + (float) $row->total;
        }

        return $out;
    }
}
