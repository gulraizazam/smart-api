<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "which physical branches are in this scope?"
 * and "what are their names?" — used by every metric that needs to iterate
 * branches or format a branch row.
 *
 * Filters out virtual rollup entries ("All Centres", "All South Region", etc.)
 * in one place so individual metric classes don't have to know about them.
 */
final class BranchResolver
{
    /** @var array<string, list<int>> */
    private array $idsCache = [];

    /** @var array<string, array<int, string>> */
    private array $namesCache = [];

    /**
     * Physical branch IDs for the scope. If the scope explicitly pins a
     * branch subset, returns those (trusts the scope-resolver which already
     * enforces per-user_branches authorization). Otherwise returns every
     * non-deleted, non-virtual location on the account.
     *
     * @return list<int>
     */
    public function idsInScope(MetricScope $scope): array
    {
        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            return $scope->branchIds;
        }

        $key = (string) $scope->accountId;
        if (isset($this->idsCache[$key])) {
            return $this->idsCache[$key];
        }

        return $this->idsCache[$key] = array_map(
            'intval',
            DB::table('locations')
                ->where('account_id', $scope->accountId)
                ->whereNull('deleted_at')
                // Virtual "All X" rollup entries aren't physical branches.
                ->where('name', 'not like', 'All %')
                ->pluck('id')
                ->toArray(),
        );
    }

    /**
     * Name lookup for a set of branch IDs.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        sort($ids);
        $key = implode(',', $ids);
        if (isset($this->namesCache[$key])) {
            return $this->namesCache[$key];
        }

        return $this->namesCache[$key] = DB::table('locations')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(static fn (string $name): string => $name)
            ->toArray();
    }
}
