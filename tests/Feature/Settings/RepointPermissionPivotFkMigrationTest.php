<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins 2026_05_19_120000_repoint_permission_pivot_fks_to_permissions.
 *
 * Reproduces the staging integrity bug: a leftover `permissions1` table with
 * `role_has_permissions.permission_id` FK misbound to it, so granting a
 * permission that exists only in `permissions` fails with SQLSTATE 1452.
 * Asserts the migration drops the misbound FK, rebinds it to `permissions`,
 * the grant then succeeds, the run is idempotent, and that on the normal
 * schema (no `permissions1`, no FK) it is a strict no-op.
 *
 * NOTE: this test issues DDL (implicit-commit), so per-test transactional
 * isolation does not apply — scrub() runs before AND after each test so the
 * shared schema is pristine regardless of order or prior residue.
 */
class RepointPermissionPivotFkMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function fkRef(string $table): ?string
    {
        $rows = DB::select(
            'SELECT REFERENCED_TABLE_NAME AS ref FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
            . "AND COLUMN_NAME = 'permission_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1",
            [$table]
        );

        return $rows[0]->ref ?? null;
    }

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_05_19_120000_repoint_permission_pivot_fks_to_permissions.php'
        );
        $migration->up();
    }

    /** Restore the dump's clean shape: no pivot FK, no permissions1, no test rows. */
    private function scrub(): void
    {
        foreach (['fk_rhp_permission'] as $fk) {
            try {
                DB::statement("ALTER TABLE role_has_permissions DROP FOREIGN KEY {$fk}");
            } catch (\Throwable $e) {
            }
        }
        try {
            DB::statement('DROP TABLE IF EXISTS permissions1');
        } catch (\Throwable $e) {
        }
        DB::table('role_has_permissions')->delete();
        DB::table('permissions')->where('name', 'like', 'dashboard.fdm.%')->delete();
        DB::table('roles')->where('name', 'FDM')->delete();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->scrub();
    }

    protected function tearDown(): void
    {
        $this->scrub();
        parent::tearDown();
    }

    public function test_misbound_fk_is_repointed_and_grant_succeeds(): void
    {
        // --- Reconstruct the staging-broken shape ---
        DB::statement('CREATE TABLE permissions1 LIKE permissions');
        DB::statement('INSERT INTO permissions1 SELECT * FROM permissions');
        DB::statement(
            'ALTER TABLE role_has_permissions ADD CONSTRAINT fk_rhp_permission '
            . 'FOREIGN KEY (permission_id) REFERENCES permissions1(id) ON DELETE CASCADE'
        );
        $this->assertSame('permissions1', $this->fkRef('role_has_permissions'));

        // A permission that exists only in `permissions` (the failure shape).
        $perm = Permission::create([
            'name' => 'dashboard.fdm.cash.view',
            'title' => 'Cash (Live + Week)',
            'main_group' => 0,
            'parent_id' => 0,
            'status' => 1,
            'guard_name' => 'web',
            'sort_order' => 1,
        ]);
        $role = $this->createRole('FDM');

        // --- Apply the corrective migration ---
        $this->runMigration();
        $this->assertSame(
            'permissions',
            $this->fkRef('role_has_permissions'),
            'FK must be repointed from permissions1 to permissions'
        );

        // The grant that previously raised SQLSTATE 1452 now succeeds.
        $role->givePermissionTo($perm->name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($role->fresh()->hasPermissionTo('dashboard.fdm.cash.view'));

        // Idempotent: a second run is a clean no-op, still bound to permissions.
        $this->runMigration();
        $this->assertSame('permissions', $this->fkRef('role_has_permissions'));
    }

    public function test_no_op_on_clean_schema_without_permissions1_or_fk(): void
    {
        $this->assertNull($this->fkRef('role_has_permissions'));

        // Must not throw and must not invent an FK where there was none.
        $this->runMigration();

        $this->assertNull(
            $this->fkRef('role_has_permissions'),
            'Migration must not create an FK on a schema that never had one'
        );
    }
}
