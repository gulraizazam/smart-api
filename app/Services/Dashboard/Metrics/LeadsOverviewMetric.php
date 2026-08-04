<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadStatusResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * KPI-row summary for the Marketing → Leads Overview panel.
 *
 * Returns 5 headline numbers for the current filter:
 *   • total      — leads created in range
 *   • converted  — leads whose status is (or is beyond) is_converted
 *   • converted_pct
 *   • junk       — leads whose status carries is_junk
 *   • revenue    — sum of net cash-in on packages tied to appointments of those leads
 *
 * Cheap enough to compute in one round-trip (single-scan of the filtered
 * `leads` table + one joined SUM for revenue).
 */
final class LeadsOverviewMetric implements Metric
{
    public function __construct(private readonly LeadStatusResolver $statuses) {}

    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';
        $ids = $this->statuses->statusIds($scope->accountId);

        // Base filtered lead set — reused in every sub-query.
        $base = DB::table('leads as l')
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [$start, $end]);
        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            if ($scope->branchIds === []) {
                return $this->empty();
            }
            $base->where(function ($q) use ($scope) {
                $q->whereIn('l.location_id', $scope->branchIds)->orWhereNull('l.location_id');
            });
        }

        $total = (clone $base)->count();
        $converted = $ids['converted'] !== []
            ? (clone $base)->whereIn('l.lead_status_id', $ids['converted'])->count()
            : 0;
        $junk = $ids['junk'] !== []
            ? (clone $base)->whereIn('l.lead_status_id', $ids['junk'])->count()
            : 0;

        // Revenue: sum(package_advances.cash_amount) where cash_flow='in' minus refunds,
        // for advances tied to appointments whose lead is in our filtered set.
        // Scoped to package_advances.date/created_at within range too (an old lead's
        // payment landing today should count toward *today's* dashboard).
        $revenueRow = DB::table('leads as l')
            ->join('appointments as a', function ($j): void {
                $j->on('a.lead_id', '=', 'l.id')->whereNull('a.deleted_at');
            })
            ->join('packages as p', function ($j): void {
                $j->on('p.appointment_id', '=', 'a.id')->whereNull('p.deleted_at');
            })
            ->join('package_advances as pa', function ($j): void {
                $j->on('pa.package_id', '=', 'p.id')->whereNull('pa.deleted_at');
            })
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [$start, $end])
            ->whereBetween('pa.created_at', [$start, $end])
            ->when($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== [], fn ($q) => $q->where(function ($qq) use ($scope) {
                $qq->whereIn('l.location_id', $scope->branchIds)->orWhereNull('l.location_id');
            }))
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND COALESCE(pa.is_refund,0) = 0 THEN pa.cash_amount ELSE 0 END),0) as revenue_in, ".
                "COALESCE(SUM(CASE WHEN COALESCE(pa.is_refund,0) = 1 THEN pa.cash_amount ELSE 0 END),0) as refunds"
            )
            ->first();
        $revenue = (float) (($revenueRow->revenue_in ?? 0) - ($revenueRow->refunds ?? 0));

        $convertedPct = $total > 0 ? round(($converted / $total) * 100, 1) : 0.0;
        $junkPct = $total > 0 ? round(($junk / $total) * 100, 1) : 0.0;

        return [
            'total' => (int) $total,
            'converted' => (int) $converted,
            'converted_pct' => $convertedPct,
            'junk' => (int) $junk,
            'junk_pct' => $junkPct,
            'revenue' => $revenue,
        ];
    }

    /** @return array<string,mixed> */
    private function empty(): array
    {
        return [
            'total' => 0,
            'converted' => 0,
            'converted_pct' => 0.0,
            'junk' => 0,
            'junk_pct' => 0.0,
            'revenue' => 0.0,
        ];
    }
}
