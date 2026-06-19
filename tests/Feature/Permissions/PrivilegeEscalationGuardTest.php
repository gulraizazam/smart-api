<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Services\UserManagement\ApplicationUserService;
use App\Services\UserManagement\RoleService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the two privilege-escalation holes found in the 2026-06-19 permission
 * QA (task #18):
 *
 *  1. Role editor — RoleService::create/update synced the role's permission set
 *     from raw client input, so anyone holding `roles_edit`/`roles_create` could
 *     grant a role (incl. their own) ANY permission, escalating past their level.
 *
 *  2. User role-assign — ApplicationUserService::create/update assigned roles from
 *     raw client ids (the SPA hides Super-Admin from the dropdown, but the request
 *     only validated `exists:roles,id`), so anyone holding `users_edit`/`users_create`
 *     could attach the existing Super-Admin role to themselves and gain everything.
 *
 * Guard semantics (escalation-proof AND non-breaking):
 *  - A non-Super-Admin may only ADD/REMOVE permissions they themselves hold; any
 *    permission outside their grant set is FROZEN to whatever the role already had
 *    (so they can neither escalate a role nor strip perms they can't see).
 *  - A non-Super-Admin may never assign the Super-Admin role.
 *  - Super-Admin (Gate::before bypass) and system/console context (no auth) are
 *    unrestricted.
 *
 * Teeth: revert either guard and the matching escalation test goes red.
 */
class PrivilegeEscalationGuardTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function forgetPermCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Authenticate as a NON-Super-Admin user holding exactly the given permissions. */
    private function actingAsNonSuperWith(array $perms): User
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('Editor_'.uniqid());
        $role->givePermissionTo($perms);
        $this->forgetPermCache();

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        $this->forgetPermCache();

        return $user;
    }

    // ── Hole 1: role editor ────────────────────────────────────────────────

    public function test_non_super_admin_cannot_grant_a_permission_they_lack_when_editing_a_role(): void
    {
        $this->createPermission('permissions_manage'); // the high perm the actor lacks
        $this->actingAsNonSuperWith(['roles_edit', 'leads.list.view']);

        $target = $this->createRole('Target');

        (new RoleService())->update($target->id, [
            'name' => 'Target',
            'permission' => ['permissions_manage', 'leads.list.view'],
        ]);
        $this->forgetPermCache();

        $names = $target->fresh()->permissions->pluck('name')->all();
        $this->assertNotContains(
            'permissions_manage',
            $names,
            'ESCALATION: actor granted a role a permission they do not themselves hold.',
        );
        $this->assertContains('leads.list.view', $names, 'actor should still grant a perm they DO hold.');
    }

    public function test_role_edit_preserves_a_permission_the_editor_cannot_grant(): void
    {
        $this->createPermission('permissions_manage');

        $target = $this->createRole('Target');
        $target->givePermissionTo('permissions_manage'); // role already has the high perm
        $this->forgetPermCache();

        // actor can't grant permissions_manage; submits a set WITHOUT it
        $this->actingAsNonSuperWith(['roles_edit', 'leads.list.view']);

        (new RoleService())->update($target->id, [
            'name' => 'Target',
            'permission' => ['leads.list.view'],
        ]);
        $this->forgetPermCache();

        $names = $target->fresh()->permissions->pluck('name')->all();
        $this->assertContains(
            'permissions_manage',
            $names,
            'A non-super editor must not be able to STRIP a permission they cannot grant.',
        );
        $this->assertContains('leads.list.view', $names);
    }

    public function test_super_admin_can_grant_any_permission_when_editing_a_role(): void
    {
        $this->createPermission('permissions_manage');
        $this->actingAsAdmin(); // Super-Admin

        $target = $this->createRole('Target');

        (new RoleService())->update($target->id, [
            'name' => 'Target',
            'permission' => ['permissions_manage'],
        ]);
        $this->forgetPermCache();

        $this->assertContains(
            'permissions_manage',
            $target->fresh()->permissions->pluck('name')->all(),
            'Super-Admin must retain full permission-granting power.',
        );
    }

    // ── Hole 2: user role-assignment ───────────────────────────────────────

    public function test_non_super_admin_cannot_assign_the_super_admin_role_to_a_user(): void
    {
        $superRole = $this->createRole('Super-Admin');
        $normalRole = $this->createRole('Receptionist');
        $this->actingAsNonSuperWith(['users_create']);

        $created = (new ApplicationUserService())->create([
            'name' => 'New Staff',
            'email' => 'newstaff_'.uniqid().'@example.test',
            'password' => 'Passw0rd!2026',
            'roles' => [$superRole->id, $normalRole->id],
        ]);
        $this->forgetPermCache();

        $created = $created->fresh();
        $this->assertFalse(
            $created->hasRole('Super-Admin'),
            'ESCALATION: a non-Super-Admin assigned the Super-Admin role.',
        );
        $this->assertTrue($created->hasRole('Receptionist'), 'a normal role should still be assigned.');
    }

    public function test_super_admin_can_assign_the_super_admin_role(): void
    {
        $superRole = $this->createRole('Super-Admin');
        $this->actingAsAdmin();

        $created = (new ApplicationUserService())->create([
            'name' => 'Trusted',
            'email' => 'trusted_'.uniqid().'@example.test',
            'password' => 'Passw0rd!2026',
            'roles' => [$superRole->id],
        ]);
        $this->forgetPermCache();

        $this->assertTrue(
            $created->fresh()->hasRole('Super-Admin'),
            'Super-Admin must retain the ability to grant the Super-Admin role.',
        );
    }

    public function test_non_super_admin_editing_a_super_user_does_not_strip_the_super_role(): void
    {
        // The freeze's non-breaking half: a non-super with users_edit editing a
        // Super-Admin user's profile must NOT demote them. Super-Admin is frozen
        // to what the target already had — kept on edit, even though the actor
        // can't add it. Mirrors the role-editor freeze (hole 1).
        $superRole = $this->createRole('Super-Admin');
        $normalRole = $this->createRole('Receptionist');

        $actor = $this->actingAsNonSuperWith(['users_edit']);

        $target = User::factory()->create(['account_id' => $actor->account_id]);
        $this->assignRoleWithPivot($target, $superRole);
        $this->assignRoleWithPivot($target, $normalRole);
        $this->forgetPermCache();

        (new ApplicationUserService())->update($target->id, [
            'name' => 'Edited Name',
            'email' => $target->email,
            'roles' => [$normalRole->id], // submission omits Super-Admin
        ]);
        $this->forgetPermCache();

        $fresh = $target->fresh();
        $this->assertTrue(
            $fresh->hasRole('Super-Admin'),
            'DEMOTION: a non-super profile edit stripped an existing Super-Admin role.',
        );
        $this->assertTrue($fresh->hasRole('Receptionist'), 'the submitted normal role should remain.');
    }
}
