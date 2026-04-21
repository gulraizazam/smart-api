<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use Illuminate\Database\Query\Builder;

/**
 * Canonical "valid revenue row" filter for the package_advances ledger.
 *
 * The definition of revenue across the dashboard is:
 *   rows where cash_flow='in' AND is_adjustment=0 AND is_tax=0 AND is_cancel=0
 *   UNION
 *   rows where cash_flow='out' AND is_refund=1      (refund offsets)
 *
 * This filter matches DoctorDashboardService::getBranchRevenue() and
 * Operations::Monthlyachievedamount — the same definition used across the
 * app. Keeping it in one place means a semantic change (e.g. including
 * tax rows) happens in exactly one spot.
 */
final class RevenueLedgerQuery
{
    /**
     * Applies the canonical valid-revenue WHERE clause to a package_advances
     * query builder. Caller is responsible for other scoping (account_id,
     * location_id, date range).
     *
     * The caller may pass an alias-aware prefix (e.g. 'pa.') for joined
     * queries; defaults to unprefixed columns for bare table queries.
     */
    public static function applyValidRevenueFilters(Builder $query, string $columnPrefix = ''): Builder
    {
        $p = $columnPrefix;

        return $query
            ->where("{$p}cash_amount", '!=', 0)
            ->where(function (Builder $outer) use ($p): void {
                $outer->where(function (Builder $in) use ($p): void {
                    $in->where("{$p}cash_flow", 'in')
                        ->where("{$p}is_adjustment", 0)
                        ->where("{$p}is_tax", 0)
                        ->where("{$p}is_cancel", 0);
                })->orWhere(function (Builder $out) use ($p): void {
                    $out->where("{$p}cash_flow", 'out')
                        ->where("{$p}is_refund", 1);
                });
            });
    }
}
