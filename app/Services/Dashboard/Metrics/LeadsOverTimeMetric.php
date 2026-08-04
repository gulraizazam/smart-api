<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\Support\LeadStatusResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Leads over time — five series (generated / booked / arrived / converted / junk)
 * bucketed by day, week, or month depending on range length.
 *
 * Bucket rule:
 *   ≤ 90 days  → daily buckets
 *   91-540 days → weekly (ISO week start)
 *   > 540 days → monthly
 *
 * Zero-filling of empty buckets is left to the SPA (Recharts renders
 * gaps as breaks) — mostly to keep the payload small.
 */
final class LeadsOverTimeMetric implements Metric
{
    public function __construct(private readonly LeadStatusResolver $statuses) {}

    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['bucket' => 'day', 'series' => []];
        }

        $days = $range->from->diffInDays($range->to) + 1;
        [$bucket, $sqlExpr] = match (true) {
            $days <= 90  => ['day',   "DATE(l.created_at)"],
            $days <= 540 => ['week',  "DATE_FORMAT(l.created_at, '%x-W%v')"],
            default      => ['month', "DATE_FORMAT(l.created_at, '%Y-%m')"],
        };

        $ids = $this->statuses->statusIds($scope->accountId);
        $bookedIds = self::sqlList($ids['booked']);
        $arrivedIds = self::sqlList($ids['arrived']);
        $convertedIds = self::sqlList($ids['converted']);
        $junkIds = self::sqlList($ids['junk']);

        $rows = LeadsQueryHelper::filtered($scope, $range)
            ->selectRaw(
                "{$sqlExpr} as bucket, ".
                'COUNT(*) as generated, '.
                ($bookedIds ? "SUM(CASE WHEN l.lead_status_id IN ({$bookedIds}) THEN 1 ELSE 0 END)" : '0').' as booked, '.
                ($arrivedIds ? "SUM(CASE WHEN l.lead_status_id IN ({$arrivedIds}) THEN 1 ELSE 0 END)" : '0').' as arrived, '.
                ($convertedIds ? "SUM(CASE WHEN l.lead_status_id IN ({$convertedIds}) THEN 1 ELSE 0 END)" : '0').' as converted, '.
                ($junkIds ? "SUM(CASE WHEN l.lead_status_id IN ({$junkIds}) THEN 1 ELSE 0 END)" : '0').' as junk'
            )
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return [
            'bucket' => $bucket,
            'series' => $rows->map(fn ($r) => [
                'bucket' => (string) $r->bucket,
                'generated' => (int) $r->generated,
                'booked' => (int) $r->booked,
                'arrived' => (int) $r->arrived,
                'converted' => (int) $r->converted,
                'junk' => (int) $r->junk,
            ])->all(),
        ];
    }

    private static function sqlList(array $ids): string
    {
        return $ids === [] ? '' : implode(',', array_map('intval', $ids));
    }
}
