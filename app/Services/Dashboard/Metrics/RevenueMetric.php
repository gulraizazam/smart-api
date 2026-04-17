<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\RevenueLedgerQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\RevenueCalculator as DoctorRevenueCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Revenue metric across any scope.
 *
 * Doctor scope → delegates to existing DoctorDashboard\RevenueCalculator
 * (validated-conversion-spend semantics). Branch / company scope → aggregates
 * the package_advances ledger via the canonical RevenueLedgerQuery filter:
 * revenue_in minus refund_out, excluding adjustments / tax / cancels.
 *
 * Used by:
 *   - ResourceLeaderboardMetric (per-doctor revenue rollups for /people)
 *   - DailyMetricsBuilder (nightly rollup populating dashboard_daily_metrics
 *     which the Overview Revenue Trend chart reads from)
 *
 * Return shape: ['total_revenue' => float, 'patient_count' => int]. At
 * branch / company scope patient_count is 0 (ledger rows are not per-
 * patient dedupable without a join).
 */
final class RevenueMetric implements Metric
{
    public function __construct(
        private readonly DoctorRevenueCalculator $doctorRevenue,
    ) {}

    /**
     * @return array{total_revenue: float, patient_count: int}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['total_revenue' => 0.0, 'patient_count' => 0];
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            return $this->doctorScoped($scope, $range);
        }

        return $this->ledgerScoped($scope, $range);
    }

    /**
     * Byte-identical to the doctor dashboard: delegates to the existing
     * RevenueCalculator.
     *
     * @return array{total_revenue: float, patient_count: int}
     */
    private function doctorScoped(MetricScope $scope, DateRange $range): array
    {
        $result = $this->doctorRevenue->calculate(
            (int) $scope->resourceId,
            $range->startString(),
            $range->endString(),
            $scope->accountId,
        );

        return [
            'total_revenue' => (float) $result['total_revenue'],
            'patient_count' => (int) $result['patient_count'],
        ];
    }

    /**
     * Branch / company aggregation over the package_advances ledger via
     * the shared RevenueLedgerQuery filter.
     *
     * @return array{total_revenue: float, patient_count: int}
     */
    private function ledgerScoped(MetricScope $scope, DateRange $range): array
    {
        $query = DB::table('package_advances')
            ->where('account_id', $scope->accountId)
            ->whereDate('created_at', '>=', $range->startString())
            ->whereDate('created_at', '<=', $range->endString());

        RevenueLedgerQuery::applyValidRevenueFilters($query);

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('location_id', $scope->branchIds);
        }

        $row = $query
            ->selectRaw("
                COALESCE(SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END), 0) AS revenue_in,
                COALESCE(SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END), 0) AS refund_out
            ")
            ->first();

        // Subtract DECIMAL-as-string values via bcmath so precision holds
        // regardless of summed row count. Only float at the JSON boundary.
        return [
            'total_revenue' => Money::subtract($row->revenue_in ?? 0, $row->refund_out ?? 0),
            'patient_count' => 0,
        ];
    }
}
