<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for admin User management
 * (creating/editing staff accounts, roles, permissions, user types).
 *
 * This is distinct from PatientPolicy which covers patient-facing operations.
 */
class UserPolicy
{
    /**
     * View the staff/admin users list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users_manage');
    }

    /**
     * Create a new staff user.
     */
    public function create(User $user): bool
    {
        return $user->can('users_manage');
    }

    /**
     * Update a staff user record.
     */
    public function update(User $user): bool
    {
        return $user->can('users_manage');
    }

    /**
     * Delete a staff user.
     */
    public function delete(User $user): bool
    {
        return $user->can('users_manage');
    }

    /**
     * Manage user types (admin, doctor, CSR, etc.).
     */
    public function manageTypes(User $user): bool
    {
        return $user->can('user_types_manage');
    }

    /**
     * Manage roles (create, edit, delete role definitions).
     */
    public function manageRoles(User $user): bool
    {
        return $user->can('roles_manage');
    }

    /**
     * Assign or revoke roles from a user.
     */
    public function assignRoles(User $user): bool
    {
        return $user->can('roles_manage');
    }

    /**
     * Manage permissions (create, edit, delete permission definitions).
     */
    public function managePermissions(User $user): bool
    {
        return $user->can('permissions_manage');
    }

    /**
     * Assign or revoke permissions from a role/user.
     */
    public function assignPermissions(User $user): bool
    {
        return $user->can('permissions_manage');
    }

    /**
     * View user activity / audit trail.
     */
    public function viewActivity(User $user): bool
    {
        return $user->can('users_manage') || $user->can('activity_log_view');
    }
}
