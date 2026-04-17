<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Contracts;

use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Contract every metric adapter implements.
 *
 * Implementations are responsible for respecting the scope (company / branch subset /
 * single resource) and the date range. Shape of the returned payload is metric-specific
 * and documented on each implementation.
 */
interface Metric
{
    /**
     * Compute this metric for the given scope and date range.
     *
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array;
}
