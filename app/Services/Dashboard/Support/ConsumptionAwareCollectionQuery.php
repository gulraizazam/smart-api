<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Consumption-aware collection attribution: attributes each payment to
 * real delivered services when the payment is directly linked to an
 * arrived appointment, and pro-rates only the remaining (unlinked /
 * not-yet-arrived) payments across the package's un-consumed services.
 *
 * Legacy behavior pro-rated every payment across all package services by
 * price share regardless of delivery. That skewed revenue-by-service for
 * packages where some sessions were still pending.
 *
 * Rule (per package_advance row):
 *   - Direct: `pa.appointment_id` is set, appointment exists (not soft-
 *     or hard-deleted), AND `appointment_status_id = 2` (arrived) →
 *     attribute the full `cash_amount` to `appointments.service_id`.
 *   - Pro-rated residual: every other row → pro-rate `cash_amount` across
 *     the package's `package_services` rows where `is_consumed = 0`,
 *     weighted by `tax_including_price`. If the package is fully
 *     delivered (no un-consumed rows), fall back to pro-rating across
 *     all services so late payments still attribute.
 *
 * Refunds (`cash_flow = 'out'` AND `is_refund = 1`) follow the same rule
 * with a negated sign. Cash-ins are gated to valid payment modes
 * (see SalesLedgerQuery::VALID_PAYMENT_MODES).
 *
 * Month filter is `pa.created_at` (payment date, not delivery date) —
 * matches the historical revenue-recognition semantics of the dashboard.
 *
 * Both query methods run ONCE for a whole window and bucket by
 * `YYYY-MM` in SQL (sumByMonthAndService) or aggregate into a single
 * total (sumByService). A trailing-12-month trend therefore costs two
 * queries, not 24.
 */
final class ConsumptionAwareCollectionQuery
{
    /**
     * Window-level aggregate. Delegates to the batched monthly variant
     * and sums across months so a single call site can still get a
     * flat `[serviceId => amount]` map for one window.
     *
     * @return array<int, float>
     */
    public static function sumByService(MetricScope $scope, string $startDate, string $endDate): array
    {
        $byMonth = self::sumByMonthAndService($scope, $startDate, $endDate);

        $out = [];
        foreach ($byMonth as $services) {
            foreach ($services as $serviceId => $val) {
                $out[$serviceId] = ($out[$serviceId] ?? 0.0) + $val;
            }
        }

        return $out;
    }

    /**
     * Batched: returns net collection per (YYYY-MM, serviceId) across the
     * whole window with just two queries (direct + pro-rated), each
     * grouping by month+service in SQL. Callers that render a monthly
     * trend index directly by month label.
     *
     * @return array<string, array<int, float>>  [ 'YYYY-MM' => [ serviceId => amount ] ]
     */
    public static function sumByMonthAndService(
        MetricScope $scope,
        string $startDate,
        string $endDate,
    ): array {
        $out = [];

        self::mergeRows($out, self::directRows($scope, $startDate, $endDate));
        self::mergeRows($out, self::proratedRows($scope, $startDate, $endDate));

        return $out;
    }

    /**
     * @param  array<string, array<int, float>>  $out
     * @param  iterable<object>  $rows
     */
    private static function mergeRows(array &$out, iterable $rows): void
    {
        foreach ($rows as $row) {
            $month = (string) $row->month_label;
            // Legacy packages occasionally have package_services rows with
            // a NULL service_id (data bug). We still need to count that
            // revenue — matching pre-rewrite behavior — so we bucket it
            // under `service_id = 0`. Downstream lookups by real service
            // id won't match, so the row naturally falls into "Other".
            $service = $row->service_id === null ? 0 : (int) $row->service_id;
            $out[$month] ??= [];
            $out[$month][$service] = ($out[$month][$service] ?? 0.0) + (float) $row->total;
        }
    }

