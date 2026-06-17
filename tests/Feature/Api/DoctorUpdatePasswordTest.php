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
 * Pins PATCH /api/doctors/{id}/password — the path-bound JSON endpoint that
 * replaces the legacy GET-then-PATCH doctor pair (changePassword/savePassword),
 * matching the users flow.
 *
 * SECURITY: the legacy `savePassword` called the UNSCOPED
 * DoctorService::changePassword with the id straight from the request body —
 * a cross-tenant IDOR (any user's password changeable by id). The new endpoint
 * gates on the account-scoped getPasswordChangeData (404 cross-account, no
 * enumeration) BEFORE writing, closing that hole. The cross-tenant test below
 * additionally asserts the foreign password is left UNCHANGED.
 */
class DoctorUpdatePasswordTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const PERMS = ['doctors_manage', 'doctors_change_password'];

    private const ROLE_NAME = 'TestDoctorPasswordManager';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsDoctorManager(): User
    {
        foreach (self::PERMS as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole(self::ROLE_NAME);
        $role->givePermissionTo(self::PERMS);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        return $actor;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $target = User::factory()->create();

        $this->patchJson("/api/doctors/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertStatus(401);
    }

    public function test_authenticated_without_permission_is_rejected(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $response = $this->patchJson("/api/doctors/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        $this->assertContains($response->status(), [302, 401, 403]);
    }

    public function test_with_permission_can_update_password_in_own_account(): void
    {
        $actor = $this->actingAsDoctorManager();
        $target = User::factory()->create([
            'account_id' => $actor->account_id,
            'password' => Hash::make('OldPass1!'),
        ]);

        $this->patchJson("/api/doctors/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertOk()->assertJson([
            'success' => true,
            'message' => 'Password has been changed successfully.',
        ]);

        $this->assertTrue(Hash::check('NewPass1!', $target->fresh()->password));
    }

    public function test_weak_password_is_rejected_with_422(): void
    {
        $actor = $this->actingAsDoctorManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        // No symbol — must fail Password::min(8)->mixedCase()->numbers()->symbols().
        $this->patchJson("/api/doctors/{$target->id}/password", [
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ])->assertStatus(422);
    }

    public function test_mismatched_confirmation_is_rejected_with_422(): void
    {
        $actor = $this->actingAsDoctorManager();
        $target = User::factory()->create(['account_id' => $actor->account_id]);

        $this->patchJson("/api/doctors/{$target->id}/password", [
            'password' => 'NewPass1!',
            'password_confirmation' => 'OtherPass1!',
        ])->assertStatus(422);
    }

    public function test_cross_tenant_target_returns_404_and_leaves_password_unchanged(): void
    {
        // THE IDOR pin. The legacy savePassword would have changed this foreign
        // user's password (unscoped). The new endpoint must 404 AND not touch it.
        $this->actingAsDoctorManager();

        DB::table('accounts')->updateOrInsert(['id' => 999], [
            'name' => 'Other Tenant', 'email' => 'other-doc@example.com',
            'contact' => '0000000000', 'suspended' => '0',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherId = DB::table('users')->insertGetId([
            'name' => 'Other Tenant Doctor', 'email' => 'other-tenant-doctor@example.com',
            'phone' => '0000000000', 'password' => Hash::make('OldPass1!'),
            'user_type_id' => 5, 'account_id' => 999, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patchJson("/api/doctors/{$otherId}/password", [
            'password' => 'Hacked1!',
            'password_confirmation' => 'Hacked1!',
        ])->assertStatus(404)->assertJson(['success' => false, 'message' => 'Doctor not found.']);

        // Cross-tenant password must be untouched (no IDOR).
        $this->assertTrue(
            Hash::check('OldPass1!', DB::table('users')->where('id', $otherId)->value('password')),
            'A cross-account doctor password must NOT be changeable — the IDOR must stay closed.',
        );
    }
}
