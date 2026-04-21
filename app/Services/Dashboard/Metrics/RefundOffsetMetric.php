<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Refund-offset aggregate over the package_advances ledger.
 *
 * Returns the total refund amount in the period (rows where
 * cash_flow='out' AND is_refund=1). Net revenue = RevenueMetric -
 * RefundOffsetMetric, but RevenueMetric already nets refunds inline; this
 * class exists separately so the rollup pipeline can write a dedicated
 * `refunds` metric_key into dashboard_daily_metrics for trend charts.
 *
 * Used by:
 *   - DailyMetricsBuilder (nightly rollup)
 *
 * @phpstan-type RefundResult array{refund_total: float}
 */
final class RefundOffsetMetric implements Metric
{
    /**
     * @return RefundResult
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['refund_total' => 0.0];
        }

        $query = DB::table('package_advances')
            ->where('account_id', $scope->accountId)
            ->where('cash_flow', 'out')
            ->where('is_refund', 1)
            ->where('cash_amount', '!=', 0)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', $range->startString())
            ->whereDate('created_at', '<=', $range->endString());

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('location_id', $scope->branchIds);
        }

        return ['refund_total' => round((float) $query->sum('cash_amount'), 2)];
    }
}
