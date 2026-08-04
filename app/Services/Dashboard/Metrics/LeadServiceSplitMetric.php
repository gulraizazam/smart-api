<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Leads grouped by the service they enquired about (joins leads_services).
 *
 * A lead may enquire about multiple services — each service is counted
 * once per lead (DISTINCT). Top 10 services are surfaced; everything
 * beyond that is bucketed into "Other" to keep the chart readable.
 */
final class LeadServiceSplitMetric implements Metric
{
    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['items' => []];
        }

        // We can't apply the branch scope inside the sub-query directly on
        // leads_services, so we build the filtered lead-id set first.
        $filteredIds = LeadsQueryHelper::filtered($scope, $range)->pluck('l.id');
        if ($filteredIds->isEmpty()) {
            return ['items' => []];
        }

        $rows = DB::table('leads_services as ls')
            ->join('services as s', 's.id', '=', 'ls.service_id')
            ->whereIn('ls.lead_id', $filteredIds)
            ->whereNull('ls.deleted_at')
            ->selectRaw('ls.service_id, s.name, COUNT(DISTINCT ls.lead_id) as count')
            ->groupBy('ls.service_id', 's.name')
            ->orderByDesc('count')
            ->get();

        // Top 10 + Other bucket.
        $top = $rows->take(10)->map(fn ($r) => [
            'service_id' => (int) $r->service_id,
            'name' => (string) $r->name,
            'count' => (int) $r->count,
        ])->values();
        if ($rows->count() > 10) {
            $otherCount = (int) $rows->slice(10)->sum('count');
            if ($otherCount > 0) {
                $top->push(['service_id' => null, 'name' => 'Other', 'count' => $otherCount]);
            }
        }

        return ['items' => $top->all()];
    }
}
