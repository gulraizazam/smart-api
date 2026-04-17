<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Models\DoctorGoogleReview;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;

/**
 * Google review count across any scope.
 *
 * Reviews are stored as monthly roll-ups per doctor (doctor_google_reviews).
 * The metric inspects the month containing the start of the date range to
 * match doctor dashboard single-month lookup.
 *
 * Doctor scope → monthly review_count for that doctor.
 * Branch scope  → sum across doctors in scope (DoctorResolver).
 * Company scope → sum across all active doctors.
 */
final class GoogleReviewsMetric implements Metric
{
    public function __construct(
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{review_count: int}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['review_count' => 0];
        }

        $start = Carbon::parse($range->startString());
        $month = (int) $start->format('m');
        $year = (int) $start->format('Y');

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $review = DoctorGoogleReview::getForDoctorMonth(
                (int) $scope->resourceId,
                $month,
                $year,
                $scope->accountId,
            );

            return ['review_count' => $review ? (int) $review->review_count : 0];
        }

        $doctorIds = $this->doctors->idsInScope($scope);

        if ($doctorIds === []) {
            return ['review_count' => 0];
        }

        $total = DoctorGoogleReview::query()
            ->where('account_id', $scope->accountId)
            ->whereIn('doctor_id', $doctorIds)
            ->where('month', $month)
            ->where('year', $year)
            ->sum('review_count');

        return ['review_count' => (int) $total];
    }
}
