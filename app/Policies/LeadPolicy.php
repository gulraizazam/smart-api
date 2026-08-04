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
     * Place a click-to-call to a lead (WebRTC softphone in the drawer).
     * Was previously gated on `leads.list.view_contact` when "call" meant
     * "view the phone number so the agent can dial it from their own phone".
     * Now that the SPA has a real dialer + records the call, this is a
     * strictly bigger surface — a role should be able to see phone numbers
     * without being trusted to initiate outbound calls on the company's
     * caller-ID. Gated on the dedicated `leads.call` slug seeded by
     * 2026_08_04_120200_add_leads_call_permission.
     */
    public function call(User $user): bool
    {
        return $user->can('leads.call');
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
