<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Leads (potential patients).
 */
class LeadPolicy
{
    /**
     * View the leads list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('leads_manage') || $user->can('leads_view');
    }

    /**
     * View a single lead.
     */
    public function view(User $user): bool
    {
        return $user->can('leads_manage') || $user->can('leads_view');
    }

    /**
     * Create a new lead.
     */
    public function create(User $user): bool
    {
        return $user->can('leads_create') || $user->can('leads_manage');
    }

    /**
     * Update a lead record.
     */
    public function update(User $user): bool
    {
        return $user->can('leads_edit') || $user->can('leads_manage');
    }

    /**
     * Delete a lead.
     */
    public function delete(User $user): bool
    {
        return $user->can('leads_destroy');
    }

    /**
     * Make a call to a lead (view phone number for calling).
     */
    public function call(User $user): bool
    {
        return $user->can('lead_call') || $user->can('contact');
    }

    /**
     * Send an email to a lead.
     */
    public function email(User $user): bool
    {
        return $user->can('lead_email') || $user->can('leads_manage');
    }

    /**
     * Export lead data.
     */
    public function export(User $user): bool
    {
        return $user->can('leads_manage') || $user->can('leads_export');
    }
}
