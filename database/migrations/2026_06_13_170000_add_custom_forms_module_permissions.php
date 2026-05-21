<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom forms + Custom form feedbacks — `custom_forms.*` +
 * `custom_form_feedbacks.*`.
 *
 * Two Settings-section modules covering the patient-facing form builder
 * (intake forms, measurements, medical history) and the per-form
 * feedback collector. Bundled together because they share infrastructure
 * (CustomFormService + CustomFormFeedbackService) and naturally sit
 * side-by-side in the role editor.
 *
 * Catalog (2 group heads + 17 children):
 *   custom_forms (11)
 *     - custom_forms.list.view
 *     - custom_forms.list.view_inactive
 *     - custom_forms.create_general
 *     - custom_forms.create_measurement
 *     - custom_forms.create_medical_history_form
 *     - custom_forms.edit
 *     - custom_forms.destroy
 *     - custom_forms.activate
 *     - custom_forms.deactivate
 *     - custom_forms.preview
 *     - custom_forms.submit
 *   custom_form_feedbacks (6)
 *     - custom_form_feedbacks.list.view
 *     - custom_form_feedbacks.edit
 *     - custom_form_feedbacks.destroy
 *     - custom_form_feedbacks.activate
 *     - custom_form_feedbacks.deactivate
 *     - custom_form_feedbacks.preview
 *
 * Mirror grants are 1-to-1 for the underscore actions plus the orphan
 * `view_inactive_custom_forms` row. The form-feedback half intentionally
 * gains `.destroy` / `.activate` / `.deactivate` rows that never existed
 * in the legacy DB — `CustomFormFeedbackService::permissions()` checks
 * those names but they were never seeded, so the action flags resolved
 * false for every non-admin role. The new rows finally make those
 * actions grantable.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * the `Admin\CustomFormsController` / `Api\CustomFormsController` /
 * `CustomFormFeedbacksController` consumers + `CustomFormService` +
 * `CustomFormFeedbackService` still call the underscore names, and the
 * `Models\CustomForms` active-scope reads `view_inactive_custom_forms`
 * directly. Companion hide-legacy migration runs after the rewrite.
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
     * @return array<string, array{title: string, children: list<array{name: string, title: string}>}>
     */
    private function modules(): array
    {
        return [
            'custom_forms' => [
                'title' => 'Custom Forms',
                'children' => [
                    ['name' => 'custom_forms.list.view',                    'title' => 'View list'],
                    ['name' => 'custom_forms.list.view_inactive',           'title' => 'See inactive forms'],
                    ['name' => 'custom_forms.create_general',               'title' => 'Create general form'],
                    ['name' => 'custom_forms.create_measurement',           'title' => 'Create measurement form'],
                    ['name' => 'custom_forms.create_medical_history_form',  'title' => 'Create medical history form'],
                    ['name' => 'custom_forms.edit',                         'title' => 'Edit'],
                    ['name' => 'custom_forms.destroy',                      'title' => 'Delete'],
                    ['name' => 'custom_forms.activate',                     'title' => 'Activate'],
                    ['name' => 'custom_forms.deactivate',                   'title' => 'Deactivate'],
                    ['name' => 'custom_forms.preview',                      'title' => 'Preview'],
                    ['name' => 'custom_forms.submit',                       'title' => 'Submit (fill on patient)'],
                ],
            ],
            'custom_form_feedbacks' => [
                'title' => 'Custom Form Feedbacks',
                'children' => [
                    ['name' => 'custom_form_feedbacks.list.view',  'title' => 'View list'],
                    ['name' => 'custom_form_feedbacks.edit',       'title' => 'Edit'],
                    ['name' => 'custom_form_feedbacks.destroy',    'title' => 'Delete'],
                    ['name' => 'custom_form_feedbacks.activate',   'title' => 'Activate'],
                    ['name' => 'custom_form_feedbacks.deactivate', 'title' => 'Deactivate'],
                    ['name' => 'custom_form_feedbacks.preview',    'title' => 'Preview'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'custom_forms_manage'                    => ['custom_forms.list.view'],
            'custom_forms_create_general'            => ['custom_forms.create_general'],
            'custom_forms_create_measurement'        => ['custom_forms.create_measurement'],
            'custom_forms_create_medical_history_form' => ['custom_forms.create_medical_history_form'],
            'custom_forms_edit'                      => ['custom_forms.edit'],
            'custom_forms_destroy'                   => ['custom_forms.destroy'],
            'custom_forms_active'                    => ['custom_forms.activate'],
            'custom_forms_inactive'                  => ['custom_forms.deactivate'],
            'custom_forms_preview'                   => ['custom_forms.preview'],
            'custom_forms_submit'                    => ['custom_forms.submit'],
            'view_inactive_custom_forms'             => ['custom_forms.list.view_inactive'],

            'custom_form_feedbacks_manage'   => ['custom_form_feedbacks.list.view'],
            'custom_form_feedbacks_edit'     => ['custom_form_feedbacks.edit'],
            'custom_form_feedbacks_preview'  => ['custom_form_feedbacks.preview'],
            // _destroy / _active / _inactive aren't in the DB so they have
            // no mirror — admins get the new perms via the admin loop;
            // non-admin roles get them granted manually via the role editor.
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $allNewNames = [];

            foreach ($this->modules() as $groupName => $def) {
                $group = Permission::updateOrCreate(
                    ['name' => $groupName],
                    [
                        'title' => $def['title'],
                        'main_group' => 1,
                        'parent_id' => 0,
                        'status' => 1,
                        'category' => 'Settings',
                        'guard_name' => self::GUARD,
                    ],
                );

                foreach ($def['children'] as $i => $row) {
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
                    $allNewNames[] = $row['name'];
                }
            }

            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($allNewNames);
            }

            $perms = DB::table('permissions')
                ->whereIn('name', array_keys($this->legacyToNewMap()))
                ->get(['id', 'name'])
                ->keyBy('name');

            $newPermIds = DB::table('permissions')
                ->whereIn('name', $allNewNames)
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
            $allNames = [];
            foreach ($this->modules() as $def) {
                foreach ($def['children'] as $row) {
                    $allNames[] = $row['name'];
                }
            }

            Permission::whereIn('name', $allNames)->delete();
            Permission::whereIn('name', array_keys($this->modules()))
                ->where('main_group', 1)
                ->where('category', 'Settings')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
