<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Leads (potential patients).
 *
 * Gates use the `leads.<area>.<action>` catalog seeded by
 * 2026_05_21_120000_add_leads_module_permissions. Legacy snake_case
 * names (`leads_manage`, `lead_call`, `lead_email`) were removed
 * after the audit — `leads_view`, `lead_call`, and `lead_email` were
 * dangling code refs with no DB row.
 */
class LeadPolicy
{
    /**
     * View the leads list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('leads.list.view');
    }

    /**
     * View a single lead.
     */
    public function view(User $user): bool
    {
        return $user->can('leads.detail.view');
    }

    /**
     * Create a new lead.
     */
    public function create(User $user): bool
    {
        return $user->can('leads.create');
    }

    /**
     * Update a lead record.
     */
    public function update(User $user): bool
    {
        return $user->can('leads.edit');
    }

    /**
     * Delete a lead.
     */
    public function delete(User $user): bool
    {
        return $user->can('leads.delete');
    }

    /**
     * Make a call to a lead (view phone number for calling). Inherits
     * the same gate as viewing phone in the list — both are about
     * seeing the contact number.
     */
    public function call(User $user): bool
    {
        return $user->can('leads.list.view_contact');
    }

    /**
     * Send an email to a lead. No dedicated email action exists in the
     * SPA right now; gate on the contact view perm so the surface
     * stays consistent with `call()` until an email feature ships.
     */
    public function email(User $user): bool
    {
        return $user->can('leads.list.view_contact');
    }

    /**
     * Export lead data.
     */
    public function export(User $user): bool
    {
        return $user->can('leads.export');
    }
}
