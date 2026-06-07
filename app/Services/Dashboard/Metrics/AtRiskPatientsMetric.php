<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * At-Risk Patients — actionable callback queue driven by three
 * independent slippage signals. A patient is "at-risk" if ANY of the
 * signals fire as of today.
 *
 *   1. Cadence Break
 *      ≥2 arrived treatments in the trailing 180-day baseline window,
 *      and max(1.5 × median_gap, 30) < days_since_last_visit ≤ 90.
 *      The 30-day floor prevents false positives on tightly-spaced
 *      sequences (consult → treatment within a week); the 90-day ceiling
 *      keeps it to "recently slipping" patients, not dormant ones.
 *
 *   2. Abandoned Package
 *      Active package + unused sessions + no future booking + last
 *      visit ≥ 30 days. Patient may have zero recent treatments — they
 *      qualify on the unused-money signal alone. Each abandoned-package
 *      patient gets a Priority tag:
 *        High   — remaining ≥ PKR 1,000 AND last_visit 30–60 days
 *        Medium — remaining ≥ PKR 1,000 AND last_visit 60–90 days
 *        Low    — remaining < PKR 1,000 OR  last_visit > 90 days
 *
 *   3. Broken Commitment
 *      No-show or cancellation AFTER the patient's last successful
 *      visit, within the last 90 days, AND no future booking. A patient
 *      who already ghosted on a booking and hasn't re-booked is more
 *      at-risk than one who simply went silent.
 *
 * Exclusions:
 *   - 0 visits in last 180 days AND no abandoned package → Dormant
 *     pool (separate re-engagement campaign, not surfaced here).
 *   - Single-visit-only patients (lifetime visits = 1) → first-visit
 *     retention pool, separate funnel.
 *   - last_visit < 30 days AND no other signal → still on schedule.
 *
 * Patients hitting multiple signals appear once, classified by their
 * highest-priority signal in this order:
 *   abandoned_package(high) > cadence_break > broken_commitment >
 *   abandoned_package(medium) > abandoned_package(low)
 * Other firing signals are listed in `signals[]` for the UI to badge.
 *
 * Sort within Cadence Break is by absolute days overdue (`days_since_last
 * - threshold`) desc — a monthly customer 60d overdue is more recoverable
 * than a quarterly one 60d overdue.
 *
 * Two public surfaces:
 *   summary($scope) — pool-level Recoverable Revenue tile.
 *   overview($scope) / list($scope, $branchId, ...) — branch + patient
 *     drill-down for the dashboard panel and modal.
 *
 * Value tiers are percentile-based on trailing-12mo spend computed once
 * per query against the in-scope patient pool. Reuses the canonical
 * package_advances cash-flow filter from RevenueConcentrationMetric.
 *
 * Recoverable-revenue valuation uses package_services.actual_price (the
 * discount-aware per-row price set at sale time) rather than naive
 * pro-rata, so a discounted-bundle scenario values correctly.
 */
final class AtRiskPatientsMetric
{
    private const CACHE_TTL = 300;

    /** Trailing window used to score cadence and define candidate pool. */
    private const BASELINE_WINDOW_DAYS = 180;

    /** Cadence-break: trigger floor (no flag fires before 30d since last visit). */
    private const CADENCE_FLOOR_DAYS = 30;

    /** Cadence-break: multiplier on personal median gap. */
    private const CADENCE_MULTIPLIER = 1.5;

    /**
     * Cadence-break: upper bound. Beyond this many days since the last visit
     * the patient is dormant, not "recently slipping" — so the cadence signal
     * stops firing (money signals like abandoned-package still catch them).
     */
    private const CADENCE_MAX_DAYS = 90;

    /** Abandoned package: minimum days since last visit before flagging. */
    private const ABANDONED_MIN_DAYS = 30;

    /** Abandoned package: priority value cutoff (PKR). */
    private const ABANDONED_VALUE_CUTOFF = 1000.0;

    /** Abandoned package: high-priority recency boundary (last visit days ≤ this). */
    private const ABANDONED_HIGH_DAYS_END = 60;

    /** Abandoned package: medium-priority recency boundary. */
    private const ABANDONED_MEDIUM_DAYS_END = 90;

    /** Package created-date cutoff — only consider packages from last 365d. */
    private const PACKAGE_RECENCY_DAYS = 365;

    /** Value-tier window — trailing 12mo spend. */
    private const VALUE_WINDOW_DAYS = 365;

    /** Broken-commitment lookback. */
    private const BROKEN_COMMITMENT_WINDOW_DAYS = 90;

    /** Status ids: 3 = "Didn't show up", 4 = "Cancelled". */
    private const NO_SHOW_STATUS_ID = 3;

    private const CANCELLED_STATUS_ID = 4;

    /**
     * Future appointment statuses that do NOT mean "rebooked / on track".
     * A future-dated row in one of these states (the patient cancelled or
     * no-showed their next visit) must not suppress the at-risk signal.
     */
    private const FUTURE_DEAD_STATUS_IDS = [self::NO_SHOW_STATUS_ID, self::CANCELLED_STATUS_ID];

    public function __construct(
        private readonly BranchResolver $branches,
    ) {}

