<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\Support\LeadStatusResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Time-to-conversion: for leads that converted in-window, how long between
 * their creation and their conversion timestamp?
 *
 * "Converted at" = MIN(appointments.converted_at) for appointments tied to
 * the lead (per backend map — the lead itself has no converted_at).
 *
 * Reports median + p90 in days. Median calculated in PHP over the fetched
 * distribution (dashboards rarely have > 10k converted leads in-window; if
 * they do we can move to a MariaDB percentile CTE later).
 */
final class LeadTimeToConversionMetric implements Metric
{
    public function __construct(private readonly LeadStatusResolver $statuses) {}

    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['median_days' => 0.0, 'p90_days' => 0.0, 'sample_size' => 0];
        }

        $convertedIds = $this->statuses->converted($scope->accountId);
        if ($convertedIds === []) {
            return ['median_days' => 0.0, 'p90_days' => 0.0, 'sample_size' => 0];
        }

        $rows = LeadsQueryHelper::filtered($scope, $range)
            ->join('appointments as a', function ($j): void {
                $j->on('a.lead_id', '=', 'l.id')
                    ->whereNull('a.deleted_at')
                    ->whereNotNull('a.converted_at');
            })
            ->whereIn('l.lead_status_id', $convertedIds)
            ->selectRaw('DATEDIFF(MIN(a.converted_at), l.created_at) as days')
            ->groupBy('l.id', 'l.created_at')
            ->havingRaw('days IS NOT NULL AND days >= 0')
            ->pluck('days')
            ->map(static fn ($d) => (int) $d)
            ->values()
            ->sort()
            ->values();

        $n = $rows->count();
        if ($n === 0) {
            return ['median_days' => 0.0, 'p90_days' => 0.0, 'sample_size' => 0];
        }

        return [
            'median_days' => (float) self::percentile($rows->all(), 0.5),
            'p90_days' => (float) self::percentile($rows->all(), 0.9),
            'sample_size' => $n,
        ];
    }

    /** Interpolated percentile — matches numpy's `interpolation='linear'`. */
    private static function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted[0];
        }
        $rank = $p * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);
        if ($lo === $hi) {
            return (float) $sorted[$lo];
        }
        return $sorted[$lo] + ($rank - $lo) * ($sorted[$hi] - $sorted[$lo]);
    }
}
