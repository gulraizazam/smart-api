<?php

declare(strict_types=1);

namespace App\Services\ManagementDashboard;

use App\Models\User;
use App\Services\Dashboard\Metrics\ActivityPulseMetric;
use App\Services\Dashboard\Metrics\AppointmentsMetric;
use App\Services\Dashboard\Metrics\ArrivalRateMetric;
use App\Services\Dashboard\Metrics\AtRiskPatientsMetric;
use App\Services\Dashboard\Metrics\AvgConversionValueMetric;
use App\Services\Dashboard\Metrics\AvgTransactionValueMetric;
use App\Services\Dashboard\Metrics\BranchDoctorFeedbackMetric;
use App\Services\Dashboard\Metrics\BranchFeedbackMetric;
use App\Services\Dashboard\Metrics\BranchFeedbackTrendMetric;
use App\Services\Dashboard\Metrics\BranchLeaderboardMetric;
use App\Services\Dashboard\Metrics\GenderRevenueMetric;
use App\Services\Dashboard\Metrics\LeadAgentLeaderboardMetric;
use App\Services\Dashboard\Metrics\LeadDepartmentSplitMetric;
use App\Services\Dashboard\Metrics\LeadFunnelMetric;
use App\Services\Dashboard\Metrics\LeadGenderDeepDiveMetric;
use App\Services\Dashboard\Metrics\LeadGenderFunnelMetric;
use App\Services\Dashboard\Metrics\LeadResponseTimeMetric;
use App\Services\Dashboard\Metrics\LeadRevenueMetric;
use App\Services\Dashboard\Metrics\LeadServiceInterestMetric;
use App\Services\Dashboard\Metrics\LeadServiceSplitMetric;
use App\Services\Dashboard\Metrics\LeadSourceSplitMetric;
use App\Services\Dashboard\Metrics\LeadStatusSplitMetric;
use App\Services\Dashboard\Metrics\LeadsOverTimeMetric;
use App\Services\Dashboard\Metrics\LeadsOverviewMetric;
use App\Services\Dashboard\Metrics\LeadTimeToConversionMetric;
use App\Services\Dashboard\Metrics\NewReturningMetric;
use App\Services\Dashboard\Metrics\PatientCohortRetentionMetric;
use App\Services\Dashboard\Metrics\RevenueConcentrationMetric;
use App\Services\Dashboard\Metrics\ServiceCategoryTrendMetric;
use App\Services\Dashboard\Metrics\ServiceSalesTrendMetric;
use App\Services\Dashboard\Metrics\UtilizationMetric;
use App\Services\Dashboard\Support\Money;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use App\Services\Dashboard\Support\SalesLedgerQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Cache;

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
        private readonly PatientCohortRetentionMetric $cohorts,
        private readonly RevenueConcentrationMetric $concentration,
        private readonly ServiceCategoryTrendMetric $categoryTrend,
        private readonly ServiceSalesTrendMetric $serviceSalesTrend,
        private readonly ActivityPulseMetric $pulse,
        private readonly NewReturningMetric $newReturning,
        private readonly GenderRevenueMetric $genderRevenue,
        private readonly AvgTransactionValueMetric $avgTransactionValue,
        private readonly AvgConversionValueMetric $avgConversionValue,
        private readonly LeadGenderFunnelMetric $leadGenderFunnel,
        private readonly LeadGenderDeepDiveMetric $leadGenderDeepDive,
        private readonly LeadServiceInterestMetric $leadServiceInterest,
        private readonly BranchFeedbackMetric $branchFeedback,
        private readonly BranchDoctorFeedbackMetric $branchDoctorFeedback,
        private readonly BranchFeedbackTrendMetric $branchFeedbackTrend,
        private readonly UtilizationMetric $utilization,
        private readonly ArrivalRateMetric $arrivalRate,
        private readonly AtRiskPatientsMetric $atRiskPatients,
        // Leads-reporting dashboard (new, phase 1) — 10 metric classes
        // consumed by the redesigned Marketing tab.
        private readonly LeadsOverviewMetric $leadsOverview,
        private readonly LeadsOverTimeMetric $leadsOverTime,
        private readonly LeadStatusSplitMetric $leadStatusSplit,
        private readonly LeadSourceSplitMetric $leadSourceSplit,
        private readonly LeadServiceSplitMetric $leadServiceSplit,
        private readonly LeadDepartmentSplitMetric $leadDepartmentSplit,
        private readonly LeadFunnelMetric $leadFunnel,
        private readonly LeadAgentLeaderboardMetric $leadAgentLeaderboard,
        private readonly LeadTimeToConversionMetric $leadTimeToConversion,
        private readonly LeadResponseTimeMetric $leadResponseTime,
        private readonly LeadRevenueMetric $leadRevenue,
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

        // Prior-period ratios = same window shifted one calendar month back.
        // Gives each KPI tile a "% vs last period" chip without a second
        // endpoint trip. We deliberately use the month-shift (not a rolling
        // N-day shift) to match how management reads comparisons and to
        // stay consistent with salesDeltas below.
        $priorRange = new DateRange(
            $range->from->subMonthNoOverflow(),
            $range->to->subMonthNoOverflow(),
        );
        $priorRatios = $this->appointments->ratios($scope, $priorRange);

        return [
            'stats' => [
                'sales' => $ratios['sales'],
                'revenue' => $ratios['revenue_consumed'],
                'consultations_arrived' => $ratios['consultations']['arrived'],
                'consultations_total' => $ratios['consultations']['total'],
                'treatments_arrived' => $ratios['treatments']['arrived'],
                'treatments_total' => $ratios['treatments']['total'],
                'deltas' => [
                    'sales_pct' => Money::percentDelta($ratios['sales'], $priorRatios['sales']),
                    'revenue_pct' => Money::percentDelta($ratios['revenue_consumed'], $priorRatios['revenue_consumed']),
                    'consultations_arrived_pct' => Money::percentDelta(
                        $ratios['consultations']['arrived'],
                        $priorRatios['consultations']['arrived'],
                    ),
                    'treatments_arrived_pct' => Money::percentDelta(
                        $ratios['treatments']['arrived'],
                        $priorRatios['treatments']['arrived'],
                    ),
                ],
            ],
            // `centre_sales` used to live here, but the SPA never read it —
            // SalesByCentreSection pulls from /branches with its own pill
            // toggles. Dropping it skips a locations fetch + a grouped
            // SUM on package_advances on every overview call.
            'sales_deltas' => $this->cachedSalesDeltas(
                $scope,
                $range,
                (float) $ratios['sales'],
                (float) $priorRatios['sales'],
            ),
        ];
    }

    /**
     * Sales Momentum is expensive (two extra SalesLedgerQuery::totalNet
     * passes for MoM and YoY baselines) and the inputs change only when
     * scope/range change. 5-min cache mirrors the policy used across
     * individual metric classes.
     *
     * Keyed by scope + range + the current sales value so the deltas stay
     * consistent with whatever Stats displayed — if Stats recomputes a
     * different current value (cache miss), we recompute deltas too.
     *
     * @return array<string, mixed>
     */
    private function cachedSalesDeltas(
        MetricScope $scope,
        DateRange $range,
        float $current,
        float $momPrevious,
    ): array {
        $cacheKey = 'mgmt_dash:sales_deltas:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString()
            .'|c='.(int) round($current);

        return Cache::remember(
            $cacheKey,
            300,
            fn () => $this->salesDeltas($scope, $range, $current, $momPrevious),
        );
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
    private function salesDeltas(
        MetricScope $scope,
        DateRange $range,
        float $current,
        float $momPrevious,
    ): array {
        // MoM baseline = prior-period sales (same window, shifted one
        // calendar month). The caller has already computed this via
        // priorRatios in overview() — reusing it spares an identical
        // SalesLedgerQuery::totalNet pass over package_advances.
        $momRange = new DateRange(
            $range->from->subMonthNoOverflow(),
            $range->to->subMonthNoOverflow(),
        );

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
     * Per-doctor retention breakdown for a single branch — used by the
     * hover card on the Client Retention Rate panel.
     *
     * @return array<string, mixed>
     */
    public function branchDoctorRetention(MetricScope $scope, int $branchId): array
    {
        return $this->newReturning->branchDoctorRetention($scope, $branchId);
    }

    /**
     * Per-doctor monthly retention trend for a single branch. Used by
     * the FDM dashboard's Trend tab so the operator sees how each
     * doctor's retention has been moving over the last N months,
     * alongside the branch-level pool.
     *
     * @return array<string, mixed>
     */
    public function branchDoctorRetentionTrend(MetricScope $scope, int $branchId, int $monthsBack = 6): array
    {
        return $this->newReturning->branchDoctorRetentionTrend($scope, $branchId, $monthsBack);
    }

    /**
     * Monthly retention-rate trend per branch + pool over the last N
     * months. Each month gets its own matured 30-60 day cohort anchored
     * at the month-end so the points are comparable.
     *
     * @return array<string, mixed>
     */
    public function retentionTrend(MetricScope $scope, int $monthsBack = 6): array
    {
        return $this->newReturning->retentionTrend($scope, $monthsBack);
    }

    /**
     * Pool-level Recoverable Revenue across all branches in scope —
     * powers the headline tile on the Client Retention Rate panel.
     *
     * @return array<string, mixed>
     */
    public function atRiskSummary(MetricScope $scope): array
    {
        return $this->atRiskPatients->summary($scope);
    }

    /**
     * Two-lens overview that powers the inline At-Risk panel: per-branch
     * rollup + top patients across all branches by recoverable and by
     * trailing-12mo spend. One round trip serves both lenses.
     *
     * @return array<string, mixed>
     */
    public function atRiskOverview(MetricScope $scope, int $patientLimit = 25): array
    {
        return $this->atRiskPatients->overview($scope, $patientLimit);
    }

    /**
     * Named at-risk patients at a single branch, evaluated against the
     * three slippage signals and value-tiered. Drives the drill-in modal.
     *
     * @param  list<string>|null  $signals     primary_signal filter
     * @param  list<string>|null  $valueTiers
     * @return array<string, mixed>
     */
    public function atRiskList(
        MetricScope $scope,
        int $branchId,
        ?array $signals,
        ?array $valueTiers,
        int $limit,
        int $offset,
    ): array {
        return $this->atRiskPatients->list($scope, $branchId, $signals, $valueTiers, $limit, $offset);
    }

    /**
     * At-risk pool aggregated by owning doctor across the user's accessible
     * branches. Used by the management Practitioner-tab dashboard to surface
     * "whose patients are at risk" + recoverable PKR per doctor. Reuses the
     * same per-branch pool cache as the named-patient drill, so warm path is
     * one cache read per branch.
     *
     * @return array{rows: list<array<string, mixed>>}
     */
    public function atRiskByDoctor(MetricScope $scope): array
    {
        return ['rows' => $this->atRiskPatients->patientCountsByDoctor($scope)];
    }

    /**
     * @return array<string, mixed>
     */
    public function branches(MetricScope $scope, DateRange $range): array
    {
        return $this->branchLeaderboard->compute($scope, $range);
    }

    /**
     * Per-doctor breakdown at a single branch — powers the hover card
     * on the Sales by Centre list. Returns revenue / conversion / avg
     * per doctor so the card can emphasise whichever metric the pill
     * toggle is on.
     *
     * @return array<string, mixed>
     */
    public function branchDoctorBreakdown(MetricScope $scope, int $branchId, DateRange $range): array
    {
        return $this->branchLeaderboard->branchDoctorBreakdown($scope, $branchId, $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function arrivalRate(MetricScope $scope, DateRange $range, int $type, string $groupBy): array
    {
        return $this->arrivalRate->compute($scope, $range, $type, $groupBy);
    }

    /**
     * @return array<string, mixed>
     */
    public function branchFeedback(MetricScope $scope, DateRange $range): array
    {
        return $this->branchFeedback->compute($scope, $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function branchDoctorFeedback(MetricScope $scope, DateRange $range, int $branchId): array
    {
        return $this->branchDoctorFeedback->compute($scope, $range, $branchId);
    }

    /**
     * Per-branch monthly feedback-rating trend over the last N months.
     * Mirrors retentionTrend: one row per branch, each point is that
     * month's avg rating + total_feedback, plus a feedback-weighted
     * pool row for the "all branches" comparator.
     *
     * @return array<string, mixed>
     */
    public function branchFeedbackTrend(MetricScope $scope, int $monthsBack = 6): array
    {
        return $this->branchFeedbackTrend->branchTrend($scope, $monthsBack);
    }

    /**
     * Per-doctor monthly feedback-rating trend for a single branch.
     * Filters out inactive doctors (active=0) so the row list reflects
     * the current roster, with the branch-level pool (all doctors,
     * including inactive) as the footer comparator.
     *
     * @return array<string, mixed>
     */
    public function branchDoctorFeedbackTrend(MetricScope $scope, int $branchId, int $monthsBack = 6): array
    {
        return $this->branchFeedbackTrend->branchDoctorTrend($scope, $branchId, $monthsBack);
    }

    /**
     * Per-doctor service utilization (arrived service minutes / rota-
     * available minutes). See UtilizationMetric for business rules.
     *
     * @return array<string, mixed>
     */
    public function utilization(MetricScope $scope, DateRange $range): array
    {
        return $this->utilization->compute($scope, $range);
    }

    /**
     * Hour × day-of-week utilization heatmap for the doctor pool.
     *
     * @return array<string, mixed>
     */
    public function utilizationHeatmap(MetricScope $scope, DateRange $range): array
    {
        return $this->utilization->heatmap($scope, $range);
    }

    /**
     * Pool utilization trend over the last N full calendar months.
     * Date range is derived from `now()`, not the top-bar filter — the
     * trend is a fixed-horizon view.
     *
     * @return array<string, mixed>
     */
    public function utilizationTrend(MetricScope $scope, int $monthsBack = 6): array
    {
        return $this->utilization->trend($scope, $monthsBack);
    }

    /**
     * Per-branch utilization trend over the last N months.
     *
     * @return array<string, mixed>
     */
    public function utilizationTrendByBranch(MetricScope $scope, int $monthsBack = 6): array
    {
        return $this->utilization->trendByBranch($scope, $monthsBack);
    }

    /**
     * Per-doctor monthly utilization trend for a single branch — powers
     * the FDM dashboard's Trend tab when scope is single-branch.
     *
     * @return array<string, mixed>
     */
    public function branchDoctorUtilizationTrend(MetricScope $scope, int $branchId, int $monthsBack = 6): array
    {
        return $this->utilization->branchDoctorTrend($scope, $branchId, $monthsBack);
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
     * Service-level sales trend — same response shape as
     * serviceCategoryTrend but grouped by individual service instead of
     * rolled up to top-level category.
     *
     * @return array<string, mixed>
     */
    public function serviceSalesTrend(MetricScope $scope, DateRange $range, int $months = 12): array
    {
        return $this->serviceSalesTrend->compute($scope, $range, $months);
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

    // =====================================================================
    // Leads-reporting dashboard (Marketing tab) — new in phase 1.
    // Each method is a thin delegate; the Metric class holds the logic.
    // =====================================================================

    /** @return array<string,mixed> */
    public function leadsOverview(MetricScope $scope, DateRange $range): array
    {
        return $this->leadsOverview->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadsOverTime(MetricScope $scope, DateRange $range): array
    {
        return $this->leadsOverTime->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadStatusSplit(MetricScope $scope, DateRange $range): array
    {
        return $this->leadStatusSplit->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadSourceSplit(MetricScope $scope, DateRange $range): array
    {
        return $this->leadSourceSplit->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadServiceSplit(MetricScope $scope, DateRange $range): array
    {
        return $this->leadServiceSplit->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadDepartmentSplit(MetricScope $scope, DateRange $range): array
    {
        return $this->leadDepartmentSplit->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadFunnel(MetricScope $scope, DateRange $range): array
    {
        return $this->leadFunnel->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadAgentLeaderboard(MetricScope $scope, DateRange $range): array
    {
        return $this->leadAgentLeaderboard->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadTimeToConversion(MetricScope $scope, DateRange $range): array
    {
        return $this->leadTimeToConversion->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadResponseTime(MetricScope $scope, DateRange $range): array
    {
        return $this->leadResponseTime->compute($scope, $range);
    }

    /** @return array<string,mixed> */
    public function leadRevenue(MetricScope $scope, DateRange $range): array
    {
        return $this->leadRevenue->compute($scope, $range);
    }
}
