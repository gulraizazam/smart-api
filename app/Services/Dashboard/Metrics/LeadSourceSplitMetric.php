<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/** Leads grouped by source (Facebook / Referral / Walk-in / …). */
final class LeadSourceSplitMetric implements Metric
{
    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['items' => []];
        }

        $rows = LeadsQueryHelper::filtered($scope, $range)
            ->leftJoin('lead_sources as s', 's.id', '=', 'l.lead_source_id')
            ->selectRaw("l.lead_source_id, COALESCE(s.name, 'Unspecified') as name, COUNT(*) as count")
            ->groupBy('l.lead_source_id', 's.name')
            ->orderByDesc('count')
            ->get();

        return [
            'items' => $rows->map(fn ($r) => [
                'source_id' => $r->lead_source_id !== null ? (int) $r->lead_source_id : null,
                'name' => (string) $r->name,
                'count' => (int) $r->count,
            ])->all(),
        ];
    }
}
