<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\AppointmentType;
use App\Enums\ResourceType;
use App\Helpers\DoctorDashboardHelper;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\AppointmentCountsQuery;
use App\Services\Dashboard\Support\RevenueInvoiceQuery;
use App\Services\Dashboard\Support\SalesLedgerQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Appointment-centric metrics across any scope. Delegates the heavy
 * lifting (cash-mode collection, invoice revenue, appointment counts) to
 * shared Dashboard\Support\* helpers so the rules are defined exactly
 * once and the home page + Management Dashboard always agree.
 *
 * Entry points:
 *   compute(scope, range)  — patients seen (consultations + treatments)
 *   ratios(scope, range)   — arrived/total ratios + sales + revenue
 *   salesByCentre(scope, range) — per-centre sales breakdown
 *   today(scope)           — today's appointment list
 *   noShowRate(scope, range)
 */
final class AppointmentsMetric implements Metric
{
    /** Cache TTL (seconds) for ratios() — matches the sibling Dashboard metrics. */
    private const CACHE_TTL = 300;

    /**
     * Patients seen (consultations + treatments with arrived/qualifying statuses).
     *
     * @return array{total: int, consultations: int, treatments: int}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['total' => 0, 'consultations' => 0, 'treatments' => 0];
        }

        $consultations = $this->countArrived(
            $scope,
            $range,
            AppointmentType::Consultancy->value,
            DoctorDashboardHelper::getConsultationStatusIds(),
        );

        $treatments = $this->countArrived(
            $scope,
            $range,
            2,
            DoctorDashboardHelper::getTreatmentStatusIds(),
        );

        return [
            'total' => $consultations + $treatments,
            'consultations' => $consultations,
            'treatments' => $treatments,
        ];
    }

    /**
     * Arrived + total split per appointment type, plus the monetary Sales
     * and Revenue figures that power the Overview Stats card.
     *
     * Every underlying number is computed by a Dashboard\Support helper —
     * AppointmentCountsQuery (ratios), SalesLedgerQuery (sales), and
     * RevenueInvoiceQuery (revenue consumed). The same helpers back the
     * matching tiles on /admin/home, so the two views can never drift.
     *
     * @return array{
     *   consultations: array{arrived: int, total: int},
     *   treatments: array{arrived: int, total: int},
     *   sales: float,
     *   revenue_consumed: float
     * }
     */
    public function ratios(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return [
                'consultations' => ['arrived' => 0, 'total' => 0],
                'treatments' => ['arrived' => 0, 'total' => 0],
                'sales' => 0.0,
                'revenue_consumed' => 0.0,
            ];
        }

