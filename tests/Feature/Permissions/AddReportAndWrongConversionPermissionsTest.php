<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the migration that creates the three grantable catalog perms the
 * 2026-06-19 QA found missing — without these, the Doctor Revenue / Doctor
 * Incentive reports and the Wrong Conversions tool were reachable only by
 * Super-Admin (the SPA/server gated on slugs that didn't exist). up() makes each
 * a visible role-editor group; down() removes them and any role grants.
 *
 * Teeth: drop a name from the migration and its "created" assertion reddens.
 */
class AddReportAndWrongConversionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'migrations/2026_06_19_200000_add_report_and_wrong_conversion_permissions.php';
    private const NAMES = ['doctor_revenue_manage', 'doctor_incentive_report', 'wrong_conversions_manage'];

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

    public function test_up_creates_three_grantable_report_permissions(): void
    {
        $this->runUp();

        foreach (self::NAMES as $name) {
            $row = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            $this->assertNotNull($row, "permission {$name} must be created");
            $this->assertSame(1, (int) $row->main_group, "{$name} must render as a role-editor group");
            $this->assertSame(1, (int) $row->status, "{$name} must be active/visible");
        }

        // grantable: a role can actually be given one
        $role = $this->createRole('ReportHolder');
        $role->givePermissionTo('doctor_revenue_manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($role->fresh()->hasPermissionTo('doctor_revenue_manage'));
    }

    public function test_down_removes_the_permissions_and_their_grants(): void
    {
        $this->runUp();

        $role = $this->createRole('ReportHolder2');
        $role->givePermissionTo(self::NAMES);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::NAMES as $name) {
            $this->assertFalse(
                DB::table('permissions')->where('name', $name)->exists(),
                "down() must remove {$name}",
            );
        }
        $this->assertSame(
            0,
            (int) DB::table('role_has_permissions')->where('role_id', $role->id)->count(),
            'down() must remove the role grants of the deleted perms',
        );
    }
}
