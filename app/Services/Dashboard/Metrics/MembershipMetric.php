<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\MembershipCalculator;

/**
 * Gold membership count across any scope.
 *
 * Doctor scope → delegates to existing MembershipCalculator.
 * Branch / company scope → MembershipCalculator::calculateForDoctors with
 * doctor set from DoctorResolver; sum the resulting map.
 *
 * Return shape: ['gold_memberships_sold' => int]
 */
final class MembershipMetric implements Metric
{
    public function __construct(
        private readonly MembershipCalculator $calculator,
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{gold_memberships_sold: int}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['gold_memberships_sold' => 0];
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $result = $this->calculator->calculate(
                (int) $scope->resourceId,
                $range->startString(),
                $range->endString(),
                $scope->accountId,
            );

            return ['gold_memberships_sold' => (int) $result['gold_memberships_sold']];
        }

        $doctorIds = $this->doctors->idsInScope($scope);

        if ($doctorIds === []) {
            return ['gold_memberships_sold' => 0];
        }

        $perDoctor = $this->calculator->calculateForDoctors(
            $doctorIds,
            $range->startString(),
            $range->endString(),
            $scope->accountId,
        );

        return ['gold_memberships_sold' => (int) array_sum($perDoctor)];
    }
}
