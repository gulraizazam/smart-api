<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Services\UserManagement\RoleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the decorative-permission hide (2026-06-20 user decision): status=0 drops
 * a permission from the role editor (groups already filtered; children now too),
 * and the migration is reversible.
 *
 * Teeth: remove the `->where('status',1)` from RoleService::buildPermissionGroup
 * and the "hidden child" assertion reddens; revert the migration and the
 * hide/restore assertions redden.
 */
class HideDecorativePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_20_110000_hide_decorative_permissions_from_editor.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_editor_hides_a_status_zero_child_but_keeps_visible_ones(): void
    {
        $this->actingAsAdmin();

        $group = $this->createPermission('zz_test_group');
        DB::table('permissions')->where('id', $group->id)->update(['main_group' => 1, 'status' => 1, 'category' => 'Settings']);

        $visible = $this->createPermission('zz_test_group.visible');
        DB::table('permissions')->where('id', $visible->id)->update(['parent_id' => $group->id, 'main_group' => 0, 'status' => 1]);

        $hidden = $this->createPermission('zz_test_group.hidden');
        DB::table('permissions')->where('id', $hidden->id)->update(['parent_id' => $group->id, 'main_group' => 0, 'status' => 0]);

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $json = json_encode(app(RoleService::class)->getAllPermissionsMapping());

        $this->assertStringContainsString('zz_test_group.visible', $json, 'a status=1 child must still render');
        $this->assertStringNotContainsString('zz_test_group.hidden', $json, 'a status=0 child must be hidden from the editor');
    }

    public function test_migration_hides_decorative_perms_and_down_restores(): void
    {
        $sample = ['logs', 'treatments.image.view', 'centers_reports'];
        foreach ($sample as $name) {
            $p = $this->createPermission($name);
            DB::table('permissions')->where('id', $p->id)->update(['status' => 1]);
        }

        (require database_path(self::FILE))->up();
        foreach ($sample as $name) {
            $this->assertSame(0, (int) DB::table('permissions')->where('name', $name)->value('status'), "{$name} must be hidden");
        }

        (require database_path(self::FILE))->down();
        foreach ($sample as $name) {
            $this->assertSame(1, (int) DB::table('permissions')->where('name', $name)->value('status'), "{$name} must be restored");
        }
    }
}
