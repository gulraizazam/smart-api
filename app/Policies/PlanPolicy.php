<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Plans and Packages.
 *
 * "Plans" (PlanInvoice / Packages) share the same permission group.
 * Package-level CRUD uses packages_* strings; plan management uses plans_manage.
 */
class PlanPolicy
{
    /**
     * View the packages / plans list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('packages_manage') || $user->can('plans_manage');
    }

    /**
     * View a single package / plan.
     */
    public function view(User $user): bool
    {
        return $user->can('packages_manage') || $user->can('plans_manage');
    }

    /**
     * Create a new package / plan.
     */
    public function create(User $user): bool
    {
        return $user->can('packages_manage');
    }

    /**
     * Edit an existing package / plan.
     */
    public function update(User $user): bool
    {
        return $user->can('packages_edit') || $user->can('packages_manage');
    }

    /**
     * Delete a package / plan.
     */
    public function delete(User $user): bool
    {
        return $user->can('packages_destroy');
    }

    /**
     * Manage plan-level settings and consumption rules.
     */
    public function managePlans(User $user): bool
    {
        return $user->can('plans_manage');
    }

    /**
     * Create a new plan (plans_create permission).
     */
    public function createPlan(User $user): bool
    {
        return $user->can('plans_create') || $user->can('plans_manage');
    }

    /**
     * Edit an existing plan.
     */
    public function editPlan(User $user): bool
    {
        return $user->can('plans_edit') || $user->can('plans_manage');
    }

    /**
     * Delete / destroy a plan.
     */
    public function destroyPlan(User $user): bool
    {
        return $user->can('plans_destroy');
    }

    /**
     * Mark a plan as inactive / archived.
     */
    public function inactivatePlan(User $user): bool
    {
        return $user->can('plans_inactive') || $user->can('plans_manage');
    }

    /**
     * View plan activity log.
     */
    public function viewLog(User $user): bool
    {
        return $user->can('plans_log') || $user->can('plans_manage');
    }

    /**
     * Allocate a discount to a package.
     */
    public function allocateDiscount(User $user): bool
    {
        return $user->can('discounts_allocate');
    }

    /**
     * Transfer a package between patients.
     */
    public function transfer(User $user): bool
    {
        return $user->can('packages_manage') || $user->can('packages_transfer');
    }

    /**
     * Manage bundles (service groups within packages).
     */
    public function manageBundles(User $user): bool
    {
        return $user->can('packages_manage') || $user->can('bundles_manage');
    }
}
