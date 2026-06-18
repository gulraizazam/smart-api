<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P-R / R1 — retire redundant permission groups from the role editor.
 *
 * Pins both reconciliation directions and the keeper guard:
 *   - Class B (legacy canonical): the dotted shadow group is hidden, legacy kept.
 *   - Class A (dotted canonical): the legacy umbrella is hidden, dotted kept.
 *   - Guard: a group is hidden ONLY if its keeper twin is present + visible.
 *   - down() restores the prior status.
 *
 * Editor visibility = `main_group=1 AND status=1` (RoleService::buildPermissionGroup),
 * so "hidden" here means status flipped to 0 on the redundant parent group.
 */
class RetireRedundantPermissionGroupsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_18_140000_retire_redundant_permission_groups_from_editor.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Create a parent group row (main_group=1) at the given status. */
    private function group(string $name, int $status = 1): void
    {
        $this->createPermission($name);
        DB::table('permissions')->where('name', $name)->update(['main_group' => 1, 'status' => $status]);
    }

    private function groupStatus(string $name): int
    {
        return (int) DB::table('permissions')->where('name', $name)->value('status');
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_class_b_hides_the_dotted_shadow_and_keeps_legacy(): void
    {
        $this->group('roles');          // dotted shadow (redundant)
        $this->group('roles_manage');   // legacy canonical (keeper)

        $this->runUp();

        $this->assertSame(0, $this->groupStatus('roles'), 'dotted shadow must be hidden');
        $this->assertSame(1, $this->groupStatus('roles_manage'), 'legacy canonical must stay visible');
    }

    public function test_class_a_hides_the_legacy_umbrella_and_keeps_dotted(): void
    {
        $this->group('services');         // dotted canonical (keeper)
        $this->group('services_manage');  // legacy umbrella (redundant)

        $this->runUp();

        $this->assertSame(0, $this->groupStatus('services_manage'), 'legacy umbrella must be hidden');
        $this->assertSame(1, $this->groupStatus('services'), 'dotted canonical must stay visible');
    }

    public function test_guard_does_not_hide_when_the_keeper_is_absent(): void
    {
        // 'feedbacks' (dotted) is in the retire map keyed to keeper 'feedbacks_manage'.
        // With the keeper absent, the migration must NOT hide 'feedbacks' — never
        // leave a module with zero visible groups.
        $this->group('feedbacks');

        $this->runUp();

        $this->assertSame(1, $this->groupStatus('feedbacks'), 'must not hide when the keeper twin is missing');
    }

    public function test_down_restores_the_hidden_groups(): void
    {
        $this->group('roles');
        $this->group('roles_manage');

        $this->runUp();
        $this->assertSame(0, $this->groupStatus('roles'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame(1, $this->groupStatus('roles'), 'down() must restore the prior status');
    }

    public function test_is_idempotent(): void
    {
        $this->group('roles');
        $this->group('roles_manage');

        $this->runUp();
        $this->runUp(); // second run must not error or double-snapshot

        $this->assertSame(0, $this->groupStatus('roles'));
        $this->assertSame(
            1,
            (int) DB::table('permission_group_retire_p3r_backup')->where('permission_name', 'roles')->count(),
            'must snapshot a hidden group exactly once',
        );
    }
}
