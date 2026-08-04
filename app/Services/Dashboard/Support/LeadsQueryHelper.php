<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Small helper the leads-dashboard metric classes reuse to build the
 * "filtered leads" base query.
 *
 * Every metric answers a question about "the leads created inside the
 * caller's range, scoped to their tenant and to their allowed branches" —
 * the guard rails are identical across all of them, so centralising them
 * here means one place to fix if the scoping rules ever change.
 */
final class LeadsQueryHelper
{
    /**
     * `leads as l`, filtered by:
     *   • account_id
     *   • deleted_at IS NULL
     *   • created_at BETWEEN range
     *   • branch scope (location_id IN allowed OR NULL — matches
     *     Leads::scopeForBranches semantics; NULL is the shared/
     *     unassigned pool)
     *
     * Returns the raw Query\Builder so callers can join, group, and
     * project as they need.
     */
    public static function filtered(MetricScope $scope, DateRange $range): Builder
    {
        $q = DB::table('leads as l')
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [
                $range->startString().' 00:00:00',
                $range->endString().' 23:59:59',
            ]);

        if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
            $branches = $scope->branchIds;
            $q->where(function ($qq) use ($branches): void {
                $qq->whereIn('l.location_id', $branches)->orWhereNull('l.location_id');
            });
        }

        return $q;
    }
}
