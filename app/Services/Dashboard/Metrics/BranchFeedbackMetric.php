<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Branch-wise feedback score — one row per branch in scope, showing the
 * weighted average rating (1–10 scale), total feedback count, and coverage
 * against the number of arrived treatment procedures over the requested
 * date range. Treatment coverage = feedback_on_treatments / arrived_treatments.
 *
 * Excludes today's data to match doctor dashboard semantics (avoid partial
 * same-day feedback polluting the KPI). Branches with zero feedback in the
 * window are still returned with avg=0, total=0 so the chart lays them out
 * against peers rather than silently hiding gaps.
 */
final class BranchFeedbackMetric implements Metric
{
    private const CACHE_TTL = 300;

    private const TREATMENT_TYPE_ID = 2;

    private const TREATMENT_STATUS_ID = 2;

    public function __construct(
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $cacheKey = 'mgmt_dash:branch_feedback:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope, $range));
    }

    /**
     * @return array{rows: list<array<string, mixed>>, coverage: array<string, mixed>}
     */
    private function build(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['rows' => [], 'coverage' => $this->emptyCoverage()];
        }

        $branchIds = $this->branches->idsInScope($scope);

        if ($branchIds === []) {
            return ['rows' => [], 'coverage' => $this->emptyCoverage()];
        }

        $branchNames = $this->branches->names($branchIds);
        $aggregates = $this->feedbackByBranch($branchIds, $range);
        $treatments = $this->treatmentsByBranch($branchIds, $range);

        $rows = [];
        $totalTreatments = 0;
        $totalTreatmentFeedback = 0;
        foreach ($branchIds as $branchId) {
            $agg = $aggregates[$branchId] ?? ['avg' => 0.0, 'total' => 0];
            $tCount = $treatments[$branchId] ?? 0;
            $tfCount = $agg['total'];
            $totalTreatments += $tCount;
            $totalTreatmentFeedback += $tfCount;
            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => $branchNames[$branchId] ?? "Branch #{$branchId}",
                'avg_rating' => round($agg['avg'], 2),
                'total_feedback' => $tfCount,
                'treatment_count' => $tCount,
                'coverage_pct' => $tCount > 0 ? round(($tfCount / $tCount) * 100, 1) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['avg_rating'] <=> $a['avg_rating']);

        return [
            'rows' => $rows,
            'coverage' => [
                'total_treatments' => $totalTreatments,
                'total_treatment_feedback' => $totalTreatmentFeedback,
                'percentage' => $totalTreatments > 0
                    ? round(($totalTreatmentFeedback / $totalTreatments) * 100, 1)
                    : null,
            ],
        ];
    }

    /**
     * Single grouped query — weighted average and count of feedback on
     * arrived treatment procedures in the window, keyed by branch. Using
     * appointment-join (instead of a bare feedback.created_at filter) keeps
     * the score/count/coverage-denominator aligned: every number on the
     * panel refers to the same set of procedure feedbacks.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{avg: float, total: int}>
     */
    private function feedbackByBranch(array $branchIds, DateRange $range): array
    {
        $endDate = $this->excludeToday($range->endString());

        $rows = DB::table('feedback')
            ->join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
            ->whereIn('appointments.location_id', $branchIds)
            ->where('appointments.appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointments.appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('appointments.scheduled_date', [$range->startString(), $endDate])
            ->whereNull('appointments.deleted_at')
            ->select('appointments.location_id as location_id')
            ->selectRaw('AVG(CAST(feedback.rating AS DECIMAL(4,2))) AS avg_rating, COUNT(DISTINCT feedback.appointment_id) AS total_feedback')
            ->groupBy('appointments.location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'avg' => $row->avg_rating !== null ? (float) $row->avg_rating : 0.0,
                'total' => (int) $row->total_feedback,
            ];
        }

        return $out;
    }

    /**
     * Arrived treatment appointments per branch, using scheduled_date to
     * match the legacy doctor-feedback coverage stats.
     *
     * @param  list<int>  $branchIds
     * @return array<int, int>
     */
    private function treatmentsByBranch(array $branchIds, DateRange $range): array
    {
        $endDate = $this->excludeToday($range->endString());

        $rows = DB::table('appointments')
            ->whereIn('location_id', $branchIds)
            ->where('appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('scheduled_date', [$range->startString(), $endDate])
            ->whereNull('deleted_at')
            ->select('location_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = (int) $row->total;
        }

        return $out;
    }

    private function excludeToday(string $endDate): string
    {
        $today = now()->format('Y-m-d');

        return $endDate >= $today
            ? now()->subDay()->format('Y-m-d')
            : $endDate;
    }

    /**
     * @return array{total_treatments: int, total_treatment_feedback: int, percentage: float|null}
     */
    private function emptyCoverage(): array
    {
        return [
            'total_treatments' => 0,
            'total_treatment_feedback' => 0,
            'percentage' => null,
        ];
    }
}
