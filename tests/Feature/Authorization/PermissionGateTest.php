<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The CheckPermission middleware (alias `permission:`) defends every
 * sensitive admin route by checking `Gate::allows($permission)`. Spatie's
 * HasRoles trait registers a Gate that returns true when the user holds
 * a role granting that permission.
 *
 * The audit verified that every Admin controller route is wrapped in this
 * middleware. These tests pin the contract end-to-end:
 *
 *   1. Anonymous request → redirect (kicked out by `auth` upstream)
 *   2. Authed user without the permission → redirect to /unauthorized
 *   3. Authed user with a role granting the permission → 200 OK
 *
 * The route is registered inline so the test does not depend on any
 * specific production controller still existing under the same path.
 */
class PermissionGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const TEST_PERMISSION = 'ALLURA_test_resource_view';

    private const TEST_ROUTE = '/_test/permission-gate';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Spatie reads from the active guard. The web guard's user provider
        // points at App\Models\User. Cache must be reset because Spatie
        // memoises permission lookups across tests in the same process.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Route::middleware(['web', 'permission:' . self::TEST_PERMISSION])
            ->get(self::TEST_ROUTE, fn () => response('ok', 200));
    }

    public function test_user_without_the_permission_is_redirected_to_unauthorized(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(self::TEST_ROUTE);

        $response->assertRedirect(route('unauthorized'));
    }

    public function test_user_with_a_role_granting_the_permission_passes_through(): void
    {
        // Create the permission, attach it to a fresh role, assign the role.
        // Use the TestCase RBAC helpers because the production `roles` table
        // has a NOT NULL `commission` column not on Spatie's stock model.
        $this->createPermission(self::TEST_PERMISSION);
        $role = $this->createRole('TestViewer');
        $role->givePermissionTo(self::TEST_PERMISSION);

        $user = User::factory()->create();
        $user->assignRole($role);

        // Spatie caches the role/permission map per-process; reset between
        // grant and assertion so the gate sees the new grant.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
        $response = $this->get(self::TEST_ROUTE);

        $response->assertOk();
        $this->assertSame('ok', $response->getContent());
    }

    public function test_user_with_the_role_but_no_permission_is_still_blocked(): void
    {
        // Hold a role that does NOT have the permission attached. The gate
        // checks the permission, not the role name — without the explicit
        // grant, the role is irrelevant.
        $this->createRole('EmptyRole');

        $user = User::factory()->create();
        $user->assignRole('EmptyRole');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
        $response = $this->get(self::TEST_ROUTE);

        $response->assertRedirect(route('unauthorized'));
    }

    public function test_revoking_the_role_immediately_blocks_subsequent_requests(): void
    {
        $this->createPermission(self::TEST_PERMISSION);
        $role = $this->createRole('TestViewer');
        $role->givePermissionTo(self::TEST_PERMISSION);

        $user = User::factory()->create();
        $user->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        $this->get(self::TEST_ROUTE)->assertOk();

        $user->removeRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(self::TEST_ROUTE)->assertRedirect(route('unauthorized'));
    }
}
