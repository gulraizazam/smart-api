<?php

declare(strict_types=1);

namespace App\Services\ManagementDashboard;

use App\Models\User;
use App\Services\Dashboard\Metrics\ActivityPulseMetric;
use App\Services\Dashboard\Metrics\AppointmentsMetric;
use App\Services\Dashboard\Metrics\AvgConversionValueMetric;
use App\Services\Dashboard\Metrics\AvgTransactionValueMetric;
use App\Services\Dashboard\Metrics\BranchLeaderboardMetric;
use App\Services\Dashboard\Metrics\GenderRevenueMetric;
use App\Services\Dashboard\Metrics\LeadGenderDeepDiveMetric;
use App\Services\Dashboard\Metrics\LeadGenderFunnelMetric;
use App\Services\Dashboard\Metrics\LeadServiceInterestMetric;
use App\Services\Dashboard\Metrics\NewReturningMetric;
use App\Services\Dashboard\Metrics\PatientCohortRetentionMetric;
use App\Services\Dashboard\Metrics\ResourceLeaderboardMetric;
use App\Services\Dashboard\Metrics\RevenueConcentrationMetric;
use App\Services\Dashboard\Metrics\ServiceCategoryTrendMetric;
use App\Services\Dashboard\Support\Money;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use App\Services\Dashboard\Support\SalesLedgerQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Thin orchestrator. Composes scope-aware Metric classes into section-level
 * payloads for the Management Dashboard API. Each public method maps 1:1 to
 * a live API endpoint on ManagementDashboardApiController.
 *
 * Service holds no business logic — logic lives in the Metric classes.
 * Its job is to: (1) resolve the allowed branch scope for the user,
 * (2) fan out metric calls, (3) assemble the response shape.
 */
final class ManagementDashboardService
{
    public function __construct(
        private readonly ResourceScopeResolver $scopeResolver,
        private readonly AppointmentsMetric $appointments,
        private readonly BranchLeaderboardMetric $branchLeaderboard,
        private readonly ResourceLeaderboardMetric $resourceLeaderboard,
        private readonly PatientCohortRetentionMetric $cohorts,
        private readonly RevenueConcentrationMetric $concentration,
        private readonly ServiceCategoryTrendMetric $categoryTrend,
        private readonly ActivityPulseMetric $pulse,
        private readonly NewReturningMetric $newReturning,
        private readonly GenderRevenueMetric $genderRevenue,
        private readonly AvgTransactionValueMetric $avgTransactionValue,
        private readonly AvgConversionValueMetric $avgConversionValue,
        private readonly LeadGenderFunnelMetric $leadGenderFunnel,
        private readonly LeadGenderDeepDiveMetric $leadGenderDeepDive,
        private readonly LeadServiceInterestMetric $leadServiceInterest,
    ) {}

    /**
     * Resolve the MetricScope for a request. If the user passes specific
     * branches, we intersect with their allowed set (resolver-enforced).
     *
     * @param  list<int>|null  $requestedBranches
     */
    public function scopeFor(User $user, ?array $requestedBranches = null): MetricScope
    {
        $allowed = $this->scopeResolver->allowedBranchIds($user);

        if ($requestedBranches === null || $requestedBranches === []) {
            return $allowed === null
                ? MetricScope::company((int) $user->account_id)
                : MetricScope::branches((int) $user->account_id, $allowed);
        }

        if ($allowed !== null) {
            $requestedBranches = array_values(array_intersect($requestedBranches, $allowed));
        }

        return MetricScope::branches((int) $user->account_id, $requestedBranches);
    }

    /**
     * Composite payload the Overview section renders: Stats card (ratios +
     * sales/revenue amounts), Revenue Trend (30-day rollup), and centre-
     * wise sales breakdown for the mini chart under the Stats tiles.
     * Branch-oriented widgets (Leaderboard, New vs Returning) live on
     * Overview but hit their own endpoints; they're not bundled here.
     *
     * @return array<string, mixed>
     */
    public function overview(MetricScope $scope, DateRange $range): array
    {
        $ratios = $this->appointments->ratios($scope, $range);

        return [
            'stats' => [
                'sales' => $ratios['sales'],
                'revenue' => $ratios['revenue_consumed'],
                'consultations_arrived' => $ratios['consultations']['arrived'],
                'consultations_total' => $ratios['consultations']['total'],
                'treatments_arrived' => $ratios['treatments']['arrived'],
                'treatments_total' => $ratios['treatments']['total'],
            ],
            'centre_sales' => $this->appointments->salesByCentre($scope, $range),
            'sales_deltas' => $this->salesDeltas($scope, $range, (float) $ratios['sales']),
        ];
    }

