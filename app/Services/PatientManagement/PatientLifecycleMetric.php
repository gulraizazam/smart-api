<?php

declare(strict_types=1);

namespace App\Services\PatientManagement;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Services\Dashboard\Metrics\AtRiskPatientsMetric;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Per-patient lifecycle aggregates for list + header surfaces.
 *
 * Centralises the six patient-level "vitals" the SPA's patient list
 * (Tier 1) and the patient detail header consume. All methods are
 * page-scoped (work on a small, caller-supplied list of patient_ids)
 * so the cost stays bounded regardless of total patient count — a
 * 174k-patient list still aggregates over the 25 visible IDs per page.
 *
 * Sourcing rules (one home per concept):
 *   - is_at_risk: composes AtRiskPatientsMetric → any change to the
 *     dashboard at-risk signal definition propagates automatically.
 *     We do not reimplement the cadence-break / abandoned-package /
 *     broken-commitment logic anywhere else.
 *   - last_visit / next_appointment: appointments table, scoped by
 *     "arrived" status for last visit and "upcoming, not cancelled"
 *     for next appointment.
 *   - lifetime_value (sales convention): SUM(package_advances cash_in
 *     where not cancelled). Mirrors the Sales convention used by
 *     PlanService::buildOptimizedResultQuery's `pa_agg.cash_receive`.
 *     "Sales" not "Revenue" — see project memory note.
 *   - outstanding_balance: SUM across the patient's *active*, non-soft-
 *     deleted plans of (plan_total − cash_in + refunds). Mirrors the
 *     per-plan formula in PlanDatatableResource.
 *   - active_packages_count: COUNT(packages where active=1, deleted_at
 *     IS NULL). Same scoping the PlanService fast-path query uses.
 */
final class PatientLifecycleMetric
{
    public function __construct(
        private readonly AtRiskPatientsMetric $atRiskMetric,
    ) {}

    /**
     * Compute lifecycle metrics for a batch of patient_ids.
     *
     * @param  int[]  $patientIds Page-scoped list (typically ≤25).
     * @return array<int, array{
     *   is_at_risk: bool,
     *   last_visit_at: string|null,
     *   last_visit_days_ago: int|null,
     *   lifetime_value: float,
     *   outstanding_balance: float,
     *   active_packages_count: int,
     *   next_appointment_at: string|null,
     * }>  Keyed by patient_id.
     */
    public function batch(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        $accountId   = (int) Auth::user()->account_id;
        $userCentres = ACL::getUserCentres();

        $defaults = [
            'is_at_risk'            => false,
            'last_visit_at'         => null,
            'last_visit_days_ago'   => null,
            'lifetime_value'        => 0.0,
            'outstanding_balance'   => 0.0,
            'active_packages_count' => 0,
            'next_appointment_at'   => null,
        ];

        $out = [];
        foreach ($patientIds as $pid) {
            $out[(int) $pid] = $defaults;
        }

        $this->mergeAtRisk($out, $accountId, $userCentres);
        $this->mergeLastVisit($out, $patientIds, $accountId, $userCentres);
        $this->mergeNextAppointment($out, $patientIds, $accountId, $userCentres);
        $this->mergeLifetimeValue($out, $patientIds);
        $this->mergeOutstandingAndActivePackages($out, $patientIds, $accountId, $userCentres);

        return $out;
    }

    // ── Per-metric mergers ────────────────────────────────────────

