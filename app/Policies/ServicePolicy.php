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
        return $user->can('services_manage') || $user->can('view_inactive_services');
    }

    /**
     * View inactive / archived services.
     */
    public function viewInactive(User $user): bool
    {
        return $user->can('view_inactive_services');
    }

    /**
     * Create a new service.
     */
    public function create(User $user): bool
    {
        return $user->can('services_manage');
    }

    /**
     * Update a service.
     */
    public function update(User $user): bool
    {
        return $user->can('services_manage');
    }

    /**
     * Delete / archive a service.
     */
    public function delete(User $user): bool
    {
        return $user->can('services_manage');
    }

    /**
     * Assign services to locations.
     */
    public function assignLocations(User $user): bool
    {
        return $user->can('services_manage');
    }

    /**
     * Manage service pricing history.
     */
    public function managePricing(User $user): bool
    {
        return $user->can('services_manage') || $user->can('services_pricing_manage');
    }
}
