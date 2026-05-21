<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Memberships and Membership Codes.
 *
 * Now uses the dotted `memberships.*` catalog. Legacy `memberships_manage`
 * collapsed list + detail; the new catalog splits them.
 */
class MembershipPolicy
{
    /**
     * View the memberships list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('memberships.list.view');
    }

    /**
     * View a single membership record.
     */
    public function view(User $user): bool
    {
        return $user->can('memberships.detail.view');
    }

    /**
     * Create a new membership.
     */
    public function create(User $user): bool
    {
        return $user->can('memberships.create');
    }

    /**
     * Update a membership record.
     */
    public function update(User $user): bool
    {
        return $user->can('memberships.edit');
    }

    /**
     * Delete / revoke a membership.
     */
    public function delete(User $user): bool
    {
        return $user->can('memberships.destroy');
    }

    /**
     * Manage membership codes (generate, assign, revoke codes).
     */
    public function manageCodes(User $user): bool
    {
        return $user->can('memberships.codes.manage');
    }

    /**
     * Assign a membership code to a patient.
     */
    public function assignCode(User $user): bool
    {
        return $user->can('memberships.codes.manage');
    }

    /**
     * Export membership data.
     */
    public function export(User $user): bool
    {
        return $user->can('memberships.export');
    }
}
