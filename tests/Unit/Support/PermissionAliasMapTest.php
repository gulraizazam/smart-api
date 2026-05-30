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
}
