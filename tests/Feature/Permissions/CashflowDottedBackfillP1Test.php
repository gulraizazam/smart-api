<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P — P1 backfill migration
 * (2026_06_17_190000_backfill_cashflow_dotted_orphan_grants).
 *
 * Pins: a role holding a legacy cashflow slug without its dotted twin gains the
 * twin; it's idempotent; roles already holding both are untouched; it NEVER
 * creates the phantom `packages.delete` (the audit false-positive); and down()
 * cleanly reverses exactly what it added.
 */
class CashflowDottedBackfillP1Test extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_17_190000_backfill_cashflow_dotted_orphan_grants.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_grants_dotted_twin_to_a_legacy_only_role(): void
    {
        $this->createPermission('cashflow_manage');
        $this->createPermission('cashflow.manage');
        $role = $this->createRole('TestStocks');
        $role->givePermissionTo('cashflow_manage'); // legacy only

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse($role->fresh()->hasPermissionTo('cashflow.manage'), 'precondition: legacy-only');

        $this->runUp();

        $this->assertTrue(
            $role->fresh()->hasPermissionTo('cashflow.manage'),
            'P1 must grant the dotted twin to a legacy-only role',
        );
    }

    public function test_idempotent_and_leaves_roles_holding_both_untouched(): void
    {
        $this->createPermission('cashflow_dashboard');
        $dotted = $this->createPermission('cashflow.dashboard.view');
        $role = $this->createRole('TestOps');
        $role->givePermissionTo(['cashflow_dashboard', 'cashflow.dashboard.view']);

        $this->runUp();
        $this->runUp(); // twice — must not error or duplicate

        $this->assertSame(
            1,
            (int) DB::table('role_has_permissions')
                ->where('role_id', $role->id)->where('permission_id', $dotted->id)->count(),
            'must not create a duplicate grant row',
        );
    }

    public function test_never_creates_the_phantom_packages_delete(): void
    {
        // The audit's packages_destroy "orphan" was a false positive — the bridge's
        // packages.delete is a phantom; the real slug is packages.destroy. P1 must
        // not create packages.delete or grant anything for packages.
        $this->createPermission('packages_destroy');
        $this->createPermission('packages.destroy');
        $this->createRole('TestAdmin')->givePermissionTo('packages_destroy');

        $this->runUp();

        $this->assertFalse(
            Permission::where('name', 'packages.delete')->exists(),
            'P1 must NOT create the phantom packages.delete',
        );
    }

    public function test_down_removes_the_backfilled_grant(): void
    {
        $this->createPermission('cashflow_manage');
        $this->createPermission('cashflow.manage');
        $role = $this->createRole('TestStocks2');
        $role->givePermissionTo('cashflow_manage');

        $this->runUp();
        $this->assertTrue($role->fresh()->hasPermissionTo('cashflow.manage'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $role->fresh()->hasPermissionTo('cashflow.manage'),
            'down() must remove exactly the backfilled grant',
        );
    }
}
