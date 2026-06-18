<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P-R / Delete (Tier 1) — remove the 5 remaining dead duplicate groups the
 * title-collision audit found still rendering in the role editor.
 *
 * Pins both directions: deleting the dead LEGACY umbrella while the dotted
 * keeper survives (membership_types etc.), AND deleting the dead DOTTED report
 * shadow while the LEGACY keeper survives (future_treatments_reports →
 * followuppatient_manage). Plus the keeper guard and full down() restore.
 */
class DeleteTier1DuplicatePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_19_100000_delete_tier1_remaining_duplicate_permissions.php';

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

    public function test_deletes_dead_legacy_umbrella_and_grants_keeps_dotted_keeper(): void
    {
        $this->group('membership_types');         // dotted keeper (gated canonical)
        $this->group('membershiptypes_manage');   // dead legacy umbrella
        $this->child('membershiptypes_create', 'membershiptypes_manage');

        $role = $this->createRole('TypeHolder');
        $role->givePermissionTo(['membershiptypes_manage', 'membershiptypes_create']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $legacyId = DB::table('permissions')->where('name', 'membershiptypes_manage')->value('id');

        $this->runUp();

        $this->assertFalse($this->exists('membershiptypes_manage'), 'dead legacy parent must be deleted');
        $this->assertFalse($this->exists('membershiptypes_create'), 'dead legacy child must be deleted');
        $this->assertTrue($this->exists('membership_types'), 'dotted keeper must survive');
        $this->assertSame(
            0,
            (int) DB::table('role_has_permissions')->where('permission_id', $legacyId)->count(),
            'stale grants on the deleted legacy perm must be gone',
        );
    }

    public function test_deletes_dead_dotted_report_shadow_keeps_legacy_keeper(): void
    {
        // Inverted direction: the report controllers gate the LEGACY slug, so the
        // dotted leaf is the inert side that must be removed.
        $this->group('followuppatient_manage');     // legacy keeper (code gates this)
        $this->group('future_treatments_reports');  // dead dotted shadow
        $this->child('future_treatments_reports.view', 'future_treatments_reports');

        $this->runUp();

        $this->assertFalse($this->exists('future_treatments_reports'), 'dead dotted shadow must be deleted');
        $this->assertFalse($this->exists('future_treatments_reports.view'), 'dead dotted child must be deleted');
        $this->assertTrue($this->exists('followuppatient_manage'), 'legacy keeper (the gated side) must survive');
    }

    public function test_guard_skips_delete_when_keeper_absent(): void
    {
        $this->group('resource_types_manage'); // keeper 'resource_types' absent

        $this->runUp();

        $this->assertTrue($this->exists('resource_types_manage'), 'must NOT delete a side when its keeper twin is missing');
    }

    public function test_down_restores_deleted_rows_and_grants(): void
    {
        $this->group('staff_targets');         // dotted keeper
        $this->group('staff_targets_manage');  // dead legacy
        $this->child('staff_targets_create', 'staff_targets_manage');
        $role = $this->createRole('TargetHolder');
        $role->givePermissionTo('staff_targets_create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleId = $role->id;

        $this->runUp();
        $this->assertFalse($this->exists('staff_targets_create'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->exists('staff_targets_manage'), 'down() must restore the deleted parent');
        $this->assertTrue($this->exists('staff_targets_create'), 'down() must restore the deleted child');
        $createId = DB::table('permissions')->where('name', 'staff_targets_create')->value('id');
        $this->assertTrue(
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $createId)->exists(),
            'down() must restore the deleted grant',
        );
    }
}
