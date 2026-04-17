<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Helpers\DashboardHelper;
use Illuminate\Support\Facades\DB;

/**
 * Canonical "revenue consumed" aggregate over paid invoices. Mirrors the
 * long-standing definition on /admin/home's "Revenue" tile: sum of
 * `invoices.total_price` where `invoice_status_id = paid` in the period,
 * scoped to account + branches.
 *
 * Before this helper existed, the same rule was re-implemented in
 * DashboardRevenueService::getSalesByCentre() (Eloquent, relies on tenant
 * trait for account scoping) and AppointmentsMetric::revenueConsumedAmount
 * (DB facade, explicit account_id). This class is the single source of
 * truth so the home page and Management Dashboard can never drift.
 */
final class RevenueInvoiceQuery
{
    /**
     * @param  list<int>  $locationIds  (empty = no branch filter)
     */
    public static function paidTotal(
        int $accountId,
        array $locationIds,
        string $startDate,
        string $endDate,
    ): float {
        $paidStatusId = DashboardHelper::getPaidInvoiceStatusId();
        if ($paidStatusId === null) {
            return 0.0;
        }

        $query = DB::table('invoices')
            ->where('account_id', $accountId)
            ->where('invoice_status_id', $paidStatusId)
            ->whereBetween('created_at', [
                $startDate.' 00:00:00',
                $endDate.' 23:59:59',
            ]);

        if ($locationIds !== []) {
            $query->whereIn('location_id', $locationIds);
        }

        return round((float) $query->sum('total_price'), 2);
    }
}
