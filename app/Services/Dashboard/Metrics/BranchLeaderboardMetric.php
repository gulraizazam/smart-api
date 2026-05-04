<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Conversion\ConversionService;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\Support\RevenueLedgerQuery;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Per-branch leaderboard for the Branches section.
 *
 * Performance: every metric is fetched as ONE bulk query grouped by location_id
 * across all branches in scope, then stitched together in PHP. Conversion /
 * no-show rates are computed from raw appointment counts at the branch level —
 * close enough to the per-doctor "validated conversion spend" semantics for a
 * leaderboard view, with dramatically lower query cost (~1s vs ~30s per-doctor
 * fan-out).
 *
 * Shared utilities used:
 *   - BranchResolver → "which branches are in scope?" + name lookup
 *   - RevenueLedgerQuery → canonical valid-revenue WHERE clause
 *
 * Cached 5 minutes per (account, scope, range) key.
 *
 * Return shape: { rows: list<branch_row> }
 */
final class BranchLeaderboardMetric implements Metric
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly BranchResolver $branches,
        private readonly ConversionService $conversion,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $cacheKey = 'mgmt_dash:branch_leaderboard:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope, $range));
    }

    /**
     * Per-doctor breakdown at a single branch for the hover card on the
     * Sales by Centre list. Doctor attribution uses the consulting doctor
     * (appointments.doctor_id) so the numbers answer "what did this
     * doctor's bookings produce" rather than "what did this user ring up".
     *
     * Produces two sales figures per doctor:
     *   - total_sales       = cash collected in range on ANY package whose
     *                         originating consult has this doctor
     *   - conversion_sales  = same, restricted to converted consults whose
     *                         converted_at falls in the window
     * Both use the canonical RevenueLedgerQuery filter on package_advances
     * so they reconcile with the branch Sales tile and the outer row's
     * net_revenue. `revenue` is preserved as an alias for total_sales so
     * legacy response consumers keep working.
     *
     * Three bulk queries (appointments + total_sales + conversion_sales)
     * stitched per doctor — same pattern the branch rollup uses.
     *
     * @return array{
     *   branch_id: int,
     *   branch_name: string|null,
     *   rows: list<array{doctor_id:int, doctor_name:string, appts_total:int, appts_arrived:int, appts_converted:int, conversion_rate:float|null, revenue:float, total_sales:float, conversion_sales:float, avg_value:float|null}>,
     *   totals: array{appts_total:int, appts_arrived:int, appts_converted:int, conversion_rate:float|null, revenue:float, total_sales:float, conversion_sales:float, avg_value:float|null},
     * }
     */
    public function branchDoctorBreakdown(MetricScope $scope, int $branchId, DateRange $range): array
    {
        $cacheKey = 'mgmt_dash:branch_doctor_breakdown:'
            .$scope->cacheKey()
            .'|b'.$branchId
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->buildBranchDoctorBreakdown($scope, $branchId, $range));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBranchDoctorBreakdown(MetricScope $scope, int $branchId, DateRange $range): array
    {
        $allowed = $this->branches->idsInScope($scope);
        if (! in_array($branchId, $allowed, true)) {
            return [
                'branch_id' => $branchId,
                'branch_name' => null,
                'rows' => [],
                'totals' => $this->emptyBreakdownTotals(),
            ];
        }

        $branchName = $this->branches->names([$branchId])[$branchId] ?? null;

        // Appointment aggregates per doctor — same semantic as
        // appointmentStatsByBranch but grouped by doctor_id and scoped to
        // a single branch.
        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();
        $apptRows = DB::table('appointments as a')
            ->where('a.account_id', $scope->accountId)
            ->where('a.location_id', $branchId)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->whereNotNull('a.doctor_id')
            ->whereNull('a.deleted_at')
            ->groupBy('a.doctor_id')
            ->select('a.doctor_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'SUM(CASE WHEN a.appointment_type_id = 1 AND a.appointment_status_id IN ('
                .implode(',', $consultationStatusIds)
                .') THEN 1 ELSE 0 END) AS arrived',
            )
            ->selectRaw('SUM(CASE WHEN a.appointment_type_id = 1 AND a.converted_at IS NOT NULL THEN 1 ELSE 0 END) AS converted')
            ->get();

        // Total Sales per doctor — ledger revenue from package_advances,
        // grouped by the consulting doctor on the package's originating
        // appointment. Uses the canonical RevenueLedgerQuery filter so the
        // number reconciles with the branch Sales tile.
        $totalSalesRows = $this->doctorSalesQuery($scope->accountId, $branchId, $range)
            ->groupBy('a.doctor_id')
            ->select('a.doctor_id')
            ->selectRaw($this->salesNetSelectRaw().' AS sales')
            ->get();

        // Conversion Sales per doctor — delegates to ConversionService's
        // validated pipeline (invoice → package_service → first-payment-
        // in-range → net>0) so the number matches /admin/reports/conversion
        // byte-for-byte instead of approximating with a converted_at gate.
        $conversionByDoctor = $this->conversion->byDoctorsAtBranch(
            $branchId,
            $range->startString(),
            $range->endString(),
            $scope->accountId,
        );

        $byDoctor = [];
        foreach ($apptRows as $r) {
            $id = (int) $r->doctor_id;
            $byDoctor[$id] = [
                'doctor_id' => $id,
                'appts_total' => (int) $r->total,
                'appts_arrived' => (int) $r->arrived,
                'appts_converted' => (int) $r->converted,
                'total_sales' => 0.0,
                'conversion_sales' => 0.0,
            ];
        }
        $ensure = function (int $id) use (&$byDoctor): void {
            $byDoctor[$id] ??= [
                'doctor_id' => $id,
                'appts_total' => 0,
                'appts_arrived' => 0,
                'appts_converted' => 0,
                'total_sales' => 0.0,
                'conversion_sales' => 0.0,
            ];
        };
        foreach ($totalSalesRows as $r) {
            $id = (int) $r->doctor_id;
            if ($id === 0) {
                continue;
            }
            $ensure($id);
            $byDoctor[$id]['total_sales'] = (float) $r->sales;
        }
        // Override every doctor's converted count with the validated
        // count from ConversionService so the numerator + denominator
        // of conversion_rate / avg_value come from the same pipeline
        // (matches admin/reports/conversion + Avg Conversion Value
        // sparkline). Crucially: doctors WITHOUT a conversionByDoctor
        // entry must have their `appts_converted` zeroed out — pre-fix
        // they kept the raw `converted_at IS NOT NULL` count from the
        // appointments query, which is gross of refunds and includes
        // appointments whose payments were later deleted. That left
        // phantom converted counts on doctors with no validated
        // conversions.
        foreach ($byDoctor as $id => $_) {
            $byDoctor[$id]['conversion_sales'] = (float) ($conversionByDoctor[$id]['spend'] ?? 0);
            $byDoctor[$id]['appts_converted'] = (int) ($conversionByDoctor[$id]['count'] ?? 0);
        }
        // Ensure any doctor that had ONLY conversion data (no raw
        // appointment row) still gets a row.
        foreach ($conversionByDoctor as $id => $v) {
            if ($id === 0 || isset($byDoctor[$id])) {
                continue;
            }
            $ensure($id);
            $byDoctor[$id]['conversion_sales'] = (float) ($v['spend'] ?? 0);
            $byDoctor[$id]['appts_converted'] = (int) ($v['count'] ?? 0);
        }

        $doctorIds = array_keys($byDoctor);
        $names = $doctorIds === []
            ? []
            : DB::table('users')->whereIn('id', $doctorIds)->pluck('name', 'id')->toArray();

        $rows = [];
        foreach ($byDoctor as $id => $d) {
            $rate = $d['appts_arrived'] > 0
                ? round(($d['appts_converted'] / $d['appts_arrived']) * 100, 1)
                : null;
            // Avg value anchors to conversion sales ÷ converted consults
            // — "what did a typical conversion generate for this doctor"
            // — which stays internally consistent (numerator and denominator
            // both refer to the same validated set).
            $avg = $d['appts_converted'] > 0
                ? round($d['conversion_sales'] / $d['appts_converted'], 2)
                : null;

            $rows[] = [
                'doctor_id' => $id,
                'doctor_name' => $names[$id] ?? "User #{$id}",
                'appts_total' => $d['appts_total'],
                'appts_arrived' => $d['appts_arrived'],
                'appts_converted' => $d['appts_converted'],
                'conversion_rate' => $rate,
                'total_sales' => round($d['total_sales'], 2),
                'conversion_sales' => round($d['conversion_sales'], 2),
                // Back-compat alias — legacy consumers read `revenue`.
                'revenue' => round($d['total_sales'], 2),
                'avg_value' => $avg,
            ];
        }

        // Sort: total_sales desc by default. Low-signal rows (zero consult
        // arrivals) land last so 100% conversion on 1 patient doesn't
        // dominate the top of the card.
        usort($rows, static function (array $a, array $b): int {
            $aLow = $a['appts_arrived'] < 3 ? 1 : 0;
            $bLow = $b['appts_arrived'] < 3 ? 1 : 0;
            if ($aLow !== $bLow) {
                return $aLow <=> $bLow;
            }

            return $b['total_sales'] <=> $a['total_sales'];
        });

        $sumTotal = array_sum(array_column($rows, 'appts_total'));
        $sumArrived = array_sum(array_column($rows, 'appts_arrived'));
        $sumConverted = array_sum(array_column($rows, 'appts_converted'));
        $sumTotalSales = array_sum(array_column($rows, 'total_sales'));
        $sumConversionSales = array_sum(array_column($rows, 'conversion_sales'));

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'rows' => $rows,
            'totals' => [
                'appts_total' => $sumTotal,
                'appts_arrived' => $sumArrived,
                'appts_converted' => $sumConverted,
                'conversion_rate' => $sumArrived > 0
                    ? round(($sumConverted / $sumArrived) * 100, 1)
                    : null,
                'total_sales' => round($sumTotalSales, 2),
                'conversion_sales' => round($sumConversionSales, 2),
                'revenue' => round($sumTotalSales, 2),
                'avg_value' => $sumConverted > 0 ? round($sumConversionSales / $sumConverted, 2) : null,
            ],
        ];
    }

    /**
     * Shared base query for the two per-doctor ledger sums. Joins
     * package_advances → packages → appointments so the caller can
     * further narrow on appointment attributes (converted_at, type, etc).
     *
     * Applies the canonical RevenueLedgerQuery filter on the advance
     * rows and the package + advance date-range scoping. Appointments are
     * filtered by branch so we don't cross-count cases where a package
     * was moved between locations mid-lifecycle.
     */
    private function doctorSalesQuery(int $accountId, int $branchId, DateRange $range): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('package_advances as pa')
            ->join('packages as p', 'p.id', '=', 'pa.package_id')
            ->join('appointments as a', 'a.id', '=', 'p.appointment_id')
            ->where('pa.account_id', $accountId)
            ->where('a.location_id', $branchId)
            ->whereNull('p.deleted_at')
            ->whereNull('a.deleted_at')
            ->whereNotNull('a.doctor_id')
            ->whereDate('pa.created_at', '>=', $range->startString())
            ->whereDate('pa.created_at', '<=', $range->endString());

        RevenueLedgerQuery::applyValidRevenueFilters($query, 'pa.');

        return $query;
    }

    /**
     * SQL fragment for the net-ledger sum (in − refund_out). Matches
     * RevenueLedgerQuery semantics; extracted so the two doctor queries
     * emit byte-identical aggregates.
     */
    private function salesNetSelectRaw(): string
    {
        return "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' THEN pa.cash_amount ELSE 0 END), 0)"
            .' - '
            ."COALESCE(SUM(CASE WHEN pa.cash_flow = 'out' AND pa.is_refund = 1 THEN pa.cash_amount ELSE 0 END), 0)";
    }

    /**
     * @return array{appts_total:int, appts_arrived:int, appts_converted:int, conversion_rate:float|null, revenue:float, total_sales:float, conversion_sales:float, avg_value:float|null}
     */
    private function emptyBreakdownTotals(): array
    {
        return [
            'appts_total' => 0,
            'appts_arrived' => 0,
            'appts_converted' => 0,
            'conversion_rate' => null,
            'total_sales' => 0.0,
            'conversion_sales' => 0.0,
            'revenue' => 0.0,
            'avg_value' => null,
        ];
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

        $prevRange = $range->previousPeriod();
        $branchNames = $this->branches->names($branchIds);
        $targetMap = $this->branchTargets($branchIds, $range, $scope->accountId);
        $revenueMap = $this->revenueByBranch($branchIds, $range, $scope->accountId);
        $prevRevenueMap = $this->revenueByBranch($branchIds, $prevRange, $scope->accountId);
        $apptStats = $this->appointmentStatsByBranch($branchIds, $range, $scope->accountId);
        $refundMap = $this->refundsByBranch($branchIds, $range, $scope->accountId);

        // Validated conversion numbers across ALL branches in one pass —
        // the same pipeline that powers /admin/reports/conversion and the
        // per-doctor tooltip. Previously this fanned out to one
        // ConversionService call per branch (12 × ~165ms). Now: one
        // candidate-fetch + one validation pass, partitioned by branch.
        $convByBranchDoctor = $this->conversion->byBranchAndDoctor(
            array_map('intval', $branchIds),
            $range->startString(),
            $range->endString(),
            $scope->accountId,
        );

        $rows = [];
        foreach ($branchIds as $branchId) {
            $revenue = (float) ($revenueMap[$branchId] ?? 0);
            $prevRevenue = (float) ($prevRevenueMap[$branchId] ?? 0);
            $stats = $apptStats[$branchId] ?? ['total' => 0, 'arrived' => 0, 'converted' => 0, 'cancelled' => 0];
            $refundTotal = (float) ($refundMap[$branchId] ?? 0);
            $target = $targetMap[$branchId] ?? null;

            $validatedConverted = 0;
            $validatedSpend = 0.0;
            foreach (($convByBranchDoctor[(int) $branchId] ?? []) as $r) {
                $validatedConverted += (int) ($r['count'] ?? 0);
                $validatedSpend += (float) ($r['spend'] ?? 0);
            }

            $percentToTarget = $target !== null && $target > 0
                ? round(($revenue / $target) * 100, 1)
                : null;

            $conversionRate = $stats['arrived'] > 0
                ? round(($validatedConverted / $stats['arrived']) * 100, 1)
                : 0.0;

            $noShowRate = $stats['total'] > 0
                ? round(($stats['cancelled'] / $stats['total']) * 100, 1)
                : 0.0;

            $avgValue = $validatedConverted > 0
                ? round($validatedSpend / $validatedConverted, 2)
                : 0.0;

            $momDelta = $prevRevenue > 0
                ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1)
                : null;

            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => $branchNames[$branchId] ?? "Branch #{$branchId}",
                'net_revenue' => round($revenue, 2),
                'target' => $target,
                'percent_to_target' => $percentToTarget,
                'prev_period_revenue' => round($prevRevenue, 2),
                'mom_delta_pct' => $momDelta,
                'appointments' => (int) $stats['total'],
                'no_show_rate' => $noShowRate,
                'conversion_rate' => $conversionRate,
                'avg_value' => $avgValue,
                'refund_total' => round($refundTotal, 2),
                'status' => $this->statusFor($percentToTarget, $noShowRate, $refundTotal, $revenue),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['net_revenue'] <=> $a['net_revenue']);

        return ['rows' => $rows];
    }

    /**
     * Bulk: net revenue (revenue_in - refund_out) per branch in one query,
     * using the canonical RevenueLedgerQuery filter.
     *
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function revenueByBranch(array $branchIds, DateRange $range, int $accountId): array
    {
        $query = DB::table('package_advances')
            ->where('account_id', $accountId)
            ->whereIn('location_id', $branchIds)
            ->whereDate('created_at', '>=', $range->startString())
            ->whereDate('created_at', '<=', $range->endString());

        RevenueLedgerQuery::applyValidRevenueFilters($query);

        $rows = $query
            ->select('location_id')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END), 0) AS revenue_in,
                COALESCE(SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END), 0) AS refund_out
            ")
            ->groupBy('location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = (float) $row->revenue_in - (float) $row->refund_out;
        }

        return $out;
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function refundsByBranch(array $branchIds, DateRange $range, int $accountId): array
    {
        $rows = DB::table('package_advances')
            ->where('account_id', $accountId)
            ->whereIn('location_id', $branchIds)
            ->whereDate('created_at', '>=', $range->startString())
            ->whereDate('created_at', '<=', $range->endString())
            ->whereNull('deleted_at')
            ->where('cash_flow', 'out')
            ->where('is_refund', 1)
            ->where('cash_amount', '>', 0)
            ->select('location_id')
            ->selectRaw('COALESCE(SUM(cash_amount), 0) AS refund_total')
            ->groupBy('location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = (float) $row->refund_total;
        }

        return $out;
    }

    /**
     * Bulk: appointment counts per branch in one grouped query.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{total: int, arrived: int, converted: int, cancelled: int}>
     */
    private function appointmentStatsByBranch(array $branchIds, DateRange $range, int $accountId): array
    {
        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();

        $rows = DB::table('appointments as a')
            ->leftJoin('appointment_statuses as ast', 'a.appointment_status_id', '=', 'ast.id')
            ->where('a.account_id', $accountId)
            ->whereIn('a.location_id', $branchIds)
            ->whereBetween('a.scheduled_date', [$range->startString(), $range->endString()])
            ->whereNull('a.deleted_at')
            ->select('a.location_id')
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN a.appointment_type_id = 1 AND a.appointment_status_id IN ('.implode(',', $consultationStatusIds).') THEN 1 ELSE 0 END) AS arrived,
                SUM(CASE WHEN a.appointment_type_id = 1 AND a.converted_at IS NOT NULL THEN 1 ELSE 0 END) AS converted,
                SUM(CASE WHEN ast.is_cancelled = 1 THEN 1 ELSE 0 END) AS cancelled
            ')
            ->groupBy('a.location_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->location_id] = [
                'total' => (int) $row->total,
                'arrived' => (int) $row->arrived,
                'converted' => (int) $row->converted,
                'cancelled' => (int) $row->cancelled,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<int, float>
     */
    private function branchTargets(array $branchIds, DateRange $range, int $accountId): array
    {
        $month = (int) Carbon::parse($range->startString())->format('m');
        $year = (int) Carbon::parse($range->startString())->format('Y');

        $rows = DB::table('centretargetmeta')
            ->join('centertarget as ct', 'centretargetmeta.centertarget_id', '=', 'ct.id')
            ->whereIn('centretargetmeta.location_id', $branchIds)
            ->where('ct.account_id', $accountId)
            ->where('ct.month', $month)
            ->where('ct.year', $year)
            ->select(
                'centretargetmeta.location_id',
                'centretargetmeta.target_amount',
                'ct.working_days',
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $workingDays = (int) ($row->working_days ?: 22);
            $map[(int) $row->location_id] = (float) $row->target_amount * $workingDays;
        }

        return $map;
    }

    private function statusFor(
        ?float $percentToTarget,
        float $noShowRate,
        float $refundTotal,
        float $revenue,
    ): string {
        if ($percentToTarget !== null && $percentToTarget < 75) {
            return 'red';
        }

        if ($noShowRate >= 20) {
            return 'red';
        }

        if ($revenue > 0 && ($refundTotal / max($revenue, 1)) >= 0.10) {
            return 'red';
        }

        if ($percentToTarget !== null && $percentToTarget < 90) {
            return 'amber';
        }

        if ($noShowRate >= 10) {
            return 'amber';
        }

        return 'green';
    }
}
