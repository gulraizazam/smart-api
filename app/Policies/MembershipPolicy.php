<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Memberships and Membership Codes.
 */
class MembershipPolicy
{
    /**
     * View the memberships list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('memberships_manage');
    }

    /**
     * View a single membership record.
     */
    public function view(User $user): bool
    {
        return $user->can('memberships_manage');
    }

    /**
     * Create a new membership.
     */
    public function create(User $user): bool
    {
        return $user->can('memberships_manage');
    }

    /**
     * Update a membership record.
     */
    public function update(User $user): bool
    {
        return $user->can('memberships_manage');
    }

    /**
     * Delete / revoke a membership.
     */
    public function delete(User $user): bool
    {
        return $user->can('memberships_manage');
    }

    /**
     * Manage membership codes (generate, assign, revoke codes).
     */
    public function manageCodes(User $user): bool
    {
        return $user->can('membership_codes_manage') || $user->can('memberships_manage');
    }

    /**
     * Assign a membership code to a patient.
     */
    public function assignCode(User $user): bool
    {
        return $user->can('membership_codes_manage') || $user->can('memberships_manage');
    }

    /**
     * Export membership data.
     */
    public function export(User $user): bool
    {
        return $user->can('memberships_manage') || $user->can('memberships_export');
    }
}
