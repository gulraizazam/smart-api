<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the migration that makes the Warehouse + User-Type permission switches
 * work (2026-06-20 user decision). up() creates the Warehouse group + CRUD
 * children and the 4 missing User-Type children; down() removes them + grants.
 *
 * Teeth: drop a slug from the migration and its "created" assertion reddens.
 */
class AddWarehouseAndUserTypePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'migrations/2026_06_20_100000_add_warehouse_and_usertype_permissions.php';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_up_creates_the_warehouse_module_as_a_grantable_group(): void
    {
        $this->runUp();

        $group = DB::table('permissions')->where('name', 'warehouse_manage')->where('guard_name', 'web')->first();
        $this->assertNotNull($group, 'warehouse_manage group must be created');
        $this->assertSame(1, (int) $group->main_group, 'warehouse_manage must render as a group');
        $this->assertSame(1, (int) $group->status);

        foreach (['warehouse_create', 'warehouse_edit', 'warehouse_destroy', 'warehouse_active'] as $child) {
            $row = DB::table('permissions')->where('name', $child)->where('guard_name', 'web')->first();
            $this->assertNotNull($row, "{$child} must be created");
            $this->assertSame((int) $group->id, (int) $row->parent_id, "{$child} must hang under warehouse_manage");
            $this->assertSame(0, (int) $row->main_group, "{$child} must be a child, not a group");
        }

        $role = $this->createRole('WarehouseRole');
        $role->givePermissionTo('warehouse_destroy');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($role->fresh()->hasPermissionTo('warehouse_destroy'));
    }

    public function test_up_attaches_usertype_children_to_the_existing_parent(): void
    {
        // Simulate the prod catalog where user_types_manage already exists.
        $parent = $this->createPermission('user_types_manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->runUp();

        foreach (['user_types_create', 'user_types_destroy', 'user_types_active', 'user_types_inactive'] as $child) {
            $row = DB::table('permissions')->where('name', $child)->where('guard_name', 'web')->first();
            $this->assertNotNull($row, "{$child} must be created");
            $this->assertSame((int) $parent->id, (int) $row->parent_id, "{$child} must hang under user_types_manage");
        }
    }

    public function test_down_removes_everything_and_its_grants(): void
    {
        $this->createPermission('user_types_manage');
        $this->runUp();

        $role = $this->createRole('Holder');
        $role->givePermissionTo(['warehouse_manage', 'user_types_create']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['warehouse_manage', 'warehouse_create', 'warehouse_edit', 'warehouse_destroy', 'warehouse_active', 'user_types_create', 'user_types_destroy', 'user_types_active', 'user_types_inactive'] as $name) {
            $this->assertFalse(DB::table('permissions')->where('name', $name)->exists(), "down() must remove {$name}");
        }
    }
}
