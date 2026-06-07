<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Permission;
use Database\Seeders\DashboardTabPermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins DashboardTabPermissionSeeder — the idempotent fix for the FDM
 * "no dashboard sections" bug on environments where the one-shot tab
 * migrations never granted the FDM role anything.
 *
 * Pins:
 *   1. Full catalog (4 parents + 36 panels) is created & categorised.
 *   2. FDM role gets the FDM tab only (17 perms), nothing from the other tabs.
 *   3. Admin roles get every panel.
 *   4. Re-running is idempotent (no dup rows / dup grants).
 *   5. A pre-existing drifted parent row is repaired (category + title).
 *   6. An existing management_dashboard parent is re-categorised to Dashboard.
 */
class DashboardTabPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Super Admin'];

    /** Every dashboard.fdm.* panel the FDM tab exposes. */
    private const FDM_PANELS = [
        'dashboard.fdm.cash.view',
        'dashboard.fdm.today_activities.view',
        'dashboard.fdm.today_status_board.view',
        'dashboard.fdm.stats.view',
        'dashboard.fdm.gender_revenue.view',
        'dashboard.fdm.sales_momentum.view',
        'dashboard.fdm.avg_values.view',
        'dashboard.fdm.new_returning.view',
        'dashboard.fdm.arrival_rate.view',
        'dashboard.fdm.utilization.view',
        'dashboard.fdm.utilization_heatmap.view',
        'dashboard.fdm.at_risk.view',
        'dashboard.fdm.client_retention.view',
        'dashboard.fdm.branch_feedback.view',
        'dashboard.fdm.retention_cohorts.view',
        'dashboard.fdm.no_show_trend.view',
        'dashboard.fdm.target_pacing.view',
    ];

    /** Parent group => expected panel count. */
    private const PARENTS = [
        'dashboard_overview' => 14,
        'dashboard_practitioners' => 1,
        'dashboard_marketing' => 4,
        'dashboard_fdm' => 17,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ADMIN_ROLES as $name) {
            $this->createRole($name);
        }
        $this->createRole('FDM');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runSeeder(): void
    {
        $this->seed(DashboardTabPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function rolePermNames(string $role): array
    {
        return Role::where('name', $role)->where('guard_name', 'web')
            ->firstOrFail()
            ->permissions->pluck('name')->all();
    }

    public function test_full_catalog_is_created_and_categorised(): void
    {
        $this->runSeeder();

        foreach (self::PARENTS as $parentName => $childCount) {
            $parent = Permission::where('name', $parentName)->first();
            $this->assertNotNull($parent, "Parent {$parentName} missing");
            $this->assertTrue((bool) $parent->main_group, "{$parentName} must be main_group");
            $this->assertSame(0, (int) $parent->parent_id);
            $this->assertTrue((bool) $parent->status);
            $this->assertSame('Dashboard', $parent->category, "{$parentName} must be in Dashboard category");

            $children = Permission::where('parent_id', $parent->id)->get();
            $this->assertCount($childCount, $children, "{$parentName} should have {$childCount} panels");
            foreach ($children as $child) {
                $this->assertFalse((bool) $child->main_group);
                $this->assertTrue((bool) $child->status);
                $this->assertStringStartsWith('dashboard.', $child->name);
            }
        }

        // 4 + 36 panels (14 + 1 + 4 + 17).
        $this->assertSame(36, Permission::where('name', 'like', 'dashboard.%')->count());
    }

    public function test_fdm_role_gets_fdm_tab_only(): void
    {
        $this->runSeeder();

        $fdmPerms = $this->rolePermNames('FDM');

        // Scope the exact-match to dashboard.* — FDM also legitimately holds
        // the cross-module cashflow_fdm_view (asserted separately below).
        $fdmDashboard = array_values(array_filter(
            $fdmPerms,
            static fn (string $p): bool => str_starts_with($p, 'dashboard.'),
        ));
        sort($fdmDashboard);
        $expected = self::FDM_PANELS;
        sort($expected);
        $this->assertSame($expected, $fdmDashboard, 'FDM must hold exactly the 17 dashboard.fdm.* panels');

        foreach (['dashboard.overview.', 'dashboard.practitioners.', 'dashboard.marketing.'] as $otherTab) {
            $leaked = array_filter($fdmPerms, static fn (string $p): bool => str_starts_with($p, $otherTab));
            $this->assertEmpty($leaked, "FDM must not hold {$otherTab}* perms");
        }

        // The FDM Cash panel depends on this cross-module gate.
        $this->assertContains(
            'cashflow_fdm_view',
            $fdmPerms,
            'FDM must hold cashflow_fdm_view so /api/cashflow/fdm/data is not 403/302',
        );
    }

    public function test_cashflow_fdm_view_is_created_and_granted_to_admins(): void
    {
        $this->runSeeder();

        $perm = Permission::where('name', 'cashflow_fdm_view')->first();
        $this->assertNotNull($perm, 'cashflow_fdm_view gate must exist');
        $this->assertFalse((bool) $perm->main_group);
        $this->assertTrue((bool) $perm->status);

        foreach (self::ADMIN_ROLES as $role) {
            $this->assertContains(
                'cashflow_fdm_view',
                $this->rolePermNames($role),
                "{$role} must hold cashflow_fdm_view",
            );
        }
    }

    public function test_admin_roles_get_every_panel(): void
    {
        $this->runSeeder();

        $allPanels = Permission::where('name', 'like', 'dashboard.%')->pluck('name')->all();
        sort($allPanels);

        foreach (self::ADMIN_ROLES as $role) {
            $granted = array_values(array_filter(
                $this->rolePermNames($role),
                static fn (string $p): bool => str_starts_with($p, 'dashboard.'),
            ));
            sort($granted);
            $this->assertSame($allPanels, $granted, "{$role} must hold every dashboard panel");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->runSeeder();
        $permCount = Permission::count();
        $fdmGrantCount = count($this->rolePermNames('FDM'));
        $adminGrantCount = count(array_filter(
            $this->rolePermNames('Administrator'),
            static fn (string $p): bool => str_starts_with($p, 'dashboard.'),
        ));

        $this->runSeeder();

        $this->assertSame($permCount, Permission::count(), 'Re-run must not create duplicate permission rows');
        $this->assertSame($fdmGrantCount, count($this->rolePermNames('FDM')), 'Re-run must not duplicate FDM grants');
        $this->assertSame(
            $adminGrantCount,
            count(array_filter(
                $this->rolePermNames('Administrator'),
                static fn (string $p): bool => str_starts_with($p, 'dashboard.'),
            )),
            'Re-run must not duplicate admin grants',
        );
    }

    public function test_drifted_parent_row_is_repaired(): void
    {
        Permission::create([
            'name' => 'dashboard_fdm',
            'title' => 'WRONG TITLE',
            'main_group' => 0,
            'parent_id' => 999,
            'status' => 0,
            'category' => 'Misfiled',
            'guard_name' => 'web',
            'sort_order' => 0,
        ]);

        $this->runSeeder();

        $parent = Permission::where('name', 'dashboard_fdm')->firstOrFail();
        $this->assertSame('Dashboard - FDM', $parent->title);
        $this->assertSame('Dashboard', $parent->category);
        $this->assertTrue((bool) $parent->main_group);
        $this->assertSame(0, (int) $parent->parent_id);
        $this->assertTrue((bool) $parent->status);
        $this->assertCount(17, Permission::where('parent_id', $parent->id)->get());
    }

    public function test_existing_management_dashboard_parent_is_recategorised(): void
    {
        Permission::create([
            'name' => 'management_dashboard',
            'title' => 'Management Dashboard',
            'main_group' => 1,
            'parent_id' => 0,
            'status' => 1,
            'category' => null,
            'guard_name' => 'web',
            'sort_order' => 0,
        ]);

        $this->runSeeder();

        $this->assertSame(
            'Dashboard',
            Permission::where('name', 'management_dashboard')->value('category'),
            'management_dashboard must be clustered into the Dashboard category',
        );
    }
}
