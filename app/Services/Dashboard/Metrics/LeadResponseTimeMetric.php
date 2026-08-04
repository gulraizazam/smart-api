<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Response time: median hours from lead creation to first agent action.
 *
 * "First agent action" = MIN(lead_comments.created_at) for the lead.
 * Leads with no comment yet are excluded (still-waiting-for-first-touch —
 * an important number in its own right, exposed as `pending_first_touch`).
 */
final class LeadResponseTimeMetric implements Metric
{
    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['median_hours' => 0.0, 'p90_hours' => 0.0, 'sample_size' => 0, 'pending_first_touch' => 0];
        }

        $rows = LeadsQueryHelper::filtered($scope, $range)
            ->join('lead_comments as c', function ($j): void {
                $j->on('c.lead_id', '=', 'l.id')->whereNull('c.deleted_at');
            })
            ->selectRaw('TIMESTAMPDIFF(HOUR, l.created_at, MIN(c.created_at)) as hours')
            ->groupBy('l.id', 'l.created_at')
            ->havingRaw('hours IS NOT NULL AND hours >= 0')
            ->pluck('hours')
            ->map(static fn ($h) => (int) $h)
            ->values()
            ->sort()
            ->values();

        // "Still waiting" = filtered leads with zero comment rows.
        $pending = LeadsQueryHelper::filtered($scope, $range)
            ->leftJoin('lead_comments as c', function ($j): void {
                $j->on('c.lead_id', '=', 'l.id')->whereNull('c.deleted_at');
            })
            ->whereNull('c.id')
            ->distinct()
            ->count('l.id');

        $n = $rows->count();
        if ($n === 0) {
            return [
                'median_hours' => 0.0,
                'p90_hours' => 0.0,
                'sample_size' => 0,
                'pending_first_touch' => (int) $pending,
            ];
        }

        return [
            'median_hours' => (float) self::percentile($rows->all(), 0.5),
            'p90_hours' => (float) self::percentile($rows->all(), 0.9),
            'sample_size' => $n,
            'pending_first_touch' => (int) $pending,
        ];
    }

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
