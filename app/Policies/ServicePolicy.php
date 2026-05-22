<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Services (treatments/service catalogue).
 */
class ServicePolicy
{
    /**
     * View the services list (active services only for non-privileged users).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('services.list.view') || $user->can('services.list.view_inactive');
    }

    /**
     * View inactive / archived services.
     */
    public function viewInactive(User $user): bool
    {
        return $user->can('services.list.view_inactive');
    }

    /**
     * Create a new service.
     */
    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    /**
     * Update a service.
     */
    public function update(User $user): bool
    {
        return $user->can('services.edit');
    }

    /**
     * Delete / archive a service.
     */
    public function delete(User $user): bool
    {
        return $user->can('services.destroy');
    }

    /**
     * Assign services to locations. Treated as an edit-class action since
     * the legacy umbrella `services_manage` has been retired; assignment
     * still mutates service<->location mapping, so reuse `services.edit`.
     */
    public function assignLocations(User $user): bool
    {
        return $user->can('services.edit');
    }

    /**
     * Manage service pricing history. Pricing edits land on the same row
     * as other service updates, so this policy collapses to `services.edit`
     * (legacy alt `services_pricing_manage` was never seeded in the DB).
     */
    public function managePricing(User $user): bool
    {
        return $user->can('services.edit');
    }
}
