<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Policies\AppointmentPolicy;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P-R / Tier 2a — repoint the legacy "Treatments" gates to the dotted
 * catalog, then delete the dead legacy group.
 *
 * Pins: (1) AppointmentPolicy gates treatments on the dotted treatments.list.view;
 * (2) no repointed source still references the legacy slugs; (3) the migration
 * deletes the legacy subtree + grants while the dotted keeper survives; (4) down()
 * fully restores. Teeth: revert any repoint and the source-contract test reddens;
 * revert the policy edit and the behavioral test reddens.
 */
class TreatmentsLegacyDedupTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_19_110000_delete_treatments_legacy_duplicate.php';

    /** every source whose legacy treatments gate was repointed to dotted. */
    private const REPOINTED = [
        'app/Policies/AppointmentPolicy.php',
        'app/Http/Controllers/Admin/PatientsController.php',
        'app/Http/Controllers/Admin/AppointmentsController.php',
        'app/Http/Controllers/Api/AppointmentsController.php',
        'app/Http/Controllers/Admin/Appointments/AppointmentExportController.php',
        'app/Services/Appointment/ConsultancyDatatableService.php',
    ];

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

    public function test_appointment_policy_gates_treatments_on_the_dotted_slug(): void
    {
        $this->createPermission('treatments.list.view');
        $policy = new AppointmentPolicy();

        $with = User::factory()->create();
        $with->givePermissionTo('treatments.list.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $with = $with->fresh();

        $this->assertTrue($policy->viewTreatments($with), 'holder of treatments.list.view can view treatments');
        $this->assertTrue($policy->manageTreatments($with), 'holder of treatments.list.view can manage treatments');

        $without = User::factory()->create();
        $this->assertFalse($policy->viewTreatments($without), 'non-holder cannot view treatments');
        $this->assertFalse($policy->manageTreatments($without), 'non-holder cannot manage treatments');
    }

    public function test_repointed_sources_drop_the_legacy_treatment_slugs(): void
    {
        foreach (self::REPOINTED as $rel) {
            $src = (string) file_get_contents(base_path($rel));
            $this->assertStringNotContainsString("'treatments_manage'", $src, "$rel still references legacy treatments_manage");
            $this->assertStringNotContainsString("'treatments_services'", $src, "$rel still references legacy treatments_services");
        }
        $this->assertStringContainsString(
            'treatments.list.view',
            (string) file_get_contents(base_path('app/Policies/AppointmentPolicy.php')),
            'policy must gate on the dotted slug',
        );

        // TreatmentService gates the after-arrived edit capability on the dotted
        // twins, not the legacy can_edit_* slugs (which are deleted with the
        // treatments_manage subtree) — only the SPA response-field keys survive.
        $ts = (string) file_get_contents(base_path('app/Services/Treatment/TreatmentService.php'));
        foreach (['can_edit_doctor', 'can_edit_service', 'can_edit_schedule'] as $slug) {
            $this->assertStringNotContainsString("allows('{$slug}')", $ts, "TreatmentService still Gate::allows the legacy {$slug}");
            $this->assertStringNotContainsString("can('{$slug}')", $ts, "TreatmentService still ->can the legacy {$slug}");
            $this->assertStringContainsString("'{$slug}' =>", $ts, "SPA response key {$slug} must be preserved");
        }
        $this->assertStringContainsString('treatments.edit.doctor.after_arrived', $ts);
    }

    public function test_migration_deletes_legacy_treatments_subtree_keeps_dotted(): void
    {
        $this->group('treatments');         // dotted keeper (canonical)
        $this->group('treatments_manage');  // dead legacy umbrella
        $this->child('treatments_services', 'treatments_manage');
        $this->child('treatments_edit', 'treatments_manage');

        $role = $this->createRole('TxHolder');
        $role->givePermissionTo(['treatments_manage', 'treatments_services']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $legacyId = DB::table('permissions')->where('name', 'treatments_manage')->value('id');

        $this->runUp();

        $this->assertFalse($this->exists('treatments_manage'), 'dead legacy parent must be deleted');
        $this->assertFalse($this->exists('treatments_services'), 'dead legacy child must be deleted');
        $this->assertFalse($this->exists('treatments_edit'), 'dead legacy child must be deleted');
        $this->assertTrue($this->exists('treatments'), 'dotted keeper must survive');
        $this->assertSame(
            0,
            (int) DB::table('role_has_permissions')->where('permission_id', $legacyId)->count(),
            'stale grants on the deleted legacy perm must be gone',
        );
    }

    public function test_migration_down_restores_deleted_rows_and_grants(): void
    {
        $this->group('treatments');
        $this->group('treatments_manage');
        $this->child('treatments_services', 'treatments_manage');
        $role = $this->createRole('TxHolder2');
        $role->givePermissionTo('treatments_services');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleId = $role->id;

        $this->runUp();
        $this->assertFalse($this->exists('treatments_services'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->exists('treatments_manage'), 'down() must restore the deleted parent');
        $this->assertTrue($this->exists('treatments_services'), 'down() must restore the deleted child');
        $childId = DB::table('permissions')->where('name', 'treatments_services')->value('id');
        $this->assertTrue(
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $childId)->exists(),
            'down() must restore the deleted grant',
        );
    }
}
