<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Models\User;
use App\Models\UserBranch;

/**
 * Single authority for "which branches can this user see on the Management
 * Dashboard?" Every calculator, controller, and policy pulls the answer from
 * here.
 *
 * Semantics:
 *   - no rows in user_branches  ⇒ company-wide (returns null)
 *   - rows in user_branches     ⇒ regional subset (returns list<int>)
 *
 * Per-request cache avoids re-querying the pivot on every widget.
 */
final class ResourceScopeResolver
{
    /** @var array<int, ?array<int, int>> */
    private array $cache = [];

    /**
     * @return list<int>|null null = all branches (company view); array = regional subset
     */
    public function allowedBranchIds(User $user): ?array
    {
        $userId = (int) $user->id;

        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $rows = UserBranch::query()
            ->where('account_id', (int) $user->account_id)
            ->where('user_id', $userId)
            ->pluck('location_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $this->cache[$userId] = $rows === [] ? null : $rows;

        return $this->cache[$userId];
    }

    public function canAccessBranch(User $user, int $branchId): bool
    {
        $allowed = $this->allowedBranchIds($user);

        return $allowed === null || in_array($branchId, $allowed, true);
    }

    public function isCompanyWide(User $user): bool
    {
        return $this->allowedBranchIds($user) === null;
    }

    /**
     * Forget cached result for a user — call after the admin UI edits user_branches.
     */
    public function invalidate(int $userId): void
    {
        unset($this->cache[$userId]);
    }
}