    /**
     * Composes AtRiskPatientsMetric — patient is at-risk if their id
     * appears in the at-risk pool of *any* branch in the user's scope.
     *
     * Cold-cache safety: materialising the at-risk pool from scratch
     * takes 15–25s for an account with many branches, which would
     * freeze the patient list waiting for it. We peek the cache first
     * and gracefully skip enrichment when the pool isn't already warm
     * — the patient list paints in <500ms, and the next request once
     * the pool warms (via the dashboard, the patient detail header,
     * or the scheduled `mgmt-dash:warm-at-risk` command) shows the
     * at-risk pills. Trade-off: brief at-risk-pill flicker after a
     * 5-min idle, in exchange for never-frozen patient list.
     */
    private function mergeAtRisk(array &$out, int $accountId, array $userCentres): void
    {
        $branchIds = array_values(array_unique(array_map('intval', $userCentres)));
        if ($branchIds === []) {
            return;
        }

        $scope = MetricScope::branches($accountId, $branchIds);

        if (! $this->atRiskMetric->isPoolWarm($scope, $branchIds)) {
            return;
        }

        $atRiskMap = $this->atRiskMetric->patientIdsByBranch($scope, $branchIds);

        $atRisk = [];
        foreach ($atRiskMap as $perBranch) {
            foreach ($perBranch as $pid) {
                $atRisk[(int) $pid] = true;
            }
        }

        foreach ($out as $pid => $_) {
            if (isset($atRisk[$pid])) {
                $out[$pid]['is_at_risk'] = true;
            }
        }
    }

