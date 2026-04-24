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
 * At-Risk Patients — turns the Client Retention Rate metric into an
 * actionable callback queue.
 *
 * Anchors on the same matured 30–60 day cohort as
 * NewReturningMetric::followUpSplit() so the panel-to-list bridge is
 * exact: the patients in this list ARE the (cohort − returned) gap
 * that the retention rate measures.
 *
 * Two public surfaces:
 *
 *   summary($scope)
 *     Pool-level Recoverable Revenue tile across all branches in scope.
 *     Splits the headline number into (a) prepaid_unused — money
 *     already received that we still owe service against — and
 *     (b) unbilled_commitment — future revenue we lose if the patient
 *     walks. The split matters operationally: prepaid-unused-dominant
 *     packages are the easiest saves (patient already paid, just needs
 *     to book), while unbilled-commitment-dominant packages need a
 *     collections-tinged conversation.
 *
 *   list($scope, $branchId, $riskTypes, $valueTiers, $limit, $offset)
 *     Named patients at a single branch, classified into one of five
 *     buckets, ranked by recoverable value. Filters drive the operator
 *     view (default to VIP+High × Abandoned+Lapsed in the UI).
 *
 * Risk buckets, checked in priority order per cohort patient:
 *   1. Abandoned package    — has ≥1 active package with unused sessions
 *   2. Lapsed regular       — ≥3 prior visits + broke their cadence
 *   3. Maintenance overdue  — 2 prior visits + all packages exhausted +
 *                             past their cadence
 *   4. Tried-once dropout   — exactly 1 lifetime arrived treatment
 *   5. (filtered out)       — has a future booking, or doesn't fit any
 *                             of the above
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

    private const COHORT_DAYS_END = 30;

    private const COHORT_DAYS_START = 60;

    private const REBOOK_GAP_DAYS = 7;

    private const PACKAGE_RECENCY_DAYS = 365;

    private const VALUE_WINDOW_DAYS = 365;

    private const BROKEN_COMMITMENT_WINDOW_DAYS = 90;

    /** Status ids: 3 = "Didn't show up", 4 = "Cancelled". */
    private const NO_SHOW_STATUS_ID = 3;

    private const CANCELLED_STATUS_ID = 4;

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
        $cacheKey = 'mgmt_dash:at_risk_summary:'.$scope->cacheKey();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->buildSummary($scope));
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
     * At-risk patient list for a single branch. Patients are pulled from
     * the same matured 30–60 day cohort the retention rate uses, then
     * classified and value-tiered.
     *
     * @param  list<string>|null  $riskTypes  Filter — null = all five buckets
     * @param  list<string>|null  $valueTiers Filter — null = all four tiers
     * @return array{
     *   branch_id: int,
     *   branch_name: string|null,
     *   rows: list<array<string, mixed>>,
     *   total_count: int,
     *   bucket_counts: array<string, int>,
     * }
     */
    public function list(
        MetricScope $scope,
        int $branchId,
        ?array $riskTypes,
        ?array $valueTiers,
        int $limit = 50,
        int $offset = 0,
    ): array {
        // Validate the requested branch is in scope. Don't silently drop.
        $allowed = $this->branches->idsInScope($scope);
        if (! in_array($branchId, $allowed, true)) {
            return [
                'branch_id' => $branchId,
                'branch_name' => null,
                'rows' => [],
                'total_count' => 0,
                'bucket_counts' => [],
            ];
        }

        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();
        if ($treatmentStatusIds === []) {
            return [
                'branch_id' => $branchId,
                'branch_name' => $this->branches->names([$branchId])[$branchId] ?? null,
                'rows' => [],
                'total_count' => 0,
                'bucket_counts' => [],
            ];
        }

        $cohortEnd = now()->subDays(self::COHORT_DAYS_END)->toDateString();
        $cohortStart = now()->subDays(self::COHORT_DAYS_START)->toDateString();
        $today = now()->toDateString();

        // 1. Cohort = patients who had an arrived treatment at this branch
        //    in the matured window AND have NOT had a follow-up >7 days
        //    later AND have NO future booking at the branch. The exact
        //    population the retention metric leaves out.
        $cohort = $this->cohortPatientsAtBranch(
            $scope,
            $branchId,
            $treatmentStatusIds,
            $cohortStart,
            $cohortEnd,
            $today,
        );

        if ($cohort === []) {
            return [
                'branch_id' => $branchId,
                'branch_name' => $this->branches->names([$branchId])[$branchId] ?? null,
                'rows' => [],
                'total_count' => 0,
                'bucket_counts' => [],
            ];
        }

        $patientIds = array_keys($cohort);

        // 2. Per-patient lifetime visit counts at this branch — used to
        //    distinguish lapsed regular (≥3) vs maintenance (2) vs
        //    tried-once (1).
        $visitStats = $this->lifetimeVisitStats(
            $scope,
            $branchId,
            $treatmentStatusIds,
            $patientIds,
            $today,
        );

        // 3. Per-patient abandoned packages. May be 0..n per patient.
        $abandonedByPatient = $this->abandonedPackagesByPatient($scope, $branchId, $patientIds);

        // 4. Per-patient trailing-12mo spend → drives value tier.
        $spendByPatient = $this->trailingSpend($scope, $patientIds);

        // 4b. Broken commitments since cohort visit — cancellations and
        //     no-shows. A patient who already broke a booking once is
        //     more at-risk than one who simply went silent. Surfaced as
        //     a tag in the UI without changing the risk-type bucket.
        $brokenByPatient = $this->brokenCommitmentsByPatient(
            $scope,
            $branchId,
            $patientIds,
            $cohort,
            $today,
        );

        // 5. Patient profile (name, phone, last doctor name).
        $profiles = $this->patientProfiles($patientIds);
        $doctorIds = array_unique(array_filter(array_map(
            static fn (array $c): ?int => $c['last_doctor_id'],
            $cohort,
        )));
        $doctorNames = $doctorIds === []
            ? []
            : DB::table('users')
                ->whereIn('id', $doctorIds)
                ->pluck('name', 'id')
                ->toArray();

        // 6. Compute value-tier cutoffs from the spend distribution.
        $tierThresholds = $this->valueTierThresholds(array_values($spendByPatient));

        // 7. Classify and assemble rows.
        $rows = [];
        $bucketCounts = [
            'abandoned_package' => 0,
            'lapsed_regular' => 0,
            'maintenance_overdue' => 0,
            'tried_once_dropout' => 0,
        ];

        foreach ($cohort as $patientId => $cohortInfo) {
            $stats = $visitStats[$patientId] ?? ['visit_count' => 0, 'median_gap_days' => null];
            $abandoned = $abandonedByPatient[$patientId] ?? [];
            $spend = (float) ($spendByPatient[$patientId] ?? 0.0);
            $profile = $profiles[$patientId] ?? ['name' => "Patient #{$patientId}", 'phone' => null];

            $bucket = $this->classify(
                $abandoned,
                $stats['visit_count'],
                $stats['median_gap_days'],
                $cohortInfo['last_visit_date'],
                $today,
            );

            if ($bucket === null) {
                continue;
            }

            $tier = $this->valueTierFor($spend, $tierThresholds);

            // Sum recoverable across all of this patient's abandoned packages.
            // For non-abandoned buckets, "recoverable" falls back to trailing
            // spend so the row still ranks by value.
            $unusedSum = 0.0;
            $prepaidSum = 0.0;
            $unbilledSum = 0.0;
            foreach ($abandoned as $pkg) {
                // Same overpayment cap as the summary tile — see comment there.
                $prepaidUnused = min($pkg['unused_value'], max(0.0, $pkg['paid'] - $pkg['consumed_value']));
                $unbilled = max(0.0, $pkg['unused_value'] - $prepaidUnused);
                $unusedSum += $pkg['unused_value'];
                $prepaidSum += $prepaidUnused;
                $unbilledSum += $unbilled;
            }

            $bucketCounts[$bucket]++;

            $broken = $brokenByPatient[$patientId] ?? null;

            $rows[] = [
                'patient_id' => $patientId,
                'patient_name' => $profile['name'],
                'phone' => $profile['phone'],
                'last_visit_date' => $cohortInfo['last_visit_date'],
                'last_doctor_id' => $cohortInfo['last_doctor_id'],
                'last_doctor_name' => $cohortInfo['last_doctor_id'] !== null
                    ? ($doctorNames[$cohortInfo['last_doctor_id']] ?? null)
                    : null,
                'risk_type' => $bucket,
                'value_tier' => $tier,
                'trailing_12mo_spend' => round($spend, 2),
                'recoverable_value' => $bucket === 'abandoned_package'
                    ? round($unusedSum, 2)
                    : 0.0,
                'prepaid_unused' => round($prepaidSum, 2),
                'unbilled_commitment' => round($unbilledSum, 2),
                'abandoned_package_count' => count($abandoned),
                'lifetime_visits' => $stats['visit_count'],
                'no_show_since' => $broken['no_show_count'] ?? 0,
                'cancelled_since' => $broken['cancelled_count'] ?? 0,
                'last_broken_date' => $broken['last_broken_date'] ?? null,
                // Used by the sort below; not part of public API
                '_rank_key' => $bucket === 'abandoned_package' ? $unusedSum : $spend,
            ];
        }

        // Filter
        if ($riskTypes !== null && $riskTypes !== []) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => in_array($r['risk_type'], $riskTypes, true),
            ));
        }
        if ($valueTiers !== null && $valueTiers !== []) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => in_array($r['value_tier'], $valueTiers, true),
            ));
        }

        // Sort: abandoned-package rows first by recoverable_value desc, then
        // everything else by trailing spend desc. Tier rank is a tiebreaker
        // so VIP tied on $0 still beats Low tied on $0.
        $tierRank = ['vip' => 0, 'high' => 1, 'mid' => 2, 'low' => 3];
        usort($rows, static function (array $a, array $b) use ($tierRank): int {
            $ar = (float) $a['_rank_key'];
            $br = (float) $b['_rank_key'];
            if ($ar !== $br) {
                return $br <=> $ar;
            }

            return ($tierRank[$a['value_tier']] ?? 9) <=> ($tierRank[$b['value_tier']] ?? 9);
        });

        $totalCount = count($rows);
        $page = array_slice($rows, $offset, $limit);
        // Strip the internal sort key from the public payload.
        $page = array_map(static function (array $r): array {
            unset($r['_rank_key']);

            return $r;
        }, $page);

        return [
            'branch_id' => $branchId,
            'branch_name' => $this->branches->names([$branchId])[$branchId] ?? null,
            'rows' => $page,
            'total_count' => $totalCount,
            'bucket_counts' => $bucketCounts,
        ];
    }

    /**
     * Cohort patients at a branch — same matured 30–60 day window the
     * retention metric uses, with the additional filter "no future
     * booking at this branch". Returns one row per patient with their
     * latest cohort-window visit date and the doctor they saw on it.
     *
     * @param  list<int>  $treatmentStatusIds
     * @return array<int, array{last_visit_date: string, last_doctor_id: int|null}>
     */
    private function cohortPatientsAtBranch(
        MetricScope $scope,
        int $branchId,
        array $treatmentStatusIds,
        string $cohortStart,
        string $cohortEnd,
        string $today,
    ): array {
        // Cohort a1 = arrived treatment at the branch in the window.
        // Exclude patients with a follow-up a2 >7d later up to today
        // (those are "returned" — already counted as retained).
        // Also exclude patients with any future-scheduled booking at
        // the branch — they're on track, not at-risk.
        $rows = DB::table('appointments as a1')
            ->where('a1.account_id', $scope->accountId)
            ->where('a1.location_id', $branchId)
            ->where('a1.appointment_type_id', 2)
            ->whereIn('a1.appointment_status_id', $treatmentStatusIds)
            ->whereBetween('a1.scheduled_date', [$cohortStart, $cohortEnd])
            ->whereNull('a1.deleted_at')
            ->whereNotExists(function ($sub) use ($branchId, $treatmentStatusIds, $today): void {
                $sub->select(DB::raw(1))
                    ->from('appointments as a2')
                    ->whereColumn('a2.patient_id', 'a1.patient_id')
                    ->where('a2.location_id', $branchId)
                    ->where('a2.appointment_type_id', 2)
                    ->whereIn('a2.appointment_status_id', $treatmentStatusIds)
                    ->whereRaw('a2.scheduled_date > DATE_ADD(a1.scheduled_date, INTERVAL '.self::REBOOK_GAP_DAYS.' DAY)')
                    ->where('a2.scheduled_date', '<=', $today)
                    ->whereColumn('a2.id', '!=', 'a1.id')
                    ->whereNull('a2.deleted_at');
            })
            ->whereNotExists(function ($sub) use ($branchId, $today): void {
                // Future booking of any kind at the branch — they're scheduled, not at-risk.
                $sub->select(DB::raw(1))
                    ->from('appointments as af')
                    ->whereColumn('af.patient_id', 'a1.patient_id')
                    ->where('af.location_id', $branchId)
                    ->where('af.scheduled_date', '>', $today)
                    ->whereNull('af.deleted_at');
            })
            ->groupBy('a1.patient_id')
            ->select('a1.patient_id')
            ->selectRaw('MAX(a1.scheduled_date) AS last_visit_date')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Resolve doctor on the most-recent cohort visit per patient.
        // Done as a second pass to keep the cohort query plan simple.
        $patientIds = $rows->pluck('patient_id')->map(static fn ($v): int => (int) $v)->all();
        $doctorMap = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->where('location_id', $branchId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereIn('patient_id', $patientIds)
            ->whereBetween('scheduled_date', [$cohortStart, $cohortEnd])
            ->whereNull('deleted_at')
            ->select('patient_id', 'doctor_id', 'scheduled_date')
            ->orderBy('patient_id')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id')
            ->map(static fn ($group) => $group->first())
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            $doctor = $doctorMap[$pid] ?? null;
            $out[$pid] = [
                'last_visit_date' => (string) $r->last_visit_date,
                'last_doctor_id' => $doctor ? (int) $doctor->doctor_id : null,
            ];
        }

        return $out;
    }

    /**
     * Lifetime arrived-treatment count per patient at the branch, plus a
     * median inter-visit gap (in days) used as the cadence baseline.
     *
     * Median gap is null when the patient has fewer than 2 prior visits
     * (no gap exists yet).
     *
     * @param  list<int>  $treatmentStatusIds
     * @param  list<int>  $patientIds
     * @return array<int, array{visit_count: int, median_gap_days: float|null}>
     */
    private function lifetimeVisitStats(
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

        $rows = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->where('location_id', $branchId)
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

        $rows = DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->where('pa.account_id', $scope->accountId)
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
     * Bucket assignment, priority order. Returns null when the patient
     * doesn't fit any at-risk bucket.
     *
     * @param  list<array{package_id: int, unused_value: float, consumed_value: float, paid: float, remaining_sessions: int}>  $abandonedPackages
     */
    private function classify(
        array $abandonedPackages,
        int $visitCount,
        ?float $medianGapDays,
        string $lastVisitDate,
        string $today,
    ): ?string {
        if ($abandonedPackages !== []) {
            return 'abandoned_package';
        }

        $daysSinceLast = max(
            0,
            (int) round((strtotime($today) - strtotime($lastVisitDate)) / 86400),
        );

        if ($visitCount >= 3) {
            // Lapsed regular needs a broken cadence — if they're still
            // within 1.5× their median gap they're not yet "lapsed".
            if ($medianGapDays !== null && $daysSinceLast > $medianGapDays * 1.5) {
                return 'lapsed_regular';
            }
            // Regular but still on cadence → on track, drop them.
            return null;
        }

        if ($visitCount === 2) {
            // Maintenance overdue — completed-plan patient past their
            // personal cadence. Same heuristic applies.
            if ($medianGapDays !== null && $daysSinceLast > $medianGapDays * 1.5) {
                return 'maintenance_overdue';
            }
            return null;
        }

        if ($visitCount === 1) {
            return 'tried_once_dropout';
        }

        return null;
    }
}
