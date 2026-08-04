<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Lead-attributed revenue: total + monthly trend + per-lead breakdown.
 *
 * Join chain (per user spec + backend map — no consultancies table; the
 * lead_id lives directly on appointments):
 *   leads.id ← appointments.lead_id
 *   appointments.id ← packages.appointment_id
 *   packages.id ← package_advances.package_id
 *
 * Revenue = SUM(cash_amount) where cash_flow='in' AND is_refund != 1
 *           - SUM(cash_amount) where is_refund = 1
 *
 * Both the lead's created_at and the payment's created_at must fall in
 * the range — "revenue in the window from leads created in the window",
 * per user's decision in the plan.
 *
 * ConversionService.php has a more thorough "positive-only, net-of-refund"
 * pipeline (see plan §4). This metric intentionally uses a simpler
 * sum-with-refund-subtract for phase 1 — good enough for a headline
 * number, follow-up ticket tracks parity with ConversionService.
 */
final class LeadRevenueMetric implements Metric
{
    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['total' => 0.0, 'series' => [], 'top_leads' => []];
        }

        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        $branchFilter = function ($q) use ($scope): void {
            if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
                $q->where(function ($qq) use ($scope) {
                    $qq->whereIn('l.location_id', $scope->branchIds)->orWhereNull('l.location_id');
                });
            }
        };

        $baseJoin = fn () => DB::table('leads as l')
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
            ->tap($branchFilter);

        // Total
        $totalRow = $baseJoin()
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND COALESCE(pa.is_refund,0) = 0 THEN pa.cash_amount ELSE 0 END),0) - ".
                'COALESCE(SUM(CASE WHEN COALESCE(pa.is_refund,0) = 1 THEN pa.cash_amount ELSE 0 END),0) as revenue'
            )
            ->first();
        $total = (float) ($totalRow->revenue ?? 0);

        // Monthly series (bucket by pa.created_at, not l.created_at — payments trend)
        $seriesRows = $baseJoin()
            ->selectRaw(
                "DATE_FORMAT(pa.created_at, '%Y-%m') as bucket, ".
                "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND COALESCE(pa.is_refund,0) = 0 THEN pa.cash_amount ELSE 0 END),0) - ".
                'COALESCE(SUM(CASE WHEN COALESCE(pa.is_refund,0) = 1 THEN pa.cash_amount ELSE 0 END),0) as revenue'
            )
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        // Top 10 leads by revenue.
        $topLeadRows = $baseJoin()
            ->selectRaw(
                'l.id as lead_id, l.name as lead_name, '.
                "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND COALESCE(pa.is_refund,0) = 0 THEN pa.cash_amount ELSE 0 END),0) - ".
                'COALESCE(SUM(CASE WHEN COALESCE(pa.is_refund,0) = 1 THEN pa.cash_amount ELSE 0 END),0) as revenue'
            )
            ->groupBy('l.id', 'l.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return [
            'total' => $total,
            'series' => $seriesRows->map(fn ($r) => [
                'bucket' => (string) $r->bucket,
                'revenue' => (float) $r->revenue,
            ])->all(),
            'top_leads' => $topLeadRows->map(fn ($r) => [
                'lead_id' => (int) $r->lead_id,
                'name' => (string) ($r->lead_name ?? ''),
                'revenue' => (float) $r->revenue,
            ])->all(),
        ];
    }
}