    /**
     * Direct-attribution rows grouped by (month, service).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function directRows(MetricScope $scope, string $startDate, string $endDate)
    {
        $modes = "'".implode("','", SalesLedgerQuery::VALID_PAYMENT_MODES)."'";

        // Only direct-attribute when the appointment points at a leaf
        // service (has a parent category). Many appointments in legacy
        // data use the category id as `service_id` — we don't actually
        // know the specific service there, so those fall through to
        // pro-rating where the package's leaf services distribute the
        // payment.
        $query = DB::table('package_advances as pa')
            ->join('payment_modes as pm', 'pa.payment_mode_id', '=', 'pm.id')
            ->join('appointments as a', 'a.id', '=', 'pa.appointment_id')
            ->join('services as s_a', 's_a.id', '=', 'a.service_id')
            ->where('pa.account_id', $scope->accountId)
            ->whereBetween('pa.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('pa.is_adjustment', 0)
            ->where('pa.is_tax', 0)
            ->where('pa.is_cancel', 0)
            ->where('pa.cash_amount', '!=', 0)
            ->whereNull('pa.deleted_at')
            ->whereNotNull('pa.appointment_id')
            ->where('a.appointment_status_id', 2)
            ->whereNull('a.deleted_at')
            ->whereNotNull('s_a.parent_id');

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('pa.location_id', $scope->branchIds);
        }

        return $query
            ->selectRaw(
                "DATE_FORMAT(pa.created_at, '%Y-%m') AS month_label, ".
                'a.service_id AS service_id, '.
                'SUM(CASE '.
                "  WHEN pa.cash_flow = 'in' AND pm.name IN ({$modes}) THEN pa.cash_amount ".
                "  WHEN pa.cash_flow = 'out' AND pa.is_refund = 1 THEN -1 * pa.cash_amount ".
                '  ELSE 0 '.
                'END) AS total'
            )
            ->groupBy('month_label', 'a.service_id')
            ->get();
    }

    /**
     * Pro-rated residual rows grouped by (month, service). A row lands in
     * this bucket when it is NOT a direct match — i.e. no linked
     * appointment, the linked appointment is missing (hard-deleted) or
     * soft-deleted, or its status is not arrived. Explicit NULL checks
     * cover the orphan case that `!= 2` would otherwise drop.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function proratedRows(MetricScope $scope, string $startDate, string $endDate)
    {
        $modes = "'".implode("','", SalesLedgerQuery::VALID_PAYMENT_MODES)."'";

        // Denominator switches to total_price when no un-consumed services
        // remain (package fully delivered) so late payments still attribute.
        $denom = 'CASE WHEN pkg_totals.unconsumed_price > 0 THEN pkg_totals.unconsumed_price ELSE pkg_totals.total_price END';

        $query = DB::table('package_advances as pa')
            ->join('payment_modes as pm', 'pa.payment_mode_id', '=', 'pm.id')
            ->leftJoin('appointments as a', 'a.id', '=', 'pa.appointment_id')
            ->leftJoin('services as s_a', 's_a.id', '=', 'a.service_id')
            ->join('package_services as ps', 'ps.package_id', '=', 'pa.package_id')
            ->joinSub(
                DB::table('package_services')
                    ->select('package_id')
                    ->selectRaw('SUM(tax_including_price) AS total_price')
                    ->selectRaw('SUM(CASE WHEN is_consumed = 0 THEN tax_including_price ELSE 0 END) AS unconsumed_price')
                    ->groupBy('package_id'),
                'pkg_totals',
                'pkg_totals.package_id',
                '=',
                'pa.package_id',
            )
            ->where('pa.account_id', $scope->accountId)
            ->whereBetween('pa.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('pa.is_adjustment', 0)
            ->where('pa.is_tax', 0)
            ->where('pa.is_cancel', 0)
            ->where('pa.cash_amount', '!=', 0)
            ->whereNull('pa.deleted_at')
            ->where('pkg_totals.total_price', '>', 0)
            // Not direct: unlinked, orphaned, soft-deleted, not arrived,
            // OR the appointment points at a top-level category instead
            // of a leaf service (in which case we don't know the specific
            // service and must pro-rate across the package's leaves).
            ->where(function ($q): void {
                $q->whereNull('pa.appointment_id')
                    ->orWhereNull('a.id')
                    ->orWhereNotNull('a.deleted_at')
                    ->orWhere('a.appointment_status_id', '!=', 2)
                    ->orWhereNull('a.appointment_status_id')
                    ->orWhereNull('s_a.parent_id');
            })
            // Only un-consumed rows; fall back to all when the package is
            // fully delivered so the payment still lands somewhere.
            ->where(function ($q): void {
                $q->where('ps.is_consumed', 0)
                    ->orWhereRaw('pkg_totals.unconsumed_price = 0');
            });

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('pa.location_id', $scope->branchIds);
        }

        return $query
            ->selectRaw(
                "DATE_FORMAT(pa.created_at, '%Y-%m') AS month_label, ".
                'ps.service_id AS service_id, '.
                'SUM(CASE '.
                "  WHEN pa.cash_flow = 'in' AND pm.name IN ({$modes}) ".
                "    THEN pa.cash_amount * ps.tax_including_price / ({$denom}) ".
                "  WHEN pa.cash_flow = 'out' AND pa.is_refund = 1 ".
                "    THEN -1 * pa.cash_amount * ps.tax_including_price / ({$denom}) ".
                '  ELSE 0 '.
                'END) AS total'
            )
            ->groupBy('month_label', 'ps.service_id')
            ->get();
    }
}
