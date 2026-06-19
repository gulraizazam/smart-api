<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the hygiene scrub: orphaned children (parent_id -> missing parent) and
 * dangling grants (permission_id -> missing perm) are removed, valid rows are
 * untouched, and down() restores everything. Teeth: revert the migration and the
 * orphan/dangling rows survive.
 */
class ScrubDanglingPermissionRefsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_19_130000_scrub_dangling_permission_refs.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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

    /** Insert rows that violate FK integrity on purpose (the junk we're scrubbing). */
    private function fkOff(callable $fn): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $fn();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function test_scrub_removes_orphans_and_dangling_grants_keeps_valid(): void
    {
        // valid perm + grant — must survive
        $this->createPermission('valid_perm');
        $validId = (int) DB::table('permissions')->where('name', 'valid_perm')->value('id');
        $role = $this->createRole('Holder');
        $role->givePermissionTo('valid_perm');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // orphaned child (parent_id -> missing) + a grant on it
        $this->createPermission('orphan_child');
        $orphanId = (int) DB::table('permissions')->where('name', 'orphan_child')->value('id');
        $this->fkOff(function () use ($orphanId, $role): void {
            DB::table('permissions')->where('id', $orphanId)->update(['parent_id' => 65000, 'main_group' => 0]);
            DB::table('role_has_permissions')->insert(['role_id' => $role->id, 'permission_id' => $orphanId]);
            // pre-existing dangling grant (perm id 64000 does not exist)
            DB::table('role_has_permissions')->insert(['role_id' => $role->id, 'permission_id' => 64000]);
        });

        $this->runUp();

        $this->assertFalse($this->exists('orphan_child'), 'orphaned child must be deleted');
        $this->assertSame(0, (int) DB::table('role_has_permissions')->where('permission_id', 64000)->count(), 'dangling grant must be removed');
        $this->assertSame(0, (int) DB::table('role_has_permissions')->where('permission_id', $orphanId)->count(), "orphan's grant must be removed");
        $this->assertTrue($this->exists('valid_perm'), 'valid perm must survive');
        $this->assertTrue(
            DB::table('role_has_permissions')->where('role_id', $role->id)->where('permission_id', $validId)->exists(),
            'valid grant must survive',
        );
    }

    public function test_down_restores_scrubbed_rows(): void
    {
        $this->createPermission('orphan_child2');
        $orphanId = (int) DB::table('permissions')->where('name', 'orphan_child2')->value('id');
        $role = $this->createRole('Holder2');
        $this->fkOff(function () use ($orphanId, $role): void {
            DB::table('permissions')->where('id', $orphanId)->update(['parent_id' => 64900, 'main_group' => 0]);
            DB::table('role_has_permissions')->insert(['role_id' => $role->id, 'permission_id' => 63000]); // dangling
        });

        $this->runUp();
        $this->assertFalse($this->exists('orphan_child2'));
        $this->assertSame(0, (int) DB::table('role_has_permissions')->where('permission_id', 63000)->count());

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->exists('orphan_child2'), 'down() restores the orphan');
        $this->assertSame(64900, (int) DB::table('permissions')->where('name', 'orphan_child2')->value('parent_id'), 'down() restores parent_id');
        $this->assertSame(1, (int) DB::table('role_has_permissions')->where('permission_id', 63000)->count(), 'down() restores the dangling grant');
    }
}
