<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dissolve the standalone `appointments.*` group head into the existing
 * `consultations.*` + `treatments.*` catalogs.
 *
 * The previous appointments-module migration (2026_06_14_120000) created
 * a separate `appointments` group head to hold the cross-cutting
 * attachment / log / patient-card / plans-create perms. In practice
 * those actions are surfaced from BOTH the consultation row actions
 * AND the treatment row actions (see TreatmentService::getPermissions
 * + ConsultancyDatatableService::getPermissions — both call
 * `Gate::allows('appointments_*')` for the same set of flags).
 *
 * Per follow-up product feedback: the role editor's section-tick
 * checkbox already gives a one-click "grant everything in this module"
 * affordance, so a third grouping isn't needed. Cleaner to give each
 * module its own copy of the perms — that way the role editor lets you
 * tick "image upload for consultations" independently of "image upload
 * for treatments", which matches the way the rest of the catalog is
 * organised.
 *
 * What this migration does:
 *
 *   1. Adds the cross-cutting perms into BOTH consultations.* and
 *      treatments.* catalogs (under their existing group heads).
 *   2. Bridges every legacy `appointments_*` grant + every transitional
 *      `appointments.*` grant onto BOTH new homes, so no role loses
 *      effective access.
 *   3. Deletes the 14 `appointments.*` perms created by the previous
 *      migration and removes the `appointments` group head.
 *
 * Perms added (12 in consultations + 13 in treatments — the +1 in
 * treatments is `treatments.view_activity`, which didn't exist; the
 * consultation side already had `consultations.view_activity` from the
 * original consultations module catalog):
 *
 *   consultations.image.view / .upload / .destroy
 *   consultations.measurement.view / .create / .edit
 *   consultations.medical_form.view / .create / .edit
 *   consultations.export_activity
 *   consultations.patient_card.view
 *   consultations.plans.create
 *
 *   treatments.image.view / .upload / .destroy
 *   treatments.measurement.view / .create / .edit
 *   treatments.medical_form.view / .create / .edit
 *   treatments.view_activity
 *   treatments.export_activity
 *   treatments.patient_card.view
 *   treatments.plans.create
 *
 * The `appointments.manage` umbrella from the previous migration is
 * NOT bridged — nothing in the codebase references it. Admin roles
 * still get every individual action via the admin loop in this
 * migration. Non-admin roles holding the legacy `appointments_manage`
 * row are unchanged (that row stays alive until the appointments
 * controller rewrite finishes).
 *
 * The legacy `appointments_*` underscore rows (status=1) stay alive
 * because TreatmentService, ConsultancyDatatableService, PatientService,
 * the AppointmentMedicalService / AppointmentMeasurementService /
 * AppointmentImageService row-action builders, AppointmentCommentController,
 * AppointmentMeasurementController etc. still call them. Companion
 * hide-legacy migration runs after that rewrite.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Super Admin'];

    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    /**
     * New perms added to each existing group. The consultation side
     * skips `view_activity` because it already exists (from the
     * original consultations module migration).
     *
     * @return array<string, list<array{name: string, title: string, sort: int}>>
     */
    private function additions(): array
    {
        return [
            'consultations' => [
                ['name' => 'consultations.image.view',          'title' => 'Images · View',          'sort' => 30],
                ['name' => 'consultations.image.upload',        'title' => 'Images · Upload',        'sort' => 31],
                ['name' => 'consultations.image.destroy',       'title' => 'Images · Delete',        'sort' => 32],
                ['name' => 'consultations.measurement.view',    'title' => 'Measurements · View',    'sort' => 33],
                ['name' => 'consultations.measurement.create',  'title' => 'Measurements · Create',  'sort' => 34],
                ['name' => 'consultations.measurement.edit',    'title' => 'Measurements · Edit',    'sort' => 35],
                ['name' => 'consultations.medical_form.view',   'title' => 'Medical Form · View',    'sort' => 36],
                ['name' => 'consultations.medical_form.create', 'title' => 'Medical Form · Create',  'sort' => 37],
                ['name' => 'consultations.medical_form.edit',   'title' => 'Medical Form · Edit',    'sort' => 38],
                ['name' => 'consultations.export_activity',     'title' => 'Activity Log · Export',  'sort' => 39],
                ['name' => 'consultations.patient_card.view',   'title' => 'Open patient quick-view', 'sort' => 40],
                ['name' => 'consultations.plans.create',        'title' => 'Launch plan-create from row', 'sort' => 41],
            ],
            'treatments' => [
                ['name' => 'treatments.image.view',          'title' => 'Images · View',          'sort' => 30],
                ['name' => 'treatments.image.upload',        'title' => 'Images · Upload',        'sort' => 31],
                ['name' => 'treatments.image.destroy',       'title' => 'Images · Delete',        'sort' => 32],
                ['name' => 'treatments.measurement.view',    'title' => 'Measurements · View',    'sort' => 33],
                ['name' => 'treatments.measurement.create',  'title' => 'Measurements · Create',  'sort' => 34],
                ['name' => 'treatments.measurement.edit',    'title' => 'Measurements · Edit',    'sort' => 35],
                ['name' => 'treatments.medical_form.view',   'title' => 'Medical Form · View',    'sort' => 36],
                ['name' => 'treatments.medical_form.create', 'title' => 'Medical Form · Create',  'sort' => 37],
                ['name' => 'treatments.medical_form.edit',   'title' => 'Medical Form · Edit',    'sort' => 38],
                ['name' => 'treatments.view_activity',       'title' => 'Activity Log · View',    'sort' => 39],
                ['name' => 'treatments.export_activity',     'title' => 'Activity Log · Export',  'sort' => 40],
                ['name' => 'treatments.patient_card.view',   'title' => 'Open patient quick-view', 'sort' => 41],
                ['name' => 'treatments.plans.create',        'title' => 'Launch plan-create from row', 'sort' => 42],
            ],
        ];
    }

    /**
     * Source perms (legacy underscore + transitional `appointments.*`)
     * whose grants are forwarded onto the new per-module homes. Each
     * source row's grants flow to BOTH list entries so a role keeps
     * the action across consultation AND treatment surfaces.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function bridges(): array
    {
        return [
            'appointments_image_manage'        => ['consultations.image.view',          'treatments.image.view'],
            'appointments_image_upload'        => ['consultations.image.upload',        'treatments.image.upload'],
            'appointments_image_destroy'       => ['consultations.image.destroy',       'treatments.image.destroy'],

            'appointments_measurement_manage'  => ['consultations.measurement.view',    'treatments.measurement.view'],
            'appointments_measurement_create'  => ['consultations.measurement.create',  'treatments.measurement.create'],
            'appointments_measurement_edit'    => ['consultations.measurement.edit',    'treatments.measurement.edit'],

            'appointments_medical_form_manage' => ['consultations.medical_form.view',   'treatments.medical_form.view'],
            'appointments_medical_create'      => ['consultations.medical_form.create', 'treatments.medical_form.create'],
            'appointments_medical_edit'        => ['consultations.medical_form.edit',   'treatments.medical_form.edit'],

            // appointments_log → consultations.view_activity (which already
            // exists from the original consultations migration) AND the new
            // treatments.view_activity row.
            'appointments_log'                 => ['consultations.view_activity',       'treatments.view_activity'],
            'appointments_log_excel'           => ['consultations.export_activity',     'treatments.export_activity'],

            'appointments_patient_card'        => ['consultations.patient_card.view',   'treatments.patient_card.view'],
            'appointments_plans_create'        => ['consultations.plans.create',        'treatments.plans.create'],

            // Transitional dotted rows from the previous migration —
            // bridge their grants the same way so admins (and anyone
            // else who picked them up via the admin loop) keep
            // effective access on both sides.
            'appointments.image.view'          => ['consultations.image.view',          'treatments.image.view'],
            'appointments.image.upload'        => ['consultations.image.upload',        'treatments.image.upload'],
            'appointments.image.destroy'       => ['consultations.image.destroy',       'treatments.image.destroy'],
            'appointments.measurement.view'    => ['consultations.measurement.view',    'treatments.measurement.view'],
            'appointments.measurement.create'  => ['consultations.measurement.create',  'treatments.measurement.create'],
            'appointments.measurement.edit'    => ['consultations.measurement.edit',    'treatments.measurement.edit'],
            'appointments.medical_form.view'   => ['consultations.medical_form.view',   'treatments.medical_form.view'],
            'appointments.medical_form.create' => ['consultations.medical_form.create', 'treatments.medical_form.create'],
            'appointments.medical_form.edit'   => ['consultations.medical_form.edit',   'treatments.medical_form.edit'],
            'appointments.log.view'            => ['consultations.view_activity',       'treatments.view_activity'],
            'appointments.log.export'          => ['consultations.export_activity',     'treatments.export_activity'],
            'appointments.patient_card.view'   => ['consultations.patient_card.view',   'treatments.patient_card.view'],
            'appointments.plans.create'        => ['consultations.plans.create',        'treatments.plans.create'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $groupIds = [
                'consultations' => (int) Permission::where('name', 'consultations')->where('main_group', 1)->value('id'),
                'treatments'    => (int) Permission::where('name', 'treatments')->where('main_group', 1)->value('id'),
            ];

            $allNewNames = [];
            foreach ($this->additions() as $module => $rows) {
                $parentId = $groupIds[$module] ?? 0;
                if ($parentId === 0) {
                    continue;
                }
                foreach ($rows as $row) {
                    Permission::updateOrCreate(
                        ['name' => $row['name']],
                        [
                            'title' => $row['title'],
                            'main_group' => 0,
                            'parent_id' => $parentId,
                            'status' => 1,
                            'category' => null,
                            'guard_name' => self::GUARD,
                            'sort_order' => $row['sort'],
                        ],
                    );
                    $allNewNames[] = $row['name'];
                }
            }

            // Admin loop — grant every new perm to admin roles.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($allNewNames);
            }

            // Bridge legacy + transitional grants onto the new homes.
            $newPermIds = DB::table('permissions')
                ->whereIn('name', $allNewNames)
                ->pluck('id', 'name');

            // Consultations.view_activity exists from the original module
            // catalog — pull its id too so the appointments_log bridge
            // can target it without creating a duplicate row.
            $newPermIds['consultations.view_activity'] = (int) DB::table('permissions')
                ->where('name', 'consultations.view_activity')
                ->value('id');

            $sourcePerms = DB::table('permissions')
                ->whereIn('name', array_keys($this->bridges()))
                ->get(['id', 'name'])
                ->keyBy('name');

            foreach ($this->bridges() as $sourceName => $targets) {
                if (! isset($sourcePerms[$sourceName])) {
                    continue;
                }
                $sourceId = (int) $sourcePerms[$sourceName]->id;

                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $sourceId)
                    ->pluck('role_id');
                $userGrants = DB::table('model_has_permissions')
                    ->where('permission_id', $sourceId)
                    ->get(['model_id', 'model_type']);

                foreach ($targets as $targetName) {
                    $targetId = (int) ($newPermIds[$targetName] ?? 0);
                    if ($targetId <= 0) {
                        continue;
                    }
                    foreach ($roleIds as $roleId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $targetId],
                            [],
                        );
                    }
                    foreach ($userGrants as $grant) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            [
                                'permission_id' => $targetId,
                                'model_id' => $grant->model_id,
                                'model_type' => $grant->model_type,
                            ],
                            [],
                        );
                    }
                }
            }

            // Tear down the appointments.* dotted catalog — all of its
            // grants have been forwarded. The legacy `appointments_*`
            // underscore rows (and the legacy `appointments_manage`
            // group head) stay intact; only the transitional dotted
            // rows + their group head go away.
            $appointmentsToDrop = array_filter(array_keys($this->bridges()), static fn ($n) => str_starts_with($n, 'appointments.'));
            $appointmentsToDrop[] = 'appointments.manage';
            Permission::whereIn('name', $appointmentsToDrop)->delete();
            Permission::where('name', 'appointments')
                ->where('main_group', 1)
                ->where('category', 'Clinic')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        // No reverse migration — re-running the previous appointments
        // module migration recreates the `appointments.*` rows, but
        // the new consultations.*/treatments.* perms added here can't
        // be safely rolled back without losing grants that may have
        // accrued post-deployment. If a rollback is needed, manually
        // run 2026_06_14_120000's down() and accept that the new
        // per-module perms below survive (they're harmless leftovers).
        DB::transaction(function (): void {
            $allNewNames = [];
            foreach ($this->additions() as $rows) {
                foreach ($rows as $row) {
                    $allNewNames[] = $row['name'];
                }
            }
            Permission::whereIn('name', $allNewNames)->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
