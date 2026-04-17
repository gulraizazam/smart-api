<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * New vs Returning patient split per branch.
 *
 * Definitions (agreed with user):
 *   - "New" = patient's lifetime-first qualified visit (to ANY branch on the
 *     account) fell within the current date range.
 *   - "Returning" = lifetime-first qualified visit was before the range.
 *   - "Qualified visit" = arrived consultation OR arrived treatment. Matches
 *     the rest of the dashboard's definition of a real visit.
 *
 * Returns both headcount split (distinct patients per branch) AND revenue
 * split (PKR attributed to new vs returning bookings) — they often tell
 * different stories. A branch might be 40% new by headcount but 20% new by
 * revenue; the gap itself is the insight (new patients often buy first
 * packages = big ticket; returning patients add incremental services over
 * time).
 *
 * Health thresholds (rough aesthetics-industry benchmarks):
 *     returning% < 30%     → red    (acquisition-dependent, fragile)
 *     30% ≤ r% < 50%       → amber  (acquisition-heavy; OK if intentional)
 *     50% ≤ r% ≤ 70%       → green  (healthy steady state)
 *     r% > 70%             → amber  (stagnation risk if acquisition stops)
 *
 * Performance: two grouped queries both using the same join-subquery for
 * lifetime-first-visit lookup. Result cached 5 min per scope+range key.
 */