    /**
     * Last visit = most recent appointment whose status carries the
     * "arrived" flag. The `arrived_at` timestamp on appointments is
     * sparsely populated in legacy data (~10k rows) — the canonical
     * signal is the status-flag join (`appointment_statuses.is_arrived = 1`),
     * which the dashboard at-risk metric and other surfaces use too.
     * That flag covers ~241k arrived appointments — 22× more accurate.
     *
     * We use `scheduled_date` as the visit date (when the appointment
     * was for, vs. when the row was inserted). Same column AtRiskPatientsMetric
     * sorts by.
     */
    private function mergeLastVisit(array &$out, array $patientIds, int $accountId, array $userCentres): void
    {
        $rows = Appointments::query()
            ->join('appointment_statuses', 'appointments.base_appointment_status_id', '=', 'appointment_statuses.id')
            ->whereIn('appointments.patient_id', $patientIds)
            ->where('appointments.account_id', $accountId)
            ->where('appointment_statuses.is_arrived', 1)
            ->whereNull('appointments.deleted_at')
            ->when(! empty($userCentres), fn ($q) => $q->whereIn('appointments.location_id', $userCentres))
            ->select('appointments.patient_id', DB::raw('MAX(appointments.scheduled_date) as last_visit'))
            ->groupBy('appointments.patient_id')
            ->get();

        $today = Carbon::today();
        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            if (! isset($out[$pid]) || ! $r->last_visit) {
                continue;
            }
            $visit = Carbon::parse($r->last_visit);
            $out[$pid]['last_visit_at']      = $visit->toIso8601String();
            $out[$pid]['last_visit_days_ago'] = max(0, (int) $visit->startOfDay()->diffInDays($today, false));
        }
    }

    /**
     * Next appointment = earliest upcoming appointment that hasn't been
     * cancelled or marked arrived. Uses `scheduled_date` (the column
     * that holds the planned visit date) and clamps by today.
     */
    private function mergeNextAppointment(array &$out, array $patientIds, int $accountId, array $userCentres): void
    {
        $rows = Appointments::query()
            ->leftJoin('appointment_statuses', 'appointments.base_appointment_status_id', '=', 'appointment_statuses.id')
            ->whereIn('appointments.patient_id', $patientIds)
            ->where('appointments.account_id', $accountId)
            ->where('appointments.scheduled_date', '>=', Carbon::today()->toDateString())
            ->where(function ($q) {
                // Not cancelled and not yet arrived (we don't surface
                // appointments the patient already showed up for).
                $q->whereNull('appointment_statuses.is_cancelled')
                    ->orWhere('appointment_statuses.is_cancelled', 0);
            })
            ->where(function ($q) {
                $q->whereNull('appointment_statuses.is_arrived')
                    ->orWhere('appointment_statuses.is_arrived', 0);
            })
            ->when(! empty($userCentres), fn ($q) => $q->whereIn('appointments.location_id', $userCentres))
            ->select('appointments.patient_id', DB::raw('MIN(appointments.scheduled_date) as next_at'))
            ->groupBy('appointments.patient_id')
            ->get();

        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            if (! isset($out[$pid]) || ! $r->next_at) {
                continue;
            }
            $out[$pid]['next_appointment_at'] = Carbon::parse($r->next_at)->toIso8601String();
        }
    }

    /**
     * Lifetime spend (Sales convention) = SUM of cash inflows that
     * weren't cancelled. Mirrors the cash_receive aggregation already
     * used by PlanService::buildOptimizedResultQuery's pa_agg subquery.
     */
    private function mergeLifetimeValue(array &$out, array $patientIds): void
    {
        $rows = PackageAdvances::query()
            ->whereIn('patient_id', $patientIds)
            ->where('cash_flow', 'in')
            ->where('is_cancel', 0)
            ->where('is_tax', 0)
            ->whereNull('deleted_at')
            ->select('patient_id', DB::raw('SUM(cash_amount) as lifetime_value'))
            ->groupBy('patient_id')
            ->get();

        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;
            if (isset($out[$pid])) {
                $out[$pid]['lifetime_value'] = (float) $r->lifetime_value;
            }
        }
    }

    /**
     * Outstanding balance and active-packages count — shared single
     * query because both walk the same packages set. Outstanding
     * mirrors the per-plan formula PlanDatatableResource computes
     * (total − cash_in + refunds) but summed across the patient's
     * active plans.
     *
     * The inner aggregate over `package_advances` (834k rows) is the
     * perf-sensitive bit — same anti-pattern we fixed on Plans. We
     * scope it to JUST the package_ids belonging to the visible
     * patient set so MariaDB aggregates ~50 child-row groups instead of
     * the whole table. Drops the cost from ~1.1s to <10ms per page.
     */
    private function mergeOutstandingAndActivePackages(array &$out, array $patientIds, int $accountId, array $userCentres): void
    {
        // Scoped subquery: only the packages we actually need.
        $scopedPackageIdsSql = Packages::query()
            ->select('id')
            ->whereIn('patient_id', $patientIds)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->whereNull('deleted_at')
            ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
            ->toSql();

        // Bindings for the inner subquery (Eloquent flattens these).
        $scopedBindings = Packages::query()
            ->whereIn('patient_id', $patientIds)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->whereNull('deleted_at')
            ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
            ->getBindings();

        $rows = Packages::query()
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(CASE WHEN cash_flow = "in" AND is_cancel = 0 THEN cash_amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN is_refund = 1 THEN cash_amount ELSE 0 END) as refunds
                FROM package_advances
                WHERE deleted_at IS NULL
                  AND package_id IN ('.$scopedPackageIdsSql.')
                GROUP BY package_id
            ) as pa_agg'), 'pa_agg.package_id', '=', 'packages.id')
            ->whereIn('packages.patient_id', $patientIds)
            ->where('packages.account_id', $accountId)
            ->where('packages.active', 1)
            ->whereNull('packages.deleted_at')
            ->when(! empty($userCentres), fn ($q) => $q->whereIn('packages.location_id', $userCentres))
            ->select(
                'packages.patient_id',
                DB::raw('COUNT(*) as active_count'),
                DB::raw('SUM(GREATEST(0, COALESCE(packages.total_price, 0) - COALESCE(pa_agg.cash_in, 0) + COALESCE(pa_agg.refunds, 0))) as outstanding'),
            )
            ->groupBy('packages.patient_id');

        // Inject bindings for the inner subquery (the leftJoin DB::raw
        // contains placeholder ? for each scoped binding).
        $rows->addBinding($scopedBindings, 'join');

        foreach ($rows->get() as $r) {
            $pid = (int) $r->patient_id;
            if (isset($out[$pid])) {
                $out[$pid]['active_packages_count'] = (int) $r->active_count;
                $out[$pid]['outstanding_balance']   = (float) $r->outstanding;
            }
        }
    }
}
