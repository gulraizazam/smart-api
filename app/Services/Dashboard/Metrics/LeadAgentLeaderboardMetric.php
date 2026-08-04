<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\LeadsQueryHelper;
use App\Services\Dashboard\Support\LeadStatusResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Per-agent leaderboard: leads generated / converted / conversion% / revenue.
 *
 * "Agent" here = `leads.created_by` (the user who intook the lead in the
 * CRM). Revenue is attributed via the appointments → packages → package_advances
 * chain, keyed back through the lead's created_by (i.e. an agent's revenue
 * = sum of net-in on packages tied to appointments whose lead they own).
 */
final class LeadAgentLeaderboardMetric implements Metric
{
    public function __construct(private readonly LeadStatusResolver $statuses) {}

    /** @return array<string,mixed> */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['items' => []];
        }

        $ids = $this->statuses->statusIds($scope->accountId);
        $convertedIds = self::sqlList($ids['converted']);
        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        // Per-agent lead counts (single scan).
        $counts = LeadsQueryHelper::filtered($scope, $range)
            ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
            ->selectRaw(
                "l.created_by as user_id, COALESCE(u.name, 'System') as name, ".
                'COUNT(*) as generated, '.
                ($convertedIds ? "SUM(CASE WHEN l.lead_status_id IN ({$convertedIds}) THEN 1 ELSE 0 END)" : '0').' as converted'
            )
            ->groupBy('l.created_by', 'u.name')
            ->get()
            ->keyBy('user_id');

        // Per-agent revenue via the join chain — separate query because
        // the shape (multi-row joins) doesn't add cleanly to the counts.
        $revenueRows = DB::table('leads as l')
            ->join('appointments as a', function ($j): void {
                $j->on('a.lead_id', '=', 'l.id')->whereNull('a.deleted_at');
            })
            ->join('packages as p', function ($j): void {
                $j->on('p.appointment_id', '=', 'a.id')->whereNull('p.deleted_at');
            })
            ->join('package_advances as pa', function ($j): void {
                $j->on('pa.package_id', '=', 'p.id')->whereNull('pa.deleted_at');
            })
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [$start, $end])
            ->whereBetween('pa.created_at', [$start, $end])
            ->when($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== [], fn ($q) => $q->where(function ($qq) use ($scope) {
                $qq->whereIn('l.location_id', $scope->branchIds)->orWhereNull('l.location_id');
            }))
            ->selectRaw(
                'l.created_by as user_id, '.
                "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND COALESCE(pa.is_refund,0) = 0 THEN pa.cash_amount ELSE 0 END),0) - ".
                'COALESCE(SUM(CASE WHEN COALESCE(pa.is_refund,0) = 1 THEN pa.cash_amount ELSE 0 END),0) as revenue'
            )
            ->groupBy('l.created_by')
            ->get()
            ->keyBy('user_id');

        $items = [];
        foreach ($counts as $userId => $row) {
            $generated = (int) $row->generated;
            $converted = (int) $row->converted;
            $pct = $generated > 0 ? round(($converted / $generated) * 100, 1) : 0.0;
            $revenue = (float) ($revenueRows[$userId]->revenue ?? 0);

            $items[] = [
                'user_id' => $userId !== null ? (int) $userId : null,
                'name' => (string) $row->name,
                'generated' => $generated,
                'converted' => $converted,
                'conversion_pct' => $pct,
                'revenue' => $revenue,
            ];
        }

        // Sort by generated desc as the default.
        usort($items, fn ($a, $b) => $b['generated'] <=> $a['generated']);

        return ['items' => $items];
    }

    private static function sqlList(array $ids): string
    {
        return $ids === [] ? '' : implode(',', array_map('intval', $ids));
    }
}
