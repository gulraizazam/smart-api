<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Revenue concentration: what fraction of revenue comes from the top N% of
 * patients. Higher concentration = more dependence on fewer patients (risk).
 *
 * Also returns Lorenz-curve points (100 evenly-spaced samples) so the
 * frontend can render the curve without its own calculation.
 *
 * Source of truth: package_advances revenue_in attributed to the patient on
 * the package that the advance belongs to. Refunds subtracted.
 *
 * Return shape:
 *   total_revenue:         float
 *   total_patients:        int
 *   top_20_pct_share:      float (percent of revenue from top 20% of patients)
 *   top_10_pct_share:      float
 *   lorenz:                list<{ x: float, y: float }>   (for the mini-chart)
 */
final class RevenueConcentrationMetric implements Metric
{
    /**
     * @return array{
     *   total_revenue: float,
     *   total_patients: int,
     *   top_20_pct_share: float,
     *   top_10_pct_share: float,
     *   lorenz: list<array{x: float, y: float}>
     * }
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        $query = DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->where('pa.account_id', $scope->accountId)
            ->whereDate('pa.created_at', '>=', $range->startString())
            ->whereDate('pa.created_at', '<=', $range->endString())
            ->where('pa.cash_amount', '>', 0)
            ->where(function ($q): void {
                $q->where(function ($in): void {
                    $in->where('pa.cash_flow', 'in')
                        ->where('pa.is_adjustment', 0)
                        ->where('pa.is_tax', 0)
                        ->where('pa.is_cancel', 0);
                })->orWhere(function ($out): void {
                    $out->where('pa.cash_flow', 'out')
                        ->where('pa.is_refund', 1);
                });
            });

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('pa.location_id', $scope->branchIds);
        }

        $rows = $query
            ->selectRaw("
                p.patient_id,
                SUM(CASE WHEN pa.cash_flow = 'in' THEN pa.cash_amount ELSE -pa.cash_amount END) AS patient_revenue
            ")
            ->groupBy('p.patient_id')
            ->having('patient_revenue', '>', 0)
            ->orderByDesc('patient_revenue')
            ->get();

        if ($rows->isEmpty()) {
            return $this->empty();
        }

        $revenues = $rows->pluck('patient_revenue')->map(static fn (mixed $v): float => (float) $v)->all();
        $totalRevenue = array_sum($revenues);
        $totalPatients = count($revenues);

        $topShare = function (float $percentile) use ($revenues, $totalRevenue, $totalPatients): float {
            $topCount = max(1, (int) ceil($totalPatients * ($percentile / 100)));
            $topSum = array_sum(array_slice($revenues, 0, $topCount));

            return $totalRevenue > 0 ? round(($topSum / $totalRevenue) * 100, 1) : 0.0;
        };

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_patients' => $totalPatients,
            'top_20_pct_share' => $topShare(20),
            'top_10_pct_share' => $topShare(10),
            'lorenz' => $this->buildLorenzCurve($revenues, $totalRevenue),
        ];
    }

    /**
     * Build a 100-point Lorenz curve. Each point = (cumulative % of patients,
     * cumulative % of revenue). Patients are sorted ASCENDING for the curve
     * (opposite of the top-N analysis above).
     *
     * @param  list<float>  $sortedDesc  revenues sorted descending (matches top-N analysis)
     * @return list<array{x: float, y: float}>
     */
    private function buildLorenzCurve(array $sortedDesc, float $totalRevenue): array
    {
        if ($totalRevenue <= 0) {
            return [];
        }

        $sortedAsc = array_reverse($sortedDesc);
        $totalPatients = count($sortedAsc);
        $points = [['x' => 0.0, 'y' => 0.0]];

        $cumulativePatients = 0;
        $cumulativeRevenue = 0.0;
        $stepSize = max(1, (int) ceil($totalPatients / 100));

        foreach ($sortedAsc as $idx => $revenue) {
            $cumulativePatients++;
            $cumulativeRevenue += $revenue;

            if ($idx % $stepSize === 0 || $idx === $totalPatients - 1) {
                $points[] = [
                    'x' => round(($cumulativePatients / $totalPatients) * 100, 2),
                    'y' => round(($cumulativeRevenue / $totalRevenue) * 100, 2),
                ];
            }
        }

        return $points;
    }

    /**
     * @return array{total_revenue: float, total_patients: int, top_20_pct_share: float, top_10_pct_share: float, lorenz: list<array{x: float, y: float}>}
     */
    private function empty(): array
    {
        return [
            'total_revenue' => 0.0,
            'total_patients' => 0,
            'top_20_pct_share' => 0.0,
            'top_10_pct_share' => 0.0,
            'lorenz' => [],
        ];
    }
}
