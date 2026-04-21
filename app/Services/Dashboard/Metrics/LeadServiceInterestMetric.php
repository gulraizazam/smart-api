<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Top services leads enquire about, split by gender. Reads from the
 * `leads_services` pivot (recent leads can track multiple services) joined
 * back to `leads` for gender resolution.
 *
 * Gender resolution follows the same rule used across lead metrics:
 * COALESCE(NULLIF(patient.gender, 0), NULLIF(lead.gender, 0)) — so any
 * later gender correction on the patient record flows back automatically.
 *
 * Return shape:
 *   [
 *     'rows' => list<array{
 *         service_id: int, service_name: string,
 *         female: int, male: int, unknown: int, total: int,
 *     }>,
 *     'top_female' => ['name' => string, 'share' => float] | null,
 *     'top_male'   => ['name' => string, 'share' => float] | null,
 *   ]
 */
final class LeadServiceInterestMetric implements Metric
{
    private const ROW_LIMIT = 5;

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['rows' => [], 'top_female' => null, 'top_male' => null];
        }

        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        $query = DB::table('leads_services as ls')
            ->join('leads as l', 'l.id', '=', 'ls.lead_id')
            ->join('services as s', 's.id', '=', 'ls.service_id')
            ->leftJoin('users as p', function ($join) {
                $join->on('p.id', '=', 'l.patient_id')
                    ->where('p.user_type_id', 3);
            })
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [$start, $end]);

        if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
            $query->whereIn('l.location_id', $scope->branchIds);
        }

        $rows = $query
            ->selectRaw('s.id AS service_id, s.name AS service_name')
            ->selectRaw('SUM(CASE WHEN COALESCE(NULLIF(p.gender,0),NULLIF(l.gender,0))=2 THEN 1 ELSE 0 END) AS female')
            ->selectRaw('SUM(CASE WHEN COALESCE(NULLIF(p.gender,0),NULLIF(l.gender,0))=1 THEN 1 ELSE 0 END) AS male')
            ->selectRaw('SUM(CASE WHEN COALESCE(NULLIF(p.gender,0),NULLIF(l.gender,0)) NOT IN (1,2) OR COALESCE(NULLIF(p.gender,0),NULLIF(l.gender,0)) IS NULL THEN 1 ELSE 0 END) AS unknown')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total')
            ->limit(self::ROW_LIMIT)
            ->get();

        $out = [];
        $totalF = 0;
        $totalM = 0;
        $topF = ['name' => '', 'count' => 0];
        $topM = ['name' => '', 'count' => 0];

        foreach ($rows as $row) {
            $f = (int) $row->female;
            $m = (int) $row->male;
            $u = (int) $row->unknown;
            $total = (int) $row->total;
            $out[] = [
                'service_id' => (int) $row->service_id,
                'service_name' => (string) $row->service_name,
                'female' => $f,
                'male' => $m,
                'unknown' => $u,
                'total' => $total,
            ];
            $totalF += $f;
            $totalM += $m;
            if ($f > $topF['count']) {
                $topF = ['name' => (string) $row->service_name, 'count' => $f];
            }
            if ($m > $topM['count']) {
                $topM = ['name' => (string) $row->service_name, 'count' => $m];
            }
        }

        return [
            'rows' => $out,
            'top_female' => $totalF > 0
                ? ['name' => $topF['name'], 'share' => round(($topF['count'] / $totalF) * 100, 1)]
                : null,
            'top_male' => $totalM > 0
                ? ['name' => $topM['name'], 'share' => round(($topM['count'] / $totalM) * 100, 1)]
                : null,
        ];
    }
}
