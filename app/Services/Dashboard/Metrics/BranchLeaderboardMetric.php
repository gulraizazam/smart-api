<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\Support\RevenueLedgerQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Per-branch leaderboard for the Branches section.
 *
 * Performance: every metric is fetched as ONE bulk query grouped by location_id
 * across all branches in scope, then stitched together in PHP. Conversion /
 * no-show rates are computed from raw appointment counts at the branch level —
 * close enough to the per-doctor "validated conversion spend" semantics for a
 * leaderboard view, with dramatically lower query cost (~1s vs ~30s per-doctor
 * fan-out).
 *
 * Shared utilities used:
 *   - BranchResolver → "which branches are in scope?" + name lookup
 *   - RevenueLedgerQuery → canonical valid-revenue WHERE clause
 *
 * Cached 5 minutes per (account, scope, range) key.
 *
 * Return shape: { rows: list<branch_row> }
 */
final class BranchLeaderboardMetric implements Metric
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $cacheKey = 'mgmt_dash:branch_leaderboard:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope, $range));
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    private function build(MetricScope $scope, DateRange $range): array
    {
        $branchIds = $this->branches->idsInScope($scope);

        if ($branchIds === []) {
            return ['rows' => []];
        }

        $prevRange = $range->previousPeriod();
        $branchNames = $this->branches->names($branchIds);
        $targetMap = $this->branchTargets($branchIds, $range, $scope->accountId);
        $revenueMap = $this->revenueByBranch($branchIds, $range, $scope->accountId);
        $prevRevenueMap = $this->revenueByBranch($branchIds, $prevRange, $scope->accountId);
        $apptStats = $this->appointmentStatsByBranch($branchIds, $range, $scope->accountId);
        $refundMap = $this->refundsByBranch($branchIds, $range, $scope->accountId);

        $rows = [];
        foreach ($branchIds as $branchId) {
            $revenue = (float) ($revenueMap[$branchId] ?? 0);
            $prevRevenue = (float) ($prevRevenueMap[$branchId] ?? 0);
            $stats = $apptStats[$branchId] ?? ['total' => 0, 'arrived' => 0, 'converted' => 0, 'cancelled' => 0];
            $refundTotal = (float) ($refundMap[$branchId] ?? 0);
            $target = $targetMap[$branchId] ?? null;

            $percentToTarget = $target !== null && $target > 0
                ? round(($revenue / $target) * 100, 1)
                : null;

            $conversionRate = $stats['arrived'] > 0
                ? round(($stats['converted'] / $stats['arrived']) * 100, 1)
                : 0.0;

            $noShowRate = $stats['total'] > 0
                ? round(($stats['cancelled'] / $stats['total']) * 100, 1)
                : 0.0;

            $avgValue = $stats['converted'] > 0
                ? round($revenue / $stats['converted'], 2)
                : 0.0;

            $momDelta = $prevRevenue > 0
                ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1)
                : null;

            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => $branchNames[$branchId] ?? "Branch #{$branchId}",
                'net_revenue' => round($revenue, 2),
                'target' => $target,
                'percent_to_target' => $percentToTarget,
                'prev_period_revenue' => round($prevRevenue, 2),
                'mom_delta_pct' => $momDelta,
                'appointments' => (int) $stats['total'],
                'no_show_rate' => $noShowRate,
                'conversion_rate' => $conversionRate,
                'avg_value' => $avgValue,
                'refund_total' => round($refundTotal, 2),
                'status' => $this->statusFor($percentToTarget, $noShowRate, $refundTotal, $revenue),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['net_revenue'] <=> $a['net_revenue']);

        return ['rows' => $rows];
    }

    /**
     * Bulk: net revenue (revenue_in - refund_out) per branch in one query,
     * using the canonical RevenueLedgerQuery filter.
     *
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function revenueByBranch(array $branchIds, DateRange $range, int $accountId): array
    {
        $query = DB::table('package_advances')
            ->where('account_id', $accountId)
            ->whereIn('location_id', $branchIds)
            ->whereDate('created_at', '>=', $range->startString())
            ->whereDate('created_at', '<=', $range->endString());

        RevenueLedgerQuery::applyValidRevenueFilters($query);

        $rows = $query
            ->select('location_id')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END), 0) AS revenue_in,
                COALESCE(SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END), 0) AS refund_out
            ")
            ->groupBy('location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = (float) $row->revenue_in - (float) $row->refund_out;
        }

        return $out;
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function refundsByBranch(array $branchIds, DateRange $range, int $accountId): array
    {
        $rows = DB::table('package_advances')
            ->where('account_id', $accountId)
            ->whereIn('location_id', $branchIds)
            ->whereDate('created_at', '>=', $range->startString())
            ->whereDate('created_at', '<=', $range->endString())
            ->where('cash_flow', 'out')
            ->where('is_refund', 1)
            ->where('cash_amount', '>', 0)
            ->select('location_id')
            ->selectRaw('COALESCE(SUM(cash_amount), 0) AS refund_total')
            ->groupBy('location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = (float) $row->refund_total;
        }

        return $out;
    }

    /**
     * Bulk: appointment counts per branch in one grouped query.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{total: int, arrived: int, converted: int, cancelled: int}>
     */
    private function appointmentStatsByBranch(array $branchIds, DateRange $range, int $accountId): array
    {
        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();

        $rows = DB::table('appointments as a')
            ->leftJoin('appointment_statuses as ast', 'a.appointment_status_id', '=', 'ast.id')
            ->where('a.account_id', $accountId)
            ->whereIn('a.location_id', $branchIds)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->whereNull('a.deleted_at')
            ->select('a.location_id')
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN a.appointment_type_id = 1 AND a.appointment_status_id IN ('.implode(',', $consultationStatusIds).') THEN 1 ELSE 0 END) AS arrived,
                SUM(CASE WHEN a.appointment_type_id = 1 AND a.converted_at IS NOT NULL THEN 1 ELSE 0 END) AS converted,
                SUM(CASE WHEN ast.is_cancelled = 1 THEN 1 ELSE 0 END) AS cancelled
            ')
            ->groupBy('a.location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'total' => (int) $row->total,
                'arrived' => (int) $row->arrived,
                'converted' => (int) $row->converted,
                'cancelled' => (int) $row->cancelled,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function branchTargets(array $branchIds, DateRange $range, int $accountId): array
    {
        $month = (int) Carbon::parse($range->startString())->format('m');
        $year = (int) Carbon::parse($range->startString())->format('Y');

        $rows = DB::table('centretargetmeta')
            ->join('centertarget as ct', 'centretargetmeta.centertarget_id', '=', 'ct.id')
            ->whereIn('centretargetmeta.location_id', $branchIds)
            ->where('ct.account_id', $accountId)
            ->where('ct.month', $month)
            ->where('ct.year', $year)
            ->select(
                'centretargetmeta.location_id',
                'centretargetmeta.target_amount',
                'ct.working_days',
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $workingDays = (int) ($row->working_days ?: 22);
            $map[(int) $row->location_id] = (float) $row->target_amount * $workingDays;
        }

        return $map;
    }

    private function statusFor(
        ?float $percentToTarget,
        float $noShowRate,
        float $refundTotal,
        float $revenue,
    ): string {
        if ($percentToTarget !== null && $percentToTarget < 75) {
            return 'red';
        }

        if ($noShowRate >= 20) {
            return 'red';
        }

        if ($revenue > 0 && ($refundTotal / max($revenue, 1)) >= 0.10) {
            return 'red';
        }

        if ($percentToTarget !== null && $percentToTarget < 90) {
            return 'amber';
        }

        if ($noShowRate >= 10) {
            return 'amber';
        }

        return 'green';
    }
}
