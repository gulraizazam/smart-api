<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins PATCH /api/users/{id}/password — the path-bound JSON endpoint
 * that obsoletes the legacy GET-then-PATCH dance against the Blade
 * change-password modal.
 *
 * Old flow (RETIRED 2026-06-17 — crm2 delinked; pinned gone below):
 *   GET  /api/users/password/{id}  -> Blade modal with hidden encrypted id
 *   PATCH /api/users/password      -> { id: <encrypted>, password, ... }
 *
 * New flow (this test pins it):
 *   PATCH /api/users/{id}/password -> { password, password_confirmation }
 *
 * Tenant isolation is server-side via `ApplicationUserService::findByAccountId`,
 * so cross-account targets surface as 404 (indistinguishable from "no
 * such user" — no enumeration). The encrypt(id) round-trip the legacy
 * flow used was never a security boundary, just an implementation
 * detail of the Blade-form scrape.
 */
class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const PERMS = ['users_manage', 'users_change_password'];

    private const ROLE_NAME = 'TestPasswordManager';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Spatie's PermissionRegistrar memoises the permission map per
        // process. Without flushing between tests, a permission created
        // in one test invisibly carries into the next (and vice versa,
        // a revoke goes stale).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create a user holding the role + permissions required to hit
     * `/api/users/{id}/password` (the route group is gated by
     * `permission:users_manage`; the controller additionally checks
     * `Gate::allows('users_change_password')`). actingAs() the user.
     *
     * Returned user is on the same `account_id` as the seeded fixtures
     * so `findByAccountId` resolves targets created in the test.
     */
    private function actingAsUserManager(): User
    {
        foreach (self::PERMS as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole(self::ROLE_NAME);
        $role->givePermissionTo(self::PERMS);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);

        // Re-flush after grant so the gate sees the just-attached perms.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        return $actor;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $target = User::factory()->create();

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertStatus(401);
    }

    public function test_authenticated_user_without_permission_is_rejected(): void
    {
        // `permission:users_manage` is the route-group middleware; without
        // it, the request hits CheckPermission and 302s away. Pin the
        // gate so a future "loosen the route group" change doesn't open
        // password updates to every authed user.
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $response = $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        // CheckPermission redirects to /unauthorized when the gate fails.
        $this->assertContains($response->status(), [302, 401, 403]);
    }

    public function test_user_with_permission_can_update_password_in_own_account(): void
    {
        $actor = $this->actingAsUserManager();
        $target = User::factory()->create([
            'account_id' => $actor->account_id,
            'password' => Hash::make('OldPass1!'),
        ]);

        $response = $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Password has been changed successfully.',
        ]);

        $this->assertTrue(
            Hash::check('NewPass1!', $target->fresh()->password),
            'Password hash on the target row must reflect the new value.',
        );
    }

    public function test_below_minimum_length_is_rejected_with_422(): void
    {
        $actor = $this->actingAsUserManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);
    }

    public function test_missing_symbol_is_rejected_with_422(): void
    {
        // Mirror Laravel's Password::min(8)->mixedCase()->numbers()->symbols()
        // so the SPA-side zod regex stays in lockstep with the server.
        $actor = $this->actingAsUserManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ])->assertStatus(422);
    }

    public function test_mismatched_confirmation_is_rejected_with_422(): void
    {
        $actor = $this->actingAsUserManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'OtherPass1!',
        ])->assertStatus(422);
    }

    public function test_target_in_different_account_returns_404(): void
    {
        // Tenant isolation pin — pre-fix, the SPA was scraping
        // encrypt(id) from a Blade form; passing a hand-crafted encrypted
        // id was the only way to attempt cross-tenant edits and it
        // depended on the obfuscation. Now the id is in the URL path,
        // so the only thing standing between an attacker and a
        // cross-tenant edit is the service-layer account scoping —
        // pin it with a high-priority test.
        $actor = $this->actingAsUserManager();

        // The GuardsTenantBoundary trait silently rewrites account_id
        // back to the authed user's tenant on create — that's the
        // *correct* behavior in production, but it makes
        // `User::factory()->create(['account_id' => 999])` insert a row
        // in the actor's tenant instead. Insert via DB::table to bypass
        // the trait so the cross-tenant scenario is real.
        DB::table('accounts')->updateOrInsert(['id' => 999], [
            'name' => 'Other Tenant',
            'email' => 'other@example.com',
            'contact' => '0000000000',
            'suspended' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherId = DB::table('users')->insertGetId([
            'name' => 'Other Tenant User',
            'email' => 'other-tenant-user@example.com',
            'phone' => '0000000000',
            'password' => Hash::make('OldPass1!'),
            'user_type_id' => 1,
            'account_id' => 999,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson("/api/users/{$otherId}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'User not found.']);
    }

    public function test_response_is_json_not_blade_html(): void
    {
        // Belt-and-suspenders against a future "fix" that re-routes this
        // to LegacyApplicationUserController or wires `return view(...)`
        // into the success path.
        $actor = $this->actingAsUserManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $response = $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type'),
            'Endpoint must always return JSON; never a Blade HTML page.',
        );
    }

    public function test_legacy_get_change_password_route_is_gone(): void
    {
        // The legacy GET returned a Blade modal carrying the encrypted-id
        // hidden input the SPA used to scrape. Retired now that crm2 is
        // delinked — it must no longer resolve, or the GET-then-PATCH dance
        // (and its encrypt(id) obfuscation) is back. Authed as a manager so
        // a 404 means "route gone", not "auth wall".
        $actor = $this->actingAsUserManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $this->getJson("/api/users/password/{$target->id}")->assertStatus(404);
    }

    public function test_legacy_patch_save_password_route_is_gone(): void
    {
        // The legacy PATCH decrypt()'d a client-supplied id and changed the
        // password WITHOUT an account-scope check — a latent cross-tenant
        // IDOR. Retired; only the path-bound, tenant-scoped
        // PATCH /api/users/{id}/password remains.
        //
        // With the legacy route gone, `users/password` no longer has a PATCH
        // handler — the URL now falls through to the RESTful `PUT {user}`
        // route, so PATCH yields 405 (Method Not Allowed). Re-adding
        // savePassword would make it 500/422 again, failing this pin.
        $this->actingAsUserManager();

        $this->patchJson('/api/users/password', [
            'id' => 'whatever',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertStatus(405);
    }
}
