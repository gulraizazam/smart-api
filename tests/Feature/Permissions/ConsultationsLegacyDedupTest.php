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
 * Tier P-R / Tier 2b — dissolve the mislabeled legacy "Consultations" group.
 *
 * Pins: (1) AppointmentPolicy gates the consultation list on the dotted slug;
 * (2) repointed sources drop the legacy consultation gates but keep the SPA
 * response-field keys; (3) the migration re-parents the appointments_* perms
 * (preserving name/grants) into the renamed Appointments group and deletes only
 * the legacy group + its repointed update_consultation_* leftovers; (4) down()
 * restores the parent, title, and deleted rows + grants.
 */
class ConsultationsLegacyDedupTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_19_120000_dedup_consultations_rehome_appointments.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function group(string $name, ?string $title = null): void
    {
        $this->createPermission($name);
        DB::table('permissions')->where('name', $name)->update(['main_group' => 1, 'status' => 1, 'title' => $title ?? $name]);
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

    private function parentOf(string $name): ?int
    {
        $v = DB::table('permissions')->where('name', $name)->value('parent_id');

        return $v === null ? null : (int) $v;
    }

    private function titleOf(string $name): ?string
    {
        return DB::table('permissions')->where('name', $name)->value('title');
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedTier2bFixture(): void
    {
        $this->group('consultations');                                   // dotted keeper (canonical)
        $this->group('consultations_manage', 'Consultations');           // mislabeled legacy group to dissolve
        $this->group('appointments_manage', 'Appointment Attachments');  // rehome target
        $this->child('appointments_invoice', 'consultations_manage');    // live appointments perm (re-parent)
        $this->child('edit_after_arrived', 'consultations_manage');      // appointment-level (re-parent)
        $this->child('update_consultation_doctor', 'consultations_manage'); // repointed -> delete
    }

    public function test_appointment_policy_gates_consultations_view_on_the_dotted_slug(): void
    {
        $this->createPermission('consultations.list.view');
        $policy = new AppointmentPolicy();

        $with = User::factory()->create();
        $with->givePermissionTo('consultations.list.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($policy->viewAny($with->fresh()), 'consultations.list.view grants the appointment/consultation list view');
        $this->assertFalse($policy->viewAny(User::factory()->create()), 'a user with neither dotted view perm cannot list');
    }

    public function test_repointed_sources_drop_legacy_consultation_gates_but_keep_response_keys(): void
    {
        $policy = (string) file_get_contents(base_path('app/Policies/AppointmentPolicy.php'));
        $this->assertStringNotContainsString("'consultations_manage'", $policy);

        $ac = (string) file_get_contents(base_path('app/Http/Controllers/Admin/AppointmentsController.php'));
        $this->assertStringNotContainsString("allows('consultations_manage')", $ac);
        $this->assertStringNotContainsString("allows('update_consultation_doctor')", $ac);
        // SPA response-field keys must survive (the SPA reads these names):
        $this->assertStringContainsString("'update_consultation_doctor' =>", $ac);
    }

    public function test_migration_reparents_appointments_renames_group_and_deletes_legacy(): void
    {
        $this->seedTier2bFixture();
        $role = $this->createRole('ApptHolder');
        $role->givePermissionTo(['appointments_invoice', 'update_consultation_doctor']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $rehomeId = (int) DB::table('permissions')->where('name', 'appointments_manage')->value('id');
        $apptInvId = (int) DB::table('permissions')->where('name', 'appointments_invoice')->value('id');

        $this->runUp();

        // appointments_* preserved + re-parented into the renamed group, grant intact
        $this->assertTrue($this->exists('appointments_invoice'), 'live appointments perm must be preserved');
        $this->assertSame($rehomeId, $this->parentOf('appointments_invoice'), 're-parented into the Appointments group');
        $this->assertSame($rehomeId, $this->parentOf('edit_after_arrived'), 'edit_after_arrived re-parented too');
        $this->assertTrue(
            DB::table('role_has_permissions')->where('role_id', $role->id)->where('permission_id', $apptInvId)->exists(),
            'the appointments grant must be preserved through the re-parent',
        );
        $this->assertSame('Appointments', $this->titleOf('appointments_manage'), 'rehome group is renamed');

        // legacy group + its repointed consultation leftover are deleted
        $this->assertFalse($this->exists('consultations_manage'), 'legacy "Consultations" group must be deleted');
        $this->assertFalse($this->exists('update_consultation_doctor'), 'repointed consultation perm must be deleted');
        $this->assertTrue($this->exists('consultations'), 'dotted keeper must survive');
    }

    public function test_migration_down_restores_parent_title_and_deleted_rows(): void
    {
        $this->seedTier2bFixture();
        $role = $this->createRole('ApptHolder2');
        $role->givePermissionTo('update_consultation_doctor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->runUp();
        $this->assertFalse($this->exists('consultations_manage'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->exists('consultations_manage'), 'down() restores the legacy group');
        $this->assertTrue($this->exists('update_consultation_doctor'), 'down() restores the deleted child');
        $legacyId = (int) DB::table('permissions')->where('name', 'consultations_manage')->value('id');
        $this->assertSame($legacyId, $this->parentOf('appointments_invoice'), 'down() re-parents the appointments perm back');
        $this->assertSame('Appointment Attachments', $this->titleOf('appointments_manage'), 'down() restores the original title');
        $childId = (int) DB::table('permissions')->where('name', 'update_consultation_doctor')->value('id');
        $this->assertTrue(
            DB::table('role_has_permissions')->where('role_id', $role->id)->where('permission_id', $childId)->exists(),
            'down() restores the deleted grant',
        );
    }
}