    /**
     * Calendar-month-shifted and year-shifted sales comparisons for the
     * Sales Momentum tile. Uses SalesLedgerQuery::totalNet (same source of
     * truth as the Stats "Sales" tile) so deltas and current value can never
     * drift apart.
     *
     * MoM baseline is a calendar-month shift (Apr 1–15 → Mar 1–15) rather
     * than a rolling N-day shift, which matches how management reads
     * month-to-date comparisons. YoY is a straight 1-year shift of both ends.
     *
     * @return array{
     *   current: float,
     *   mom: array{previous: float, delta_pct: float|null},
     *   yoy: array{previous: float, delta_pct: float|null},
     * }
     */
    private function salesDeltas(MetricScope $scope, DateRange $range, float $current): array
    {
        $momRange = new DateRange(
            $range->from->subMonthNoOverflow(),
            $range->to->subMonthNoOverflow(),
        );
        $momPrevious = SalesLedgerQuery::totalNet($scope, $momRange);

        $yoyRange = new DateRange(
            $range->from->subYear(),
            $range->to->subYear(),
        );
        $yoyPrevious = SalesLedgerQuery::totalNet($scope, $yoyRange);

        return [
            'current' => Money::toFloat($current),
            'range' => [
                'from' => $range->startString(),
                'to' => $range->endString(),
            ],
            'mom' => [
                'previous' => $momPrevious,
                'delta_pct' => Money::percentDelta($current, $momPrevious),
                'from' => $momRange->startString(),
                'to' => $momRange->endString(),
            ],
            'yoy' => [
                'previous' => $yoyPrevious,
                'delta_pct' => Money::percentDelta($current, $yoyPrevious),
                'from' => $yoyRange->startString(),
                'to' => $yoyRange->endString(),
            ],
        ];
    }

    /**
     * Revenue split by patient gender (male / female / unknown) for the
     * selected scope and date range. Backs the compact gender donut on
     * the Overview section.
     *
     * @return array<string, mixed>
     */
    public function genderRevenue(MetricScope $scope, DateRange $range): array
    {
        return $this->genderRevenue->compute($scope, $range);
    }

    /**
     * Trailing 12-month Average Transaction Value series. Backs the compact
     * ATV sparkline. Range argument is unused — the widget is always last
     * 12 months for trend continuity.
     *
     * @return array<string, mixed>
     */
    public function avgTransactionValue(MetricScope $scope, DateRange $range): array
    {
        return $this->avgTransactionValue->compute($scope, $range);
    }

    /**
     * Trailing 12-month Average Conversion Value series. Nationwide by
     * default, scope-aware. Range argument is unused — always last 12 months.
     *
     * @return array<string, mixed>
     */
    public function avgConversionValue(MetricScope $scope, DateRange $range): array
    {
        return $this->avgConversionValue->compute($scope, $range);
    }

    /**
     * Lead → Patient 4-stage funnel split by gender. Backs the compact
     * funnel tile on the Overview section. Period-driven (uses the top
     * filter range), not a trailing window.
     *
     * @return array<string, mixed>
     */
    public function leadGenderFunnel(MetricScope $scope, DateRange $range): array
    {
        return $this->leadGenderFunnel->compute($scope, $range);
    }

    /**
     * Lead funnel deep-dive — composite payload for the half-width panel.
     * Returns the 4-stage funnel + 12-week conversion-rate trend + top lead
     * sources, all split by gender. Powers the expanded Lead Conversion
     * panel on the Overview section.
     *
     * @return array<string, mixed>
     */
    public function leadGenderDeepDive(MetricScope $scope, DateRange $range): array
    {
        return $this->leadGenderDeepDive->compute($scope, $range);
    }

    /**
     * Top services leads enquire about, split by gender. Powers the compact
     * Service Interest tile (replaces the Leads-by-Gender placeholder).
     *
     * @return array<string, mixed>
     */
    public function leadServiceInterest(MetricScope $scope, DateRange $range): array
    {
        return $this->leadServiceInterest->compute($scope, $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function newReturning(MetricScope $scope, DateRange $range): array
    {
        return $this->newReturning->compute($scope, $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function branches(MetricScope $scope, DateRange $range): array
    {
        return $this->branchLeaderboard->compute($scope, $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function people(MetricScope $scope, DateRange $range): array
    {
        return $this->resourceLeaderboard->compute($scope, $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function patients(
        MetricScope $scope,
        DateRange $range,
        int $cohortMonths = 12,
        bool $extendedWindows = false,
    ): array {
        return [
            'retention' => $this->cohorts->compute($scope, $range, $cohortMonths, $extendedWindows),
            'concentration' => $this->concentration->compute($scope, $range),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceCategoryTrend(MetricScope $scope, DateRange $range, int $months = 12): array
    {
        return $this->categoryTrend->compute($scope, $range, $months);
    }

    /**
     * Today's activities — a scope-respecting feed pinned to today only,
     * independent of the Overview date filter. Wraps the canonical
     * ActivityLogService through ActivityPulseMetric, so the feed picks
     * up the same curated notable types (payments, refunds, memberships,
     * cancellations, etc.) and HIPAA access gates as the audit module.
     *
     * @return array<string, mixed>
     */
    public function todayActivities(MetricScope $scope, ?string $cursor = null, int $limit = 20): array
    {
        $today = DateRange::fromStrings(now()->format('Y-m-d'), now()->format('Y-m-d'));

        return $this->pulse->fetch($scope, $today, $cursor, $limit);
    }
}
