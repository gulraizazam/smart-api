<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 12-month Average Transaction Value (ATV) series.
 *
 * ATV = SUM(cash_amount) / COUNT(*) per calendar month, against the same
 * package_advances "cash in" filter used by the revenue and gender metrics:
 *   cash_flow='in', is_adjustment='0', is_tax='0', is_cancel='0',
 *   cash_amount > 0
 *
 * Deliberately spans ALL appointment types (not consultations-only) — ATV
 * is a pricing-power signal; it must include treatments and packages.
 *
 * Ignores the $range arg: ATV is a trailing-12-month widget, not a period
 * metric. Respects MetricScope (account + optional branch subset via
 * packages.location_id).
 *
 * Return shape:
 *   [
 *     'series'           => list<array{month: string (YYYY-MM), atv: float, revenue: float, txns: int}>,
 *     'latest'           => array{month: string, atv: float, revenue: float, txns: int} | null,
 *     'prior3_avg'       => float | null,   // avg ATV of months -1, -2, -3 relative to latest
 *     'prior3_delta_pct' => float | null,   // null when prior3_avg is 0
 *   ]
 */
final class AvgTransactionValueMetric implements Metric
{
    private const CACHE_TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['series' => [], 'latest' => null, 'prior3_avg' => null, 'prior3_delta_pct' => null];
        }

        // Window is "now's trailing 12 months" — not range-dependent — so key
        // by scope + current month to stay correct across a month boundary.
        $cacheKey = 'mgmt_dash:avg_transaction_value:'
            .$scope->cacheKey()
            .'|'.now()->format('Y-m');

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(MetricScope $scope): array
    {
        $end = CarbonImmutable::now()->endOfMonth();
        $start = $end->subMonths(11)->startOfMonth();

        $query = DB::table('package_advances as pa')
            ->join('packages as pk', 'pk.id', '=', 'pa.package_id')
            ->where('pa.account_id', $scope->accountId)
            ->where('pa.cash_flow', 'in')
            ->where('pa.is_adjustment', '0')
            ->where('pa.is_tax', '0')
            ->where('pa.is_cancel', '0')
            ->where('pa.cash_amount', '>', 0)
            ->whereBetween('pa.created_at', [
                $start->format('Y-m-d 00:00:00'),
                $end->format('Y-m-d 23:59:59'),
            ]);

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            if ($scope->branchIds === []) {
                return ['series' => [], 'latest' => null, 'prior3_avg' => null, 'prior3_delta_pct' => null];
            }
            $query->whereIn('pk.location_id', $scope->branchIds);
        }

        $rows = $query
            ->selectRaw("DATE_FORMAT(pa.created_at, '%Y-%m') as month, SUM(pa.cash_amount) as revenue, COUNT(*) as txns")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $series = [];
        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $row = $rows[$key] ?? null;
            $revenue = $row ? (float) $row->revenue : 0.0;
            $txns = $row ? (int) $row->txns : 0;
            $atv = $txns > 0 ? round($revenue / $txns, 2) : 0.0;
            $series[] = [
                'month' => $key,
                'atv' => $atv,
                'revenue' => round($revenue, 2),
                'txns' => $txns,
            ];
            $cursor = $cursor->addMonth();
        }

        $latest = end($series) ?: null;
        $prior3Avg = null;
        $prior3Delta = null;
        if ($latest !== null && count($series) >= 4) {
            $priorSlice = array_slice($series, -4, 3);
            $atvs = array_map(static fn (array $row): float => (float) $row['atv'], $priorSlice);
            $sum = array_sum($atvs);
            if ($sum > 0) {
                $prior3Avg = round($sum / 3, 2);
                if ($prior3Avg > 0) {
                    $prior3Delta = round((($latest['atv'] - $prior3Avg) / $prior3Avg) * 100, 1);
                }
            }
        }

        return [
            'series' => $series,
            'latest' => $latest ?: null,
            'prior3_avg' => $prior3Avg,
            'prior3_delta_pct' => $prior3Delta,
        ];
    }
}
