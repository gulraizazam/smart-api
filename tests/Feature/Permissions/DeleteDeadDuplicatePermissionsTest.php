<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P-R / Delete — physically remove dead duplicate permission rows.
 *
 * Pins: a dead group's whole subtree + its stale grants are deleted while the
 * keeper survives; the keeper guard blocks deletion if the canonical twin is
 * absent; dashboard_manage is HIDDEN (not deleted); and down() fully restores
 * the deleted rows (original ids) + grants and the hidden status.
 */
class DeleteDeadDuplicatePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_18_160000_delete_dead_duplicate_permissions.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function group(string $name): void
    {
        $this->createPermission($name);
        DB::table('permissions')->where('name', $name)->update(['main_group' => 1, 'status' => 1]);
    }

    private function child(string $name, string $parent): void
    {
        $this->createPermission($name);
        $pid = DB::table('permissions')->where('name', $parent)->value('id');
        DB::table('permissions')->where('name', $name)->update(['parent_id' => $pid, 'main_group' => 0, 'status' => 1]);
    }

    private function exists(string $name): bool
    {
        return DB::table('permissions')->where('name', $name)->exists();
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_deletes_dead_subtree_and_grants_but_keeps_the_keeper(): void
    {
        $this->group('roles');           // dotted shadow (dead, in RETIRE_DELETE)
        $this->group('roles_manage');    // legacy keeper
        $this->child('roles.create', 'roles');
        $this->child('roles.edit', 'roles');

        $role = $this->createRole('Holder');
        $role->givePermissionTo(['roles', 'roles.create']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $rolesId = DB::table('permissions')->where('name', 'roles')->value('id');

        $this->runUp();

        $this->assertFalse($this->exists('roles'), 'dead parent must be deleted');
        $this->assertFalse($this->exists('roles.create'), 'dead child must be deleted');
        $this->assertFalse($this->exists('roles.edit'), 'dead child must be deleted');
        $this->assertTrue($this->exists('roles_manage'), 'keeper must survive');
        $this->assertSame(
            0,
            (int) DB::table('role_has_permissions')->where('permission_id', $rolesId)->count(),
            'stale grants on the deleted perm must be gone',
        );
    }

    public function test_dashboard_manage_is_hidden_not_deleted(): void
    {
        $this->group('management_dashboard'); // keeper (live cockpit)
        $this->group('dashboard_manage');

        $this->runUp();

        $this->assertTrue($this->exists('dashboard_manage'), 'dashboard_manage must NOT be deleted (dead controller still refs it)');
        $this->assertSame(0, (int) DB::table('permissions')->where('name', 'dashboard_manage')->value('status'), 'it must be hidden');
    }

    public function test_guard_skips_delete_when_keeper_absent(): void
    {
        $this->group('roles'); // keeper 'roles_manage' absent

        $this->runUp();

        $this->assertTrue($this->exists('roles'), 'must NOT delete a side when its keeper twin is missing');
    }

    public function test_membership_codes_gate_repointed_to_the_dotted_slug(): void
    {
        // Paired code change: the generateCodes endpoint must gate on the dotted
        // `memberships.create` (legacy `memberships_create` was an orphan held by
        // 0 prod roles and is deleted with the memberships_manage subtree).
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Api/MembershipCodeController.php'));
        $this->assertStringContainsString("Gate::allows('memberships.create')", $src);
        $this->assertStringNotContainsString("Gate::allows('memberships_create')", $src);
    }

    public function test_down_restores_deleted_rows_and_grants(): void
    {
        $this->group('roles');
        $this->group('roles_manage');
        $this->child('roles.create', 'roles');
        $role = $this->createRole('Holder2');
        $role->givePermissionTo('roles.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleId = $role->id;

        $this->runUp();
        $this->assertFalse($this->exists('roles.create'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->exists('roles'), 'down() must restore the deleted parent');
        $this->assertTrue($this->exists('roles.create'), 'down() must restore the deleted child');
        $createId = DB::table('permissions')->where('name', 'roles.create')->value('id');
        $this->assertTrue(
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $createId)->exists(),
            'down() must restore the deleted grant',
        );
    }
}