        // The Overview Stats card calls this TWICE per request (current +
        // prior period for the MoM deltas), and it's the page's heaviest
        // computation (appointment counts + sales ledger + invoice revenue).
        // Cache per scope+range for 5 min — same pattern as the sibling
        // metrics (GenderRevenue/ATV/ACV) — so both passes hit the cache.
        $cacheKey = 'mgmt_dash:appointment_ratios:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->buildRatios($scope, $range));
    }

    /**
     * Uncached computation behind ratios() — see ratios() for the shape.
     *
     * @return array{
     *   consultations: array{arrived: int, total: int},
     *   treatments: array{arrived: int, total: int},
     *   sales: float,
     *   revenue_consumed: float
     * }
     */
    private function buildRatios(MetricScope $scope, DateRange $range): array
    {
        // One round-trip for both consult + treatment counts. The Stats
        // card always shows both, so splitting them into separate queries
        // (as before) was pure overhead on the dashboard's slowest path.
        $consultTypeId = AppointmentType::Consultancy->value;
        $treatmentTypeId = 2;
        $counts = AppointmentCountsQuery::forTypes(
            accountId: $scope->accountId,
            locationIds: $this->locationIdsForScope($scope),
            qualifyingByType: [
                $consultTypeId => DoctorDashboardHelper::getConsultationStatusIds(),
                $treatmentTypeId => DoctorDashboardHelper::getTreatmentStatusIds(),
            ],
            startDate: $range->startString(),
            endDate: $range->endString(),
            doctorId: ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor)
                ? $scope->resourceId
                : null,
        );

        $consultCounts = $counts[$consultTypeId];
        $treatCounts = $counts[$treatmentTypeId];

        return [
            'consultations' => [
                'arrived' => $consultCounts['done_count'],
                'total' => $consultCounts['all_count'],
            ],
            'treatments' => [
                'arrived' => $treatCounts['done_count'],
                'total' => $treatCounts['all_count'],
            ],
            'sales' => $this->salesAmount($scope, $range),
            'revenue_consumed' => RevenueInvoiceQuery::paidTotal(
                accountId: $scope->accountId,
                locationIds: $this->locationIdsForScope($scope),
                startDate: $range->startString(),
                endDate: $range->endString(),
            ),
        ];
    }

    /**
     * Per-centre sales breakdown for the period — same semantics as the
     * scalar `sales` field in ratios(), grouped by branch. Executes a
     * single GROUP BY query over the same SalesLedgerQuery filter set,
     * so company-wide total = sum of per-centre rows by construction.
     *
     * Branches with zero activity are included so the chart can still
     * render them (explains why they're empty).
     *
     * @return array{rows: list<array{branch_id: int, branch_name: string, sales: float}>, total: float}
     */
    public function salesByCentre(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['rows' => [], 'total' => 0.0];
        }

        $branches = DB::table('locations')
            ->where('account_id', $scope->accountId)
            ->whereNull('deleted_at')
            // Virtual "All X" rollup entries aren't physical branches.
            ->where('name', 'not like', 'All %')
            ->when(
                $scope->isBranchScoped() && $scope->branchIds !== null,
                fn ($q) => $q->whereIn('id', $scope->branchIds),
            )
            ->pluck('name', 'id')
            ->all();

        if ($branches === []) {
            return ['rows' => [], 'total' => 0.0];
        }

        $agg = SalesLedgerQuery::forScope($scope, $range)
            ->whereIn('pa.location_id', array_keys($branches))
            ->selectRaw('pa.location_id, '.SalesLedgerQuery::netCollectionSelectRaw().' AS sales')
            ->groupBy('pa.location_id')
            ->pluck('sales', 'pa.location_id');

        $rows = [];
        $total = 0.0;
        foreach ($branches as $id => $name) {
            $value = round((float) ($agg[(int) $id] ?? 0), 2);
            $total += $value;
            $rows[] = [
                'branch_id' => (int) $id,
                'branch_name' => (string) $name,
                'sales' => $value,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['sales'] <=> $a['sales']);

        return ['rows' => $rows, 'total' => round($total, 2)];
    }

    /**
     * Today's appointments for the scope. Sorted: arrived → scheduled → other → cancelled.
     *
     * @return array{
     *   total: int,
     *   treatments: array{count: int, arrived: int, list: list<array<string, mixed>>},
     *   consultations: array{count: int, arrived: int, list: list<array<string, mixed>>}
     * }
     */
    public function today(MetricScope $scope): array
    {
        $today = Carbon::now()->format('Y-m-d');

        $query = DB::table('appointments as a')
            ->leftJoin('users as p', 'a.patient_id', '=', 'p.id')
            ->leftJoin('services as s', 'a.service_id', '=', 's.id')
            ->leftJoin('locations as l', 'a.location_id', '=', 'l.id')
            ->leftJoin('appointment_statuses as ast', 'a.appointment_status_id', '=', 'ast.id')
            ->where('a.account_id', $scope->accountId)
            ->where('a.scheduled_date', $today)
            ->whereNull('a.deleted_at');

        $this->applyScopeFilters($query, $scope, 'a.');

        $appointments = $query
            ->select(
                'a.id',
                'a.appointment_type_id',
                'a.scheduled_time',
                'a.appointment_status_id',
                'a.doctor_id',
                'ast.name as status_name',
                'ast.is_arrived',
                'ast.is_cancelled',
                'p.name as patient_name',
                's.name as service_name',
                'l.name as location_name',
            )
            ->orderBy('a.scheduled_time')
            ->get();

        $treatments = $appointments->where('appointment_type_id', 2);
        $consultations = $appointments->where('appointment_type_id', AppointmentType::Consultancy->value);

        return [
            'total' => $appointments->count(),
            'treatments' => [
                'count' => $treatments->count(),
                'arrived' => $treatments->where('is_arrived', 1)->count(),
                'list' => $this->sortAppointmentList($treatments->map(fn (object $apt): array => $this->shapeAppointmentRow($apt))->all()),
            ],
            'consultations' => [
                'count' => $consultations->count(),
                'arrived' => $consultations->where('is_arrived', 1)->count(),
                'list' => $this->sortAppointmentList($consultations->map(fn (object $apt): array => $this->shapeAppointmentRow($apt))->all()),
            ],
        ];
    }

    /**
     * No-show rate over the period: cancelled appointments / total scheduled.
     *
     * @return array{rate: float, no_shows: int, total: int}
     */
    public function noShowRate(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return ['rate' => 0.0, 'no_shows' => 0, 'total' => 0];
        }

        $query = DB::table('appointments as a')
            ->leftJoin('appointment_statuses as ast', 'a.appointment_status_id', '=', 'ast.id')
            ->where('a.account_id', $scope->accountId)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->whereNull('a.deleted_at');

        $this->applyScopeFilters($query, $scope, 'a.');

        $row = $query
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN ast.is_cancelled = 1 THEN 1 ELSE 0 END) AS no_shows
            ')
            ->first();

        $total = (int) ($row->total ?? 0);
        $noShows = (int) ($row->no_shows ?? 0);

        return [
            'rate' => $total > 0 ? round(($noShows / $total) * 100, 1) : 0.0,
            'no_shows' => $noShows,
            'total' => $total,
        ];
    }

    /**
     * Thin wrapper around AppointmentCountsQuery — applies the scope's
     * branch + doctor filters and forwards everything else.
     *
     * @param  list<int>  $qualifyingStatusIds
     * @return array{all_count: int, done_count: int}
     */
    private function appointmentCounts(
        MetricScope $scope,
        DateRange $range,
        int $appointmentTypeId,
        array $qualifyingStatusIds,
    ): array {
        return AppointmentCountsQuery::forType(
            accountId: $scope->accountId,
            locationIds: $this->locationIdsForScope($scope),
            appointmentTypeId: $appointmentTypeId,
            qualifyingStatusIds: $qualifyingStatusIds,
            startDate: $range->startString(),
            endDate: $range->endString(),
            doctorId: ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor)
                ? $scope->resourceId
                : null,
        );
    }

    /**
     * Arrived-only count (used by compute()). Delegates to
     * AppointmentCountsQuery and returns just the done_count.
     *
     * @param  list<int>  $qualifyingStatusIds
     */
    private function countArrived(
        MetricScope $scope,
        DateRange $range,
        int $appointmentTypeId,
        array $qualifyingStatusIds,
    ): int {
        return $this->appointmentCounts($scope, $range, $appointmentTypeId, $qualifyingStatusIds)['done_count'];
    }

    /**
     * Scalar sales amount — cash-mode collection net of refunds for the
     * whole scope. Single source of truth lives in SalesLedgerQuery; the
     * per-centre sum = this scalar by construction.
     */
    private function salesAmount(MetricScope $scope, DateRange $range): float
    {
        return SalesLedgerQuery::totalNet($scope, $range);
    }

    /**
     * @return list<int>
     */
    private function locationIdsForScope(MetricScope $scope): array
    {
        return ($scope->isBranchScoped() && $scope->branchIds !== null)
            ? array_map('intval', $scope->branchIds)
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeAppointmentRow(object $apt): array
    {
        $sortPriority = 2;
        if ($apt->is_arrived) {
            $sortPriority = 1;
        } elseif ($apt->is_cancelled) {
            $sortPriority = 4;
        } elseif (
            $apt->status_name
            && ! in_array(strtolower((string) $apt->status_name), ['scheduled', 'confirmed'], true)
        ) {
            $sortPriority = 3;
        }

        return [
            'id' => (int) $apt->id,
            'type' => $apt->appointment_type_id === AppointmentType::Consultancy->value
                ? 'Consultation'
                : 'Treatment',
            'time' => $apt->scheduled_time
                ? Carbon::parse($apt->scheduled_time)->format('h:i A')
                : 'Unscheduled',
            'time_raw' => $apt->scheduled_time,
            'patient' => $apt->patient_name ?? 'N/A',
            'service' => $apt->service_name ?? 'N/A',
            'location' => $apt->location_name ?? 'N/A',
            'status' => $apt->status_name ?? 'N/A',
            'is_arrived' => (bool) $apt->is_arrived,
            'is_cancelled' => (bool) $apt->is_cancelled,
            'sort_priority' => $sortPriority,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortAppointmentList(array $rows): array
    {
        return collect($rows)
            ->sortBy([
                ['sort_priority', 'asc'],
                ['time_raw', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Apply branch + resource scope filters to a query. Still used by
     * today() and noShowRate() since those methods aren't a good fit for
     * the AppointmentCountsQuery helper (joins + status-name logic, not
     * just counts).
     */
    private function applyScopeFilters(Builder $query, MetricScope $scope, string $columnPrefix): void
    {
        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn("{$columnPrefix}location_id", $scope->branchIds);
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $query->where("{$columnPrefix}doctor_id", $scope->resourceId);
        }
    }
}