final class NewReturningMetric implements Metric
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $cacheKey = 'mgmt_dash:new_returning:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope, $range));
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    private function build(MetricScope $scope, DateRange $range): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return ['rows' => []];
        }

        $qualifyingStatusIds = array_values(array_unique(array_merge(
            DoctorDashboardHelper::getConsultationStatusIds(),
            DoctorDashboardHelper::getTreatmentStatusIds(),
        )));

        if ($qualifyingStatusIds === []) {
            return ['rows' => []];
        }

        $rangeStart = $range->startString();
        $rangeEnd = $range->endString();

        $headcountByBranch = $this->headcountSplit($scope, $branchIds, $qualifyingStatusIds, $rangeStart, $rangeEnd);
        $revenueByBranch = $this->revenueSplit($scope, $branchIds, $qualifyingStatusIds, $rangeStart, $rangeEnd);
        $retentionByBranch = $this->retentionSplit($scope, $branchIds, $qualifyingStatusIds, $range);
        $followUpByBranch = $this->followUpSplit($scope, $branchIds, $rangeStart, $rangeEnd);
        $branchNames = $this->branches->names($branchIds);

        $rows = [];
        foreach ($branchIds as $branchId) {
            $hc = $headcountByBranch[$branchId] ?? ['new' => 0, 'returning' => 0];
            $rev = $revenueByBranch[$branchId] ?? ['new' => 0.0, 'returning' => 0.0];
            $ret = $retentionByBranch[$branchId] ?? ['prior_active' => 0, 'retained' => 0];
            $fu = $followUpByBranch[$branchId] ?? ['total' => 0, 'with_followup' => 0];

            $totalPatients = $hc['new'] + $hc['returning'];
            $totalRevenue = $rev['new'] + $rev['returning'];

            if ($totalPatients === 0) {
                continue;
            }

            $newPct = round(($hc['new'] / $totalPatients) * 100, 1);
            $returningPct = round(($hc['returning'] / $totalPatients) * 100, 1);
            $newRevenuePct = $totalRevenue > 0 ? round(($rev['new'] / $totalRevenue) * 100, 1) : 0.0;

            $retentionRate = $ret['prior_active'] > 0
                ? round(($ret['retained'] / $ret['prior_active']) * 100, 1)
                : null;

            $followUpRate = $fu['total'] > 0
                ? round(($fu['with_followup'] / $fu['total']) * 100, 1)
                : null;

            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => $branchNames[$branchId] ?? "Branch #{$branchId}",
                'new_patients' => (int) $hc['new'],
                'returning_patients' => (int) $hc['returning'],
                'total_patients' => $totalPatients,
                'new_pct' => $newPct,
                'returning_pct' => $returningPct,
                'new_revenue' => round((float) $rev['new'], 2),
                'returning_revenue' => round((float) $rev['returning'], 2),
                'new_revenue_pct' => $newRevenuePct,
                'retention_rate' => $retentionRate,
                'prior_active' => (int) $ret['prior_active'],
                'retained_from_prior' => (int) $ret['retained'],
                'follow_up_rate' => $followUpRate,
                'follow_up_treated' => (int) $fu['total'],
                'follow_up_returned' => (int) $fu['with_followup'],
                'health' => $this->healthFor($returningPct, $retentionRate),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['total_patients'] <=> $a['total_patients']);

        return ['rows' => $rows];
    }

    /**
     * Period-over-period retention per branch.
     *   prior_active = distinct patients who had a qualifying visit at Branch X
     *                  in the equal-length period preceding the current range
     *   retained     = those same patients who also visited Branch X in the
     *                  current range
     *   retention_rate = retained / prior_active × 100
     *
     * Answers "is this branch keeping its customer base?" — a long-horizon
     * stickiness signal that changes slowly and precedes revenue changes.
     *
     * @param  list<int>  $branchIds
     * @param  list<int>  $statusIds
     * @return array<int, array{prior_active: int, retained: int}>
     */
    private function retentionSplit(MetricScope $scope, array $branchIds, array $statusIds, DateRange $range): array
    {
        $priorRange = $range->previousPeriod();
        $priorStart = $priorRange->startString();
        $priorEnd = $priorRange->endString();
        $rangeStart = $range->startString();
        $rangeEnd = $range->endString();

        $qualifyingBase = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereNull('deleted_at');

        $priorSub = (clone $qualifyingBase)
            ->whereBetween('scheduled_date', [$priorStart, $priorEnd])
            ->select('location_id', 'patient_id')
            ->distinct();

        $currentSub = (clone $qualifyingBase)
            ->whereBetween('scheduled_date', [$rangeStart, $rangeEnd])
            ->select('location_id', 'patient_id')
            ->distinct();

        $rows = DB::query()
            ->fromSub($priorSub, 'prior')
            ->leftJoinSub($currentSub, 'cur', function ($join): void {
                $join->on('cur.location_id', '=', 'prior.location_id')
                    ->on('cur.patient_id', '=', 'prior.patient_id');
            })
            ->select('prior.location_id')
            ->selectRaw('
                COUNT(DISTINCT prior.patient_id) AS prior_active,
                COUNT(DISTINCT CASE WHEN cur.patient_id IS NOT NULL THEN prior.patient_id END) AS retained
            ')
            ->groupBy('prior.location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'prior_active' => (int) $row->prior_active,
                'retained' => (int) $row->retained,
            ];
        }

        return $out;
    }

    /**
     * 45-day follow-up rate per branch — mirrors the doctor dashboard's
     * Patient Return Rate semantics, but scoped to branch instead of doctor.
     *
     *   total           = distinct patients who had an arrived treatment at
     *                     Branch X during the current range
     *   with_followup   = of those, patients who had ANOTHER arrived treatment
     *                     at Branch X within 45 days of their first treatment
     *                     in range
     *   follow_up_rate  = with_followup / total × 100
     *
     * Answers "is the branch booking the next appointment?" — a short-horizon
     * operational signal. Complements retention (which is long-horizon).
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{total: int, with_followup: int}>
     */
    private function followUpSplit(MetricScope $scope, array $branchIds, string $rangeStart, string $rangeEnd): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [];
        }

        // Self-join: for each treatment at (branch, patient) in range, does a
        // later treatment at same branch within 45 days exist? Aggregate to
        // one "has_followup" flag per (patient, branch) pair, then sum per
        // branch. MySQL runs this in a single pass with the composite index
        // on (account_id, location_id, patient_id, scheduled_date).
        $pairs = DB::table('appointments as a1')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->on('a2.location_id', '=', 'a1.location_id')
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereColumn('a2.scheduled_date', '>', 'a1.scheduled_date')
                    ->whereRaw('a2.scheduled_date <= DATE_ADD(a1.scheduled_date, INTERVAL 45 DAY)')
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->whereIn('a1.location_id', $branchIds)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereBetween('a1.scheduled_date', [$rangeStart, $rangeEnd])
            ->whereNull('a1.deleted_at')
            ->select('a1.location_id', 'a1.patient_id')
            ->selectRaw('MAX(CASE WHEN a2.id IS NOT NULL THEN 1 ELSE 0 END) AS has_followup')
            ->groupBy('a1.location_id', 'a1.patient_id');

        $rows = DB::query()
            ->fromSub($pairs, 'pb')
            ->select('location_id')
            ->selectRaw('COUNT(*) AS total, SUM(has_followup) AS with_followup')
            ->groupBy('location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'total' => (int) $row->total,
                'with_followup' => (int) $row->with_followup,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $statusIds
     * @return array<int, array{new: int, returning: int}>
     */
    private function headcountSplit(
        MetricScope $scope,
        array $branchIds,
        array $statusIds,
        string $rangeStart,
        string $rangeEnd,
    ): array {
        $firstVisits = $this->firstVisitSubquery($scope, $statusIds);

        $rows = DB::table('appointments as a')
            ->joinSub($firstVisits, 'firsts', 'firsts.patient_id', '=', 'a.patient_id')
            ->where('a.account_id', $scope->accountId)
            ->whereIn('a.location_id', $branchIds)
            ->whereIn('a.appointment_status_id', $statusIds)
            ->whereBetween('a.scheduled_date', [$rangeStart, $rangeEnd])
            ->whereNull('a.deleted_at')
            ->selectRaw(
                '
                    a.location_id,
                    COUNT(DISTINCT CASE WHEN firsts.first_visit >= ? THEN a.patient_id END) AS new_patients,
                    COUNT(DISTINCT CASE WHEN firsts.first_visit <  ? THEN a.patient_id END) AS returning_patients
                ',
                [$rangeStart, $rangeStart],
            )
            ->groupBy('a.location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'new' => (int) $row->new_patients,
                'returning' => (int) $row->returning_patients,
            ];
        }

        return $out;
    }

    /**
     * Revenue split = net PackageAdvances (revenue_in − refund_out) where the
     * package's patient_id maps to a new or returning patient via first-visit.
     * Filters match the RevenueMetric ledger definition exactly.
     *
     * @param  list<int>  $branchIds
     * @param  list<int>  $statusIds
     * @return array<int, array{new: float, returning: float}>
     */
    private function revenueSplit(
        MetricScope $scope,
        array $branchIds,
        array $statusIds,
        string $rangeStart,
        string $rangeEnd,
    ): array {
        $firstVisits = $this->firstVisitSubquery($scope, $statusIds);

        $rows = DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->joinSub($firstVisits, 'firsts', 'firsts.patient_id', '=', 'p.patient_id')
            ->where('pa.account_id', $scope->accountId)
            ->whereIn('pa.location_id', $branchIds)
            ->whereDate('pa.created_at', '>=', $rangeStart)
            ->whereDate('pa.created_at', '<=', $rangeEnd)
            ->where('pa.cash_amount', '!=', 0)
            ->where(function ($outer): void {
                $outer->where(function ($in): void {
                    $in->where('pa.cash_flow', 'in')
                        ->where('pa.is_adjustment', 0)
                        ->where('pa.is_tax', 0)
                        ->where('pa.is_cancel', 0);
                })->orWhere(function ($out): void {
                    $out->where('pa.cash_flow', 'out')
                        ->where('pa.is_refund', 1);
                });
            })
            ->selectRaw(
                "
                    pa.location_id,
                    COALESCE(SUM(CASE WHEN firsts.first_visit >= ? AND pa.cash_flow = 'in'  THEN pa.cash_amount ELSE 0 END), 0)
                      - COALESCE(SUM(CASE WHEN firsts.first_visit >= ? AND pa.cash_flow = 'out' THEN pa.cash_amount ELSE 0 END), 0) AS new_revenue,
                    COALESCE(SUM(CASE WHEN firsts.first_visit <  ? AND pa.cash_flow = 'in'  THEN pa.cash_amount ELSE 0 END), 0)
                      - COALESCE(SUM(CASE WHEN firsts.first_visit <  ? AND pa.cash_flow = 'out' THEN pa.cash_amount ELSE 0 END), 0) AS returning_revenue
                ",
                [$rangeStart, $rangeStart, $rangeStart, $rangeStart],
            )
            ->groupBy('pa.location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'new' => (float) $row->new_revenue,
                'returning' => (float) $row->returning_revenue,
            ];
        }

        return $out;
    }

    /**
     * Lifetime-first qualified visit per patient for this account. Used as a
     * join-subquery by both headcount and revenue queries — MySQL materializes
     * it once per statement.
     *
     * @param  list<int>  $statusIds
     */
    private function firstVisitSubquery(MetricScope $scope, array $statusIds): Builder
    {
        return DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereNull('deleted_at')
            ->groupBy('patient_id')
            ->select('patient_id', DB::raw('MIN(scheduled_date) as first_visit'));
    }

    // Branch id/name resolution moved to BranchResolver (shared across metrics).

    /**
     * Composite health: factors in both the returning-mix AND the period
     * retention rate. A branch with a healthy mix but cratering retention
     * should still flag amber, since retention is the leading indicator.
     */
    private function healthFor(float $returningPct, ?float $retentionRate): string
    {
        $mixFlag = match (true) {
            $returningPct < 30.0 => 'red',
            $returningPct < 50.0 => 'amber',
            $returningPct <= 70.0 => 'green',
            default => 'amber',
        };

        if ($retentionRate === null) {
            return $mixFlag;
        }

        $retentionFlag = match (true) {
            $retentionRate < 30.0 => 'red',
            $retentionRate < 50.0 => 'amber',
            default => 'green',
        };

        // Take the worse of the two flags so retention problems aren't masked
        // by a healthy-looking mix.
        $severity = ['green' => 0, 'amber' => 1, 'red' => 2];

        return $severity[$mixFlag] >= $severity[$retentionFlag] ? $mixFlag : $retentionFlag;
    }
}
