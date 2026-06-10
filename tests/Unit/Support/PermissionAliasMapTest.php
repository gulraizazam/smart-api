<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PermissionAliasMap;
use Tests\TestCase;

/**
 * Pins the legacy↔dotted permission bridge (go-live §5.3) and the 1-to-many
 * inverse fix. Without these, roles migrated to the dotted catalog 403 on the
 * still-legacy Gate::allows() checks in Api\LeadsController at cutover.
 */
class PermissionAliasMapTest extends TestCase
{
    public function test_legacy_leads_manage_resolves_to_both_dotted_views(): void
    {
        // leads_manage historically guarded BOTH the list and the detail view,
        // so it must alias to both dotted slugs (the 1-to-many inverse). The
        // earlier last-wins inverse silently dropped one.
        $aliases = PermissionAliasMap::aliasesFor('leads_manage');

        $this->assertContains('leads.list.view', $aliases);
        $this->assertContains('leads.detail.view', $aliases);
    }

    public function test_leads_bridge_resolves_in_both_directions(): void
    {
        $this->assertSame(['leads_create'], PermissionAliasMap::aliasesFor('leads.create'));
        $this->assertContains('leads.create', PermissionAliasMap::aliasesFor('leads_create'));

        $this->assertSame(['leads_destroy'], PermissionAliasMap::aliasesFor('leads.delete'));
        $this->assertContains('leads.delete', PermissionAliasMap::aliasesFor('leads_destroy'));

        $this->assertSame(['view_inactive_leads'], PermissionAliasMap::aliasesFor('leads.list.view_inactive'));
        $this->assertContains('leads.list.view_inactive', PermissionAliasMap::aliasesFor('view_inactive_leads'));
    }

    public function test_legacy_contact_bridges_to_leads_and_consultations_view_contact(): void
    {
        // crm2 reveals the lead/appointment phone under the broad `contact`
        // perm; crm3 gates it on the granular `*.list.view_contact` slugs. The
        // bridge must let a `contact` grant satisfy those gates at run time —
        // otherwise a `contact` holder sees the phone on patients (already
        // bridged) but not on leads/consultations.
        $this->assertContains('contact', PermissionAliasMap::aliasesFor('leads.list.view_contact'));
        $this->assertContains('contact', PermissionAliasMap::aliasesFor('consultations.list.view_contact'));

        // Inverse: a `contact` grant surfaces every contact gate (patients
        // bridge predates this; leads + consultations are the new ones).
        $contactAliases = PermissionAliasMap::aliasesFor('contact');
        $this->assertContains('leads.list.view_contact', $contactAliases);
        $this->assertContains('consultations.list.view_contact', $contactAliases);
        $this->assertContains('patients.list.view_contact', $contactAliases);
    }

    public function test_existing_plans_aliases_are_unchanged_by_the_one_to_many_fix(): void
    {
        // Regression guard: the inverse change must not alter pre-existing aliases.
        $this->assertSame(['plans_log', 'patients_plan_log'], PermissionAliasMap::aliasesFor('plans.log.view'));
        $this->assertContains('plans.list.view', PermissionAliasMap::aliasesFor('plans_manage'));
        $this->assertContains('plans.list.view', PermissionAliasMap::aliasesFor('patients_plan_manage'));
    }

    public function test_unknown_ability_has_no_aliases(): void
    {
        $this->assertSame([], PermissionAliasMap::aliasesFor('nonexistent_permission_xyz'));
    }

    public function test_blade_route_dotted_slugs_bridge_to_legacy_umbrellas(): void
    {
        // §5.4: legacy Blade routes re-pointed to dotted slugs must still pass for
        // a role holding only the legacy umbrella (and vice-versa).
        $this->assertSame(['services_manage'], PermissionAliasMap::aliasesFor('services.list.view'));
        $this->assertContains('services.list.view', PermissionAliasMap::aliasesFor('services_manage'));

        $this->assertSame(['invoices_manage'], PermissionAliasMap::aliasesFor('invoices.list.view'));
        $this->assertSame(['patients_manage'], PermissionAliasMap::aliasesFor('patients.list.view'));
        $this->assertSame(['resourcerotas_manage'], PermissionAliasMap::aliasesFor('scheduling_shifts.list.view'));
        $this->assertSame(['cashflow_manage'], PermissionAliasMap::aliasesFor('cashflow.manage'));

        // packages_manage now bridges to BOTH detail + list (1-to-many).
        $packages = PermissionAliasMap::aliasesFor('packages_manage');
        $this->assertContains('packages.detail.view', $packages);
        $this->assertContains('packages.list.view', $packages);
    }

    public function test_granular_cashflow_bridges_dotted_to_snake_pairwise(): void
    {
        // Mechanical `.`->`_` pairs (representative sample).
        $this->assertSame(['cashflow_expense_create'], PermissionAliasMap::aliasesFor('cashflow.expense.create'));
        $this->assertSame(['cashflow_expense_void'], PermissionAliasMap::aliasesFor('cashflow.expense.void'));
        $this->assertSame(['cashflow_transfer_void'], PermissionAliasMap::aliasesFor('cashflow.transfer.void'));
        $this->assertSame(['cashflow_vendor_transaction_delete'], PermissionAliasMap::aliasesFor('cashflow.vendor.transaction.delete'));
        $this->assertSame(['cashflow_staff_advance_void'], PermissionAliasMap::aliasesFor('cashflow.staff_advance.void'));
        $this->assertSame(['cashflow_fdm_view'], PermissionAliasMap::aliasesFor('cashflow.fdm.view'));

        // The FOUR structural mismatches — NOT a blind `.`->`_` transform; the
        // snake catalog drops the trailing segment (no _view / _manage) or uses
        // the bare slug. Pin them so a future "tidy-up" can't silently break them.
        $this->assertSame(['cashflow_dashboard'], PermissionAliasMap::aliasesFor('cashflow.dashboard.view'));
        $this->assertSame(['cashflow_reports'], PermissionAliasMap::aliasesFor('cashflow.reports.view'));
        $this->assertSame(['cashflow_settings'], PermissionAliasMap::aliasesFor('cashflow.settings.manage'));
        $this->assertSame(['cashflow_vendor_transaction'], PermissionAliasMap::aliasesFor('cashflow.vendor.transaction.create'));
    }

    public function test_granular_cashflow_inverse_resolves_snake_to_dotted(): void
    {
        // A crm2 (snake) grant must surface the matching crm3 (dotted) slug, so
        // the Gate::before bridge + the SPA auth payload honour it. Cover the four
        // structural pairs whose names don't line up mechanically.
        $this->assertContains('cashflow.dashboard.view', PermissionAliasMap::aliasesFor('cashflow_dashboard'));
        $this->assertContains('cashflow.reports.view', PermissionAliasMap::aliasesFor('cashflow_reports'));
        $this->assertContains('cashflow.settings.manage', PermissionAliasMap::aliasesFor('cashflow_settings'));
        $this->assertContains('cashflow.vendor.transaction.create', PermissionAliasMap::aliasesFor('cashflow_vendor_transaction'));
        $this->assertContains('cashflow.expense.create', PermissionAliasMap::aliasesFor('cashflow_expense_create'));
    }

    public function test_cashflow_staff_transfer_is_intentionally_unmapped(): void
    {
        // staff<->staff handover is a crm3-only feature with NO snake_case
        // counterpart on prod, so it must NOT be bridged (would be inert anyway).
        $this->assertSame([], PermissionAliasMap::aliasesFor('cashflow.staff_transfer.create'));
        $this->assertSame([], PermissionAliasMap::aliasesFor('cashflow.staff_transfer.void'));
    }
}
