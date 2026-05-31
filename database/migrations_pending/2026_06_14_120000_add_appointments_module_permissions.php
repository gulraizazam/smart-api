<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Appointments module — fine-grained permission catalog
 * (`appointments.<area>.<action>`).
 *
 * Covers the cross-cutting appointment surfaces that BOTH consultations
 * and treatments share: image attachments, body measurements, medical
 * history form, the audit log, the patient-card drawer, and the
 * appointment-to-plan launch. Lives in Clinic category.
 *
 * NOT in scope for this migration — already covered elsewhere:
 *   - `appointments_consultancy` / `_edit` / `_destroy` / `_export*` /
 *     `_invoice*` / `_appointment_status` were mirrored to the
 *     consultations.* + treatments.* catalogs in their respective
 *     module migrations (2026_05_22 + 2026_05_24). Those rows live
 *     under a different parent group and continue to be sources for
 *     existing mirror grants — leave them alone here.
 *   - `appointments_services` is unused in the codebase; ignore.
 *
 * Catalog (group head `appointments` + 14 children):
 *   Umbrella
 *     - appointments.manage              broad "is appointments-admin"
 *                                        gate (legacy AppointmentCommentController +
 *                                        AppointmentsController fallthrough)
 *   Image attachments
 *     - appointments.image.view          row-action visibility
 *     - appointments.image.upload
 *     - appointments.image.destroy
 *   Body measurements
 *     - appointments.measurement.view
 *     - appointments.measurement.create
 *     - appointments.measurement.edit
 *   Medical history form
 *     - appointments.medical_form.view
 *     - appointments.medical_form.create
 *     - appointments.medical_form.edit
 *   Cross-cutting
 *     - appointments.log.view            audit log drawer
 *     - appointments.log.export          export audit log to Excel
 *     - appointments.patient_card.view   open patient quick-view drawer
 *     - appointments.plans.create        launch plan-create from row
 *
 * Mirror grants (legacy → new):
 *   appointments_manage             → appointments.manage
 *   appointments_image_manage       → appointments.image.view
 *   appointments_image_upload       → appointments.image.upload
 *   appointments_image_destroy      → appointments.image.destroy
 *   appointments_measurement_manage → appointments.measurement.view
 *   appointments_measurement_create → appointments.measurement.create
 *   appointments_measurement_edit   → appointments.measurement.edit
 *   appointments_medical_form_manage → appointments.medical_form.view
 *   appointments_medical_create     → appointments.medical_form.create
 *   appointments_medical_edit       → appointments.medical_form.edit
 *   appointments_log                → appointments.log.view
 *   appointments_log_excel          → appointments.log.export
 *   appointments_patient_card       → appointments.patient_card.view
 *   appointments_plans_create       → appointments.plans.create
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `TreatmentService::getPermissions`, `ConsultancyDatatableService::getPermissions`,
 * `PatientService` (patient-detail tab gates), `AppointmentMedicalService` /
 * `AppointmentMeasurementService` / `AppointmentImageService` row-action
 * builders, `Api\AppointmentCommentController`, `Api\AppointmentsController`,
 * `Admin\AppointmentMeasurementController` (and friends) all still call
 * the underscore names. Companion hide-legacy migration runs after the
 * controller / service rewrite.
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
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'appointments.manage',              'title' => 'Appointments admin (umbrella)'],

            ['name' => 'appointments.image.view',          'title' => 'Images · View'],
            ['name' => 'appointments.image.upload',        'title' => 'Images · Upload'],
            ['name' => 'appointments.image.destroy',       'title' => 'Images · Delete'],

            ['name' => 'appointments.measurement.view',    'title' => 'Measurements · View'],
            ['name' => 'appointments.measurement.create',  'title' => 'Measurements · Create'],
            ['name' => 'appointments.measurement.edit',    'title' => 'Measurements · Edit'],

            ['name' => 'appointments.medical_form.view',   'title' => 'Medical Form · View'],
            ['name' => 'appointments.medical_form.create', 'title' => 'Medical Form · Create'],
            ['name' => 'appointments.medical_form.edit',   'title' => 'Medical Form · Edit'],

            ['name' => 'appointments.log.view',            'title' => 'Audit Log · View'],
            ['name' => 'appointments.log.export',          'title' => 'Audit Log · Export'],

            ['name' => 'appointments.patient_card.view',   'title' => 'Open patient quick-view'],
            ['name' => 'appointments.plans.create',        'title' => 'Launch plan-create from row'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'appointments_manage'             => ['appointments.manage'],

            'appointments_image_manage'       => ['appointments.image.view'],
            'appointments_image_upload'       => ['appointments.image.upload'],
            'appointments_image_destroy'      => ['appointments.image.destroy'],

            'appointments_measurement_manage' => ['appointments.measurement.view'],
            'appointments_measurement_create' => ['appointments.measurement.create'],
            'appointments_measurement_edit'   => ['appointments.measurement.edit'],

            'appointments_medical_form_manage' => ['appointments.medical_form.view'],
            'appointments_medical_create'     => ['appointments.medical_form.create'],
            'appointments_medical_edit'       => ['appointments.medical_form.edit'],

            'appointments_log'                => ['appointments.log.view'],
            'appointments_log_excel'          => ['appointments.log.export'],

            'appointments_patient_card'       => ['appointments.patient_card.view'],
            'appointments_plans_create'       => ['appointments.plans.create'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'appointments'],
                [
                    'title' => 'Appointments',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Clinic',
                    'guard_name' => self::GUARD,
                ],
            );

            foreach ($this->newPerms() as $i => $row) {
                Permission::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'main_group' => 0,
                        'parent_id' => $group->id,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => $i + 1,
                    ],
                );
            }

            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());

            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            $perms = DB::table('permissions')
                ->whereIn('name', array_keys($this->legacyToNewMap()))
                ->get(['id', 'name'])
                ->keyBy('name');

            $newPermIds = DB::table('permissions')
                ->whereIn('name', $newNames)
                ->pluck('id', 'name');

            foreach ($this->legacyToNewMap() as $legacy => $news) {
                if (! isset($perms[$legacy])) {
                    continue;
                }
                $legacyId = (int) $perms[$legacy]->id;
                $rolesWithLegacy = DB::table('role_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->pluck('role_id');
                $usersWithLegacy = DB::table('model_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->get(['model_id', 'model_type']);

                foreach ($news as $newName) {
                    $newId = (int) ($newPermIds[$newName] ?? 0);
                    if ($newId <= 0) {
                        continue;
                    }
                    foreach ($rolesWithLegacy as $roleId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $newId],
                            [],
                        );
                    }
                    foreach ($usersWithLegacy as $assoc) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            [
                                'permission_id' => $newId,
                                'model_id'      => $assoc->model_id,
                                'model_type'    => $assoc->model_type,
                            ],
                            [],
                        );
                    }
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $names = array_map(static fn ($r) => $r['name'], $this->newPerms());
            Permission::whereIn('name', $names)->delete();

            // Don't delete the existing `appointments_manage` group — only
            // the new dotted `appointments` group head this migration
            // created. The legacy umbrella pre-existed (id=65) and stays.
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
};
