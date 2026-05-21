<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Plans.
 *
 * Uses the dotted `plans.*` catalog (added in 2026_05_31_120000). The
 * previous policy mixed `packages_*` and `plans_*` strings — those were
 * collapsed because in this codebase Plans and the old "Packages" feature
 * map to the same `packages` DB table, and ops should only see one
 * permission group.
 */
class PlanPolicy
{
    /**
     * View the plans list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('plans.list.view');
    }

    /**
     * View a single plan.
     */
    public function view(User $user): bool
    {
        return $user->can('plans.detail.view') || $user->can('plans.list.view');
    }

    /**
     * Create a new plan.
     */
    public function create(User $user): bool
    {
        return $user->can('plans.create');
    }

    /**
     * Edit an existing plan.
     */
    public function update(User $user): bool
    {
        return $user->can('plans.edit');
    }

    /**
     * Delete a plan.
     */
    public function delete(User $user): bool
    {
        return $user->can('plans.destroy');
    }

    /**
     * Manage plan-level settings and consumption rules.
     */
    public function managePlans(User $user): bool
    {
        return $user->can('plans.edit') || $user->can('plans.list.view');
    }

    /**
     * Create a new plan.
     */
    public function createPlan(User $user): bool
    {
        return $user->can('plans.create');
    }

    /**
     * Edit an existing plan.
     */
    public function editPlan(User $user): bool
    {
        return $user->can('plans.edit');
    }

    /**
     * Delete / destroy a plan.
     */
    public function destroyPlan(User $user): bool
    {
        return $user->can('plans.destroy');
    }

    /**
     * Mark a plan as inactive / archived.
     */
    public function inactivatePlan(User $user): bool
    {
        return $user->can('plans.deactivate');
    }

    /**
     * View plan activity log.
     */
    public function viewLog(User $user): bool
    {
        return $user->can('plans.log.view');
    }

    /**
     * Allocate a discount to a plan.
     */
    public function allocateDiscount(User $user): bool
    {
        return $user->can('discounts.allocate');
    }

    /**
     * Transfer a plan between patients.
     */
    public function transfer(User $user): bool
    {
        return $user->can('plans.transfer');
    }

    /**
     * Manage bundles (service groups within plans).
     */
    public function manageBundles(User $user): bool
    {
        return $user->can('plans.edit');
    }
}
