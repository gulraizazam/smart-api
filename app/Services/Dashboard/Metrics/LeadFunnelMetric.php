<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\Support\LeadStatusResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Conversion funnel: Generated → Booked → Arrived → Converted.
 *
 * Uses the hierarchical status buckets — "booked_or_beyond" so a
 * converted lead is also counted at every earlier stage. Drop-off
 * percentages are between adjacent stages.
 */
final class LeadFunnelMetric implements Metric
{
    public function __construct(private readonly LeadStatusResolver $statuses) {}

    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['stages' => []];
        }

        $ids = $this->statuses->statusIds($scope->accountId);
        $base = LeadsQueryHelper::filtered($scope, $range);

        $generated = (clone $base)->count();
        $booked = $ids['booked'] !== []
            ? (clone $base)->whereIn('l.lead_status_id', $ids['booked'])->count()
            : 0;
        $arrived = $ids['arrived'] !== []
            ? (clone $base)->whereIn('l.lead_status_id', $ids['arrived'])->count()
            : 0;
        $converted = $ids['converted'] !== []
            ? (clone $base)->whereIn('l.lead_status_id', $ids['converted'])->count()
            : 0;

        $pct = fn (int $n, int $d): float => $d > 0 ? round(($n / $d) * 100, 1) : 0.0;

        return [
            'stages' => [
                ['key' => 'generated', 'label' => 'Generated', 'count' => $generated, 'pct_of_prev' => 100.0],
                ['key' => 'booked',    'label' => 'Booked',    'count' => $booked,    'pct_of_prev' => $pct($booked, $generated)],
                ['key' => 'arrived',   'label' => 'Arrived',   'count' => $arrived,   'pct_of_prev' => $pct($arrived, $booked)],
                ['key' => 'converted', 'label' => 'Converted', 'count' => $converted, 'pct_of_prev' => $pct($converted, $arrived)],
            ],
        ];
    }
}
