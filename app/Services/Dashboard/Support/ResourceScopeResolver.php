<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Models\User;
use App\Models\UserHasLocations;
use Illuminate\Support\Facades\Config;

/**
 * Single authority for "which branches can this user see on the Management
 * Dashboard?" Every calculator, controller, and policy pulls the answer from
 * here.
 *
 * Semantics (mirror CashflowHelper::getUserBranches() — the established
 * project-wide rule for user→location scoping):
 *   - users.select_all = 1                          ⇒ company-wide (null)
 *   - user_has_locations contains "All Centres" id  ⇒ company-wide (null)
 *   - otherwise                                     ⇒ list<int> of physical
 *                                                    location ids (the virtual
 *                                                    All Centres id is never
 *                                                    returned to callers)
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

        if ((bool) ($user->select_all ?? false)) {
            return $this->cache[$userId] = null;
        }

        $allCentresId = (int) Config::get('constants.all_centres_location_id');

        $locationIds = UserHasLocations::query()
            ->where('user_id', $userId)
            ->pluck('location_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($allCentresId !== 0 && in_array($allCentresId, $locationIds, true)) {
            return $this->cache[$userId] = null;
        }

        $physical = array_values(array_filter(
            $locationIds,
            static fn (int $id): bool => $id !== $allCentresId,
        ));

        return $this->cache[$userId] = $physical;
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
     * Forget cached result for a user — call after admin UI edits user_has_locations.
     */
    public function invalidate(int $userId): void
    {
        unset($this->cache[$userId]);
    }
}
