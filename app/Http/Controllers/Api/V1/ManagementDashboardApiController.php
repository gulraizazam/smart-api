<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\ManagementDashboard\ManagementDashboardService;
use App\Traits\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * JSON endpoints for the Management Dashboard. Live endpoints back the
 * Overview composite, Practitioners list, plus the cross-section widgets
 * Overview pulls in (branches, new vs returning, service category trend,
 * patients = cohort + concentration, today's activities).
 */
class ManagementDashboardApiController extends Controller
{
    use ApiResponse;

    private const int MAX_RANGE_DAYS = 400;

    public function __construct(
        private readonly ManagementDashboardService $service,
        private readonly ResourceScopeResolver $scopeResolver,
    ) {}

    public function filterBranches(): JsonResponse
    {
        try {
            $user = Auth::user();
            $allowed = $this->scopeResolver->allowedBranchIds($user);

            $branches = Locations::query()
                ->where('account_id', $user->account_id)
                ->whereNull('deleted_at')
                ->where('name', 'not like', 'All %')
                ->when($allowed !== null, static fn ($q) => $q->whereIn('id', $allowed))
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return $this->successResponse('Filter branches loaded.', [
                'branches' => $branches,
                'is_company_wide' => $allowed === null,
                'allowed_branch_count' => $allowed === null ? $branches->count() : count($allowed),
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ManagementDashboard filter-branches');
        }
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'overview', fn ($scope, $range) => $this->service->overview($scope, $range));
    }

    public function branches(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'branches', fn ($scope, $range) => $this->service->branches($scope, $range));
    }

    public function branchDoctorBreakdown(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }

        return $this->handleSection(
            $request,
            'branch-doctor-breakdown',
            fn ($scope, $range) => $this->service->branchDoctorBreakdown($scope, $branchId, $range),
        );
    }

    public function branchFeedback(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'branch-feedback', fn ($scope, $range) => $this->service->branchFeedback($scope, $range));
    }

    public function branchDoctorFeedback(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }

        return $this->handleSection(
            $request,
            'branch-doctor-feedback',
            fn ($scope, $range) => $this->service->branchDoctorFeedback($scope, $range, $branchId),
        );
    }

    public function branchFeedbackTrend(Request $request): JsonResponse
    {
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'branch-feedback-trend',
            fn ($scope) => $this->service->branchFeedbackTrend($scope, $months),
        );
    }

    public function branchDoctorFeedbackTrend(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'branch-doctor-feedback-trend',
            fn ($scope) => $this->service->branchDoctorFeedbackTrend($scope, $branchId, $months),
        );
    }

    public function branchDoctorRetention(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }

        return $this->handleSection(
            $request,
            'branch-doctor-retention',
            fn ($scope) => $this->service->branchDoctorRetention($scope, $branchId),
        );
    }

    public function retentionTrend(Request $request): JsonResponse
    {
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'retention-trend',
            fn ($scope) => $this->service->retentionTrend($scope, $months),
        );
    }

    public function branchDoctorRetentionTrend(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'branch-doctor-retention-trend',
            fn ($scope) => $this->service->branchDoctorRetentionTrend($scope, $branchId, $months),
        );
    }

    public function atRiskSummary(Request $request): JsonResponse
    {
        return $this->handleSection(
            $request,
            'at-risk-summary',
            fn ($scope) => $this->service->atRiskSummary($scope),
        );
    }

    public function atRiskOverview(Request $request): JsonResponse
    {
        $patientLimit = max(1, min(100, (int) $request->query('patient_limit', 25)));

        return $this->handleSection(
            $request,
            'at-risk-overview',
            fn ($scope) => $this->service->atRiskOverview($scope, $patientLimit),
        );
    }

    public function atRiskByDoctor(Request $request): JsonResponse
    {
        return $this->handleSection(
            $request,
            'at-risk-by-doctor',
            fn ($scope) => $this->service->atRiskByDoctor($scope),
        );
    }

    public function atRiskList(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }

        $signals = $this->csvParam($request->query('signals'));
        $valueTiers = $this->csvParam($request->query('value_tiers'));
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $offset = max(0, (int) $request->query('offset', 0));

        return $this->handleSection(
            $request,
            'at-risk-list',
            fn ($scope) => $this->service->atRiskList(
                $scope,
                $branchId,
                $signals,
                $valueTiers,
                $limit,
                $offset,
            ),
        );
    }

    /**
     * Parse a comma-separated query parameter into a list of strings.
     * Returns null when the parameter is absent or empty so callers can
     * distinguish "no filter" from "filter to empty set".
     *
     * @return list<string>|null
     */
    private function csvParam(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = explode(',', (string) $value);
        }

        $clean = array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $parts),
            static fn (string $v): bool => $v !== '',
        ));

        return $clean === [] ? null : $clean;
    }

    public function arrivalRate(Request $request): JsonResponse
    {
        $type = $request->query('type', 'consultation');
        $typeId = $type === 'treatment' ? 2 : 1;
        $groupBy = $request->query('group_by', 'branch');
        if (! in_array($groupBy, ['branch', 'category'], true)) {
            return $this->errorResponse('Invalid request.', 422, ['group_by' => ['group_by must be branch or category.']]);
        }

        return $this->handleSection(
            $request,
            'arrival-rate',
            fn ($scope, $range) => $this->service->arrivalRate($scope, $range, $typeId, $groupBy),
        );
    }

    public function utilization(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'utilization', fn ($scope, $range) => $this->service->utilization($scope, $range));
    }

    public function utilizationHeatmap(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'utilization-heatmap', fn ($scope, $range) => $this->service->utilizationHeatmap($scope, $range));
    }

    public function utilizationTrend(Request $request): JsonResponse
    {
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'utilization-trend',
            fn ($scope) => $this->service->utilizationTrend($scope, $months),
        );
    }

    public function utilizationTrendByBranch(Request $request): JsonResponse
    {
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'utilization-trend-by-branch',
            fn ($scope) => $this->service->utilizationTrendByBranch($scope, $months),
        );
    }

    public function branchDoctorUtilizationTrend(Request $request): JsonResponse
    {
        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return $this->errorResponse('Invalid request.', 422, ['branch_id' => ['branch_id is required.']]);
        }
        $months = max(2, min(24, (int) $request->query('months', 6)));

        return $this->handleSection(
            $request,
            'branch-doctor-utilization-trend',
            fn ($scope) => $this->service->branchDoctorUtilizationTrend($scope, $branchId, $months),
        );
    }

    public function patients(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'patients', function ($scope, $range) use ($request) {
            $cohortMonths = max(3, min(24, (int) $request->query('cohort_months', 12)));
            $extended = $cohortMonths > 12 || $request->boolean('extended_windows');

            return $this->service->patients($scope, $range, $cohortMonths, $extended);
        });
    }

    public function newReturning(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'new-returning', fn ($scope, $range) => $this->service->newReturning($scope, $range));
    }

    public function serviceCategoryTrend(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'service-category-trend', function ($scope, $range) use ($request) {
            $months = max(3, min(24, (int) $request->query('months', 12)));

            return $this->service->serviceCategoryTrend($scope, $range, $months);
        });
    }

    public function salesByServiceCategory(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'sales-by-service-category', function ($scope, $range) {
            return $this->service->salesByServiceCategory($scope, $range);
        });
    }

    public function serviceSalesTrend(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'service-sales-trend', function ($scope, $range) use ($request) {
            $months = max(3, min(24, (int) $request->query('months', 12)));

            return $this->service->serviceSalesTrend($scope, $range, $months);
        });
    }

    public function leadGenderFunnel(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'lead-gender-funnel', function ($scope, $range) {
            // Deep-dive returns the superset — funnel + trend + sources +
            // economics + rescue. Flatten funnel stages to the root so the
            // compact tile's legacy shape (data.generated/...etc) still works,
            // and expose the toggle-view blocks as sibling keys.
            $data = $this->service->leadGenderDeepDive($scope, $range);

            return array_merge($data['funnel'] ?? [], [
                'trend' => $data['trend'] ?? null,
                'sources' => $data['sources'] ?? [],
                'economics' => $data['economics'] ?? null,
                'rescue' => $data['rescue'] ?? null,
            ]);
        });
    }

    public function avgTransactionValue(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'avg-transaction-value', fn ($scope, $range) => $this->service->avgTransactionValue($scope, $range));
    }

    public function avgConversionValue(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'avg-conversion-value', fn ($scope, $range) => $this->service->avgConversionValue($scope, $range));
    }

    public function genderRevenue(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'gender-revenue', fn ($scope, $range) => $this->service->genderRevenue($scope, $range));
    }

    public function leadServiceInterest(Request $request): JsonResponse
    {
        return $this->handleSection($request, 'lead-service-interest', fn ($scope, $range) => $this->service->leadServiceInterest($scope, $range));
    }

    public function todayActivities(Request $request): JsonResponse
    {
        try {
            $scope = $this->service->scopeFor(Auth::user(), $this->branchFilter($request));
            $limit = min(50, max(5, $request->integer('limit') ?: 20));
            $cursor = $request->query('cursor');
            $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

            $data = $this->service->todayActivities($scope, $cursor, $limit);

            return $this->successResponse('Today activities loaded.', $data);
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid request.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ManagementDashboard today-activities');
        }
    }

    /**
     * Shared wrapper: build scope/range, invoke the section closure, return a
     * consistent envelope. Validation errors return 422 with field errors;
     * other failures log-and-render via ApiResponse::handleException.
     */
    private function handleSection(Request $request, string $context, \Closure $compute): JsonResponse
    {
        try {
            [$scope, $range] = $this->buildScopeAndRange($request);

            return $this->successResponse(ucfirst($context).' loaded.', $compute($scope, $range));
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid request.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e, "ManagementDashboard {$context}");
        }
    }

    /**
     * @return array{0: MetricScope, 1: DateRange}
     */
    private function buildScopeAndRange(Request $request): array
    {
        $scope = $this->service->scopeFor(Auth::user(), $this->branchFilter($request));
        $range = $this->buildRange($request);

        return [$scope, $range];
    }

    /**
     * Build the branch filter from the request. Any requested branch the user
     * is NOT assigned to triggers a 422 — we don't silently drop them, because
     * silent drops let attackers enumerate valid branch IDs by observing
     * response differences.
     *
     * @return list<int>|null
     */
    private function branchFilter(Request $request): ?array
    {
        $branches = $request->query('branches');

        if ($branches === null || $branches === '' || $branches === []) {
            return null;
        }

        if (is_string($branches)) {
            $branches = explode(',', $branches);
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $branches),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return null;
        }

        $allowed = $this->scopeResolver->allowedBranchIds(Auth::user());

        if ($allowed !== null) {
            $disallowed = array_values(array_diff($ids, $allowed));

            if ($disallowed !== []) {
                throw ValidationException::withMessages([
                    'branches' => ['Requested branches are outside your assigned scope.'],
                ]);
            }
        }

        return $ids;
    }

    private function buildRange(Request $request): DateRange
    {
        $preset = (string) $request->query('range', 'this_month');

        if ($preset === 'custom') {
            return $this->buildCustomRange($request);
        }

        [$from, $to] = match ($preset) {
            'today' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
            'yesterday' => [now()->subDay()->format('Y-m-d'), now()->subDay()->format('Y-m-d')],
            // "Last N days" is inclusive of today: (N-1) days ago → today = N calendar days.
            'last_7_days' => [now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')],
            'last_30_days' => [now()->subDays(29)->format('Y-m-d'), now()->format('Y-m-d')],
            'this_week' => [now()->startOfWeek()->format('Y-m-d'), now()->format('Y-m-d')],
            'this_month' => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
                now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
            ],
            default => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')],
        };

        return DateRange::fromStrings($from, $to);
    }

    /**
     * Strict custom-range parser: requires real calendar dates (rejects
     * 2026-13-45), enforces from <= to, caps the window to MAX_RANGE_DAYS
     * to prevent full-table-scan abuse. Invalid input throws a 422 instead
     * of silently falling back — silent fallback hides client bugs and lets
     * a crafted request run against the wrong window without anyone noticing.
     */
    private function buildCustomRange(Request $request): DateRange
    {
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $fromDate = $this->parseStrictDate($from);
        $toDate = $this->parseStrictDate($to);

        if ($fromDate === null || $toDate === null) {
            throw ValidationException::withMessages([
                'range' => ['Custom range requires valid from/to dates in YYYY-MM-DD format.'],
            ]);
        }

        if ($fromDate->gt($toDate)) {
            throw ValidationException::withMessages([
                'range' => ['Custom range "from" must be on or before "to".'],
            ]);
        }

        $days = (int) $fromDate->diffInDays($toDate) + 1;
        if ($days > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'range' => ['Custom range cannot exceed '.self::MAX_RANGE_DAYS.' days.'],
            ]);
        }

        return DateRange::fromStrings($fromDate->format('Y-m-d'), $toDate->format('Y-m-d'));
    }

    private function parseStrictDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return $parsed;
    }
}
