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

        // Pre-compute the lifetime-first-visit map ONCE for only the
        // patients that actually appear in the current range. The naive
        // approach (a join-subquery against `appointments` with no
        // patient filter) made the planner aggregate first-visit for
        // every patient ever recorded — slow on the in-range scan that
        // came after. Two cheap pre-queries instead of one giant one.
        $patientsInRange = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->whereIn('appointment_status_id', $qualifyingStatusIds)
            ->whereBetween('scheduled_date', [$rangeStart, $rangeEnd])
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('patient_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $firstVisitByPatient = $this->firstVisitMap($scope, $qualifyingStatusIds, $patientsInRange);

        $headcountByBranch = $this->headcountSplit($scope, $branchIds, $qualifyingStatusIds, $rangeStart, $rangeEnd, $firstVisitByPatient);
        $revenueByBranch = $this->revenueSplit($scope, $branchIds, $qualifyingStatusIds, $rangeStart, $rangeEnd, $firstVisitByPatient);
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
     * Per-doctor retention at a specific branch — same matured 30–60 day
     * cohort as the branch-level `follow_up_rate`, but grouped by the
     * doctor who saw the patient in their a1 treatment so the dashboard
     * can surface "who's holding patients at this branch".
     *
     * Two rates per doctor:
     *   - retention_rate             = "institutional" — patient returned
     *                                  to the same branch (possibly to a
     *                                  different doctor). Matches the
     *                                  branch-level metric's semantics.
     *   - same_doctor_retention_rate = "sticky" — patient returned and
     *                                  was seen by the same doctor again.
     *                                  Tells you how personally bonded
     *                                  their patients are.
     *
     * Notes:
     *   - A patient who saw two different doctors in the cohort at the
     *     same branch counts once per doctor (each doctor's retention is
     *     measured independently). Branch-level totals still dedupe.
     *   - Doctors are sorted by branch retention_rate desc; cohort < 5
     *     land at the bottom (low signal).
     *
     * @return array{
     *   branch_id: int,
     *   branch_name: string|null,
     *   rows: list<array{doctor_id:int, doctor_name:string, cohort_count:int, returned_count:int, retention_rate:float|null, same_doctor_returned_count:int, same_doctor_retention_rate:float|null}>,
     *   totals: array{cohort_count:int, returned_count:int, retention_rate:float|null},
     * }
     */
    public function branchDoctorRetention(MetricScope $scope, int $branchId): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [
                'branch_id' => $branchId,
                'branch_name' => null,
                'rows' => [],
                'totals' => ['cohort_count' => 0, 'returned_count' => 0, 'retention_rate' => null],
            ];
        }

        // Same matured trailing cohort the branch-level metric uses —
        // keeps both views comparable.
        $cohortEnd = now()->subDays(30)->toDateString();
        $cohortStart = now()->subDays(60)->toDateString();
        $today = now()->toDateString();

        // Per-(doctor, patient) pair at the branch: two follow-up flags —
        //   has_followup_branch      = later treatment at the branch by
        //                              any doctor (institutional retention)
        //   has_followup_same_doctor = later treatment at the branch by
        //                              the SAME doctor as a1 (personal
        //                              "sticky" retention)
        // Two left-joins with different match conditions so both can be
        // aggregated in one pass over the cohort rows.
        // Inner-join `users` to filter to active doctors only — former
        // / deactivated doctors with old appointments in the cohort
        // window otherwise show up as ghost rows on the FDM panel.
        $pairs = DB::table('appointments as a1')
            ->join('users as u', 'a1.doctor_id', '=', 'u.id')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds, $today): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->on('a2.location_id', '=', 'a1.location_id')
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->where('a2.scheduled_date', '<=', $today)
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->leftJoin('appointments as a2d', function ($join) use ($treatmentStatusIds, $today): void {
                $join->on('a2d.patient_id', '=', 'a1.patient_id')
                    ->on('a2d.location_id', '=', 'a1.location_id')
                    ->on('a2d.doctor_id', '=', 'a1.doctor_id')
                    ->where('a2d.appointment_type_id', 2)
                    ->whereIn('a2d.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2d.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->where('a2d.scheduled_date', '<=', $today)
                    ->whereColumn('a2d.id', '!=', 'a1.id')
                    ->whereNull('a2d.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->where('a1.location_id', $branchId)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereBetween('a1.scheduled_date', [$cohortStart, $cohortEnd])
            ->whereNull('a1.deleted_at')
            ->where('u.active', 1)
            ->select('a1.doctor_id', 'a1.patient_id')
            ->selectRaw('MAX(CASE WHEN a2.id IS NOT NULL THEN 1 ELSE 0 END) AS has_followup_branch')
            ->selectRaw('MAX(CASE WHEN a2d.id IS NOT NULL THEN 1 ELSE 0 END) AS has_followup_same_doctor')
            ->groupBy('a1.doctor_id', 'a1.patient_id');

        $rows = DB::query()
            ->fromSub($pairs, 'pb')
            ->select('doctor_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(has_followup_branch) AS with_followup_branch')
            ->selectRaw('SUM(has_followup_same_doctor) AS with_followup_same')
            ->groupBy('doctor_id')
            ->get();

        // Resolve doctor names in one query.
        $doctorIds = $rows->pluck('doctor_id')->map(fn ($v): int => (int) $v)->all();
        $names = $doctorIds === []
            ? []
            : DB::table('users')->whereIn('id', $doctorIds)->pluck('name', 'id')->toArray();

        $branchName = DB::table('locations')->where('id', $branchId)->value('name');

        $list = [];
        foreach ($rows as $row) {
            $doctorId = (int) $row->doctor_id;
            $cohort = (int) $row->total;
            $returnedBranch = (int) $row->with_followup_branch;
            $returnedSame = (int) $row->with_followup_same;
            $rateBranch = $cohort > 0 ? round(($returnedBranch / $cohort) * 100, 1) : null;
            $rateSame = $cohort > 0 ? round(($returnedSame / $cohort) * 100, 1) : null;

            $list[] = [
                'doctor_id' => $doctorId,
                'doctor_name' => $names[$doctorId] ?? "User #{$doctorId}",
                'cohort_count' => $cohort,
                'returned_count' => $returnedBranch,
                'retention_rate' => $rateBranch,
                'same_doctor_returned_count' => $returnedSame,
                'same_doctor_retention_rate' => $rateSame,
            ];
        }

        // Sort: low-cohort doctors (<5) get pushed to the bottom so 100%
        // on 1 patient doesn't dominate. Within each tier, higher rate
        // first — then cohort size as tiebreaker.
        usort($list, function (array $a, array $b): int {
            $aLow = $a['cohort_count'] < 5 ? 1 : 0;
            $bLow = $b['cohort_count'] < 5 ? 1 : 0;
            if ($aLow !== $bLow) {
                return $aLow <=> $bLow;
            }
            $ar = $a['retention_rate'] ?? -1;
            $br = $b['retention_rate'] ?? -1;
            if ($ar !== $br) {
                return $br <=> $ar;
            }

            return $b['cohort_count'] <=> $a['cohort_count'];
        });

        // Branch-level totals — patient counted once regardless of how
        // many doctors saw them, so the card reconciles with the main
        // panel's row rate. Re-uses followUpSplit() with a single-branch
        // array so we're not inlining the same self-join twice.
        $branchSplit = $this->followUpSplit($scope, [$branchId], $cohortStart, $cohortEnd);
        $branchCohort = $branchSplit[$branchId]['total'] ?? 0;
        $branchReturned = $branchSplit[$branchId]['with_followup'] ?? 0;
        $branchRate = $branchCohort > 0
            ? round(($branchReturned / $branchCohort) * 100, 1)
            : null;

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'rows' => $list,
            'totals' => [
                'cohort_count' => $branchCohort,
                'returned_count' => $branchReturned,
                'retention_rate' => $branchRate,
            ],
        ];
    }

    /**
     * Per-doctor retention trend for a single branch. Same matured
     * 30–60 day cohort logic as `branchDoctorRetention`, applied at
     * each calendar-month anchor going back `monthsBack` months.
     *
     * Used by the FDM dashboard's Trend tab (and by Overview when the
     * filter is narrowed to a single branch). Returns one row per
     * doctor with one point per month — same shape as `retentionTrend`
     * but rows are doctors, not branches.
     *
     * Branch-level rollup is included as `branch_pool` so the SPA can
     * render "this branch overall" alongside the doctor lines for an
     * at-a-glance comparison.
     *
     * @return array{
     *   branch_id: int,
     *   branch_name: string|null,
     *   months: list<string>,
     *   rows: list<array{doctor_id:int, doctor_name:string, points: list<array{month:string, cohort:int, returned:int, rate:float|null}>}>,
     *   branch_pool: list<array{month:string, cohort:int, returned:int, rate:float|null}>,
     * }
     */
    public function branchDoctorRetentionTrend(MetricScope $scope, int $branchId, int $monthsBack = 6): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [
                'branch_id' => $branchId,
                'branch_name' => null,
                'months' => [],
                'rows' => [],
                'branch_pool' => [],
            ];
        }

        $today = \Carbon\CarbonImmutable::now();

        // Build month anchors (oldest → newest). Each anchor = month-end,
        // clamped to today for the in-progress month so we measure "as of
        // now" instead of overshooting into the future.
        $anchors = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $monthStart = $today->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();
            $asOf = $monthEnd->greaterThan($today) ? $today : $monthEnd;
            $anchors[] = [
                'key' => $monthStart->format('Y-m'),
                'asOf' => $asOf->toDateString(),
            ];
        }
        $monthKeys = array_map(static fn ($a) => $a['key'], $anchors);

        // Per-doctor per-month bucket — fill in two passes so doctors
        // that only show up in some months still get a complete points
        // array (with `null`/zero points where they had no cohort).
        $perDoctor = [];
        $branchPool = [];
        foreach ($anchors as $anchor) {
            $rows = $this->branchDoctorCohortAsOf($scope, $branchId, $anchor['asOf'], $treatmentStatusIds);
            foreach ($rows as $row) {
                $doctorId = (int) $row->doctor_id;
                if (! isset($perDoctor[$doctorId])) {
                    $perDoctor[$doctorId] = [];
                    foreach ($monthKeys as $k) {
                        $perDoctor[$doctorId][$k] = ['cohort' => 0, 'returned' => 0];
                    }
                }
                $perDoctor[$doctorId][$anchor['key']] = [
                    'cohort' => (int) $row->total,
                    'returned' => (int) $row->with_followup_branch,
                ];
            }

            // Branch-level pool for this anchor — same followUpSplit
            // path the existing retentionTrend uses, single-branch.
            $branchSplit = $this->followUpSplit($scope, [$branchId], '', '', $anchor['asOf']);
            $bucket = $branchSplit[$branchId] ?? ['total' => 0, 'with_followup' => 0];
            $branchPool[] = [
                'month' => $anchor['key'],
                'cohort' => (int) ($bucket['total'] ?? 0),
                'returned' => (int) ($bucket['with_followup'] ?? 0),
                'rate' => ($bucket['total'] ?? 0) > 0
                    ? round((($bucket['with_followup'] ?? 0) / $bucket['total']) * 100, 1)
                    : null,
            ];
        }

        $doctorIds = array_keys($perDoctor);
        $names = $doctorIds === []
            ? []
            : DB::table('users')->whereIn('id', $doctorIds)->pluck('name', 'id')->toArray();
        $branchName = DB::table('locations')->where('id', $branchId)->value('name');

        $resultRows = [];
        foreach ($perDoctor as $doctorId => $monthly) {
            $points = [];
            foreach ($anchors as $anchor) {
                $c = $monthly[$anchor['key']]['cohort'];
                $r = $monthly[$anchor['key']]['returned'];
                $points[] = [
                    'month' => $anchor['key'],
                    'cohort' => $c,
                    'returned' => $r,
                    'rate' => $c > 0 ? round(($r / $c) * 100, 1) : null,
                ];
            }
            $resultRows[] = [
                'doctor_id' => $doctorId,
                'doctor_name' => $names[$doctorId] ?? "User #{$doctorId}",
                'points' => $points,
            ];
        }

        // Sort by latest-month rate desc; null/empty trailing months go
        // to the bottom — same posture as branch-level retentionTrend.
        usort($resultRows, function (array $a, array $b): int {
            $lastA = $a['points'][count($a['points']) - 1]['rate'] ?? -1;
            $lastB = $b['points'][count($b['points']) - 1]['rate'] ?? -1;
            return $lastB <=> $lastA;
        });

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'months' => $monthKeys,
            'rows' => $resultRows,
            'branch_pool' => $branchPool,
        ];
    }

    /**
     * Per-doctor cohort + follow-up counts for a branch as of a given
     * date. Same matured trailing 30–60 day cohort window as
     * `branchDoctorRetention`, just anchored on `$asOf` instead of
     * `now()` — so callers can compute historical snapshots.
     *
     * @param  list<int>  $treatmentStatusIds
     * @return \Illuminate\Support\Collection<int, object{doctor_id: int, total: int, with_followup_branch: int, with_followup_same: int}>
     */
    private function branchDoctorCohortAsOf(MetricScope $scope, int $branchId, string $asOf, array $treatmentStatusIds): \Illuminate\Support\Collection
    {
        $cohortEnd = \Carbon\Carbon::parse($asOf)->subDays(30)->toDateString();
        $cohortStart = \Carbon\Carbon::parse($asOf)->subDays(60)->toDateString();

        // Inner-join `users` to filter to active doctors only — former
        // / deactivated doctors with old cohort appointments otherwise
        // show up as ghost rows on the FDM panel's trend.
        $pairs = DB::table('appointments as a1')
            ->join('users as u', 'a1.doctor_id', '=', 'u.id')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds, $asOf): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->on('a2.location_id', '=', 'a1.location_id')
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->where('a2.scheduled_date', '<=', $asOf)
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->leftJoin('appointments as a2d', function ($join) use ($treatmentStatusIds, $asOf): void {
                $join->on('a2d.patient_id', '=', 'a1.patient_id')
                    ->on('a2d.location_id', '=', 'a1.location_id')
                    ->on('a2d.doctor_id', '=', 'a1.doctor_id')
                    ->where('a2d.appointment_type_id', 2)
                    ->whereIn('a2d.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2d.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->where('a2d.scheduled_date', '<=', $asOf)
                    ->whereColumn('a2d.id', '!=', 'a1.id')
                    ->whereNull('a2d.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->where('a1.location_id', $branchId)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereBetween('a1.scheduled_date', [$cohortStart, $cohortEnd])
            ->whereNull('a1.deleted_at')
            ->where('u.active', 1)
            ->select('a1.doctor_id', 'a1.patient_id')
            ->selectRaw('MAX(CASE WHEN a2.id IS NOT NULL THEN 1 ELSE 0 END) AS has_followup_branch')
            ->selectRaw('MAX(CASE WHEN a2d.id IS NOT NULL THEN 1 ELSE 0 END) AS has_followup_same_doctor')
            ->groupBy('a1.doctor_id', 'a1.patient_id');

        return DB::query()
            ->fromSub($pairs, 'pb')
            ->select('doctor_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(has_followup_branch) AS with_followup_branch')
            ->selectRaw('SUM(has_followup_same_doctor) AS with_followup_same')
            ->groupBy('doctor_id')
            ->get();
    }

    /**
     * Return rate per branch — ALWAYS uses a matured trailing cohort,
     * independent of the top-bar date filter. This keeps the metric a
     * true "rebook health" signal rather than an immature-window mirage
     * on short ranges.
     *
     *   cohort (a1)     = distinct patients who had an ARRIVED treatment
     *                     at Branch X between (today − 60d) and (today − 30d)
     *   returned (a2)   = those cohort patients who have since had ANOTHER
     *                     arrived treatment at Branch X (up to today),
     *                     scheduled MORE THAN 7 DAYS after their a1
     *   follow_up_rate  = returned / cohort × 100
     *
     * The 7-day gap filter excludes same-package multi-session noise
     * (e.g. a laser package scheduling two sessions in the same week)
     * so the metric reflects genuine rebooking, not continuation of an
     * already-purchased course.
     *
     * Observation window per patient is 30–60 days depending on where in
     * the cohort their a1 landed — every member has had ≥ 30 days to
     * return, so the rate is always mature and comparable across branches.
     *
     * Answers "are patients coming back for more treatments?" — short-
     * horizon operational signal, complements retention (long-horizon).
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{total: int, with_followup: int}>
     */
    private function followUpSplit(MetricScope $scope, array $branchIds, string $rangeStart, string $rangeEnd, ?string $asOf = null): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [];
        }

        // Fixed matured cohort. The method signature still takes the top-
        // filter range so the caller doesn't need to change, but the
        // values are deliberately unused here. `$asOf` lets callers
        // reconstruct a historical snapshot (retention trend) — when
        // null we anchor on today.
        unset($rangeStart, $rangeEnd);
        $anchor = $asOf !== null ? \Carbon\CarbonImmutable::parse($asOf) : \Carbon\CarbonImmutable::now();
        $cohortEnd = $anchor->subDays(30)->toDateString();
        $cohortStart = $anchor->subDays(60)->toDateString();
        $today = $anchor->toDateString();

        // Self-join: for each treatment at (branch, patient) in the cohort
        // window, does any later ARRIVED treatment at the same branch exist
        // MORE THAN 7 DAYS later and up to today? The 7-day gap filters
        // multi-session-of-same-package noise. Aggregate to one
        // has_followup flag per (patient, branch) pair, then sum per branch.
        $pairs = DB::table('appointments as a1')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds, $today): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->on('a2.location_id', '=', 'a1.location_id')
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->where('a2.scheduled_date', '<=', $today)
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->whereIn('a1.location_id', $branchIds)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereBetween('a1.scheduled_date', [$cohortStart, $cohortEnd])
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
     * Batched per-(branch, month) follow-up split for the retention trend —
     * the N-months-at-once form of followUpSplit($asOf). Computes EVERY
     * anchor's matured-cohort split in ONE grouped query (constant query
     * count, not one-per-month), by cross-joining the cohort self-join
     * against the anchor list: each anchor carries its own cohort window
     * [asOf−60, asOf−30] and its own follow-up cutoff (asOf).
     *
     * Identical to calling followUpSplit($scope,$branchIds,'','',$asOf) once
     * per anchor:
     *   - the anchor-independent a2 rules (same branch+patient, treatment,
     *     arrived, >7d after a1, not deleted, not a1 itself) stay in the
     *     LEFT JOIN;
     *   - the only anchor-DEPENDENT rule, "the follow-up must be ≤ that
     *     month's asOf", is applied per-anchor inside the has_followup CASE
     *     (a2.scheduled_date <= an.as_of), so an out-of-window a2 only zeroes
     *     the flag — it never drops the cohort (a1) row.
     *
     * @param  list<int>  $branchIds
     * @param  list<array{label:string, key:string, asOf:string}>  $anchors
     * @return array<string, array<int, array{total:int, with_followup:int}>>
     *         keyed by anchor 'key' (Y-m) then branch id
     */
    private function followUpSplitBatched(MetricScope $scope, array $branchIds, array $anchors): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === [] || $anchors === []) {
            return [];
        }

        $anchorsSub = $this->anchorsSubquery($anchors);

        // a1 (cohort, per branch+patient) × anchors. a2 = later same-branch
        // arrived treatment >7d after a1 (anchor-independent → in the JOIN);
        // the asOf cap is applied per-anchor in the CASE below.
        $pairs = DB::table('appointments as a1')
            ->crossJoinSub($anchorsSub, 'an')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->on('a2.location_id', '=', 'a1.location_id')
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->whereIn('a1.location_id', $branchIds)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereColumn('a1.scheduled_date', '>=', 'an.cohort_start')
            ->whereColumn('a1.scheduled_date', '<=', 'an.cohort_end')
            ->whereNull('a1.deleted_at')
            ->select('an.k', 'a1.location_id', 'a1.patient_id')
            ->selectRaw('MAX(CASE WHEN a2.id IS NOT NULL AND a2.scheduled_date <= an.as_of THEN 1 ELSE 0 END) AS has_followup')
            ->groupBy('an.k', 'a1.location_id', 'a1.patient_id');

        $rows = DB::query()
            ->fromSub($pairs, 'pb')
            ->select('k', 'location_id')
            ->selectRaw('COUNT(*) AS total, SUM(has_followup) AS with_followup')
            ->groupBy('k', 'location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->k][(int) $row->location_id] = [
                'total' => (int) $row->total,
                'with_followup' => (int) $row->with_followup,
            ];
        }

        return $out;
    }

    /**
     * Batched pool-level follow-up for the retention trend — the N-months-
     * at-once form of followUpPool($asOf). Same matured-cohort + 7-day-gap
     * rules, but per-patient (deduped across branches) so a patient who
     * treated at two branches counts once per month and is "returned" if
     * they rebooked at ANY branch in scope. ONE grouped query for all
     * anchors. Identical to one followUpPool() call per anchor; the asOf
     * cap is applied per-anchor in the has_followup CASE, exactly as in
     * followUpSplitBatched.
     *
     * @param  list<int>  $branchIds
     * @param  list<array{label:string, key:string, asOf:string}>  $anchors
     * @return array<string, array{cohort:int, returned:int}>  keyed by anchor 'key'
     */
    private function followUpPoolBatched(MetricScope $scope, array $branchIds, array $anchors): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === [] || $anchors === []) {
            return [];
        }

        $anchorsSub = $this->anchorsSubquery($anchors);

        $pairs = DB::table('appointments as a1')
            ->crossJoinSub($anchorsSub, 'an')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds, $branchIds): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->whereIn('a2.location_id', $branchIds)
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->whereIn('a1.location_id', $branchIds)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereColumn('a1.scheduled_date', '>=', 'an.cohort_start')
            ->whereColumn('a1.scheduled_date', '<=', 'an.cohort_end')
            ->whereNull('a1.deleted_at')
            ->select('an.k', 'a1.patient_id')
            ->selectRaw('MAX(CASE WHEN a2.id IS NOT NULL AND a2.scheduled_date <= an.as_of THEN 1 ELSE 0 END) AS has_followup')
            ->groupBy('an.k', 'a1.patient_id');

        $rows = DB::query()
            ->fromSub($pairs, 'p')
            ->select('k')
            ->selectRaw('COUNT(*) AS cohort, SUM(has_followup) AS returned')
            ->groupBy('k')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->k] = [
                'cohort' => (int) ($row->cohort ?? 0),
                'returned' => (int) ($row->returned ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Derived-table of retention anchors for the batched follow-up queries:
     * one row per month with its key, asOf cutoff, and matured-cohort window
     * (asOf−60 … asOf−30). Built as a UNION ALL of bound SELECTs so the dates
     * are parameterized (no string-built SQL). Dates are precomputed in PHP
     * (Carbon) so the window math matches followUpSplit/followUpPool exactly.
     *
     * @param  list<array{label:string, key:string, asOf:string}>  $anchors
     */
    private function anchorsSubquery(array $anchors): \Illuminate\Database\Query\Builder
    {
        $sub = null;
        foreach ($anchors as $a) {
            $anchor = \Carbon\CarbonImmutable::parse($a['asOf']);
            $select = DB::query()->selectRaw(
                '? as k, ? as as_of, ? as cohort_start, ? as cohort_end',
                [
                    $a['key'],
                    $anchor->toDateString(),
                    $anchor->subDays(60)->toDateString(),
                    $anchor->subDays(30)->toDateString(),
                ]
            );
            $sub = $sub === null ? $select : $sub->unionAll($select);
        }

        // $anchors is non-empty (guarded by callers), so $sub is set.
        return $sub;
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
        array $firstVisitByPatient = [],
    ): array {
        // Single scan over in-range appointments + a PHP-side bucket
        // using the pre-computed first-visit map. Avoids the heavy
        // join-subquery against the full appointments table.
        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereBetween('scheduled_date', [$rangeStart, $rangeEnd])
            ->whereNull('deleted_at')
            ->distinct()
            ->select('location_id', 'patient_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $bid = (int) $row->location_id;
            $pid = (int) $row->patient_id;
            $out[$bid] ??= ['new' => 0, 'returning' => 0];
            $first = $firstVisitByPatient[$pid] ?? null;
            if ($first !== null && $first >= $rangeStart) {
                $out[$bid]['new']++;
            } else {
                $out[$bid]['returning']++;
            }
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
        array $firstVisitByPatient = [],
    ): array {
        // SUM per (location, patient) at the DB. `package_advances`
        // already carries patient_id, so the previous JOIN to `packages`
        // was redundant — dropped it. Yields ~one row per patient-branch
        // instead of one row per ledger event. Bucketing into new vs
        // returning happens in PHP using the pre-computed first-visit map.
        $rows = DB::table('package_advances')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->whereDate('created_at', '>=', $rangeStart)
            ->whereDate('created_at', '<=', $rangeEnd)
            ->where('cash_amount', '!=', 0)
            ->whereNull('deleted_at')
            ->where(function ($outer): void {
                $outer->where(function ($in): void {
                    $in->where('cash_flow', 'in')
                        ->where('is_adjustment', 0)
                        ->where('is_tax', 0)
                        ->where('is_cancel', 0);
                })->orWhere(function ($out): void {
                    $out->where('cash_flow', 'out')
                        ->where('is_refund', 1);
                });
            })
            ->groupBy('location_id', 'patient_id')
            ->select('location_id', 'patient_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END), 0)
                 - COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_refund = 1 THEN cash_amount ELSE 0 END), 0) AS net"
            )
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $bid = (int) $r->location_id;
            $pid = (int) $r->patient_id;
            $first = $firstVisitByPatient[$pid] ?? null;
            $bucket = ($first !== null && $first >= $rangeStart) ? 'new' : 'returning';
            $out[$bid] ??= ['new' => 0.0, 'returning' => 0.0];
            $out[$bid][$bucket] += (float) $r->net;
        }

        return $out;
    }

    /**
     * Lifetime-first qualified visit per patient — pre-computed in PHP
     * and indexed by patient_id. Filters to only patients we actually
     * need so the GROUP BY is bounded.
     *
     * @param  list<int>  $statusIds
     * @param  list<int>  $patientIds
     * @return array<int, string>  patient_id => first_visit (Y-m-d)
     */
    private function firstVisitMap(MetricScope $scope, array $statusIds, array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($patientIds, 5000) as $chunk) {
            $rows = DB::table('appointments')
                ->where('account_id', $scope->accountId)
                ->whereIn('appointment_status_id', $statusIds)
                ->whereIn('patient_id', $chunk)
                ->whereNull('deleted_at')
                ->groupBy('patient_id')
                ->select('patient_id')
                ->selectRaw('MIN(scheduled_date) AS first_visit')
                ->get();
            foreach ($rows as $r) {
                $out[(int) $r->patient_id] = (string) $r->first_visit;
            }
        }

        return $out;
    }

    /**
     * Lifetime-first qualified visit per patient for this account. Used as a
     * join-subquery by both headcount and revenue queries — MariaDB materializes
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

    /**
     * Monthly retention-rate trend over the last N months, per branch +
     * pooled. Each month gets its OWN matured 30-60 day cohort anchored
     * at that month's end (clamped to today for the current month), so
     * every data point is apples-to-apples — every cohort has had ≥30
     * days to rebook by the time we measure it.
     *
     * Example: for "Feb 2026" on 2026-04-23:
     *   asOf        = 2026-02-28
     *   cohortStart = 2025-12-30   (asOf − 60d)
     *   cohortEnd   = 2026-01-29   (asOf − 30d)
     *   follow-ups counted through 2026-02-28
     *
     * Returns rows sorted by latest retention rate desc — same ranking
     * UX as utilizationTrendByBranch — plus a pool row.
     *
     * @return array{months: list<string>, rows: list<array{branch_id:int, branch_name:string, points: list<array{month:string, cohort:int, returned:int, rate:float|null}>}>, pool: list<array{month:string, cohort:int, returned:int, rate:float|null}>}
     */
    public function retentionTrend(MetricScope $scope, int $monthsBack = 6): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return ['months' => [], 'rows' => [], 'pool' => []];
        }

        $today = \Carbon\CarbonImmutable::now();
        $branchNames = $this->branches->names($branchIds);

        // Build the list of month anchors: oldest → newest. Each anchor
        // is the last day of that calendar month, clamped to today for
        // the current month (so "this month" isn't measured mid-way).
        $anchors = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $monthStart = $today->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();
            $asOf = $monthEnd->greaterThan($today) ? $today : $monthEnd;
            $anchors[] = [
                'label' => $monthStart->format('M Y'),
                'key' => $monthStart->format('Y-m'),
                'asOf' => $asOf->toDateString(),
            ];
        }

        $months = array_map(fn ($a) => $a['key'], $anchors);

        // Per-branch per-month bucket — seed zeros then fill.
        $perBranch = [];
        foreach ($branchIds as $bid) {
            $perBranch[$bid] = [];
            foreach ($anchors as $a) {
                $perBranch[$bid][$a['key']] = ['cohort' => 0, 'returned' => 0];
            }
        }
        $poolMonthly = [];
        foreach ($anchors as $a) {
            $poolMonthly[$a['key']] = ['cohort' => 0, 'returned' => 0];
        }

        // Batched: ALL months' per-(branch, month) buckets in ONE grouped
        // query, and ALL months' pooled buckets in ONE more — instead of
        // a followUpSplit()+followUpPool() pair per month (the old per-month
        // N+1). Each anchor carries its own cohort window + asOf cap, so the
        // matured-cohort and "follow-up must be ≤ that month's asOf" rules
        // are preserved exactly, just unrolled across anchors in SQL.
        $splitByMonth = $this->followUpSplitBatched($scope, $branchIds, $anchors);
        $poolByMonth = $this->followUpPoolBatched($scope, $branchIds, $anchors);

        foreach ($anchors as $a) {
            $perBranchMonth = $splitByMonth[$a['key']] ?? [];
            foreach ($branchIds as $bid) {
                $perBranch[$bid][$a['key']] = [
                    'cohort' => $perBranchMonth[$bid]['total'] ?? 0,
                    'returned' => $perBranchMonth[$bid]['with_followup'] ?? 0,
                ];
            }
            $poolMonthly[$a['key']] = $poolByMonth[$a['key']] ?? ['cohort' => 0, 'returned' => 0];
        }

        $rows = [];
        foreach ($branchIds as $bid) {
            $points = [];
            foreach ($anchors as $a) {
                $c = $perBranch[$bid][$a['key']]['cohort'];
                $r = $perBranch[$bid][$a['key']]['returned'];
                $points[] = [
                    'month' => $a['key'],
                    'cohort' => $c,
                    'returned' => $r,
                    'rate' => $c > 0 ? round(($r / $c) * 100, 1) : null,
                ];
            }
            $rows[] = [
                'branch_id' => $bid,
                'branch_name' => $branchNames[$bid] ?? "Branch #{$bid}",
                'points' => $points,
            ];
        }

        // Sort by latest-month rate desc (null last). Matches the
        // ranked-list UX of utilizationTrendByBranch.
        usort($rows, function (array $a, array $b): int {
            $last = count($a['points']) - 1;
            $ar = $a['points'][$last]['rate'] ?? -1;
            $br = $b['points'][$last]['rate'] ?? -1;
            return $br <=> $ar;
        });

        $pool = [];
        foreach ($anchors as $a) {
            $c = $poolMonthly[$a['key']]['cohort'];
            $r = $poolMonthly[$a['key']]['returned'];
            $pool[] = [
                'month' => $a['key'],
                'cohort' => $c,
                'returned' => $r,
                'rate' => $c > 0 ? round(($r / $c) * 100, 1) : null,
            ];
        }

        return [
            'months' => $months,
            'rows' => $rows,
            'pool' => $pool,
        ];
    }

    /**
     * Pool-level follow-up for a given asOf anchor — dedupes patients
     * across branches so a patient who treated at two branches in the
     * cohort window only counts once, and is "returned" if they rebooked
     * at ANY branch. Same 7-day gap + matured-cohort rules.
     *
     * @param  list<int>  $branchIds
     * @return array{cohort:int, returned:int}
     */
    private function followUpPool(MetricScope $scope, array $branchIds, string $asOf): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return ['cohort' => 0, 'returned' => 0];
        }

        $anchor = \Carbon\CarbonImmutable::parse($asOf);
        $cohortEnd = $anchor->subDays(30)->toDateString();
        $cohortStart = $anchor->subDays(60)->toDateString();
        $today = $anchor->toDateString();

        // Per-patient (not per-(patient, branch)): did this patient have
        // ANY cohort treatment, and if so, ANY later treatment >7d after
        // one of their cohort visits at any branch in scope?
        $pairs = DB::table('appointments as a1')
            ->leftJoin('appointments as a2', function ($join) use ($treatmentStatusIds, $today, $branchIds): void {
                $join->on('a2.patient_id', '=', 'a1.patient_id')
                    ->whereIn('a2.location_id', $branchIds)
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL 7 DAY)')
                    ->where('a2.scheduled_date', '<=', $today)
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->where('a1.account_id', $scope->accountId)
            ->whereIn('a1.location_id', $branchIds)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereBetween('a1.scheduled_date', [$cohortStart, $cohortEnd])
            ->whereNull('a1.deleted_at')
            ->select('a1.patient_id')
            ->selectRaw('MAX(CASE WHEN a2.id IS NOT NULL THEN 1 ELSE 0 END) AS has_followup')
            ->groupBy('a1.patient_id');

        $row = DB::query()
            ->fromSub($pairs, 'p')
            ->selectRaw('COUNT(*) AS cohort, SUM(has_followup) AS returned')
            ->first();

        return [
            'cohort' => (int) ($row->cohort ?? 0),
            'returned' => (int) ($row->returned ?? 0),
        ];
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