    /**
     * Pool-level recoverable revenue across all branches in scope.
     * Drives the headline tile on the Client Retention Rate panel.
     *
     * @return array{
     *   total_recoverable: float,
     *   prepaid_unused: float,
     *   unbilled_commitment: float,
     *   abandoned_patient_count: int,
     *   abandoned_package_count: int,
     * }
     */
    public function summary(MetricScope $scope): array
    {
        $cacheKey = 'mgmt_dash:at_risk_summary:v2:'.$scope->cacheKey();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->buildSummary($scope));
    }

    /**
     * Two-lens overview that powers the inline At-Risk panel next to the
     * Client Retention Rate panel. One round trip returns:
     *   - branches  : per-branch rollup sorted by recoverable_value desc
     *   - top_patients_by_recoverable : top N patients across all branches
     *   - top_patients_by_spend       : top N patients by trailing-12mo spend
     *
     * Both patient lists carry branch_id/branch_name so the inline panel
     * can route a row click to the existing AtRiskPatientsModal.
     *
     * @return array{
     *   branches: list<array{
     *     branch_id: int,
     *     branch_name: string|null,
     *     at_risk_count: int,
     *     recoverable_value: float,
     *     prepaid_unused: float,
     *     unbilled_commitment: float,
     *     cadence_break_count: int,
     *     abandoned_package_count: int,
     *     broken_commitment_count: int,
     *     priority_high_count: int,
     *     priority_medium_count: int,
     *     priority_low_count: int,
     *   }>,
     *   top_patients_by_recoverable: list<array<string, mixed>>,
     *   top_patients_by_spend: list<array<string, mixed>>,
     *   pool_total: int,
     *   pool_recoverable: float,
     * }
     */
    public function overview(MetricScope $scope, int $patientLimit = 25): array
    {
        $cacheKey = 'mgmt_dash:at_risk_overview:v2:'
            .$scope->cacheKey()
            .'|p'.$patientLimit;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->buildOverview($scope, $patientLimit),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverview(MetricScope $scope, int $patientLimit): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return [
                'branches' => [],
                'top_patients_by_recoverable' => [],
                'top_patients_by_spend' => [],
                'pool_total' => 0,
                'pool_recoverable' => 0.0,
            ];
        }

        $branchNames = $this->branches->names($branchIds);

        // Pre-fetch the slow shared queries across ALL branches in one
        // shot. Modal/list path keeps the per-branch queries — fine for
        // a single-click drill-down.
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        $today = now()->toDateString();

        if ($treatmentStatusIds === []) {
            $bulkAbandoned = $bulkCohort = [];
        } else {
            // Abandoned packages first — feed into candidate-pool union.
            $bulkAbandoned = $this->abandonedPackagesByBranchPatient(
                $scope,
                array_map('intval', $branchIds),
            );
            // Candidate pool = recent-treatment ∪ abandoned-package, minus
            // future-bookings. Composed against the bulk-abandoned map.
            $bulkCohort = $this->candidatePoolByBranch(
                $scope,
                array_map('intval', $branchIds),
                $treatmentStatusIds,
                $bulkAbandoned,
                $today,
            );
        }

        // Union of every candidate patient across branches — trailing
        // spend + profile lookups are branch-agnostic.
        $allPatientIds = [];
        $patientIdsByBranch = [];
        foreach ($bulkCohort as $b => $branchPatients) {
            foreach ($branchPatients as $pid => $_) {
                $allPatientIds[$pid] = true;
                $patientIdsByBranch[(int) $b][] = (int) $pid;
            }
        }
        $allPatientIds = array_keys($allPatientIds);
        $bulkSpend = $allPatientIds !== [] ? $this->trailingSpend($scope, $allPatientIds) : [];
        $bulkProfiles = $allPatientIds !== [] ? $this->patientProfiles($allPatientIds) : [];

        // Pre-fetch the heavy per-branch queries in bulk — visit stats,
        // lifetime counts, broken commitments. Each is one DB roundtrip
        // covering every (branch, candidate-patient) pair, then sliced
        // per-branch into buildBranchPoolBody. Cuts the per-branch loop
        // from ~3 queries × N branches to a constant 3 queries.
        $bulkBaselineStats = $treatmentStatusIds !== [] && $allPatientIds !== []
            ? $this->visitStatsInWindowByBranch(
                $scope,
                array_map('intval', $branchIds),
                $treatmentStatusIds,
                $patientIdsByBranch,
                now()->subDays(self::BASELINE_WINDOW_DAYS)->toDateString(),
                $today,
            )
            : [];
        $bulkLifetimeCounts = $treatmentStatusIds !== [] && $allPatientIds !== []
            ? $this->lifetimeVisitCountsByBranch(
                $scope,
                array_map('intval', $branchIds),
                $treatmentStatusIds,
                $patientIdsByBranch,
                $today,
            )
            : [];
        $bulkBroken = $allPatientIds !== []
            ? $this->brokenCommitmentsByPatientBulk(
                $scope,
                array_map('intval', $branchIds),
                $bulkCohort,
                $today,
            )
            : [];

        // Doctor names — collect every last-visit doctor across all
        // branches in one users lookup. Matches the existing pattern for
        // patient profiles + spend.
        $allDoctorIds = [];
        foreach ($bulkCohort as $branchPatients) {
            foreach ($branchPatients as $info) {
                $did = $info['last_doctor_id'] ?? null;
                if ($did !== null) {
                    $allDoctorIds[$did] = true;
                }
            }
        }
        $bulkDoctorNames = $allDoctorIds === []
            ? []
            : DB::table('users')
                ->whereIn('id', array_keys($allDoctorIds))
                ->pluck('name', 'id')
                ->toArray();

        $branches = [];
        $allPatients = [];
        foreach ($branchIds as $branchId) {
            // Read pool from cache; on miss, build directly with the
            // pre-fetched abandoned slice and write into the same key
            // so modal's branchPool() reads back from this cache entry.
            $cacheKey = $this->branchPoolCacheKey($scope, (int) $branchId);
            $pool = Cache::get($cacheKey);
            if (! is_array($pool)) {
                $pool = $treatmentStatusIds === []
                    ? ['branch_name' => $branchNames[$branchId] ?? null, 'rows' => [], 'signal_counts' => []]
                    : $this->buildBranchPoolBody(
                        $scope,
                        (int) $branchId,
                        $treatmentStatusIds,
                        $bulkAbandoned[(int) $branchId] ?? [],
                        $bulkCohort[(int) $branchId] ?? [],
                        $bulkSpend,
                        $bulkProfiles,
                        $bulkBaselineStats[(int) $branchId] ?? [],
                        $bulkLifetimeCounts[(int) $branchId] ?? [],
                        $bulkBroken[(int) $branchId] ?? [],
                        $bulkDoctorNames,
                    );
                Cache::put($cacheKey, $pool, self::CACHE_TTL);
            }
            $rows = $pool['rows'] ?? [];
            $branchName = $pool['branch_name'] ?? ($branchNames[$branchId] ?? null);

            $recoverable = 0.0;
            $prepaid = 0.0;
            $unbilled = 0.0;
            foreach ($rows as $r) {
                $recoverable += (float) ($r['recoverable_value'] ?? 0);
                $prepaid += (float) ($r['prepaid_unused'] ?? 0);
                $unbilled += (float) ($r['unbilled_commitment'] ?? 0);

                // Tag each patient with branch context for the cross-branch
                // patient lenses, then collect into the global pool.
                $r['branch_id'] = (int) $branchId;
                $r['branch_name'] = $branchName;
                $allPatients[] = $r;
            }

            $signalCounts = $pool['signal_counts'] ?? [];
            $branches[] = [
                'branch_id' => (int) $branchId,
                'branch_name' => $branchName,
                'at_risk_count' => count($rows),
                'recoverable_value' => round($recoverable, 2),
                'prepaid_unused' => round($prepaid, 2),
                'unbilled_commitment' => round($unbilled, 2),
                'cadence_break_count' => (int) ($signalCounts['cadence_break'] ?? 0),
                'abandoned_package_count' => (int) ($signalCounts['abandoned_package'] ?? 0),
                'broken_commitment_count' => (int) ($signalCounts['broken_commitment'] ?? 0),
                'priority_high_count' => (int) ($signalCounts['priority_high'] ?? 0),
                'priority_medium_count' => (int) ($signalCounts['priority_medium'] ?? 0),
                'priority_low_count' => (int) ($signalCounts['priority_low'] ?? 0),
            ];
        }

        // Branches: sort by recoverable_value desc — biggest dollar opportunity
        // first. Ties broken by at_risk_count desc.
        usort($branches, static function (array $a, array $b): int {
            $cmp = ($b['recoverable_value'] <=> $a['recoverable_value']);

            return $cmp !== 0 ? $cmp : ($b['at_risk_count'] <=> $a['at_risk_count']);
        });

        // Top patients — two sorts, two slices.
        $byRecoverable = $this->sortPatients($allPatients, 'recoverable_value');
        $bySpend = $this->sortPatients($allPatients, 'trailing_12mo_spend');

        $poolRecoverable = array_sum(array_column($branches, 'recoverable_value'));
        $poolTotal = array_sum(array_column($branches, 'at_risk_count'));

        return [
            'branches' => array_values($branches),
            'top_patients_by_recoverable' => array_slice($byRecoverable, 0, $patientLimit),
            'top_patients_by_spend' => array_slice($bySpend, 0, $patientLimit),
            'pool_total' => (int) $poolTotal,
            'pool_recoverable' => round((float) $poolRecoverable, 2),
        ];
    }

    /**
     * Rank patients by a numeric field desc, with non-abandoned patients
     * (no recoverable inventory) falling to bottom of the recoverable sort.
     *
     * @param  list<array<string, mixed>>  $patients
     * @return list<array<string, mixed>>
     */
    private function sortPatients(array $patients, string $sortKey): array
    {
        $copy = $patients;
        usort($copy, static function (array $a, array $b) use ($sortKey): int {
            $av = (float) ($a[$sortKey] ?? 0);
            $bv = (float) ($b[$sortKey] ?? 0);
            if ($av === $bv) {
                // Stable secondary: abandoned ahead of others when ranking
                // by recoverable, since non-abandoned rows have $0 there.
                $aw = ($a['primary_signal'] ?? '') === 'abandoned_package' ? 1 : 0;
                $bw = ($b['primary_signal'] ?? '') === 'abandoned_package' ? 1 : 0;

                return $bw <=> $aw;
            }

            return $bv <=> $av;
        });

        return array_values($copy);
    }

    /**
     * @return array{
     *   total_recoverable: float,
     *   prepaid_unused: float,
     *   unbilled_commitment: float,
     *   abandoned_patient_count: int,
     *   abandoned_package_count: int,
     * }
     */
    private function buildSummary(MetricScope $scope): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return $this->emptySummary();
        }

        // Abandoned packages = active, not refunded, recent, with unused sessions.
        // We compute consumed_value, unused_value, and net paid in one pass per
        // package, then aggregate up.
        $rows = $this->abandonedPackageValuationQuery($scope, $branchIds, /* patientIds */ null)
            ->get();

        if ($rows->isEmpty()) {
            return $this->emptySummary();
        }

        $totalUnused = 0.0;
        $totalPrepaidUnused = 0.0;
        $totalUnbilled = 0.0;
        $patientIds = [];

        foreach ($rows as $r) {
            $unused = (float) $r->unused_value;
            $consumed = (float) $r->consumed_value;
            $paid = (float) $r->paid;

            // Cap prepaid_unused at unused_value — patients can over-pay a
            // package (deposits that exceed list price land as credit on
            // file, not as inventory we owe). Beyond unused_value it isn't
            // "recoverable" against this package, so excluding it keeps
            // the headline tied to actual service-delivery liability.
            $prepaidUnused = min($unused, max(0.0, $paid - $consumed));
            $unbilled = max(0.0, $unused - $prepaidUnused);

            $totalUnused += $unused;
            $totalPrepaidUnused += $prepaidUnused;
            $totalUnbilled += $unbilled;
            $patientIds[(int) $r->patient_id] = true;
        }

        return [
            'total_recoverable' => round($totalUnused, 2),
            'prepaid_unused' => round($totalPrepaidUnused, 2),
            'unbilled_commitment' => round($totalUnbilled, 2),
            'abandoned_patient_count' => count($patientIds),
            'abandoned_package_count' => $rows->count(),
        ];
    }

    /**
     * @return array{total_recoverable: float, prepaid_unused: float, unbilled_commitment: float, abandoned_patient_count: int, abandoned_package_count: int}
     */
    private function emptySummary(): array
    {
        return [
            'total_recoverable' => 0.0,
            'prepaid_unused' => 0.0,
            'unbilled_commitment' => 0.0,
            'abandoned_patient_count' => 0,
            'abandoned_package_count' => 0,
        ];
    }

    /**
     * At-risk patient IDs grouped by branch. Reads the same per-branch
     * cache the dashboard panel uses (5-min TTL), so warm-path cost is
     * one cache hit per branch. Used by lists outside the dashboard
     * (Plans datatable etc.) that need to mark rows whose patient sits
     * in the at-risk pool — i.e. ANY of the three slippage signals fires
     * (cadence break / abandoned package / broken commitment).
     *
     * Branches that aren't in the caller's scope are silently dropped;
     * the caller doesn't need to pre-filter against allowedBranchIds.
     *
     * @param  list<int>  $branchIds  Branches to fetch — only those also in scope are returned.
     * @return array<int, list<int>>  branch_id => [patient_id, ...] (only branches with ≥1 at-risk patient)
     */
    public function patientIdsByBranch(MetricScope $scope, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [];
        }

        $allowed = array_map('intval', $this->branches->idsInScope($scope));
        $result = [];
        foreach ($branchIds as $branchId) {
            $bid = (int) $branchId;
            if (! in_array($bid, $allowed, true)) {
                continue;
            }
            $pool = $this->branchPool($scope, $bid);
            $rows = $pool['rows'] ?? [];
            if ($rows === []) {
                continue;
            }
            $result[$bid] = array_values(array_map(
                static fn (array $r): int => (int) $r['patient_id'],
                $rows,
            ));
        }

        return $result;
    }

    /**
     * At-risk pool aggregated by owning doctor across the user's accessible
     * branches. Drives the management Practitioner-tab "at-risk per doctor"
     * column + drawer breakdown. Reuses each branch's already-cached pool —
     * does not re-run heavy queries — so warm path is N cache reads + a
     * group-by, where N = branches in scope.
     *
     * Each row sums recoverable / prepaid / signal counts across the doctor's
     * patients regardless of which branch they were treated at. The whole
     * result is itself cached for 5 minutes per scope so callers don't pay
     * the per-branch fanout again.
     *
     * @return list<array{
     *   doctor_id: int,
     *   doctor_name: string,
     *   at_risk_count: int,
     *   recoverable_value: float,
     *   prepaid_unused: float,
     *   signal_breakdown: array{cadence_break:int, abandoned_package:int, broken_commitment:int}
     * }>
     */
    public function patientCountsByDoctor(MetricScope $scope): array
    {
        $cacheKey = 'mgmt_dash:at_risk_by_doctor:v1:'.$scope->cacheKey();

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->buildPatientCountsByDoctor($scope),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPatientCountsByDoctor(MetricScope $scope): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return [];
        }

        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [];
        }

        // doctor_id => running totals
        $byDoctor = [];

        foreach ($branchIds as $branchId) {
            $pool = $this->branchPool($scope, (int) $branchId);
            $rows = $pool['rows'] ?? [];
            if ($rows === []) {
                continue;
            }

            foreach ($rows as $r) {
                $doctorId = $r['last_doctor_id'] ?? null;
                if ($doctorId === null) {
                    continue;
                }
                $doctorId = (int) $doctorId;

                if (! isset($byDoctor[$doctorId])) {
                    $byDoctor[$doctorId] = [
                        'doctor_id' => $doctorId,
                        'doctor_name' => (string) ($r['last_doctor_name'] ?? "User #{$doctorId}"),
                        'at_risk_count' => 0,
                        'recoverable_value' => 0.0,
                        'prepaid_unused' => 0.0,
                        'signal_breakdown' => [
                            'cadence_break' => 0,
                            'abandoned_package' => 0,
                            'broken_commitment' => 0,
                        ],
                    ];
                }

                $byDoctor[$doctorId]['at_risk_count']++;
                $byDoctor[$doctorId]['recoverable_value'] += (float) ($r['recoverable_value'] ?? 0);
                $byDoctor[$doctorId]['prepaid_unused'] += (float) ($r['prepaid_unused'] ?? 0);

                $signals = $r['signals'] ?? [];
                if (is_array($signals)) {
                    foreach ($signals as $sig) {
                        if (isset($byDoctor[$doctorId]['signal_breakdown'][$sig])) {
                            $byDoctor[$doctorId]['signal_breakdown'][$sig]++;
                        }
                    }
                }
            }
        }

        // Backfill doctor_name where the cached pool has the doctor_id but no
        // name (defensive — pools normally include the name).
        $missingNames = [];
        foreach ($byDoctor as $id => $row) {
            if ($row['doctor_name'] === "User #{$id}") {
                $missingNames[] = $id;
            }
        }
        if ($missingNames !== []) {
            $names = DB::table('users')
                ->whereIn('id', $missingNames)
                ->pluck('name', 'id')
                ->toArray();
            foreach ($missingNames as $id) {
                if (isset($names[$id])) {
                    $byDoctor[$id]['doctor_name'] = (string) $names[$id];
                }
            }
        }

        // Round currency values to 2dp and sort by at_risk_count desc for
        // a stable, predictable list.
        $out = array_values($byDoctor);
        foreach ($out as &$row) {
            $row['recoverable_value'] = round((float) $row['recoverable_value'], 2);
            $row['prepaid_unused'] = round((float) $row['prepaid_unused'], 2);
        }
        unset($row);

        usort($out, static function (array $a, array $b): int {
            $cmp = $b['at_risk_count'] <=> $a['at_risk_count'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return $b['recoverable_value'] <=> $a['recoverable_value'];
        });

        return $out;
    }

    /**
     * At-risk patient list for a single branch. Patients are evaluated
     * against the three slippage signals (cadence break, abandoned
     * package, broken commitment) and ranked by the priority order.
     *
     * @param  list<string>|null  $signals     Filter on primary_signal — null = all
     * @param  list<string>|null  $valueTiers  Filter — null = all four tiers
     * @return array{
     *   branch_id: int,
     *   branch_name: string|null,
     *   rows: list<array<string, mixed>>,
     *   total_count: int,
     *   signal_counts: array<string, int>,
     * }
     */
    public function list(
        MetricScope $scope,
        int $branchId,
        ?array $signals,
        ?array $valueTiers,
        int $limit = 50,
        int $offset = 0,
    ): array {
        // Cached unfiltered superset — heavy work happens once per
        // (scope, branch) and is reused across filter combos and the
        // overview rollup.
        $pool = $this->branchPool($scope, $branchId);
        $rows = $pool['rows'];

        if ($signals !== null && $signals !== []) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => in_array($r['primary_signal'], $signals, true),
            ));
        }
        if ($valueTiers !== null && $valueTiers !== []) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => in_array($r['value_tier'], $valueTiers, true),
            ));
        }

        $totalCount = count($rows);
        $page = array_slice($rows, $offset, $limit);

        return [
            'branch_id' => $branchId,
            'branch_name' => $pool['branch_name'],
            'rows' => $page,
            'total_count' => $totalCount,
            'signal_counts' => $pool['signal_counts'],
        ];
    }

    /**
     * Cached per-branch unfiltered superset. Returns every classified
     * at-risk patient at the branch (sorted) plus bucket counts. Public
     * `list()` and the overview rollup both read from here so the heavy
     * cohort/abandoned/spend/broken-commitment queries run once per
     * (scope, branch) per 5 minutes.
     *
     * @return array{
     *   branch_name: string|null,
     *   rows: list<array<string, mixed>>,
     *   signal_counts: array<string, int>,
     * }
     */
    private function branchPool(MetricScope $scope, int $branchId): array
    {
        $cacheKey = 'mgmt_dash:at_risk_pool:v2:'.$scope->cacheKey().'|b'.$branchId;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->buildBranchPool($scope, $branchId),
        );
    }

    /**
     * Cache key for a branch pool — exposed so the overview path can
     * write directly into the same cache slot after a bulk build.
     */
    private function branchPoolCacheKey(MetricScope $scope, int $branchId): string
    {
        return 'mgmt_dash:at_risk_pool:v2:'.$scope->cacheKey().'|b'.$branchId;
    }

    /**
     * Check whether the at-risk pool is already cached for ALL of the
     * supplied branches. Latency-sensitive callers (patient list, patient
     * detail header) read this before composing `patientIdsByBranch` —
     * a cold materialisation is the heaviest path in this metric (measured
     * ~0.3s for one branch, ~2.7s across all ~20 branches locally; higher on
     * prod-scale data), enough to noticeably block a latency-sensitive list.
     * Returning `false` lets them gracefully skip the
     * at-risk enrichment for that request and rely on the warm-up
     * command (or the dashboard) to repopulate the cache.
     *
     * Returning `true` guarantees `patientIdsByBranch` for the same
     * scope+branches will hit the cache (sub-millisecond).
     */
    public function isPoolWarm(MetricScope $scope, array $branchIds): bool
    {
        if ($branchIds === []) {
            return true;
        }
        foreach ($branchIds as $bid) {
            if (! Cache::has($this->branchPoolCacheKey($scope, (int) $bid))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *   branch_name: string|null,
     *   rows: list<array<string, mixed>>,
     *   signal_counts: array<string, int>,
     * }
     */
    private function buildBranchPool(MetricScope $scope, int $branchId): array
    {
        // Validate the requested branch is in scope. Don't silently drop.
        $allowed = $this->branches->idsInScope($scope);
        if (! in_array($branchId, $allowed, true)) {
            return [
                'branch_name' => null,
                'rows' => [],
                'signal_counts' => [],
            ];
        }

        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [
                'branch_name' => $this->branches->names([$branchId])[$branchId] ?? null,
                'rows' => [],
                'signal_counts' => [],
            ];
        }

        return $this->buildBranchPoolBody($scope, $branchId, $treatmentStatusIds, null);
    }

    /**
     * @param  list<int>  $treatmentStatusIds
     * @return array{
     *   branch_name: string|null,
     *   rows: list<array<string, mixed>>,
     *   signal_counts: array<string, int>,
     * }
     */
    /**
     * @param  array<int, float>|null  $prefetchedSpend
     * @param  array<int, array{name: string, phone: string|null}>|null  $prefetchedProfiles
     */
    private function buildBranchPoolBody(
        MetricScope $scope,
        int $branchId,
        array $treatmentStatusIds,
        ?array $prefetchedAbandoned,
        ?array $prefetchedCohort = null,
        ?array $prefetchedSpend = null,
        ?array $prefetchedProfiles = null,
        ?array $prefetchedBaselineStats = null,
        ?array $prefetchedLifetimeCounts = null,
        ?array $prefetchedBroken = null,
        ?array $prefetchedDoctorNames = null,
    ): array {
        $today = now()->toDateString();

        // 1. Per-patient abandoned packages at this branch. Drives both
        //    the candidate-pool union (so package-only patients are
        //    included even with no recent visits) and the recoverable
        //    valuation per row.
        $abandonedByPatient = $prefetchedAbandoned !== null
            ? $prefetchedAbandoned
            : $this->abandonedPackagesAtBranch($scope, $branchId);

        $abandonedPatientIds = array_map('intval', array_keys($abandonedByPatient));

        // Packages that have been refunded (any cash_flow=out / is_refund=1
        // advance) are a closing deal — they must not contribute collectable
        // (owed) or prepaid-at-risk money. Looked up once for the whole branch.
        $allAbandonedPackageIds = [];
        foreach ($abandonedByPatient as $packages) {
            foreach ($packages as $pkg) {
                $allAbandonedPackageIds[(int) $pkg['package_id']] = true;
            }
        }
        $refundedPackageIds = array_flip(
            $this->refundedPackageIds(array_keys($allAbandonedPackageIds)),
        );

        // 2. Candidate pool = recent-treatment patients ∪ abandoned-package
        //    patients, minus anyone with a future booking at the branch.
        $candidates = $prefetchedCohort !== null
            ? $prefetchedCohort
            : $this->candidatePoolAtBranch(
                $scope,
                $branchId,
                $treatmentStatusIds,
                $abandonedPatientIds,
                $today,
            );

        if ($candidates === []) {
            return [
                'branch_name' => $this->branches->names([$branchId])[$branchId] ?? null,
                'rows' => [],
                'signal_counts' => [
                    'cadence_break' => 0,
                    'abandoned_package' => 0,
                    'broken_commitment' => 0,
                    'priority_high' => 0,
                    'priority_medium' => 0,
                    'priority_low' => 0,
                ],
            ];
        }

        $patientIds = array_map('intval', array_keys($candidates));

        // 3. Per-patient visit stats — count + median gap, restricted to
        //    the baseline window (180d). Lifetime count fetched separately
        //    for the single-visit exclusion check.
        $baselineStats = $prefetchedBaselineStats !== null
            ? array_intersect_key($prefetchedBaselineStats, array_flip($patientIds))
            : $this->visitStatsInWindow(
                $scope,
                $branchId,
                $treatmentStatusIds,
                $patientIds,
                now()->subDays(self::BASELINE_WINDOW_DAYS)->toDateString(),
                $today,
            );
        $lifetimeCounts = $prefetchedLifetimeCounts !== null
            ? array_intersect_key($prefetchedLifetimeCounts, array_flip($patientIds))
            : $this->lifetimeVisitCounts(
                $scope,
                $branchId,
                $treatmentStatusIds,
                $patientIds,
                $today,
            );

        // 4. Trailing 12mo spend (for value tier).
        $spendByPatient = $prefetchedSpend !== null
            ? array_intersect_key($prefetchedSpend, array_flip($patientIds))
            : $this->trailingSpend($scope, $patientIds);

        // 5. Broken commitments AFTER last successful visit, last 90 days.
        $brokenByPatient = $prefetchedBroken !== null
            ? array_intersect_key($prefetchedBroken, array_flip($patientIds))
            : $this->brokenCommitmentsByPatient(
                $scope,
                $branchId,
                $patientIds,
                $candidates,
                $today,
            );

        // 6. Profiles + doctor names for last-visit doctor.
        $profiles = $prefetchedProfiles !== null
            ? array_intersect_key($prefetchedProfiles, array_flip($patientIds))
            : $this->patientProfiles($patientIds);
        if ($prefetchedDoctorNames !== null) {
            $doctorNames = $prefetchedDoctorNames;
        } else {
            $doctorIds = array_unique(array_filter(array_map(
                static fn (array $c): ?int => $c['last_doctor_id'] ?? null,
                $candidates,
            )));
            $doctorNames = $doctorIds === []
                ? []
                : DB::table('users')
                    ->whereIn('id', $doctorIds)
                    ->pluck('name', 'id')
                    ->toArray();
        }

        // 7. Value-tier thresholds from the spend distribution.
        $tierThresholds = $this->valueTierThresholds(array_values($spendByPatient));

        // 8. Evaluate signals per candidate; assemble at-risk rows.
        $rows = [];
        $signalCounts = [
            'cadence_break' => 0,
            'abandoned_package' => 0,
            'broken_commitment' => 0,
            'priority_high' => 0,
            'priority_medium' => 0,
            'priority_low' => 0,
        ];

        foreach ($candidates as $patientId => $info) {
            $bs = $baselineStats[$patientId] ?? ['visit_count' => 0, 'median_gap_days' => null];
            $abandoned = $abandonedByPatient[$patientId] ?? [];
            $broken = $brokenByPatient[$patientId] ?? null;
            $lifetime = (int) ($lifetimeCounts[$patientId] ?? 0);

            $eval = $this->evaluateSignals(
                $abandoned,
                (int) $bs['visit_count'],
                $lifetime,
                $bs['median_gap_days'],
                $info['last_visit_date'],
                $broken,
                $today,
            );

            if ($eval === null) {
                continue;
            }

            $tier = $this->valueTierFor((float) ($spendByPatient[$patientId] ?? 0), $tierThresholds);

            // Aggregate package money into two honest buckets:
            //   collectable    = balance the patient still OWES on undelivered
            //                    sessions (money we can bring in)
            //   prepaid-at-risk= cash already paid for undelivered sessions
            //                    (refund exposure, NOT recoverable income)
            // Refunded packages are skipped entirely — a closed deal owes us
            // nothing and carries no prepaid exposure.
            $prepaidSum = 0.0;
            $collectableSum = 0.0;
            foreach ($abandoned as $pkg) {
                if (isset($refundedPackageIds[(int) $pkg['package_id']])) {
                    continue;
                }
                $prepaidUnused = min($pkg['unused_value'], max(0.0, $pkg['paid'] - $pkg['consumed_value']));
                $prepaidSum += $prepaidUnused;
                $collectableSum += max(0.0, $pkg['unused_value'] - $prepaidUnused);
            }

            // Tally counters. A patient with multiple firing signals
            // increments every signal counter (so badges add up); the
            // priority counter only fires for abandoned-package patients.
            foreach ($eval['signals'] as $sig) {
                $signalCounts[$sig]++;
            }
            if ($eval['priority'] !== null) {
                $signalCounts['priority_'.$eval['priority']]++;
            }

            $rows[] = [
                'patient_id' => $patientId,
                'patient_name' => $profiles[$patientId]['name'] ?? "Patient #{$patientId}",
                'phone' => $profiles[$patientId]['phone'] ?? null,
                'last_visit_date' => $info['last_visit_date'],
                'last_doctor_id' => $info['last_doctor_id'] ?? null,
                'last_doctor_name' => isset($info['last_doctor_id']) && $info['last_doctor_id'] !== null
                    ? ($doctorNames[$info['last_doctor_id']] ?? null)
                    : null,
                'signals' => $eval['signals'],
                'primary_signal' => $eval['primary_signal'],
                'priority' => $eval['priority'],
                'days_since_last' => $eval['days_since_last'],
                'median_gap_days' => $eval['median_gap_days'] !== null
                    ? round($eval['median_gap_days'], 1)
                    : null,
                'threshold_days' => $eval['threshold_days'],
                'days_overdue' => $eval['days_overdue'],
                'overdue_multiplier' => $eval['overdue_multiplier'],
                'value_tier' => $tier,
                'trailing_12mo_spend' => round((float) ($spendByPatient[$patientId] ?? 0), 2),
                // recoverable_value now means COLLECTABLE (owed); kept under
                // the same key for back-compat + branch sort. unbilled_commitment
                // mirrors it (both = the outstanding balance).
                'recoverable_value' => round($collectableSum, 2),
                'prepaid_unused' => round($prepaidSum, 2),
                'unbilled_commitment' => round($collectableSum, 2),
                'abandoned_package_count' => count($abandoned),
                'lifetime_visits' => $lifetime,
                'visits_in_baseline' => (int) $bs['visit_count'],
                'no_show_since' => $broken['no_show_count'] ?? 0,
                'cancelled_since' => $broken['cancelled_count'] ?? 0,
                'last_broken_date' => $broken['last_broken_date'] ?? null,
            ];
        }

        // Sort per the locked spec:
        //   1. Abandoned + High priority
        //   2. Cadence Break (most days_overdue first)
        //   3. Broken Commitment
        //   4. Abandoned + Medium priority
        //   5. Abandoned + Low priority
        // Within each tier, value-tier (VIP first) breaks ties, then
        // recoverable value desc, then trailing spend desc.
        $tierRank = ['vip' => 0, 'high' => 1, 'mid' => 2, 'low' => 3];
        $sortBucket = static function (array $r): int {
            if ($r['primary_signal'] === 'abandoned_package' && $r['priority'] === 'high') return 0;
            if ($r['primary_signal'] === 'cadence_break') return 1;
            if ($r['primary_signal'] === 'broken_commitment') return 2;
            if ($r['primary_signal'] === 'abandoned_package' && $r['priority'] === 'medium') return 3;
            if ($r['primary_signal'] === 'abandoned_package' && $r['priority'] === 'low') return 4;
            return 9;
        };
        usort($rows, static function (array $a, array $b) use ($sortBucket, $tierRank): int {
            $ab = $sortBucket($a);
            $bb = $sortBucket($b);
            if ($ab !== $bb) return $ab <=> $bb;
            // Within Cadence Break, sort by absolute days overdue desc.
            if ($a['primary_signal'] === 'cadence_break') {
                $cmp = ($b['days_overdue'] ?? 0) <=> ($a['days_overdue'] ?? 0);
                if ($cmp !== 0) return $cmp;
            }
            // Otherwise: value-tier rank, then recoverable, then spend.
            $ta = $tierRank[$a['value_tier']] ?? 9;
            $tb = $tierRank[$b['value_tier']] ?? 9;
            if ($ta !== $tb) return $ta <=> $tb;
            $cmp = ((float) $b['recoverable_value']) <=> ((float) $a['recoverable_value']);
            if ($cmp !== 0) return $cmp;
            return ((float) $b['trailing_12mo_spend']) <=> ((float) $a['trailing_12mo_spend']);
        });

        return [
            'branch_name' => $this->branches->names([$branchId])[$branchId] ?? null,
            'rows' => $rows,
            'signal_counts' => $signalCounts,
        ];
    }

    /**
     * Candidate pool at a branch — patients who could plausibly fire any
     * at-risk signal. Defined as the union of:
     *   (a) ≥1 arrived treatment in the last BASELINE_WINDOW_DAYS, OR
     *   (b) ≥1 abandoned-package patient at the branch, OR
     *   (c) ≥1 no-show/cancel in the broken-commitment window AFTER their
     *       last successful visit at this branch.
     * Excluding any patient with a future booking at the branch.
     *
     * Returns latest arrived-treatment date (all-time, not window-bounded)
     * and the doctor seen on that visit. last_visit_date may be null in
     * the rare case the candidate has zero arrived treatments at the
     * branch ever (entered the pool only via an abandoned package owned
     * elsewhere — defensive case).
     *
     * @param  list<int>  $treatmentStatusIds
     * @param  list<int>  $abandonedPatientIds  patients owning ≥1 abandoned package at this branch
     * @return array<int, array{last_visit_date: string|null, last_doctor_id: int|null}>
     */
    private function candidatePoolAtBranch(
        MetricScope $scope,
        int $branchId,
        array $treatmentStatusIds,
        array $abandonedPatientIds,
        string $today,
    ): array {
        $baselineCutoff = now()->subDays(self::BASELINE_WINDOW_DAYS)->toDateString();
        $brokenCutoff = now()->subDays(self::BROKEN_COMMITMENT_WINDOW_DAYS)->toDateString();

        // 1. Patients with ≥1 arrived treatment in baseline window.
        $recentRows = DB::table('appointments as a1')
            ->where('a1.account_id', $scope->accountId)
            ->where('a1.location_id', $branchId)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->where('a1.scheduled_date', '>=', $baselineCutoff)
            ->where('a1.scheduled_date', '<=', $today)
            ->whereNull('a1.deleted_at')
            ->whereNotExists(function ($sub) use ($branchId, $today): void {
                // Future booking → on track, drop.
                $sub->select(DB::raw(1))
                    ->from('appointments as af')
                    ->whereColumn('af.patient_id', 'a1.patient_id')
                    ->where('af.location_id', $branchId)
                    ->where('af.scheduled_date', '>', $today)
                    ->whereNotIn('af.appointment_status_id', self::FUTURE_DEAD_STATUS_IDS)
                    ->whereNull('af.deleted_at');
            })
            ->groupBy('a1.patient_id')
            ->select('a1.patient_id')
            ->get();

        $candidateIds = $recentRows->pluck('patient_id')->map(static fn ($v): int => (int) $v)->all();

        // 2. Union with abandoned-package patients (filter to those without
        //    a future booking at this branch).
        if ($abandonedPatientIds !== []) {
            $abandonedWithFuture = DB::table('appointments')
                ->where('account_id', $scope->accountId)
                ->where('location_id', $branchId)
                ->whereIn('patient_id', $abandonedPatientIds)
                ->where('scheduled_date', '>', $today)
                ->whereNotIn('appointment_status_id', self::FUTURE_DEAD_STATUS_IDS)
                ->whereNull('deleted_at')
                ->pluck('patient_id')
                ->map(static fn ($v): int => (int) $v)
                ->unique()
                ->all();

            $abandonedKeep = array_diff($abandonedPatientIds, $abandonedWithFuture);
            $candidateIds = array_values(array_unique(array_merge($candidateIds, $abandonedKeep)));
        }

        // 3. Union with broken-commitment-only patients — no-show/cancel
        //    after their last successful visit, within window, no future
        //    booking. Catches patients whose last actual visit was >180d
        //    ago but who recently broke a booking.
        $brokenIds = DB::table('appointments as bc')
            ->where('bc.account_id', $scope->accountId)
            ->where('bc.location_id', $branchId)
            ->where('bc.appointment_type_id', 2)
            ->whereIn('bc.appointment_status_id', [self::NO_SHOW_STATUS_ID, self::CANCELLED_STATUS_ID])
            ->where('bc.scheduled_date', '>=', $brokenCutoff)
            ->where('bc.scheduled_date', '<=', $today)
            ->whereNull('bc.deleted_at')
            ->whereNotExists(function ($sub) use ($branchId, $today): void {
                $sub->select(DB::raw(1))->from('appointments as af')
                    ->whereColumn('af.patient_id', 'bc.patient_id')
                    ->where('af.location_id', $branchId)
                    ->where('af.scheduled_date', '>', $today)
                    ->whereNotIn('af.appointment_status_id', self::FUTURE_DEAD_STATUS_IDS)
                    ->whereNull('af.deleted_at');
            })
            ->whereExists(function ($sub) use ($branchId, $treatmentStatusIds): void {
                // Must have ≥1 successful arrived visit BEFORE the broken
                // commitment — broken-commitment requires "after last
                // successful visit" semantics.
                $sub->select(DB::raw(1))->from('appointments as lv')
                    ->whereColumn('lv.patient_id', 'bc.patient_id')
                    ->where('lv.location_id', $branchId)
                    ->where('lv.appointment_type_id', 2)
                    ->whereIn('lv.appointment_status_id', $treatmentStatusIds)
                    ->whereColumn('lv.scheduled_date', '<', 'bc.scheduled_date')
                    ->whereNull('lv.deleted_at');
            })
            ->groupBy('bc.patient_id')
            ->select('bc.patient_id')
            ->pluck('patient_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();
        if ($brokenIds !== []) {
            $candidateIds = array_values(array_unique(array_merge($candidateIds, $brokenIds)));
        }

        // Drop deactivated / soft-deleted patient records — the at-risk pool
        // is an actionable contact list, not a historical record.
        $candidateIds = $this->activePatientIds($candidateIds);

        if ($candidateIds === []) {
            return [];
        }

        // 3. For every candidate, fetch latest arrived-treatment date +
        //    doctor (all-time at this branch — abandoned-package candidates
        //    may have a last visit older than the baseline window).
        $latestRows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->where('location_id', $branchId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $candidateIds)
            ->where('scheduled_date', '<=', $today)
            ->whereNull('deleted_at')
            ->select('patient_id', 'doctor_id', 'scheduled_date', 'id')
            ->orderBy('patient_id')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        $latestByPatient = [];
        foreach ($latestRows as $r) {
            $pid = (int) $r->patient_id;
            if (! isset($latestByPatient[$pid])) {
                $latestByPatient[$pid] = [
                    'last_visit_date' => (string) $r->scheduled_date,
                    'last_doctor_id' => $r->doctor_id !== null ? (int) $r->doctor_id : null,
                ];
            }
        }

        $out = [];
        foreach ($candidateIds as $pid) {
            $out[$pid] = $latestByPatient[$pid] ?? [
                'last_visit_date' => null,
                'last_doctor_id' => null,
            ];
        }

        return $out;
    }

    /**
     * Bulk variant — runs the candidate-pool query once across every
     * branch in scope. Returns `branch_id -> patient_id -> {last_visit_date,
     * last_doctor_id}` map. Used by the overview path.
     *
     * @param  list<int>  $branchIds
     * @param  list<int>  $treatmentStatusIds
     * @param  array<int, array<int, mixed>>  $abandonedByBranch  branch_id -> patient_id -> packages[]
     * @return array<int, array<int, array{last_visit_date: string|null, last_doctor_id: int|null}>>
     */
    private function candidatePoolByBranch(
        MetricScope $scope,
        array $branchIds,
        array $treatmentStatusIds,
        array $abandonedByBranch,
        string $today,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        $baselineCutoff = now()->subDays(self::BASELINE_WINDOW_DAYS)->toDateString();
        $brokenCutoff = now()->subDays(self::BROKEN_COMMITMENT_WINDOW_DAYS)->toDateString();

        // 1. (branch, patient) pairs with ≥1 arrived treatment in baseline window.
        $recentRows = DB::table('appointments as a1')
            ->where('a1.account_id', $scope->accountId)
            ->whereIn('a1.location_id', $branchIds)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->where('a1.scheduled_date', '>=', $baselineCutoff)
            ->where('a1.scheduled_date', '<=', $today)
            ->whereNull('a1.deleted_at')
            ->whereNotExists(function ($sub) use ($today): void {
                $sub->select(DB::raw(1))
                    ->from('appointments as af')
                    ->whereColumn('af.patient_id', 'a1.patient_id')
                    ->whereColumn('af.location_id', 'a1.location_id')
                    ->where('af.scheduled_date', '>', $today)
                    ->whereNotIn('af.appointment_status_id', self::FUTURE_DEAD_STATUS_IDS)
                    ->whereNull('af.deleted_at');
            })
            ->groupBy('a1.location_id', 'a1.patient_id')
            ->select('a1.location_id', 'a1.patient_id')
            ->get();

        $candidateMap = []; // branch_id -> patient_id -> true
        foreach ($recentRows as $r) {
            $b = (int) $r->location_id;
            $p = (int) $r->patient_id;
            $candidateMap[$b][$p] = true;
        }

        // 2. Union abandoned-package patients per branch (filter out those
        //    with future bookings at the same branch).
        $allAbandonedPairs = []; // for the future-booking lookup
        foreach ($abandonedByBranch as $b => $patients) {
            foreach ($patients as $p => $_) {
                $allAbandonedPairs[] = [(int) $b, (int) $p];
            }
        }

        if ($allAbandonedPairs !== []) {
            $patientIds = array_values(array_unique(array_map(static fn ($p) => $p[1], $allAbandonedPairs)));
            $futureRows = DB::table('appointments')
                ->where('account_id', $scope->accountId)
                ->whereIn('location_id', $branchIds)
                ->whereIn('patient_id', $patientIds)
                ->where('scheduled_date', '>', $today)
                ->whereNotIn('appointment_status_id', self::FUTURE_DEAD_STATUS_IDS)
                ->whereNull('deleted_at')
                ->select('location_id', 'patient_id')
                ->get();
            $futureSet = []; // branch_id -> patient_id -> true
            foreach ($futureRows as $f) {
                $futureSet[(int) $f->location_id][(int) $f->patient_id] = true;
            }

            foreach ($allAbandonedPairs as [$b, $p]) {
                if (isset($futureSet[$b][$p])) {
                    continue;
                }
                $candidateMap[$b][$p] = true;
            }
        }

        // 3. Union broken-commitment-only patients per branch.
        $brokenRows = DB::table('appointments as bc')
            ->where('bc.account_id', $scope->accountId)
            ->whereIn('bc.location_id', $branchIds)
            ->where('bc.appointment_type_id', 2)
            ->whereIn('bc.appointment_status_id', [self::NO_SHOW_STATUS_ID, self::CANCELLED_STATUS_ID])
            ->where('bc.scheduled_date', '>=', $brokenCutoff)
            ->where('bc.scheduled_date', '<=', $today)
            ->whereNull('bc.deleted_at')
            ->whereNotExists(function ($sub) use ($today): void {
                $sub->select(DB::raw(1))->from('appointments as af')
                    ->whereColumn('af.patient_id', 'bc.patient_id')
                    ->whereColumn('af.location_id', 'bc.location_id')
                    ->where('af.scheduled_date', '>', $today)
                    ->whereNotIn('af.appointment_status_id', self::FUTURE_DEAD_STATUS_IDS)
                    ->whereNull('af.deleted_at');
            })
            ->whereExists(function ($sub) use ($treatmentStatusIds): void {
                $sub->select(DB::raw(1))->from('appointments as lv')
                    ->whereColumn('lv.patient_id', 'bc.patient_id')
                    ->whereColumn('lv.location_id', 'bc.location_id')
                    ->where('lv.appointment_type_id', 2)
                    ->whereIn('lv.appointment_status_id', $treatmentStatusIds)
                    ->whereColumn('lv.scheduled_date', '<', 'bc.scheduled_date')
                    ->whereNull('lv.deleted_at');
            })
            ->groupBy('bc.location_id', 'bc.patient_id')
            ->select('bc.location_id', 'bc.patient_id')
            ->get();
        foreach ($brokenRows as $r) {
            $b = (int) $r->location_id;
            $p = (int) $r->patient_id;
            $candidateMap[$b][$p] = true;
        }

        // Drop deactivated / soft-deleted patient records across all branches.
        $candidateMap = $this->retainActivePatients($candidateMap);

        if ($candidateMap === []) {
            return [];
        }

        // 3. Latest arrived-treatment date + doctor per (branch, patient)
        //    across all-time (so abandoned-package candidates with old
        //    last visits still get a date).
        $allCandidatePatientIds = [];
        foreach ($candidateMap as $patients) {
            foreach ($patients as $pid => $_) {
                $allCandidatePatientIds[$pid] = true;
            }
        }
        $allCandidatePatientIds = array_keys($allCandidatePatientIds);

        $latestRows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $allCandidatePatientIds)
            ->where('scheduled_date', '<=', $today)
            ->whereNull('deleted_at')
            ->select('location_id', 'patient_id', 'doctor_id', 'scheduled_date', 'id')
            ->orderBy('location_id')
            ->orderBy('patient_id')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        $latestMap = []; // branch_id -> patient_id -> {date, doctor_id}
        foreach ($latestRows as $r) {
            $b = (int) $r->location_id;
            $p = (int) $r->patient_id;
            if (! isset($latestMap[$b][$p])) {
                $latestMap[$b][$p] = [
                    'last_visit_date' => (string) $r->scheduled_date,
                    'last_doctor_id' => $r->doctor_id !== null ? (int) $r->doctor_id : null,
                ];
            }
        }

        $out = [];
        foreach ($candidateMap as $b => $patients) {
            foreach ($patients as $p => $_) {
                $out[$b][$p] = $latestMap[$b][$p] ?? [
                    'last_visit_date' => null,
                    'last_doctor_id' => null,
                ];
            }
        }

        return $out;
    }

    /**
     * Per-patient arrived-treatment count + median inter-visit gap within
     * the supplied date window. The median gap is the cadence baseline
     * for cadence-break detection; null when the patient has fewer than
     * 2 visits in the window (no gap exists).
     *
     * @param  list<int>  $treatmentStatusIds
     * @param  list<int>  $patientIds
     * @return array<int, array{visit_count: int, median_gap_days: float|null}>
     */
    private function visitStatsInWindow(
        MetricScope $scope,
        int $branchId,
        array $treatmentStatusIds,
        array $patientIds,
        string $windowStart,
        string $windowEnd,
    ): array {
        if ($patientIds === []) {
            return [];
        }

        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->where('location_id', $branchId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $patientIds)
            ->where('scheduled_date', '>=', $windowStart)
            ->where('scheduled_date', '<=', $windowEnd)
            ->whereNull('deleted_at')
            ->select('patient_id', 'scheduled_date')
            ->orderBy('patient_id')
            ->orderBy('scheduled_date')
            ->get();

        $byPatient = [];
        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            $byPatient[$pid][] = (string) $r->scheduled_date;
        }

        $out = [];
        foreach ($byPatient as $pid => $dates) {
            $count = count($dates);
            $median = null;
            if ($count >= 2) {
                $gaps = [];
                for ($i = 1; $i < $count; $i++) {
                    $gaps[] = (strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400;
                }
                sort($gaps);
                $g = count($gaps);
                $median = $g % 2 === 0
                    ? ($gaps[$g / 2 - 1] + $gaps[$g / 2]) / 2.0
                    : (float) $gaps[(int) floor($g / 2)];
            }
            $out[$pid] = ['visit_count' => $count, 'median_gap_days' => $median];
        }

        return $out;
    }

    /**
     * Bulk visit-stats query — runs visitStatsInWindow logic across every
     * (branch, candidate-patient) pair in scope in a single DB roundtrip.
     * Returns `branch_id -> patient_id -> {visit_count, median_gap_days}`.
     *
     * @param  list<int>  $branchIds
     * @param  list<int>  $treatmentStatusIds
     * @param  array<int, list<int>>  $patientIdsByBranch  branch_id -> [patient_ids]
     * @return array<int, array<int, array{visit_count: int, median_gap_days: float|null}>>
     */
    private function visitStatsInWindowByBranch(
        MetricScope $scope,
        array $branchIds,
        array $treatmentStatusIds,
        array $patientIdsByBranch,
        string $windowStart,
        string $windowEnd,
    ): array {
        $allPatientIds = [];
        foreach ($patientIdsByBranch as $ids) {
            foreach ($ids as $pid) {
                $allPatientIds[$pid] = true;
            }
        }
        $allPatientIds = array_keys($allPatientIds);
        if ($allPatientIds === [] || $branchIds === []) {
            return [];
        }

        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $allPatientIds)
            ->where('scheduled_date', '>=', $windowStart)
            ->where('scheduled_date', '<=', $windowEnd)
            ->whereNull('deleted_at')
            ->select('location_id', 'patient_id', 'scheduled_date')
            ->orderBy('location_id')
            ->orderBy('patient_id')
            ->orderBy('scheduled_date')
            ->get();

        // Group dates by (branch, patient).
        $byBranchPatient = [];
        foreach ($rows as $r) {
            $byBranchPatient[(int) $r->location_id][(int) $r->patient_id][] = (string) $r->scheduled_date;
        }

        $out = [];
        foreach ($byBranchPatient as $branchId => $patients) {
            foreach ($patients as $pid => $dates) {
                $count = count($dates);
                $median = null;
                if ($count >= 2) {
                    $gaps = [];
                    for ($i = 1; $i < $count; $i++) {
                        $gaps[] = (strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400;
                    }
                    sort($gaps);
                    $g = count($gaps);
                    $median = $g % 2 === 0
                        ? ($gaps[$g / 2 - 1] + $gaps[$g / 2]) / 2.0
                        : (float) $gaps[(int) floor($g / 2)];
                }
                $out[$branchId][$pid] = ['visit_count' => $count, 'median_gap_days' => $median];
            }
        }

        return $out;
    }

    /**
     * Bulk variant of lifetimeVisitCounts — `branch_id -> patient_id -> count`.
     *
     * @param  list<int>  $branchIds
     * @param  list<int>  $treatmentStatusIds
     * @param  array<int, list<int>>  $patientIdsByBranch
     * @return array<int, array<int, int>>
     */
    private function lifetimeVisitCountsByBranch(
        MetricScope $scope,
        array $branchIds,
        array $treatmentStatusIds,
        array $patientIdsByBranch,
        string $today,
    ): array {
        $allPatientIds = [];
        foreach ($patientIdsByBranch as $ids) {
            foreach ($ids as $pid) {
                $allPatientIds[$pid] = true;
            }
        }
        $allPatientIds = array_keys($allPatientIds);
        if ($allPatientIds === [] || $branchIds === []) {
            return [];
        }

        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $allPatientIds)
            ->where('scheduled_date', '<=', $today)
            ->whereNull('deleted_at')
            ->groupBy('location_id', 'patient_id')
            ->select('location_id', 'patient_id')
            ->selectRaw('COUNT(*) AS visit_count')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->location_id][(int) $r->patient_id] = (int) $r->visit_count;
        }

        return $out;
    }

    /**
     * Bulk broken-commitment query across every (branch, candidate-patient)
     * pair. Same "AFTER last successful visit, last 90 days" semantics as
     * the per-branch version, but slices the work into one DB roundtrip.
     *
     * @param  list<int>  $branchIds
     * @param  array<int, array<int, array{last_visit_date: string|null, last_doctor_id: int|null}>>  $candidatesByBranch
     * @return array<int, array<int, array{no_show_count: int, cancelled_count: int, last_broken_date: string}>>
     */
    private function brokenCommitmentsByPatientBulk(
        MetricScope $scope,
        array $branchIds,
        array $candidatesByBranch,
        string $today,
    ): array {
        $allPatientIds = [];
        foreach ($candidatesByBranch as $patients) {
            foreach ($patients as $pid => $_) {
                $allPatientIds[$pid] = true;
            }
        }
        $allPatientIds = array_keys($allPatientIds);
        if ($allPatientIds === [] || $branchIds === []) {
            return [];
        }

        $lookback = now()->subDays(self::BROKEN_COMMITMENT_WINDOW_DAYS)->toDateString();

        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->where('appointment_type_id', 2)
            ->whereIn('patient_id', $allPatientIds)
            ->whereIn('appointment_status_id', [self::NO_SHOW_STATUS_ID, self::CANCELLED_STATUS_ID])
            ->where('scheduled_date', '<=', $today)
            ->where('scheduled_date', '>=', $lookback)
            ->whereNull('deleted_at')
            ->select('location_id', 'patient_id', 'scheduled_date', 'appointment_status_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $bid = (int) $r->location_id;
            $pid = (int) $r->patient_id;
            $lastVisit = $candidatesByBranch[$bid][$pid]['last_visit_date'] ?? null;
            if ($lastVisit === null || $r->scheduled_date <= $lastVisit) {
                continue;
            }
            $out[$bid][$pid] ??= [
                'no_show_count' => 0,
                'cancelled_count' => 0,
                'last_broken_date' => '',
            ];
            if ((int) $r->appointment_status_id === self::NO_SHOW_STATUS_ID) {
                $out[$bid][$pid]['no_show_count']++;
            } else {
                $out[$bid][$pid]['cancelled_count']++;
            }
            $date = (string) $r->scheduled_date;
            if ($date > $out[$bid][$pid]['last_broken_date']) {
                $out[$bid][$pid]['last_broken_date'] = $date;
            }
        }

        return $out;
    }

    /**
     * Lifetime arrived-treatment count per patient at the branch — used
     * exclusively for the single-visit exclusion gate. Cheaper than
     * loading all visit dates because we only need COUNT(*).
     *
     * @param  list<int>  $treatmentStatusIds
     * @param  list<int>  $patientIds
     * @return array<int, int>  patient_id -> count
     */
    private function lifetimeVisitCounts(
        MetricScope $scope,
        int $branchId,
        array $treatmentStatusIds,
        array $patientIds,
        string $today,
    ): array {
        if ($patientIds === []) {
            return [];
        }

        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->where('location_id', $branchId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $patientIds)
            ->where('scheduled_date', '<=', $today)
            ->whereNull('deleted_at')
            ->groupBy('patient_id')
            ->select('patient_id')
            ->selectRaw('COUNT(*) AS visit_count')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->patient_id] = (int) $r->visit_count;
        }

        return $out;
    }

    /**
     * All abandoned-package valuations at a branch, grouped by patient.
     * Used by the per-branch (modal) path where we need the abandoned
     * set BEFORE the candidate pool is composed. Bulk path uses
     * abandonedPackagesByBranchPatient directly.
     *
     * @return array<int, list<array{package_id: int, unused_value: float, consumed_value: float, paid: float, remaining_sessions: int}>>
     */
    private function abandonedPackagesAtBranch(MetricScope $scope, int $branchId): array
    {
        $rows = $this->abandonedPackageValuationQuery($scope, [$branchId], /* patientIds */ null)->get();

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            $out[$pid][] = [
                'package_id' => (int) $r->package_id,
                'unused_value' => (float) $r->unused_value,
                'consumed_value' => (float) $r->consumed_value,
                'paid' => (float) $r->paid,
                'remaining_sessions' => (int) $r->remaining_sessions,
            ];
        }

        return $out;
    }

    /**
     * Abandoned packages grouped by patient. Each value is a list of
     * package valuations (unused_value, consumed_value, paid). A patient
     * may own more than one abandoned package — they all aggregate.
     *
     * @param  list<int>  $patientIds
     * @return array<int, list<array{package_id: int, unused_value: float, consumed_value: float, paid: float, remaining_sessions: int}>>
     */
    private function abandonedPackagesByPatient(MetricScope $scope, int $branchId, array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        $rows = $this->abandonedPackageValuationQuery($scope, [$branchId], $patientIds)->get();

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            $out[$pid][] = [
                'package_id' => (int) $r->package_id,
                'unused_value' => (float) $r->unused_value,
                'consumed_value' => (float) $r->consumed_value,
                'paid' => (float) $r->paid,
                'remaining_sessions' => (int) $r->remaining_sessions,
            ];
        }

        return $out;
    }

    /**
     * Bulk variant — one query covers every branch in scope. Returns a
     * `branch_id -> patient_id -> packages[]` map so the per-branch
     * builder can look up its slice without hitting the DB. Used by
     * the overview path; modal path stays on the per-branch query
     * (single 1.7s query is fine for one click).
     *
     * @param  list<int>  $branchIds
     * @return array<int, array<int, list<array<string, mixed>>>>
     */
    private function abandonedPackagesByBranchPatient(MetricScope $scope, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        $cutoff = now()->subDays(self::PACKAGE_RECENCY_DAYS)->toDateString();

        // Step 1 — pull candidate package metadata once, indexed lookup
        // via idx_packages_account_location_active. This is cheap.
        $candidates = DB::table('packages')
            ->select('id', 'patient_id', 'location_id')
            ->where('account_id', $scope->accountId)
            ->whereIn('location_id', $branchIds)
            ->where('active', 1)
            ->where('is_refund', 0)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', $cutoff)
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $packageIds = $candidates->pluck('id')->map(static fn ($v): int => (int) $v)->all();

        // Step 2 — two aggregations in parallel, both filtered to the
        // candidate package IDs. Indexed by package_id, so each scan is
        // bounded by the candidate set instead of the full table.
        $svcRows = $this->servicesAggregateForPackages($packageIds);
        $paidRows = $this->paidAggregateForPackages($packageIds);

        $out = [];
        foreach ($candidates as $p) {
            $svc = $svcRows[(int) $p->id] ?? null;
            if (! $svc || $svc['remaining_sessions'] <= 0) {
                continue;
            }
            $paid = $paidRows[(int) $p->id] ?? 0.0;
            $bid = (int) $p->location_id;
            $pid = (int) $p->patient_id;
            $out[$bid][$pid][] = [
                'package_id' => (int) $p->id,
                'unused_value' => $svc['unused_value'],
                'consumed_value' => $svc['consumed_value'],
                'paid' => $paid,
                'remaining_sessions' => $svc['remaining_sessions'],
            ];
        }

        return $out;
    }

    /**
     * Per-package consumed/unused/remaining aggregates for a bounded set
     * of package IDs. Uses MariaDB's IN-list — fine up to a few thousand
     * IDs; chunks if larger.
     *
     * @param  list<int>  $packageIds
     * @return array<int, array{consumed_value: float, unused_value: float, remaining_sessions: int}>
     */
    private function servicesAggregateForPackages(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }

        $priceExpr = '
            CASE
                WHEN price > 0 THEN price
                WHEN tax_including_price > 0 THEN tax_including_price
                WHEN actual_price > 0 THEN actual_price
                WHEN orignal_price > 0 THEN orignal_price
                ELSE 0
            END
        ';

        $out = [];
        foreach (array_chunk($packageIds, 5000) as $chunk) {
            $rows = DB::table('package_services')
                ->select('package_id')
                ->selectRaw("SUM(CASE WHEN is_consumed = 1 THEN {$priceExpr} ELSE 0 END) AS consumed_value")
                ->selectRaw("SUM(CASE WHEN is_consumed = 0 THEN {$priceExpr} ELSE 0 END) AS unused_value")
                ->selectRaw('SUM(CASE WHEN is_consumed = 0 THEN 1 ELSE 0 END) AS remaining_sessions')
                ->whereIn('package_id', $chunk)
                ->groupBy('package_id')
                ->get();

            foreach ($rows as $r) {
                $out[(int) $r->package_id] = [
                    'consumed_value' => (float) $r->consumed_value,
                    'unused_value' => (float) $r->unused_value,
                    'remaining_sessions' => (int) $r->remaining_sessions,
                ];
            }
        }

        return $out;
    }

    /**
     * Per-package paid amount for a bounded set of package IDs. Same
     * canonical ledger filter as RevenueLedgerQuery.
     *
     * @param  list<int>  $packageIds
     * @return array<int, float>
     */
    private function paidAggregateForPackages(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($packageIds, 5000) as $chunk) {
            $rows = DB::table('package_advances')
                ->select('package_id')
                ->selectRaw(
                    "SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE -cash_amount END) AS paid"
                )
                ->where('cash_amount', '>', 0)
                ->where(function ($q): void {
                    $q->where(function ($in): void {
                        $in->where('cash_flow', 'in')
                            ->where('is_adjustment', 0)
                            ->where('is_tax', 0)
                            ->where('is_cancel', 0);
                    })->orWhere(function ($refund): void {
                        $refund->where('cash_flow', 'out')
                            ->where('is_refund', 1);
                    });
                })
                ->whereIn('package_id', $chunk)
                ->groupBy('package_id')
                ->get();

            foreach ($rows as $r) {
                $out[(int) $r->package_id] = (float) $r->paid;
            }
        }

        return $out;
    }

    /**
     * Package ids that have had any refund (a `cash_flow = 'out'`,
     * `is_refund = 1` advance). Such a package is a closing deal and is
     * excluded from the at-risk pool's collectable / prepaid valuation.
     *
     * @param  list<int>  $packageIds
     * @return list<int>
     */
    private function refundedPackageIds(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($packageIds, 5000) as $chunk) {
            $ids = DB::table('package_advances')
                ->where('cash_flow', 'out')
                ->where('is_refund', 1)
                ->where('cash_amount', '>', 0)
                ->whereIn('package_id', $chunk)
                ->distinct()
                ->pluck('package_id');
            foreach ($ids as $id) {
                $out[] = (int) $id;
            }
        }

        return $out;
    }

    /**
     * Per-package valuation query for abandoned packages. Active, not
     * refunded, recent (created within 365 days), with at least one
     * unused service. Optionally scoped to a patient subset.
     *
     * Joined sub-aggregates rather than correlated subqueries so the
     * planner can use grouped indexes on package_services.package_id and
     * package_advances.package_id.
     *
     * @param  list<int>       $branchIds
     * @param  list<int>|null  $patientIds  null = all patients in scope
     */
    private function abandonedPackageValuationQuery(MetricScope $scope, array $branchIds, ?array $patientIds): Builder
    {
        $cutoff = now()->subDays(self::PACKAGE_RECENCY_DAYS)->toDateString();

        // Per-package service totals — count + value, split by consumption.
        //
        // Counter-intuitive column semantics in this schema (verified on
        // package 44440 vs the receipt UI):
        //   `price`               = customer-paid total per session
        //                           (post-discount, tax-included). This
        //                           is the receipt's TOTAL column.
        //   `tax_including_price` = same value as `price` (redundant copy).
        //   `actual_price`        = REGULAR / list price (pre-discount),
        //                           NOT the actual paid amount despite
        //                           the name.
        //   `orignal_price`       = also list price.
        //
        // We want what the patient actually paid (or owes) — that's
        // `price`. Fall back through tax_including_price → actual_price
        // → orignal_price for defence against data gaps; the chain ends
        // at zero so a totally-blank row contributes nothing rather than
        // poisoning the aggregate.
        $priceExpr = '
            CASE
                WHEN price > 0 THEN price
                WHEN tax_including_price > 0 THEN tax_including_price
                WHEN actual_price > 0 THEN actual_price
                WHEN orignal_price > 0 THEN orignal_price
                ELSE 0
            END
        ';
        $services = DB::table('package_services')
            ->select('package_id')
            ->selectRaw("SUM(CASE WHEN is_consumed = 1 THEN {$priceExpr} ELSE 0 END) AS consumed_value")
            ->selectRaw("SUM(CASE WHEN is_consumed = 0 THEN {$priceExpr} ELSE 0 END) AS unused_value")
            ->selectRaw('SUM(CASE WHEN is_consumed = 0 THEN 1 ELSE 0 END) AS remaining_sessions')
            ->groupBy('package_id');

        // Per-package paid amount — same standard ledger filter every other
        // metric uses (RevenueConcentrationMetric.php lines 73–82).
        $paid = DB::table('package_advances')
            ->select('package_id')
            ->selectRaw("
                SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE -cash_amount END) AS paid
            ")
            ->where('cash_amount', '>', 0)
            ->where(function ($q): void {
                $q->where(function ($in): void {
                    $in->where('cash_flow', 'in')
                        ->where('is_adjustment', 0)
                        ->where('is_tax', 0)
                        ->where('is_cancel', 0);
                })->orWhere(function ($out): void {
                    $out->where('cash_flow', 'out')
                        ->where('is_refund', 1);
                });
            })
            ->groupBy('package_id');

        $q = DB::table('packages as p')
            ->joinSub($services, 'svc', 'svc.package_id', '=', 'p.id')
            ->leftJoinSub($paid, 'pay', 'pay.package_id', '=', 'p.id')
            ->where('p.account_id', $scope->accountId)
            ->whereIn('p.location_id', $branchIds)
            ->where('p.active', 1)
            ->where('p.is_refund', 0)
            ->whereNull('p.deleted_at')
            ->whereDate('p.created_at', '>=', $cutoff)
            ->where('svc.remaining_sessions', '>', 0)
            ->select(
                'p.id as package_id',
                'p.patient_id',
                'p.location_id',
                'svc.consumed_value',
                'svc.unused_value',
                'svc.remaining_sessions',
            )
            ->selectRaw('COALESCE(pay.paid, 0) AS paid');

        if ($patientIds !== null) {
            $q->whereIn('p.patient_id', $patientIds);
        }

        return $q;
    }

    /**
     * Broken-commitment counts per patient at this branch since their
     * cohort visit — cancellations and no-shows. Pulled with a single
     * query bounded by a 90-day lookback (covers the 30–60d cohort plus
     * a margin for edge cases), then filtered in PHP to dates AFTER each
     * patient's personal cohort visit so we don't count breakage that
     * happened before they last actually arrived.
     *
     * @param  list<int>  $patientIds
     * @param  array<int, array{last_visit_date: string, last_doctor_id: int|null}>  $cohort
     * @return array<int, array{no_show_count: int, cancelled_count: int, last_broken_date: string}>
     */
    private function brokenCommitmentsByPatient(
        MetricScope $scope,
        int $branchId,
        array $patientIds,
        array $cohort,
        string $today,
    ): array {
        if ($patientIds === []) {
            return [];
        }

        $lookback = now()->subDays(self::BROKEN_COMMITMENT_WINDOW_DAYS)->toDateString();

        // Cohort is treatment-only, so broken commitments must also be
        // treatment-only — a cancelled consultation doesn't belong on a
        // treatment-cohort patient's "Cancelled" chip.
        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->where('location_id', $branchId)
            ->where('appointment_type_id', 2)
            ->whereIn('patient_id', $patientIds)
            ->whereIn('appointment_status_id', [self::NO_SHOW_STATUS_ID, self::CANCELLED_STATUS_ID])
            ->where('scheduled_date', '<=', $today)
            ->where('scheduled_date', '>=', $lookback)
            ->whereNull('deleted_at')
            ->select('patient_id', 'scheduled_date', 'appointment_status_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            $cohortVisit = $cohort[$pid]['last_visit_date'] ?? null;
            if ($cohortVisit === null) {
                continue;
            }
            // Only count breakage AFTER their cohort visit. A no-show
            // last December is irrelevant if they showed up in March.
            if ($r->scheduled_date <= $cohortVisit) {
                continue;
            }

            $out[$pid] ??= [
                'no_show_count' => 0,
                'cancelled_count' => 0,
                'last_broken_date' => '',
            ];

            if ((int) $r->appointment_status_id === self::NO_SHOW_STATUS_ID) {
                $out[$pid]['no_show_count']++;
            } else {
                $out[$pid]['cancelled_count']++;
            }

            $date = (string) $r->scheduled_date;
            if ($date > $out[$pid]['last_broken_date']) {
                $out[$pid]['last_broken_date'] = $date;
            }
        }

        return $out;
    }

    /**
     * Trailing-12mo spend per patient across the in-scope branches,
     * using the canonical cash-flow filter. Returns only patients in
     * the supplied id list.
     *
     * @param  list<int>  $patientIds
     * @return array<int, float>
     */
    private function trailingSpend(MetricScope $scope, array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        $cutoff = now()->subDays(self::VALUE_WINDOW_DAYS)->toDateString();
        // Branch-scope the spend: value tiers + the By-Spend ranking must
        // reflect what the patient spent at the in-scope branch(es), not
        // their company-wide spend. (idsInScope returns all branches for an
        // account-wide scope, so Overview is unaffected.)
        $branchIds = $this->branches->idsInScope($scope);

        $rows = DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->where('pa.account_id', $scope->accountId)
            ->whereIn('p.location_id', $branchIds)
            ->whereDate('pa.created_at', '>=', $cutoff)
            ->whereIn('p.patient_id', $patientIds)
            ->where('pa.cash_amount', '>', 0)
            ->where(function ($q): void {
                $q->where(function ($in): void {
                    $in->where('pa.cash_flow', 'in')
                        ->where('pa.is_adjustment', 0)
                        ->where('pa.is_tax', 0)
                        ->where('pa.is_cancel', 0);
                })->orWhere(function ($out): void {
                    $out->where('pa.cash_flow', 'out')
                        ->where('pa.is_refund', 1);
                });
            })
            ->select('p.patient_id')
            ->selectRaw("
                SUM(CASE WHEN pa.cash_flow = 'in' THEN pa.cash_amount ELSE -pa.cash_amount END) AS spend
            ")
            ->groupBy('p.patient_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->patient_id] = max(0.0, (float) $r->spend);
        }

        return $out;
    }

    /**
     * Patient name + phone lookup.
     *
     * @param  list<int>  $patientIds
     * @return array<int, array{name: string, phone: string|null}>
     */
    private function patientProfiles(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        $rows = DB::table('users')
            ->whereIn('id', $patientIds)
            ->select('id', 'name', 'phone')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = [
                'name' => (string) $r->name,
                'phone' => $r->phone !== null ? (string) $r->phone : null,
            ];
        }

        return $out;
    }

    /**
     * Filter patient ids to currently-active, non-soft-deleted user records.
     * The at-risk pool is a "who to contact" worklist, so deactivated or
     * deleted patients must never surface. Raw query builder, so the
     * soft-delete guard is explicit (no Eloquent global scope here).
     *
     * @param  list<int>  $patientIds
     * @return list<int>
     */
    private function activePatientIds(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $patientIds)
            ->where('active', 1)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Prune a branch -> patient candidate map down to active, non-deleted
     * patients (the multi-branch counterpart of activePatientIds).
     *
     * @param  array<int, array<int, bool>>  $candidateMap
     * @return array<int, array<int, bool>>
     */
    private function retainActivePatients(array $candidateMap): array
    {
        $pids = [];
        foreach ($candidateMap as $patients) {
            foreach ($patients as $p => $_) {
                $pids[$p] = true;
            }
        }

        $active = array_flip($this->activePatientIds(array_keys($pids)));

        foreach ($candidateMap as $b => $patients) {
            foreach ($patients as $p => $_) {
                if (! isset($active[$p])) {
                    unset($candidateMap[$b][$p]);
                }
            }
            if (($candidateMap[$b] ?? []) === []) {
                unset($candidateMap[$b]);
            }
        }

        return $candidateMap;
    }

    /**
     * Percentile cutoffs for the four value tiers, computed from the
     * spend distribution of the in-scope cohort. Patients at or above
     * the cutoff land in the higher tier.
     *
     * @param  list<float>  $spends
     * @return array{vip: float, high: float, mid: float}
     */
    private function valueTierThresholds(array $spends): array
    {
        // Drop zeros so a long tail of $0 patients doesn't drag the mid
        // and high cutoffs to zero (which would land everyone in VIP).
        $nonZero = array_values(array_filter($spends, static fn (float $s): bool => $s > 0));
        if ($nonZero === []) {
            return ['vip' => INF, 'high' => INF, 'mid' => INF];
        }

        sort($nonZero);
        $count = count($nonZero);

        $pct = function (float $p) use ($nonZero, $count): float {
            $idx = (int) floor($count * $p);
            $idx = max(0, min($count - 1, $idx));

            return (float) $nonZero[$idx];
        };

        return [
            'vip' => $pct(0.90),  // top 10%
            'high' => $pct(0.70), // 10–30%
            'mid' => $pct(0.30),  // 30–70%
        ];
    }

    /**
     * @param  array{vip: float, high: float, mid: float}  $thresholds
     */
    private function valueTierFor(float $spend, array $thresholds): string
    {
        if ($spend >= $thresholds['vip']) {
            return 'vip';
        }
        if ($spend >= $thresholds['high']) {
            return 'high';
        }
        if ($spend >= $thresholds['mid']) {
            return 'mid';
        }

        return 'low';
    }

    /**
     * Evaluate the three slippage signals for one candidate patient and
     * pick the primary signal per the locked priority order. Returns null
     * if no signal fires (patient drops out of the at-risk pool).
     *
     * Priority for primary_signal selection:
     *   1. abandoned_package + priority='high'
     *   2. cadence_break (most overdue first via days_overdue sort)
     *   3. broken_commitment
     *   4. abandoned_package + priority='medium'
     *   5. abandoned_package + priority='low'
     *
     * @param  list<array{package_id: int, unused_value: float, consumed_value: float, paid: float, remaining_sessions: int}>  $abandonedPackages
     * @param  array{no_show_count: int, cancelled_count: int, last_broken_date: string}|null  $broken
     * @return array{
     *   signals: list<string>,
     *   primary_signal: string,
     *   priority: string|null,
     *   days_since_last: int|null,
     *   median_gap_days: float|null,
     *   threshold_days: int|null,
     *   days_overdue: int|null,
     *   overdue_multiplier: float|null,
     * }|null
     */
    private function evaluateSignals(
        array $abandonedPackages,
        int $visitsInBaseline,
        int $lifetimeVisits,
        ?float $medianGapDays,
        ?string $lastVisitDate,
        ?array $broken,
        string $today,
    ): ?array {
        $daysSinceLast = $lastVisitDate !== null
            ? max(0, (int) round((strtotime($today) - strtotime($lastVisitDate)) / 86400))
            : null;

        // --- Signal 1: Cadence Break --------------------------------------
        // ≥2 arrived treatments in baseline window AND
        // max(1.5 × median_gap, 30) < days_since_last ≤ 90.
        // The upper bound keeps the signal to "recently slipping" patients;
        // beyond 90 days they're dormant (and still caught by money signals).
        // Threshold uses native float math — a patient with median 25 fires
        // at day 38 (38 > 37.5), not day 39. The display value is rounded
        // for the UI but the comparison is precise.
        $cadenceBreak = false;
        $thresholdDays = null;
        $daysOverdue = null;
        $overdueMultiplier = null;
        if (
            $visitsInBaseline >= 2
            && $medianGapDays !== null
            && $daysSinceLast !== null
        ) {
            $rawThreshold = max(
                (float) self::CADENCE_FLOOR_DAYS,
                $medianGapDays * self::CADENCE_MULTIPLIER,
            );
            if ($daysSinceLast > $rawThreshold && $daysSinceLast <= self::CADENCE_MAX_DAYS) {
                $cadenceBreak = true;
                $thresholdDays = (int) round($rawThreshold);
                $daysOverdue = $daysSinceLast - $thresholdDays;
                $overdueMultiplier = $medianGapDays > 0
                    ? round($daysSinceLast / $medianGapDays, 2)
                    : null;
            }
        }

        // --- Signal 2: Abandoned Package ----------------------------------
        // ≥1 active package with unused sessions AND last_visit ≥ 30 days.
        // (No future booking is already enforced upstream when the candidate
        // pool was built.)
        $abandonedFires = false;
        $priority = null;
        $abandonedRemainingValue = 0.0;
        if ($abandonedPackages !== []) {
            foreach ($abandonedPackages as $pkg) {
                $abandonedRemainingValue += (float) $pkg['unused_value'];
            }
            $hasMinRecency = $daysSinceLast !== null
                && $daysSinceLast >= self::ABANDONED_MIN_DAYS;
            if ($hasMinRecency) {
                $abandonedFires = true;
                $priority = $this->priorityFor(
                    $abandonedRemainingValue,
                    $daysSinceLast,
                );
            }
        }

        // --- Signal 3: Broken Commitment ----------------------------------
        // No-show or cancellation AFTER last successful visit, within 90d,
        // AND no future booking (enforced upstream).
        // brokenCommitmentsByPatient already filters to "after last visit"
        // and within the 90-day window, so its presence implies the signal.
        $brokenFires = $broken !== null
            && (($broken['no_show_count'] ?? 0) + ($broken['cancelled_count'] ?? 0)) > 0;

        if (! $cadenceBreak && ! $abandonedFires && ! $brokenFires) {
            return null;
        }

        // Single-visit-only exclusion: lifetime arrived treatments == 1
        // belongs to the first-visit retention funnel, not at-risk —
        // unless they have an abandoned package or broken commitment
        // (which are independent signals worth surfacing).
        if (
            $lifetimeVisits === 1
            && ! $abandonedFires
            && ! $brokenFires
        ) {
            return null;
        }

        $signals = [];
        if ($cadenceBreak) {
            $signals[] = 'cadence_break';
        }
        if ($abandonedFires) {
            $signals[] = 'abandoned_package';
        }
        if ($brokenFires) {
            $signals[] = 'broken_commitment';
        }

        // Pick primary per the locked priority order.
        $primary = null;
        if ($abandonedFires && $priority === 'high') {
            $primary = 'abandoned_package';
        } elseif ($cadenceBreak) {
            $primary = 'cadence_break';
        } elseif ($brokenFires) {
            $primary = 'broken_commitment';
        } elseif ($abandonedFires) {
            // medium or low
            $primary = 'abandoned_package';
        }

        return [
            'signals' => $signals,
            'primary_signal' => $primary,
            // Priority of the abandoned-package signal when it fires.
            // Set whenever the patient has an actionable abandoned
            // package, regardless of which signal is primary — the
            // branch-level priority counters use this to surface "how
            // much high-value money is at risk?" independent of the
            // patient's headline classification.
            'priority' => $priority,
            'days_since_last' => $daysSinceLast,
            'median_gap_days' => $medianGapDays,
            'threshold_days' => $thresholdDays,
            'days_overdue' => $daysOverdue,
            'overdue_multiplier' => $overdueMultiplier,
        ];
    }

    /**
     * Abandoned-package priority bucket.
     *
     *   High   — remaining ≥ 1,000  AND  last_visit 30–60 days
     *   Medium — remaining ≥ 1,000  AND  last_visit 60–90 days
     *   Low    — remaining < 1,000  OR   last_visit > 90 days
     */
    private function priorityFor(float $remainingValue, int $daysSinceLast): string
    {
        if ($remainingValue < self::ABANDONED_VALUE_CUTOFF) {
            return 'low';
        }
        if ($daysSinceLast <= self::ABANDONED_HIGH_DAYS_END) {
            return 'high';
        }
        if ($daysSinceLast <= self::ABANDONED_MEDIUM_DAYS_END) {
            return 'medium';
        }

        return 'low';
    }
}
