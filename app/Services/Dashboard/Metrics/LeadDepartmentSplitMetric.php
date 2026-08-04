<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Leads grouped by medical/service department (Skin / Hair / Aesthetics / …).
 *
 * Historical leads without a department land in the "Unassigned" bucket;
 * the "Retro-populate department_id" follow-up in the plan file addresses
 * shrinking that bucket over time.
 */
final class LeadDepartmentSplitMetric implements Metric
{
    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['items' => []];
        }

        $rows = LeadsQueryHelper::filtered($scope, $range)
            ->leftJoin('lead_departments as d', 'd.id', '=', 'l.department_id')
            ->selectRaw("l.department_id, COALESCE(d.name, 'Unassigned') as name, COUNT(*) as count")
            ->groupBy('l.department_id', 'd.name')
            ->orderByDesc('count')
            ->get();

        return [
            'items' => $rows->map(fn ($r) => [
                'department_id' => $r->department_id !== null ? (int) $r->department_id : null,
                'name' => (string) $r->name,
                'count' => (int) $r->count,
            ])->all(),
        ];
    }
}
