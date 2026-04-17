<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\PersonalBestCalculator;

/**
 * "Personal best" records over the last 3 months.
 *
 * Doctor scope → delegates to existing PersonalBestCalculator (highest revenue
 * month, highest conversion month, highest upsell month, most patients in a
 * single day, highest feedback month, most Google reviews month).
 *
 * Branch / company scope → intentionally NOT implemented in v1. The Management
 * Dashboard "records" concept is branch-best or company-best, which require a
 * different shape (name of top performer per metric + their value). That lives
 * in ResourceLeaderboardMetric / BranchLeaderboardMetric rather than being
 * force-fit into this metric's per-user shape. Calling compute() for a non-
 * doctor scope returns an empty result instead of throwing, so orchestrators
 * can safely ignore the widget when scope is broad.
 *
 * The $range parameter is ignored (lookback is always 3 months by design) but
 * required by the Metric contract.
 */
final class PersonalBestMetric implements Metric
{
    public function __construct(
        private readonly PersonalBestCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            return $this->calculator->calculate(
                (int) $scope->resourceId,
                $scope->accountId,
            );
        }

        return $this->empty();
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'highest_revenue' => null,
            'highest_conversion' => null,
            'highest_upsell' => null,
            'most_patients_day' => null,
            'highest_feedback' => null,
            'most_google_reviews' => null,
            'lookback_months' => PersonalBestCalculator::LOOKBACK_MONTHS,
        ];
    }
}
