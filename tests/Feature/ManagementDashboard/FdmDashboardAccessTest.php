<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The /api/management-dashboard/* group backs BOTH the Management Dashboard
 * and the FDM dashboard tab (which reuses the same sections). FDM roles are
 * granted `dashboard_fdm` + `dashboard.fdm.*`, NOT `management_dashboard.view`.
 *
 * Regression: the group was gated solely on `can:management_dashboard.view`,
 * so an FDM holding only the FDM permissions got 403 on every section — the
 * SPA showed the FDM tab but "no data in any section". The group now authorizes
 * `management_dashboard.view` OR `dashboard_fdm` via the
 * `access-management-dashboard-api` gate.
 *
 * @see \App\Providers\AppServiceProvider (Gate::define('access-management-dashboard-api'))
 * @see routes/api/dashboard.php
 */
class FdmDashboardAccessTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_fdm_permission_alone_grants_access_to_a_management_dashboard_section(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard_fdm']);
        // Assign a branch so the section resolves a concrete (single-branch) scope.
        DB::table('user_has_locations')->updateOrInsert(
            ['user_id' => $user->id, 'location_id' => $this->defaultLocation->id],
            ['region_id' => 1],
        );

        $this->actingAs($user);

        $response = $this->getJson('/api/management-dashboard/overview?range=this_month');

        // The gate must let the FDM through — the bug returned 403 here.
        $this->assertNotSame(403, $response->status(), 'FDM with dashboard_fdm must NOT be forbidden.');
        $response->assertOk();
    }

    public function test_user_without_either_permission_is_forbidden(): void
    {
        $user = $this->makeUserWithPermissions([]); // role with no dashboard perms

        $this->actingAs($user);

        $this->getJson('/api/management-dashboard/overview?range=this_month')
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeUserWithPermissions(array $permissions): User
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $role = $this->createRole('fdm-access-test-'.uniqid());
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        if ($permissions !== []) {
            $role->givePermissionTo($permissions);
        }

        $user = User::factory()->create(['account_id' => 1]);
        $user->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
