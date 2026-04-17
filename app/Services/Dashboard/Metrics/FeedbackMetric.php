<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\FeedbackCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Feedback average + total across any scope.
 *
 * Doctor scope → delegates to existing FeedbackCalculator (excludes today).
 * Branch / company scope → single aggregated query over the feedback table
 * (weighted average, not average-of-averages). Branch scope filters on
 * feedback.location_id; company scope drops the location filter.
 *
 * Excludes today's data to match doctor dashboard semantics (intended to
 * avoid showing partial same-day feedback on KPIs).
 *
 * Return shape: ['avg_rating' => float, 'total_feedback' => int]
 */
final class FeedbackMetric implements Metric
{
    public function __construct(
        private readonly FeedbackCalculator $calculator,
    ) {}

    /**
     * @return array{avg_rating: float, total_feedback: int}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $result = $this->calculator->calculate(
                (int) $scope->resourceId,
                $range->startString(),
                $range->endString(),
            );

            return [
                'avg_rating' => (float) $result['avg_rating'],
                'total_feedback' => (int) $result['total_feedback'],
            ];
        }

        return $this->aggregate($scope, $range);
    }

    private function aggregate(MetricScope $scope, DateRange $range): array
    {
        $endDate = $this->excludeToday($range->endString());
        $start = $range->startString().' 00:00:00';
        $end = $endDate.' 23:59:59';

        $query = DB::table('feedback')->whereBetween('created_at', [$start, $end]);

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('location_id', $scope->branchIds);
        }

        $row = $query
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedback')
            ->first();

        return [
            'avg_rating' => $row && $row->avg_rating !== null ? (float) $row->avg_rating : 0.0,
            'total_feedback' => $row ? (int) $row->total_feedback : 0,
        ];
    }

    private function excludeToday(string $endDate): string
    {
        $today = now()->format('Y-m-d');

        return $endDate >= $today
            ? now()->subDay()->format('Y-m-d')
            : $endDate;
    }

    /**
     * @return array{avg_rating: float, total_feedback: int}
     */
    private function empty(): array
    {
        return ['avg_rating' => 0.0, 'total_feedback' => 0];
    }
}
