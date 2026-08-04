<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Count of leads grouped by lead_status — the "status split" view of the
 * LeadsSplitByPanel on the dashboard.
 */
final class LeadStatusSplitMetric implements Metric
{
    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['items' => []];
        }

        $rows = LeadsQueryHelper::filtered($scope, $range)
            ->leftJoin('lead_statuses as s', 's.id', '=', 'l.lead_status_id')
            ->selectRaw(
                "l.lead_status_id, ".
                "COALESCE(s.name, 'Unassigned') as name, ".
                "COALESCE(s.is_junk, 0) as is_junk, ".
                "COALESCE(s.is_booked, 0) as is_booked, ".
                "COALESCE(s.is_arrived, 0) as is_arrived, ".
                "COALESCE(s.is_converted, 0) as is_converted, ".
                'COUNT(*) as count'
            )
            ->groupBy('l.lead_status_id', 's.name', 's.is_junk', 's.is_booked', 's.is_arrived', 's.is_converted')
            ->orderByDesc('count')
            ->get();

        return [
            'items' => $rows->map(fn ($r) => [
                'status_id' => $r->lead_status_id !== null ? (int) $r->lead_status_id : null,
                'name' => (string) $r->name,
                'count' => (int) $r->count,
                'is_junk' => (bool) $r->is_junk,
                'is_booked' => (bool) $r->is_booked,
                'is_arrived' => (bool) $r->is_arrived,
                'is_converted' => (bool) $r->is_converted,
            ])->all(),
        ];
    }
}
