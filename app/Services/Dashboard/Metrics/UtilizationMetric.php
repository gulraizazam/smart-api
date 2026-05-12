<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Practitioner utilization: service time delivered versus rota-available
 * time, per doctor.
 *
 *   util_pct = busy_minutes / available_minutes
 *
 * Business rules (see docs/management-dashboard/utilization.md — absent;
 * rules captured here):
 *
 *   Busy       Σ services.duration for appointments where
 *              appointment_statuses.is_arrived = 1. Consultations and
 *              treatments both count; cancelled / no-show are excluded.
 *              Services missing a duration are skipped and reported via
 *              notes.appointments_without_duration so we surface the
 *              data-quality gap rather than silently deflate utilization.
 *
 *   Available  Rota shift length minus a policy-derived break. Rota comes
 *              from resource_has_rota_days.{start_time,end_time} (joined
 *              through resources.external_id = users.id for the doctor).
 *              Missing rota for a date = practitioner off — the product
 *              uses "no rota" as the leave signal, so days without rows
 *              contribute 0 minutes. PTO rows on resource_time_offs
 *              override rota: full-day PTO zeros the day; partial-day PTO
 *              subtracts its hours.
 *
 *   Break      The rota's start_off / end_off columns exist but are not
 *              populated by the product — we derive a break from shift
 *              length instead: 60 min for 8–9h, 45 min for 7h, 30 min for
 *              6h, 0 otherwise.
 *
 *   Bands      util_pct > 60 → green, 40–60 → amber, < 40 → red.
 *              Null when available_minutes = 0 (can't compute a ratio).
 *
 *   Scope      v1 is doctors only. All doctor role variants (aesthetic
 *              doctor, consultant, lifestyle consultant) are pooled as
 *              one category via DoctorResolver.
 *
 * Return shape:
 *   {
 *     rows:    list<{ resource_id, resource_name, branch_id,
 *                     available_minutes, busy_minutes, util_pct,
 *                     cancelled_minutes, health }>,
 *     totals:  { available_minutes, busy_minutes, cancelled_minutes, util_pct },
 *     window:  { from, to, days },
 *     notes:   { rota_missing_days, pto_days, appointments_without_duration }
 *   }
 */
final class UtilizationMetric implements Metric
{
    public function __construct(
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $doctorIds = $this->resolveDoctorPool($scope, $range);

        if ($doctorIds === []) {
            return $this->empty($range);
        }

        $names = $this->doctors->names($doctorIds);

        $availability = $this->availableMinutesByDoctor($doctorIds, $scope, $range);

        // Single consolidated appointment aggregate — replaces 5 separate
        // queries (arrived total, cancelled total, mix, per-day, missing-
        // duration count) with one grouped pass. Each of those previously
        // cost ~8s because of the whereExists subquery; consolidation +
        // the DISTINCT-join form of the allocation filter cuts the total
        // from ~42s to under 10s on the production pool.
        $agg = $this->appointmentAggregates($doctorIds, $scope, $range);

        $rows = [];
        $totals = ['available' => 0, 'busy' => 0, 'cancelled' => 0];

        foreach ($doctorIds as $doctorId) {
            $avail = (int) ($availability[$doctorId]['minutes'] ?? 0);
            $branchId = $availability[$doctorId]['branch_id'] ?? null;
            $busyMin = (int) ($agg['busy'][$doctorId] ?? 0);
            $cxMin = (int) ($agg['cancelled'][$doctorId] ?? 0);

            $utilPct = $avail > 0
                ? round(($busyMin / $avail) * 100, 1)
                : null;

            $rows[] = [
                'resource_id' => $doctorId,
                'resource_name' => $names[$doctorId] ?? "User #{$doctorId}",
                'branch_id' => $branchId,
                'available_minutes' => $avail,
                'busy_minutes' => $busyMin,
                'util_pct' => $utilPct,
                'cancelled_minutes' => $cxMin,
                'health' => $this->healthBand($utilPct),
                'mix' => [
                    'consult_minutes' => (int) ($agg['mix'][$doctorId]['consult'] ?? 0),
                    'treatment_minutes' => (int) ($agg['mix'][$doctorId]['treatment'] ?? 0),
                ],
                'daily' => $this->buildDailyTrend(
                    $availability[$doctorId]['per_day'] ?? [],
                    $agg['perDayBusy'][$doctorId] ?? [],
                    $range,
                ),
            ];

            $totals['available'] += $avail;
            $totals['busy'] += $busyMin;
            $totals['cancelled'] += $cxMin;
        }
        $missingDuration = $agg['missingDuration'];

        usort($rows, static function (array $a, array $b): int {
            // Nulls last, then highest util first.
            $av = $a['util_pct'] ?? -1;
            $bv = $b['util_pct'] ?? -1;

            return $bv <=> $av;
        });

        return [
            'rows' => $rows,
            'totals' => [
                'available_minutes' => $totals['available'],
                'busy_minutes' => $totals['busy'],
                'cancelled_minutes' => $totals['cancelled'],
                'util_pct' => $totals['available'] > 0
                    ? round(($totals['busy'] / $totals['available']) * 100, 1)
                    : null,
            ],
            'window' => [
                'from' => $range->startString(),
                'to' => $range->endString(),
                'days' => $range->days(),
            ],
            'notes' => [
                'appointments_without_duration' => $missingDuration,
            ],
        ];
    }

    /**
     * Per-branch utilization trend — same N-month window as trend(), but
     * correctly attributed to the branch where work actually happened.
     *
     * Availability: the merged daily per-doctor availability from
     * compute() (which honours closures, non-working days, PTO, and the
     * break policy) is redistributed across each branch proportionally
     * by that day's raw rota slot share. Single-branch doctors credit
     * 100% to their branch; multi-branch doctors split by their real
     * share. The sum of per-branch availability equals the pool total
     * exactly — the two views reconcile.
     *
     * Busy: summed per `appointments.location_id` (the branch where the
     * appointment actually happened), so an appointment at a branch the
     * doctor isn't primarily based at still credits that branch.
     *
     * @return array{
     *   branches: list<array{
     *     branch_id: int,
     *     branch_name: string,
     *     series: list<array{month:string, month_label:string, available_minutes:int, busy_minutes:int, util_pct:float|null}>,
     *     totals: array{available_minutes:int, busy_minutes:int, util_pct:float|null},
     *   }>,
     *   window: array{from:string, to:string, months:int},
     * }
     */
    public function trendByBranch(MetricScope $scope, int $monthsBack = 6): array
    {
        $monthsBack = max(2, min(24, $monthsBack));
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($monthsBack - 1);
        $end = CarbonImmutable::now()->endOfMonth();
        $range = new DateRange($start, $end);

        $doctorIds = $this->resolveDoctorPool($scope, $range);

        $monthKeys = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $monthKeys[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        if ($doctorIds === []) {
            return [
                'branches' => [],
                'window' => [
                    'from' => $start->format('Y-m-d'),
                    'to' => $end->format('Y-m-d'),
                    'months' => $monthsBack,
                ],
            ];
        }

        $data = $this->compute($scope, $range);
        $branchShares = $this->branchShareMinutes($doctorIds, $scope, $range);
        $busyByBranchMonth = $this->busyMinutesPerBranchPerMonth($doctorIds, $scope, $range);

        // Redistribute each (doctor, date) merged availability across
        // branches by that day's raw rota slot share.
        $availByBranchMonth = [];
        foreach ($data['rows'] as $row) {
            $doctorId = (int) $row['resource_id'];
            foreach ($row['daily'] as $cell) {
                $date = (string) $cell['d'];
                $avail = (int) $cell['a'];
                if ($avail <= 0) {
                    continue;
                }
                $shares = $branchShares[$doctorId][$date] ?? [];
                $totalRaw = array_sum($shares);
                if ($totalRaw <= 0) {
                    // No slot info — fall back to the doctor's primary
                    // branch so we don't lose the contribution entirely.
                    $primary = $row['branch_id'] ?? 0;
                    $shares = [$primary => 1];
                    $totalRaw = 1;
                }
                $month = substr($date, 0, 7);
                foreach ($shares as $branchId => $rawMin) {
                    $branchId = (int) $branchId;
                    $minutes = (int) round($avail * ($rawMin / $totalRaw));
                    if ($minutes <= 0) {
                        continue;
                    }
                    $availByBranchMonth[$branchId][$month]
                        = ($availByBranchMonth[$branchId][$month] ?? 0) + $minutes;
                }
            }
        }

        // Union of branches on either side so a branch with only busy
        // (ghost roster) or only availability (zero arrivals) still
        // surfaces.
        $allBranchIds = array_values(array_unique(array_merge(
            array_keys($availByBranchMonth),
            array_keys($busyByBranchMonth),
        )));

        $names = $allBranchIds === []
            ? []
            : DB::table('locations')
                ->whereIn('id', $allBranchIds)
                ->pluck('name', 'id')
                ->toArray();

        $branches = [];
        foreach ($allBranchIds as $branchId) {
            $series = [];
            $totA = 0;
            $totB = 0;
            foreach ($monthKeys as $key) {
                $a = (int) ($availByBranchMonth[$branchId][$key] ?? 0);
                $b = (int) ($busyByBranchMonth[$branchId][$key] ?? 0);
                $series[] = [
                    'month' => $key,
                    'month_label' => CarbonImmutable::parse($key.'-01')->format("M 'y"),
                    'available_minutes' => $a,
                    'busy_minutes' => $b,
                    'util_pct' => $a > 0 ? round(($b / $a) * 100, 1) : null,
                ];
                $totA += $a;
                $totB += $b;
            }

            $branches[] = [
                'branch_id' => (int) $branchId,
                'branch_name' => $names[$branchId] ?? ($branchId > 0 ? "Branch #{$branchId}" : 'Unassigned'),
                'series' => $series,
                'totals' => [
                    'available_minutes' => $totA,
                    'busy_minutes' => $totB,
                    'util_pct' => $totA > 0 ? round(($totB / $totA) * 100, 1) : null,
                ],
            ];
        }

        // Busiest first — natural reading order for the ranked list.
        usort($branches, function ($a, $b): int {
            $av = $a['totals']['util_pct'] ?? -1;
            $bv = $b['totals']['util_pct'] ?? -1;

            return $bv <=> $av;
        });

        return [
            'branches' => $branches,
            'window' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
                'months' => $monthsBack,
            ],
        ];
    }

    /**
     * Per-doctor monthly utilization trend for a single branch. Used by
     * the FDM dashboard's Trend tab so the operator sees how each
     * doctor's utilization has been moving over the last N months,
     * alongside the branch-level pool.
     *
     * Reuses `compute()` for the full window then re-buckets each
     * doctor's daily a/b figures by month — no extra SQL round-trips.
     * Inactive doctors are dropped via the active-doctor filter inside
     * `compute()` itself.
     *
     * @return array{
     *   branch_id: int,
     *   branch_name: string|null,
     *   rows: list<array<string, mixed>>,
     *   branch_pool: array<string, mixed>,
     *   window: array{from:string, to:string, months:int},
     * }
     */
    public function branchDoctorTrend(MetricScope $scope, int $branchId, int $monthsBack = 6): array
    {
        $monthsBack = max(2, min(24, $monthsBack));
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($monthsBack - 1);
        $end = CarbonImmutable::now()->endOfMonth();
        $range = new DateRange($start, $end);
        $window = [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'months' => $monthsBack,
        ];

        $branchName = DB::table('locations')->where('id', $branchId)->whereNull('deleted_at')->value('name');

        $emptyResponse = [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'rows' => [],
            'branch_pool' => [
                'series' => [],
                'totals' => ['available_minutes' => 0, 'busy_minutes' => 0, 'util_pct' => null],
            ],
            'window' => $window,
        ];

        if ($scope->isDenyAll()) {
            return $emptyResponse;
        }
        if ($scope->isBranchScoped() && $scope->branchIds !== null && ! in_array($branchId, $scope->branchIds, true)) {
            return $emptyResponse;
        }
        if ($branchName === null) {
            return $emptyResponse;
        }

        $monthKeys = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $monthKeys[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        // Force scope to the single branch so the underlying queries
        // attribute every minute to this one location — same pattern the
        // doctor breakdown panels use.
        $branchScope = MetricScope::branches($scope->accountId, [$branchId]);
        $data = $this->compute($branchScope, $range);

        $rows = [];
        $poolByMonth = [];
        foreach ($monthKeys as $key) {
            $poolByMonth[$key] = ['a' => 0, 'b' => 0];
        }

        foreach ($data['rows'] as $row) {
            $byMonth = [];
            foreach ($monthKeys as $key) {
                $byMonth[$key] = ['a' => 0, 'b' => 0];
            }
            foreach ($row['daily'] as $cell) {
                $month = substr((string) $cell['d'], 0, 7);
                if (! isset($byMonth[$month])) {
                    continue;
                }
                $byMonth[$month]['a'] += (int) $cell['a'];
                $byMonth[$month]['b'] += (int) $cell['b'];
            }

            $series = [];
            foreach ($monthKeys as $key) {
                $a = $byMonth[$key]['a'];
                $b = $byMonth[$key]['b'];
                $series[] = [
                    'month' => $key,
                    'month_label' => CarbonImmutable::parse($key.'-01')->format("M 'y"),
                    'available_minutes' => $a,
                    'busy_minutes' => $b,
                    'util_pct' => $a > 0 ? round(($b / $a) * 100, 1) : null,
                ];
                $poolByMonth[$key]['a'] += $a;
                $poolByMonth[$key]['b'] += $b;
            }

            $rows[] = [
                'doctor_id' => (int) $row['resource_id'],
                'doctor_name' => (string) $row['resource_name'],
                'series' => $series,
                'totals' => [
                    'available_minutes' => (int) $row['available_minutes'],
                    'busy_minutes' => (int) $row['busy_minutes'],
                    'util_pct' => $row['util_pct'],
                ],
            ];
        }

        // Sort doctors by latest-month util desc; nulls land at the
        // bottom — same posture as the per-branch trend.
        usort($rows, function (array $a, array $b): int {
            $av = end($a['series'])['util_pct'] ?? -1;
            $bv = end($b['series'])['util_pct'] ?? -1;
            return $bv <=> $av;
        });

        $poolSeries = [];
        $poolTotA = 0;
        $poolTotB = 0;
        foreach ($monthKeys as $key) {
            $a = $poolByMonth[$key]['a'];
            $b = $poolByMonth[$key]['b'];
            $poolSeries[] = [
                'month' => $key,
                'month_label' => CarbonImmutable::parse($key.'-01')->format("M 'y"),
                'available_minutes' => $a,
                'busy_minutes' => $b,
                'util_pct' => $a > 0 ? round(($b / $a) * 100, 1) : null,
            ];
            $poolTotA += $a;
            $poolTotB += $b;
        }

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'rows' => $rows,
            'branch_pool' => [
                'series' => $poolSeries,
                'totals' => [
                    'available_minutes' => $poolTotA,
                    'busy_minutes' => $poolTotB,
                    'util_pct' => $poolTotA > 0 ? round(($poolTotB / $poolTotA) * 100, 1) : null,
                ],
            ],
            'window' => $window,
        ];
    }

    /**
     * Raw rota slot minutes per (doctor, date, branch) — weights used
     * for splitting merged daily availability across branches. Filters
     * match the main rota query (active, soft-delete, allocation) but
     * no merge or break — break/closure/non-working/PTO are already
     * baked into the merged daily totals being distributed.
     *
     * @param  list<int>  $doctorIds
     * @return array<int, array<string, array<int, int>>>
     */
    private function branchShareMinutes(array $doctorIds, MetricScope $scope, DateRange $range): array
    {
        $rows = DB::table('resource_has_rota_days as rhrd')
            ->join('resource_has_rota as rhr', 'rhr.id', '=', 'rhrd.resource_has_rota_id')
            ->join('resources as r', 'r.id', '=', 'rhr.resource_id')
            ->joinSub(
                $this->allowedUserLocationSub($scope, $range),
                'alloc',
                fn ($j) => $j
                    ->on('alloc.user_id', '=', 'r.external_id')
                    ->on('alloc.location_id', '=', 'rhr.location_id'),
            )
            ->whereIn('r.external_id', $doctorIds)
            ->where('r.account_id', $scope->accountId)
            ->where('r.active', 1)
            ->whereNull('r.deleted_at')
            ->whereBetween('rhrd.date', [$range->startString(), $range->endString()])
            ->where('rhrd.active', 1)
            ->where('rhr.active', 1)
            ->whereNull('rhrd.deleted_at')
            ->whereNull('rhr.deleted_at')
            ->whereNotNull('rhrd.start_time')
            ->whereNotNull('rhrd.end_time')
            ->when(
                $scope->isBranchScoped() && $scope->branchIds !== null,
                fn ($q) => $q->whereIn('rhr.location_id', $scope->branchIds),
            )
            ->select(
                'r.external_id as doctor_id',
                'rhr.location_id as branch_id',
                'rhrd.date',
                'rhrd.start_time',
                'rhrd.end_time',
            )
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $interval = $this->parseShiftInterval((string) $row->start_time, (string) $row->end_time);
            if ($interval === null) {
                continue;
            }
            $doctorId = (int) $row->doctor_id;
            $branchId = (int) $row->branch_id;
            $date = (string) $row->date;
            $min = (int) round(($interval['e'] - $interval['s']) / 60);
            $out[$doctorId][$date][$branchId]
                = ($out[$doctorId][$date][$branchId] ?? 0) + $min;
        }

        return $out;
    }

    /**
     * Busy minutes per (branch, month) — keyed by each appointment's
     * actual `location_id`. Matches the intuition "what happened here"
     * rather than "the doctor's home base".
     *
     * @param  list<int>  $doctorIds
     * @return array<int, array<string, int>>
     */
    private function busyMinutesPerBranchPerMonth(array $doctorIds, MetricScope $scope, DateRange $range): array
    {
        // Activity-gated allocation filter in joinSub form — MariaDB picks
        // the (user_id, location_id) composite index cleanly. Dropping
        // this pattern made the same query ~10× slower in testing.
        $query = DB::table('appointments as a')
            ->join('appointment_statuses as ast', 'ast.id', '=', 'a.appointment_status_id')
            ->join('services as s', 's.id', '=', 'a.service_id')
            ->joinSub(
                $this->allowedUserLocationSub($scope, $range),
                'alloc',
                fn ($j) => $j
                    ->on('alloc.user_id', '=', 'a.doctor_id')
                    ->on('alloc.location_id', '=', 'a.location_id'),
            )
            ->whereIn('a.doctor_id', $doctorIds)
            ->where('a.account_id', $scope->accountId)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->where('ast.is_arrived', 1)
            ->whereNull('a.deleted_at')
            ->whereNotNull('s.duration');

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('a.location_id', $scope->branchIds);
        }

        $rows = $query
            ->selectRaw(
                'a.location_id as branch_id, '.
                "DATE_FORMAT(a.scheduled_date, '%Y-%m') as month, ".
                'SUM('.
                    '(CAST(SUBSTRING_INDEX(s.duration, ":", 1) AS UNSIGNED) * 60) + '.
                    'CAST(SUBSTRING_INDEX(s.duration, ":", -1) AS UNSIGNED)'.
                ') as m',
            )
            ->groupBy('a.location_id', 'month')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->branch_id][(string) $row->month] = (int) $row->m;
        }

        return $out;
    }

    /**
     * Pool-level utilization trend over the last N full calendar months
     * (inclusive of the current month). Reuses compute() for a single
     * N-month window and rolls the per-doctor daily series into monthly
     * pool totals. Keeps all the same rules (merge, break, PTO, closure,
     * non-working, allocation) so the trend line and the main panel stay
     * on the same basis.
     *
     * @return array{
     *   series: list<array{month:string, month_label:string, available_minutes:int, busy_minutes:int, util_pct:float|null}>,
     *   totals: array{available_minutes:int, busy_minutes:int, util_pct:float|null},
     *   window: array{from:string, to:string, months:int},
     * }
     */
    public function trend(MetricScope $scope, int $monthsBack = 6): array
    {
        $monthsBack = max(2, min(24, $monthsBack));
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($monthsBack - 1);
        $end = CarbonImmutable::now()->endOfMonth();
        $range = new DateRange($start, $end);

        $data = $this->compute($scope, $range);

        // Pre-seed every month in range with zeros so empty months still
        // render on the chart (otherwise a month with no rota data would
        // leave a gap).
        $byMonth = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $byMonth[$key] = ['a' => 0, 'b' => 0];
            $cursor = $cursor->addMonth();
        }

        foreach ($data['rows'] as $row) {
            foreach ($row['daily'] as $cell) {
                $month = substr((string) $cell['d'], 0, 7);
                if (! isset($byMonth[$month])) {
                    continue;
                }
                $byMonth[$month]['a'] += (int) $cell['a'];
                $byMonth[$month]['b'] += (int) $cell['b'];
            }
        }

        $series = [];
        $totalA = 0;
        $totalB = 0;
        foreach ($byMonth as $month => $totals) {
            $util = $totals['a'] > 0 ? round(($totals['b'] / $totals['a']) * 100, 1) : null;
            $series[] = [
                'month' => $month,
                'month_label' => CarbonImmutable::parse($month.'-01')->format("M 'y"),
                'available_minutes' => (int) $totals['a'],
                'busy_minutes' => (int) $totals['b'],
                'util_pct' => $util,
            ];
            $totalA += $totals['a'];
            $totalB += $totals['b'];
        }

        return [
            'series' => $series,
            'totals' => [
                'available_minutes' => $totalA,
                'busy_minutes' => $totalB,
                'util_pct' => $totalA > 0 ? round(($totalB / $totalA) * 100, 1) : null,
            ],
            'window' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
                'months' => $monthsBack,
            ],
        ];
    }

    /**
     * Available minutes per doctor. Resolves rota days, applies the policy
     * break, subtracts PTO, and honours branch closures. Returns both a
     * window-wide total and a per-day breakdown — the latter is consumed
     * by the trend sparkline.
     *
     * @param  list<int>  $doctorIds
     * @return array<int, array{minutes: int, branch_id: int|null, per_day: array<string, int>}>
     */
    private function availableMinutesByDoctor(array $doctorIds, MetricScope $scope, DateRange $range): array
    {
        $rotaQuery = DB::table('resource_has_rota_days as rhrd')
            ->join('resource_has_rota as rhr', 'rhr.id', '=', 'rhrd.resource_has_rota_id')
            ->join('resources as r', 'r.id', '=', 'rhr.resource_id')
            ->whereIn('r.external_id', $doctorIds)
            ->where('r.account_id', $scope->accountId)
            ->where('r.active', 1)
            ->whereNull('r.deleted_at')
            ->whereBetween('rhrd.date', [$range->startString(), $range->endString()])
            ->where('rhrd.active', 1)
            ->where('rhr.active', 1)
            ->whereNull('rhrd.deleted_at')
            // resource_has_rota uses SoftDeletes — stale rotas can remain
            // with active=1; exclude them to avoid phantom availability.
            ->whereNull('rhr.deleted_at')
            ->whereNotNull('rhrd.start_time')
            ->whereNotNull('rhrd.end_time')
            // Activity-gated allocation filter — keep rotas where the
            // doctor is currently allocated to the branch OR had real
            // arrived-appointment activity there in the window.
            // See allowedUserLocationSub() for the rationale; this
            // drops stale orphan rotas (Saba-at-Johar-Town case) while
            // preserving historical activity (Bahadurabad-November
            // case) that was previously being silently filtered.
            ->joinSub(
                $this->allowedUserLocationSub($scope, $range),
                'alloc',
                fn ($j) => $j
                    ->on('alloc.user_id', '=', 'r.external_id')
                    ->on('alloc.location_id', '=', 'rhr.location_id'),
            );

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $rotaQuery->whereIn('rhr.location_id', $scope->branchIds);
        }

        $rotaRows = $rotaQuery
            ->select([
                'r.external_id as doctor_id',
                'rhr.location_id',
                'rhrd.date',
                'rhrd.start_time',
                'rhrd.end_time',
            ])
            ->get();

        // Branch/date closure set from business_closures. A closure linked
        // to no locations — or to the pseudo-location "All Centres"
        // (name LIKE 'All %') — applies globally; otherwise it's scoped
        // to its linked locations. The UI already layers this on the rota
        // grid (hence "Eid-ul-Fitr Holidays" overlays); utilization must
        // do the same or rostered-but-closed days inflate availability.
        $closedByBranchDate = $this->closedBranchDates($scope, $range);

        // Non-working dates driven by settings.business_working_days +
        // working_day_exceptions. Default is Mon–Sat open, Sun closed;
        // individual dates can flip to is_working=1 (forced open) or
        // is_working=0 (forced closed). The rota UI greys closed dates
        // as "Closed" regardless of whether a rota_day row exists — if
        // we don't honour it here, Sunday rota rows (auto-generated)
        // inflate availability.
        $nonWorkingDates = $this->nonWorkingDates($scope, $range);

        // Collect raw time intervals (in seconds-of-day) per (doctor, date).
        // A single date can have multiple rota_day rows for legitimate
        // reasons: a true split shift at one branch (e.g. 12:00–17:00 +
        // 19:30–23:30), or a multi-branch practitioner rostered at 2–3
        // branches with OVERLAPPING windows. In the latter case the human
        // can only be in one place, so naively summing double-counts their
        // hours. We resolve by merging overlapping intervals below.
        $slotsByDoctorDate = [];
        $branchByDoctor = [];

        foreach ($rotaRows as $row) {
            $doctorId = (int) $row->doctor_id;
            $date = (string) $row->date;
            $branchId = (int) $row->location_id;

            // Drop slots that fall on a branch closure — the branch is
            // shut, so the doctor can't deliver service regardless of
            // what the rota claims.
            if (isset($closedByBranchDate[$branchId][$date])
                || isset($closedByBranchDate[0][$date]) /* global closure */) {
                continue;
            }

            // Drop slots that fall on a non-working day (weekly pattern
            // or dated exception).
            if (isset($nonWorkingDates[$date])) {
                continue;
            }

            $interval = $this->parseShiftInterval((string) $row->start_time, (string) $row->end_time);
            if ($interval === null) {
                continue;
            }

            $slotsByDoctorDate[$doctorId][$date][] = $interval;

            $slot = (int) round(($interval['e'] - $interval['s']) / 60);
            if (! isset($branchByDoctor[$doctorId]) || $slot > $branchByDoctor[$doctorId]['shift']) {
                $branchByDoctor[$doctorId] = ['branch_id' => $branchId, 'shift' => $slot];
            }
        }

        // Merge overlapping intervals per (doctor, date). After the merge:
        //   — number of resulting blocks tells us how to apply break policy:
        //     one continuous block → apply policyBreak(); two or more disjoint
        //     blocks → 0 break (the inter-block gap already covers the break).
        //   — total minutes = sum of (end - start) across merged blocks.
        $byDoctorDate = [];
        foreach ($slotsByDoctorDate as $doctorId => $days) {
            foreach ($days as $date => $slots) {
                $merged = $this->mergeIntervals($slots);
                $totalShiftSec = 0;
                foreach ($merged as $m) {
                    $totalShiftSec += ($m['e'] - $m['s']);
                }
                $totalShift = (int) round($totalShiftSec / 60);
                $break = count($merged) > 1 ? 0 : $this->policyBreak($totalShift);

                $byDoctorDate[$doctorId][$date] = [
                    'minutes' => max(0, $totalShift - $break),
                    'raw_shift' => $totalShift,
                ];
            }
        }

        // Subtract PTO. We resolve PTO resource_id → users.id via resources
        // so we can zero/reduce the right entries on $byDoctorDate above.
        $ptoRows = $this->ptoRows($doctorIds, $scope, $range);

        foreach ($ptoRows as $row) {
            $doctorId = (int) $row->doctor_id;
            $date = (string) $row->date;

            if (! isset($byDoctorDate[$doctorId][$date])) {
                continue;
            }

            if ($row->is_full_day) {
                $byDoctorDate[$doctorId][$date]['minutes'] = 0;

                continue;
            }

            // Partial PTO — subtract the gross PTO minutes from net
            // available, capped at zero. Break is already subtracted from
            // net; we don't try to reconcile overlap with break windows
            // because breaks aren't stored (policy-derived only).
            $ptoMin = $this->minutesBetween((string) $row->start_time, (string) $row->end_time);
            $byDoctorDate[$doctorId][$date]['minutes']
                = max(0, ($byDoctorDate[$doctorId][$date]['minutes'] ?? 0) - $ptoMin);
        }

        $result = [];
        foreach ($byDoctorDate as $doctorId => $days) {
            $total = array_sum(array_column($days, 'minutes'));
            $perDay = [];
            foreach ($days as $date => $agg) {
                $perDay[$date] = (int) $agg['minutes'];
            }
            $result[$doctorId] = [
                'minutes' => (int) $total,
                'branch_id' => $branchByDoctor[$doctorId]['branch_id'] ?? null,
                'per_day' => $perDay,
            ];
        }

        return $result;
    }

    /**
     * Build the per-day trend array for a single doctor over the window.
     * Emits one cell per day including zero-available days so the client
     * can render a continuous sparkline. Keys are short to keep the JSON
     * compact: d=date, a=available_minutes, b=busy_minutes, u=util_pct.
     *
     * @param  array<string, int>  $perDayAvail
     * @param  array<string, int>  $perDayBusy
     * @return list<array{d: string, a: int, b: int, u: float|null}>
     */
    private function buildDailyTrend(array $perDayAvail, array $perDayBusy, DateRange $range): array
    {
        $out = [];
        $cursor = CarbonImmutable::parse($range->startString())->startOfDay();
        $end = CarbonImmutable::parse($range->endString())->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->format('Y-m-d');
            $avail = $perDayAvail[$date] ?? 0;
            $busy = $perDayBusy[$date] ?? 0;
            $util = $avail > 0 ? round(($busy / $avail) * 100, 1) : null;

            $out[] = [
                'd' => $date,
                'a' => (int) $avail,
                'b' => (int) $busy,
                'u' => $util,
            ];

            $cursor = $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  list<int>  $doctorIds
     * @return iterable<object{doctor_id: int, date: string, is_full_day: int, start_time: ?string, end_time: ?string}>
     */
    private function ptoRows(array $doctorIds, MetricScope $scope, DateRange $range): iterable
    {
        // resource_time_offs stores start_date (and optional repeat_until +
        // is_repeat) rather than a from/to range, so we read raw rows in
        // window — including repeat rows whose window overlaps — and expand
        // repeats in PHP. For v1 we treat is_repeat=true as "weekly repeat
        // until repeat_until" (the only pattern currently produced by the
        // UI per product confirmation). Non-repeat rows apply to
        // start_date only.
        $query = DB::table('resource_time_offs as rt')
            ->join('resources as r', 'r.id', '=', 'rt.resource_id')
            ->whereIn('r.external_id', $doctorIds)
            ->where('r.account_id', $scope->accountId)
            ->whereNull('rt.deleted_at')
            // Either a single-date row in window, OR a repeat row whose
            // repeat window overlaps our range.
            ->where(function ($q) use ($range) {
                $q->whereBetween('rt.start_date', [$range->startString(), $range->endString()])
                    ->orWhere(function ($q2) use ($range) {
                        $q2->where('rt.is_repeat', 1)
                            ->where('rt.start_date', '<=', $range->endString())
                            ->where(function ($q3) use ($range) {
                                $q3->whereNull('rt.repeat_until')
                                    ->orWhere('rt.repeat_until', '>=', $range->startString());
                            });
                    });
            });

        $rows = $query
            ->select([
                'r.external_id as doctor_id',
                'rt.start_date',
                'rt.is_full_day',
                'rt.is_repeat',
                'rt.repeat_until',
                'rt.start_time',
                'rt.end_time',
            ])
            ->get();

        $out = [];
        $from = CarbonImmutable::parse($range->startString())->startOfDay();
        $to = CarbonImmutable::parse($range->endString())->startOfDay();

        foreach ($rows as $row) {
            $doctorId = (int) $row->doctor_id;
            $startDate = CarbonImmutable::parse((string) $row->start_date)->startOfDay();

            if (! $row->is_repeat) {
                if ($startDate->between($from, $to)) {
                    $out[] = (object) [
                        'doctor_id' => $doctorId,
                        'date' => $startDate->format('Y-m-d'),
                        'is_full_day' => (bool) $row->is_full_day,
                        'start_time' => $row->start_time,
                        'end_time' => $row->end_time,
                    ];
                }

                continue;
            }

            $until = $row->repeat_until
                ? CarbonImmutable::parse((string) $row->repeat_until)->startOfDay()
                : $to;
            $untilClamped = $until->lt($to) ? $until : $to;

            // First occurrence on or after $from on the same weekday as
            // $startDate. dayOfWeek is 0–6; (want − have + 7) % 7 gives the
            // number of days forward from $from to land on the right weekday.
            if ($startDate->gte($from)) {
                $cursor = $startDate;
            } else {
                $offset = (($startDate->dayOfWeek - $from->dayOfWeek) + 7) % 7;
                $cursor = $from->addDays($offset);
            }

            while ($cursor->lte($untilClamped)) {
                $out[] = (object) [
                    'doctor_id' => $doctorId,
                    'date' => $cursor->format('Y-m-d'),
                    'is_full_day' => (bool) $row->is_full_day,
                    'start_time' => $row->start_time,
                    'end_time' => $row->end_time,
                ];

                $cursor = $cursor->addWeek();
            }
        }

        return $out;
    }

    /**
     * One-shot appointment aggregates — replaces five separate queries
     * (arrived/cancelled totals, per-doctor mix, per-doctor per-day busy,
     * missing-duration count) with a single grouped pass. Each of those
     * five queries previously cost ~8s because the allocation filter
     * materialized as an EXISTS subquery; the consolidated form uses a
     * DISTINCT-derived JOIN on doctor_has_locations which MariaDB plans
     * far more efficiently against the appointments index.
     *
     * Returns:
     *   busy[doctor_id]            => minutes (arrived only)
     *   cancelled[doctor_id]       => minutes (cancelled only, info)
     *   mix[doctor_id][consult|treatment] => minutes (arrived only)
     *   perDayBusy[doctor_id][YYYY-MM-DD] => minutes (arrived only)
     *   missingDuration            => count of arrived appts with no duration
     *
     * @param  list<int>  $doctorIds
     * @return array{busy:array<int,int>, cancelled:array<int,int>, mix:array<int,array{consult:int,treatment:int}>, perDayBusy:array<int,array<string,int>>, missingDuration:int}
     */
    private function appointmentAggregates(array $doctorIds, MetricScope $scope, DateRange $range): array
    {
        // Pre-fetch the allowed (doctor_id, location_id) pairs in PHP.
        // EXPLAIN ANALYZE showed the previous joinSub fooled the planner
        // into looping the appointments table per allocation row (~63
        // iterations × 23ms scanning 34K rows each → 1.5s). Pulling the
        // same set as a small array (3ms) and filtering in PHP after the
        // grouped query lets the planner pick the date-range index
        // naturally, dropping the SQL portion to ~20ms.
        $allowedPairs = $this->allowedUserLocationPairs($scope, $range);

        $query = DB::table('appointments as a')
            ->join('appointment_statuses as ast', 'ast.id', '=', 'a.appointment_status_id')
            ->leftJoin('services as s', 's.id', '=', 'a.service_id')
            ->whereIn('a.doctor_id', $doctorIds)
            ->where('a.account_id', $scope->accountId)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->where(function ($q) {
                // Only pull rows we care about (arrived or cancelled) —
                // avoids scanning unscheduled/booked rows we'd discard.
                $q->where('ast.is_arrived', 1)->orWhere('ast.is_cancelled', 1);
            })
            ->whereNull('a.deleted_at');

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('a.location_id', $scope->branchIds);
        }

        // GROUP BY now includes a.location_id so each row carries the
        // location for the post-query allocation check. Aggregate values
        // are unchanged because they sum over rows that share all the
        // GROUP BY columns regardless of whether location is split.
        $rows = $query
            ->selectRaw(
                'a.doctor_id, '.
                'a.location_id, '.
                'a.scheduled_date as d, '.
                'a.appointment_type_id as type_id, '.
                'ast.is_arrived, '.
                'ast.is_cancelled, '.
                'COALESCE('.
                    'CASE WHEN s.duration IS NULL OR s.duration = \'\' OR s.duration = \'00:00\' THEN 0 '.
                    'ELSE (CAST(SUBSTRING_INDEX(s.duration, \':\', 1) AS UNSIGNED) * 60) + '.
                    'CAST(SUBSTRING_INDEX(s.duration, \':\', -1) AS UNSIGNED) END, 0) as minutes, '.
                'SUM(CASE WHEN ast.is_arrived = 1 AND (s.duration IS NULL OR s.duration = \'\' OR s.duration = \'00:00\') THEN 1 ELSE 0 END) as missing_dur_count, '.
                'COUNT(*) as n',
            )
            ->groupBy('a.doctor_id', 'a.location_id', 'a.scheduled_date', 'a.appointment_type_id', 'ast.is_arrived', 'ast.is_cancelled', 's.duration')
            ->get()
            // Apply the allocation filter that the joinSub used to do.
            // Drops rows where the doctor wasn't allocated to (or active
            // at) the appointment's branch — same semantic as before.
            ->filter(static fn ($r): bool => isset($allowedPairs[(int) $r->doctor_id][(int) $r->location_id]));

        $busy = [];
        $cancelled = [];
        $mix = [];
        $perDayBusy = [];
        $missingDuration = 0;

        foreach ($rows as $row) {
            $doctorId = (int) $row->doctor_id;
            $date = (string) $row->d;
            $type = (int) $row->type_id;
            $minutes = (int) $row->minutes * (int) $row->n;
            $missingDuration += (int) $row->missing_dur_count;

            if ((int) $row->is_arrived === 1) {
                $busy[$doctorId] = ($busy[$doctorId] ?? 0) + $minutes;
                $perDayBusy[$doctorId][$date] = ($perDayBusy[$doctorId][$date] ?? 0) + $minutes;
                if ($type === 1) {
                    $mix[$doctorId]['consult'] = ($mix[$doctorId]['consult'] ?? 0) + $minutes;
                } elseif ($type === 2) {
                    $mix[$doctorId]['treatment'] = ($mix[$doctorId]['treatment'] ?? 0) + $minutes;
                }
            }

            if ((int) $row->is_cancelled === 1) {
                $cancelled[$doctorId] = ($cancelled[$doctorId] ?? 0) + $minutes;
            }
        }

        return [
            'busy' => $busy,
            'cancelled' => $cancelled,
            'mix' => $mix,
            'perDayBusy' => $perDayBusy,
            'missingDuration' => $missingDuration,
        ];
    }

    /**
     * Restrict an appointments-table query to rows where the doctor is
     * currently allocated to the appointment's branch. Mirrors the
     * allocation filter on the rota side, so busy and available minutes
     * stay on the same allocation basis — without this, an arrived
     * appointment at a branch the doctor has been de-allocated from
     * would count as busy even though the corresponding rota was
     * (correctly) dropped, pushing utilization above 100%.
     *
     * Assumes the table alias `a` for the appointments table (all
     * callers in this file use that alias).
     */
    private function restrictToAllocatedAppointments(mixed $query, MetricScope $scope, DateRange $range): void
    {
        $query->joinSub(
            $this->allowedUserLocationSub($scope, $range),
            'alloc',
            fn ($j) => $j
                ->on('alloc.user_id', '=', 'a.doctor_id')
                ->on('alloc.location_id', '=', 'a.location_id'),
        );
    }

    /**
     * Doctor pool for a window — union of:
     *   1. Currently-active doctors per DoctorResolver (Spatie role).
     *   2. Any user with ≥1 arrived appointment in the window.
     *
     * The second branch keeps historical doctors who have since left
     * the active roster from being silently dropped — e.g. Bahadurabad
     * Nov had two doctors who worked there but are no longer on the
     * active list, and their Nov activity was being filtered out at
     * this resolution step, well before the allocation filter ran.
     *
     * Branch-scoped queries narrow the historical branch to the scope's
     * branches so an ex-doctor who only worked at other branches in the
     * window doesn't leak into a branch-specific report.
     *
     * @return list<int>
     */
    private function resolveDoctorPool(MetricScope $scope, DateRange $range): array
    {
        $current = $this->doctors->idsInScope($scope);

        $historicalQuery = DB::table('appointments as a')
            ->join('appointment_statuses as ast', 'ast.id', '=', 'a.appointment_status_id')
            ->where('a.account_id', $scope->accountId)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->where('ast.is_arrived', 1)
            ->whereNull('a.deleted_at');

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $historicalQuery->whereIn('a.location_id', $scope->branchIds);
        }

        $historical = $historicalQuery->pluck('a.doctor_id')->unique()->toArray();

        $combined = array_values(array_unique(array_merge(
            array_map('intval', $current),
            array_map('intval', $historical),
        )));

        if ($combined === []) {
            return $combined;
        }

        // Drop inactive (resigned / off-roster) practitioners — historical
        // rows would otherwise pad the panel with people who no longer
        // work here, which is noise for the operator. Same rule we apply
        // on retention / feedback panels.
        return DB::table('users')
            ->whereIn('id', $combined)
            ->where('active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();
    }

    /**
     * Canonical "valid (user_id, location_id) pairs" subquery used by
     * every rota and appointment query in this file. A pair qualifies if:
     *
     *   1. The doctor is currently allocated to that branch
     *      (`doctor_has_locations.is_allocated = 1`), OR
     *   2. The doctor had ≥1 arrived appointment at that branch within
     *      the window — i.e. historical activity evidences historical
     *      allocation even if the flag has since been flipped.
     *
     * Without (2), the `Saba at Johar Town` orphan-rota case stays
     * excluded (no activity → not valid); with (2), the `Bahadurabad
     * November` historical-doctor case is included (278 real appointments
     * that were being silently dropped).
     *
     * Returned as a Laravel Builder so callers can consume via joinSub.
     */
    /**
     * PHP-side variant of the allowed (user_id, location_id) set —
     * returned as `[user_id => [location_id => true]]` so callers can
     * O(1) check pair membership instead of joinSub-ing it.
     *
     * @return array<int, array<int, true>>
     */
    private function allowedUserLocationPairs(MetricScope $scope, DateRange $range): array
    {
        $allocated = DB::table('doctor_has_locations')
            ->select('user_id', 'location_id')
            ->where('is_allocated', 1);

        $active = DB::table('appointments as aa')
            ->join('appointment_statuses as aas', 'aas.id', '=', 'aa.appointment_status_id')
            ->where('aa.account_id', $scope->accountId)
            ->whereBetween('aa.scheduled_date', [$range->startString(), $range->endString()])
            ->where('aas.is_arrived', 1)
            ->whereNull('aa.deleted_at')
            ->select('aa.doctor_id as user_id', 'aa.location_id');

        $rows = DB::query()
            ->fromSub($allocated->union($active), 'combined')
            ->select('user_id', 'location_id')
            ->distinct()
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->user_id][(int) $r->location_id] = true;
        }

        return $out;
    }

    private function allowedUserLocationSub(MetricScope $scope, DateRange $range): \Illuminate\Database\Query\Builder
    {
        $allocated = DB::table('doctor_has_locations')
            ->select('user_id', 'location_id')
            ->where('is_allocated', 1);

        $active = DB::table('appointments as aa')
            ->join('appointment_statuses as aas', 'aas.id', '=', 'aa.appointment_status_id')
            ->where('aa.account_id', $scope->accountId)
            ->whereBetween('aa.scheduled_date', [$range->startString(), $range->endString()])
            ->where('aas.is_arrived', 1)
            ->whereNull('aa.deleted_at')
            ->select('aa.doctor_id as user_id', 'aa.location_id');

        // union() (not unionAll) gives us DISTINCT pairs, which keeps
        // downstream joins from multiplying rows.
        return DB::query()->fromSub(
            $allocated->union($active),
            'combined',
        )->select('user_id', 'location_id')->distinct();
    }

    /**
     * Closed (branch, date) pairs driven by business_closures. Returns a
     * nested map [branch_id => [date => true]]. The special key 0
     * represents a global closure (applies to every branch) — callers
     * check both $map[branch_id][$date] and $map[0][$date].
     *
     * A closure is global when it has no linked locations OR when all of
     * its linked locations have names starting with "All " (the pseudo-
     * location pattern used throughout this codebase for company-wide
     * rows — see filterBranches controller filter).
     *
     * @return array<int, array<string, true>>
     */
    private function closedBranchDates(MetricScope $scope, DateRange $range): array
    {
        $closures = DB::table('business_closures as bc')
            ->leftJoin('business_closure_locations as bcl', 'bcl.business_closure_id', '=', 'bc.id')
            ->leftJoin('locations as l', 'l.id', '=', 'bcl.location_id')
            ->where('bc.account_id', $scope->accountId)
            ->whereNull('bc.deleted_at')
            ->where('bc.start_date', '<=', $range->endString())
            ->where('bc.end_date', '>=', $range->startString())
            ->select([
                'bc.id',
                'bc.start_date',
                'bc.end_date',
                'bcl.location_id',
                'l.name as location_name',
            ])
            ->get();

        if ($closures->isEmpty()) {
            return [];
        }

        // Group pivot rows per closure id so we can tell "global" from
        // "branch-specific" without guessing.
        $byClosure = [];
        foreach ($closures as $row) {
            $byClosure[$row->id]['start'] = (string) $row->start_date;
            $byClosure[$row->id]['end'] = (string) $row->end_date;
            if ($row->location_id !== null) {
                $byClosure[$row->id]['locations'][] = [
                    'id' => (int) $row->location_id,
                    'name' => (string) ($row->location_name ?? ''),
                ];
            }
        }

        $map = [];
        $from = CarbonImmutable::parse($range->startString())->startOfDay();
        $to = CarbonImmutable::parse($range->endString())->startOfDay();

        foreach ($byClosure as $closure) {
            $start = CarbonImmutable::parse($closure['start'])->startOfDay();
            $end = CarbonImmutable::parse($closure['end'])->startOfDay();
            // Clamp to the query window so we don't iterate long historic
            // closures unnecessarily.
            $iterStart = $start->lt($from) ? $from : $start;
            $iterEnd = $end->gt($to) ? $to : $end;
            if ($iterStart->gt($iterEnd)) {
                continue;
            }

            $locations = $closure['locations'] ?? [];
            $isGlobal = $locations === []
                || $this->allLocationsArePseudo($locations);

            $branchIds = $isGlobal
                ? [0]
                : array_values(array_unique(array_map(
                    static fn (array $l): int => $l['id'],
                    $locations,
                )));

            // Skip branch-specific closures that don't intersect scope —
            // cheap early exit for unrelated branches in big accounts.
            if (! $isGlobal && $scope->isBranchScoped() && $scope->branchIds !== null) {
                $branchIds = array_values(array_intersect($branchIds, $scope->branchIds));
                if ($branchIds === []) {
                    continue;
                }
            }

            for ($d = $iterStart; $d->lte($iterEnd); $d = $d->addDay()) {
                $date = $d->format('Y-m-d');
                foreach ($branchIds as $branchId) {
                    $map[$branchId][$date] = true;
                }
            }
        }

        return $map;
    }

    /**
     * Non-working dates in the window for this account. Combines the
     * weekly pattern from `settings.business_working_days` (JSON map of
     * {monday: true, …, sunday: false}) with per-date overrides from
     * `working_day_exceptions` (is_working = 1 forces open, = 0 forces
     * closed). Returns a map keyed by YYYY-MM-DD where the value is true
     * for any date the account considers non-working.
     *
     * Matches the check the rota UI already does — see
     * AppointmentsController::getBusinessWorkingDays() for the settings
     * shape and WorkingDayException::isWorkingDay() for the merge logic.
     *
     * @return array<string, true>
     */
    private function nonWorkingDates(MetricScope $scope, DateRange $range): array
    {
        $settingsJson = DB::table('settings')
            ->where('account_id', $scope->accountId)
            ->where('slug', 'business_working_days')
            ->value('data');

        $working = [
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
            'sunday' => false,
        ];
        if (is_string($settingsJson) && $settingsJson !== '') {
            $decoded = json_decode($settingsJson, true);
            if (is_array($decoded)) {
                $working = array_merge($working, $decoded);
            }
        }

        $exceptions = DB::table('working_day_exceptions')
            ->where('account_id', $scope->accountId)
            ->whereBetween('exception_date', [$range->startString(), $range->endString()])
            ->pluck('is_working', 'exception_date')
            ->toArray();

        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $out = [];
        $cursor = CarbonImmutable::parse($range->startString())->startOfDay();
        $end = CarbonImmutable::parse($range->endString())->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->format('Y-m-d');
            if (array_key_exists($date, $exceptions)) {
                // Exception overrides default — is_working=0 means closed,
                // is_working=1 means explicitly open even on a default-off day.
                if ((int) $exceptions[$date] === 0) {
                    $out[$date] = true;
                }
            } else {
                $dayName = $dayNames[(int) $cursor->dayOfWeek];
                if (empty($working[$dayName])) {
                    $out[$date] = true;
                }
            }
            $cursor = $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  list<array{id: int, name: string}>  $locations
     */
    private function allLocationsArePseudo(array $locations): bool
    {
        foreach ($locations as $loc) {
            if (stripos($loc['name'], 'All ') !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge overlapping / touching intervals. Input is a list of
     * ['s' => startSec, 'e' => endSec]; output is the same shape with
     * overlaps collapsed. Touching intervals (end == next start) are also
     * merged because there's no physical gap between them.
     *
     * @param  list<array{s:int, e:int}>  $slots
     * @return list<array{s:int, e:int}>
     */
    private function mergeIntervals(array $slots): array
    {
        if (count($slots) <= 1) {
            return $slots;
        }

        usort($slots, static fn (array $a, array $b): int => $a['s'] <=> $b['s']);

        $merged = [$slots[0]];
        for ($i = 1; $i < count($slots); $i++) {
            $last = $merged[count($merged) - 1];
            $current = $slots[$i];

            if ($current['s'] <= $last['e']) {
                $merged[count($merged) - 1]['e'] = max($last['e'], $current['e']);
            } else {
                $merged[] = $current;
            }
        }

        return $merged;
    }

    /**
     * Minutes between two HH:MM or HH:MM:SS time strings on the same day.
     * Negative/equal returns 0 so we never contribute negative availability.
     */
    private function minutesBetween(string $start, string $end): int
    {
        $s = $this->parseClock($start);
        $e = $this->parseClock($end);

        if ($s === null || $e === null || $e <= $s) {
            return 0;
        }

        return (int) round(($e - $s) / 60);
    }

    private function parseClock(string $value): ?int
    {
        // The rota_days table stores start/end in two formats depending on
        // which write path created the row: ~5k rows are plain "HH:MM"
        // (24-hr) while ~220k are "HH:MM AM/PM" (12-hr). Accept both.
        $value = trim($value);
        $isPm = false;
        $isAm = false;

        // Detect and strip an AM/PM suffix case-insensitively.
        if (preg_match('/\s*(AM|PM)\s*$/i', $value, $match)) {
            $isPm = strtoupper($match[1]) === 'PM';
            $isAm = ! $isPm;
            $value = trim(substr($value, 0, -strlen($match[0])));
        }

        $parts = explode(':', $value);
        if (count($parts) < 2) {
            return null;
        }

        $h = (int) $parts[0];
        $m = (int) $parts[1];
        $sec = isset($parts[2]) ? (int) $parts[2] : 0;

        if ($h < 0 || $m < 0 || $m > 59) {
            return null;
        }

        // 12-hour normalisation: 12 AM → 0, 12 PM stays 12, other PM hours
        // add 12, other AM hours stay as-is. Reject hours > 12 in 12-hr form
        // to avoid ambiguous data like "19:00 PM".
        if ($isAm || $isPm) {
            if ($h < 1 || $h > 12) {
                return null;
            }
            if ($isAm && $h === 12) {
                $h = 0;
            } elseif ($isPm && $h !== 12) {
                $h += 12;
            }
        } elseif ($h > 23) {
            return null;
        }

        return ($h * 3600) + ($m * 60) + $sec;
    }

    /**
     * Parse a shift's start/end HH:MM strings into an interval dict with
     * seconds-of-day bounds. Handles shifts that end at midnight by
     * treating end_time="00:00" (or any end <= start) as 24:00 of the
     * same day — the storage layer writes the literal clock time but
     * the rota UI treats midnight as "end of day", which is where the
     * `resource_has_rota_days` rows in this DB consistently differ from
     * their `end_timestamp` sibling. We trust the clock string and map
     * midnight to 86400 so the slot isn't silently dropped.
     *
     * Returns null if input is unparseable or if start and end are both
     * 00:00 (a zero-length shift — no physical time to credit).
     *
     * @return array{s:int, e:int}|null
     */
    private function parseShiftInterval(string $start, string $end): ?array
    {
        $s = $this->parseClock($start);
        $e = $this->parseClock($end);
        if ($s === null || $e === null) {
            return null;
        }

        if ($e <= $s) {
            $e = 86400; // shift ends at midnight (next-day 00:00)
        }

        if ($e <= $s) {
            return null;
        }

        return ['s' => $s, 'e' => $e];
    }

    /**
     * Break length inferred from shift length. The product doesn't store
     * break start/end, so we apply a policy derived from shift length:
     * 60 min for 8h+ (anything 8 and above — a 12h day doesn't deserve
     * less break than a 9h day), 45 for 7h, 30 for 6h, 0 otherwise.
     * Shift length is in minutes.
     */
    private function policyBreak(int $shiftMinutes): int
    {
        $hours = $shiftMinutes / 60;

        return match (true) {
            $hours >= 8 => 60,
            $hours >= 7 => 45,
            $hours >= 6 => 30,
            default => 0,
        };
    }

    /**
     * Map a util % to its health band. Nulls (unavailable doctor / no rota)
     * surface as null, not red — red is reserved for genuinely low util.
     */
    private function healthBand(?float $pct): ?string
    {
        if ($pct === null) {
            return null;
        }

        return match (true) {
            $pct > 60 => 'green',
            $pct >= 40 => 'amber',
            default => 'red',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(DateRange $range): array
    {
        return [
            'rows' => [],
            'totals' => [
                'available_minutes' => 0,
                'busy_minutes' => 0,
                'cancelled_minutes' => 0,
                'util_pct' => null,
            ],
            'window' => [
                'from' => $range->startString(),
                'to' => $range->endString(),
                'days' => $range->days(),
            ],
            'notes' => ['appointments_without_duration' => 0],
        ];
    }

    /**
     * Hour × day-of-week utilization heatmap for the pool. Projects rota
     * slots and arrived appointments onto a 7 (day-of-week) × 24 (hour)
     * grid, with cell-level available, busy, and util%.
     *
     * day-of-week is 0=Monday through 6=Sunday (ISO convention — keeps
     * Fri/Sat at indexes 4/5 so the Pakistan-weekend pattern reads
     * naturally in the UI).
     *
     * @return array{
     *   cells: list<array{dow:int, hour:int, available_minutes:int, busy_minutes:int, util_pct:float|null}>,
     *   totals: array{available_minutes:int, busy_minutes:int, util_pct:float|null},
     *   window: array{from:string, to:string, days:int}
     * }
     */
    public function heatmap(MetricScope $scope, DateRange $range): array
    {
        $doctorIds = $this->resolveDoctorPool($scope, $range);
        $empty = [
            'cells' => $this->emptyHeatmapCells(),
            'totals' => [
                'available_minutes' => 0,
                'busy_minutes' => 0,
                'util_pct' => null,
            ],
            'window' => [
                'from' => $range->startString(),
                'to' => $range->endString(),
                'days' => $range->days(),
            ],
        ];

        if ($doctorIds === []) {
            return $empty;
        }

        $closedByBranchDate = $this->closedBranchDates($scope, $range);
        $nonWorkingDates = $this->nonWorkingDates($scope, $range);

        // --- Availability: project rota slots onto hour buckets. ---
        // Same allocation filter as availableMinutesByDoctor — orphan
        // rotas at un-allocated branches would otherwise inflate the grid.
        $rotaRows = DB::table('resource_has_rota_days as rhrd')
            ->join('resource_has_rota as rhr', 'rhr.id', '=', 'rhrd.resource_has_rota_id')
            ->join('resources as r', 'r.id', '=', 'rhr.resource_id')
            ->joinSub(
                $this->allowedUserLocationSub($scope, $range),
                'alloc',
                fn ($j) => $j
                    ->on('alloc.user_id', '=', 'r.external_id')
                    ->on('alloc.location_id', '=', 'rhr.location_id'),
            )
            ->whereIn('r.external_id', $doctorIds)
            ->where('r.account_id', $scope->accountId)
            ->whereBetween('rhrd.date', [$range->startString(), $range->endString()])
            ->where('rhrd.active', 1)
            ->where('rhr.active', 1)
            ->whereNull('rhrd.deleted_at')
            ->whereNull('rhr.deleted_at')
            ->whereNotNull('rhrd.start_time')
            ->whereNotNull('rhrd.end_time')
            ->when(
                $scope->isBranchScoped() && $scope->branchIds !== null,
                fn ($q) => $q->whereIn('rhr.location_id', $scope->branchIds),
            )
            ->select('r.external_id as doctor_id', 'rhr.location_id', 'rhrd.date', 'rhrd.start_time', 'rhrd.end_time')
            ->get();

        // PTO subtraction by (doctor, date).
        $pto = $this->ptoRowsByDoctorDate($doctorIds, $scope, $range);

        // Grid: [dow][hour] => [available_minutes, busy_minutes]
        $grid = [];
        for ($dow = 0; $dow < 7; $dow++) {
            for ($h = 0; $h < 24; $h++) {
                $grid[$dow][$h] = ['a' => 0, 'b' => 0];
            }
        }

        // First pass: collect raw intervals per (doctor, date) so we can
        // merge multi-branch overlaps before projecting onto the grid.
        // Without merging, a doctor rostered at two branches simultaneously
        // (common for multi-branch aestheticians) would credit the same
        // minute to the heatmap twice.
        $rawByDoctorDate = [];
        foreach ($rotaRows as $row) {
            $branchId = (int) $row->location_id;
            $date = (string) $row->date;

            if (isset($closedByBranchDate[$branchId][$date])
                || isset($closedByBranchDate[0][$date])
                || isset($nonWorkingDates[$date])) {
                continue;
            }

            $interval = $this->parseShiftInterval((string) $row->start_time, (string) $row->end_time);
            if ($interval === null) {
                continue;
            }

            $doctorId = (int) $row->doctor_id;
            $rawByDoctorDate[$doctorId][$date][] = $interval;
        }

        foreach ($rawByDoctorDate as $doctorId => $days) {
            foreach ($days as $date => $slots) {
                $merged = $this->mergeIntervals($slots);
                $dow = $this->dayOfWeek($date);
                $ptoSpans = $pto[$doctorId][$date] ?? [];

                foreach ($merged as $slot) {
                    $this->projectIntoGrid($grid, $dow, $slot['s'], $slot['e'], 'a', $ptoSpans);
                }
            }
        }

        // --- Busy: project arrived appointments onto hour buckets. ---
        $apptQuery = DB::table('appointments as a')
            ->join('appointment_statuses as ast', 'ast.id', '=', 'a.appointment_status_id')
            ->join('services as s', 's.id', '=', 'a.service_id')
            ->whereIn('a.doctor_id', $doctorIds)
            ->where('a.account_id', $scope->accountId)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->where('ast.is_arrived', 1)
            ->whereNull('a.deleted_at')
            ->whereNotNull('a.scheduled_time')
            ->whereNotNull('s.duration')
            ->when(
                $scope->isBranchScoped() && $scope->branchIds !== null,
                fn ($q) => $q->whereIn('a.location_id', $scope->branchIds),
            );
        $this->restrictToAllocatedAppointments($apptQuery, $scope, $range);
        $apptRows = $apptQuery
            ->selectRaw(
                'a.scheduled_date as d, a.scheduled_time as t, '.
                '(CAST(SUBSTRING_INDEX(s.duration, ":", 1) AS UNSIGNED) * 60) + '.
                'CAST(SUBSTRING_INDEX(s.duration, ":", -1) AS UNSIGNED) as m',
            )
            ->get();

        foreach ($apptRows as $row) {
            $start = $this->parseClock((string) $row->t);
            $minutes = (int) $row->m;
            if ($start === null || $minutes <= 0) {
                continue;
            }
            $end = $start + ($minutes * 60);
            $dow = $this->dayOfWeek((string) $row->d);

            $this->projectIntoGrid($grid, $dow, $start, $end, 'b', []);
        }

        // --- Flatten + compute util per cell. ---
        $cells = [];
        $totalAvail = 0;
        $totalBusy = 0;
        for ($dow = 0; $dow < 7; $dow++) {
            for ($h = 0; $h < 24; $h++) {
                $a = $grid[$dow][$h]['a'];
                $b = $grid[$dow][$h]['b'];
                $cells[] = [
                    'dow' => $dow,
                    'hour' => $h,
                    'available_minutes' => (int) $a,
                    'busy_minutes' => (int) $b,
                    'util_pct' => $a > 0 ? round(($b / $a) * 100, 1) : null,
                ];
                $totalAvail += $a;
                $totalBusy += $b;
            }
        }

        return [
            'cells' => $cells,
            'totals' => [
                'available_minutes' => (int) $totalAvail,
                'busy_minutes' => (int) $totalBusy,
                'util_pct' => $totalAvail > 0 ? round(($totalBusy / $totalAvail) * 100, 1) : null,
            ],
            'window' => [
                'from' => $range->startString(),
                'to' => $range->endString(),
                'days' => $range->days(),
            ],
        ];
    }

    /**
     * Project a [start, end] time range in seconds-of-day onto the 24
     * hourly buckets of a given day-of-week in the grid, crediting the
     * specified bucket key ('a' for available, 'b' for busy). PTO spans
     * (if any) are subtracted minute-by-minute from the credited total
     * so partial-day time-off is honoured correctly in the heatmap.
     *
     * @param  array<int, array<int, array{a:int, b:int}>>  $grid  passed by reference
     * @param  list<array{start:int, end:int}>  $ptoSpans  seconds-of-day windows to exclude
     */
    private function projectIntoGrid(array &$grid, int $dow, int $start, int $end, string $key, array $ptoSpans): void
    {
        // Iterate hourly buckets, crediting the overlap to the grid cell,
        // minus any overlap with PTO spans (only relevant when $key='a').
        for ($h = 0; $h < 24; $h++) {
            $bucketStart = $h * 3600;
            $bucketEnd = $bucketStart + 3600;
            $overlap = max(0, min($end, $bucketEnd) - max($start, $bucketStart));
            if ($overlap === 0) {
                continue;
            }

            if ($ptoSpans !== []) {
                foreach ($ptoSpans as $pto) {
                    $minusStart = max($pto['start'], $bucketStart, $start);
                    $minusEnd = min($pto['end'], $bucketEnd, $end);
                    if ($minusEnd > $minusStart) {
                        $overlap -= ($minusEnd - $minusStart);
                    }
                }
                if ($overlap <= 0) {
                    continue;
                }
            }

            // Store minutes (overlap is in seconds; convert).
            $grid[$dow][$h][$key] += (int) round($overlap / 60);
        }
    }

    /**
     * PTO rows indexed by (doctor_id → date → list of span dicts with
     * seconds-of-day start/end). Full-day PTO renders as a single span
     * covering the whole day.
     *
     * @param  list<int>  $doctorIds
     * @return array<int, array<string, list<array{start:int, end:int}>>>
     */
    private function ptoRowsByDoctorDate(array $doctorIds, MetricScope $scope, DateRange $range): array
    {
        $rows = $this->ptoRows($doctorIds, $scope, $range);
        $out = [];

        foreach ($rows as $row) {
            $doctorId = (int) $row->doctor_id;
            $date = (string) $row->date;

            if ($row->is_full_day) {
                $out[$doctorId][$date][] = ['start' => 0, 'end' => 86400];

                continue;
            }

            $s = $this->parseClock((string) $row->start_time);
            $e = $this->parseClock((string) $row->end_time);
            if ($s === null || $e === null || $e <= $s) {
                continue;
            }

            $out[$doctorId][$date][] = ['start' => $s, 'end' => $e];
        }

        return $out;
    }

    /**
     * ISO day-of-week: 0=Monday … 6=Sunday.
     */
    private function dayOfWeek(string $date): int
    {
        $dow = (int) CarbonImmutable::parse($date)->dayOfWeekIso;

        return $dow - 1; // 1..7 → 0..6
    }

    /**
     * @return list<array{dow:int, hour:int, available_minutes:int, busy_minutes:int, util_pct:float|null}>
     */
    private function emptyHeatmapCells(): array
    {
        $cells = [];
        for ($dow = 0; $dow < 7; $dow++) {
            for ($h = 0; $h < 24; $h++) {
                $cells[] = [
                    'dow' => $dow,
                    'hour' => $h,
                    'available_minutes' => 0,
                    'busy_minutes' => 0,
                    'util_pct' => null,
                ];
            }
        }

        return $cells;
    }
}
