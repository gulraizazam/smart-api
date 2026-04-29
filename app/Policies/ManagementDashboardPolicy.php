<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\Dashboard\Support\ResourceScopeResolver;

/**
 * Gate for Management Dashboard access. Branch visibility is delegated to
 * ResourceScopeResolver (data-driven via user_has_locations); this policy
 * only answers capability questions.
 */
final class ManagementDashboardPolicy
{
    public function __construct(
        private readonly ResourceScopeResolver $scope,
    ) {}

    public function view(User $user): bool
    {
        return $user->can('management_dashboard.view');
    }

    public function viewBranch(User $user, int $branchId): bool
    {
        return $user->can('management_dashboard.view')
            && $this->scope->canAccessBranch($user, $branchId);
    }

    public function manageTargets(User $user): bool
    {
        return $user->can('management_dashboard.manage_targets');
    }

    public function manageAlerts(User $user): bool
    {
        return $user->can('management_dashboard.manage_alerts');
    }

    public function acknowledgeAlerts(User $user): bool
    {
        return $user->can('management_dashboard.acknowledge_alerts');
    }

    /**
     * @return list<int>|null
     */
    public function allowedBranchIds(User $user): ?array
    {
        return $this->scope->allowedBranchIds($user);
    }
}
