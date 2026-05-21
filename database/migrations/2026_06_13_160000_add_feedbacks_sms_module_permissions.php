<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Small Settings catalogs — `feedbacks.*` (Doctor Ratings) +
 * `sms_templates.*`.
 *
 * Two short Settings entries paired in one migration. Neither legacy
 * group had a clean catalog: feedbacks_manage held create/edit/delete
 * only (no list-view row), sms_templates_manage held edit + activate +
 * deactivate only (no create / list-view rows even though
 * StoreSMSTemplateRequest already gates `sms_templates_manage` to
 * enable creation). The dotted catalog formalises the intended action
 * set so the controller rewrite can stop reusing `*_manage` as a
 * stand-in for `*.create` / `*.list.view`.
 *
 * Catalog (2 group heads + 10 children):
 *   feedbacks (4)
 *     - feedbacks.list.view
 *     - feedbacks.create
 *     - feedbacks.edit
 *     - feedbacks.delete
 *   sms_templates (6)
 *     - sms_templates.list.view
 *     - sms_templates.list.view_inactive
 *     - sms_templates.create
 *     - sms_templates.edit
 *     - sms_templates.activate
 *     - sms_templates.deactivate
 *
 * Bug surfaced & fixed via the mirror: `Models\SMSTemplates` checks
 * `view_inactive_smstemplates` (no underscore between `sms` and
 * `templates`) but the orphan permission row in the DB is
 * `view_inactive_sms_templates` (with the underscore). The misspelling
 * means the inactive-template scope has been silently denying every
 * non-admin role for years. Mirroring BOTH spellings onto the new
 * `sms_templates.list.view_inactive` perm keeps the broken consumer
 * working while the model gets re-pointed at the dotted name in the
 * follow-up rewrite.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `FeedbackController`, `FeedbacksReportController`, the 6 Feedback
 * FormRequests, `FeedbackService`, `TreatmentService::getPermissions`,
 * `Admin\SMSTemplatesController`, `Api\SMSTemplatesController`, the 3
 * SMSTemplate FormRequests, `SMSTemplateService`, and the `SMSTemplates`
 * model active-scope all still call the underscore names. Companion
 * hide-legacy migration runs after the controller rewrite.
 *
 * Worth flagging separately during that rewrite: `FeedbackController`
 * + `FeedbacksReportController` use `abort_unless(..., 401)` for authz
 * failures. The cross-cutting rule is that authz must return 403 (a
 * 401 force-logs-out the SPA via api.ts). Out of scope for THIS
 * migration but tee it up for the controller rewrite.
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
            'feedbacks' => [
                'title' => 'Doctor Ratings',
                'children' => [
                    ['name' => 'feedbacks.list.view', 'title' => 'View list'],
                    ['name' => 'feedbacks.create',    'title' => 'Create'],
                    ['name' => 'feedbacks.edit',      'title' => 'Edit'],
                    ['name' => 'feedbacks.delete',    'title' => 'Delete'],
                ],
            ],
            'sms_templates' => [
                'title' => 'SMS Templates',
                'children' => [
                    ['name' => 'sms_templates.list.view',          'title' => 'View list'],
                    ['name' => 'sms_templates.list.view_inactive', 'title' => 'See inactive templates'],
                    ['name' => 'sms_templates.create',             'title' => 'Create'],
                    ['name' => 'sms_templates.edit',               'title' => 'Edit'],
                    ['name' => 'sms_templates.activate',           'title' => 'Activate'],
                    ['name' => 'sms_templates.deactivate',         'title' => 'Deactivate'],
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
            // Feedbacks (Doctor Ratings).
            'feedbacks_manage'  => ['feedbacks.list.view'],
            'feedbacks_create'  => ['feedbacks.create'],
            'feedbacks_edit'    => ['feedbacks.edit'],
            'feedbacks_delete'  => ['feedbacks.delete'],

            // SMS templates — `_manage` doubled as create-gate in
            // StoreSMSTemplateRequest, so it mirrors onto both
            // list.view AND create to preserve effective access.
            'sms_templates_manage'         => ['sms_templates.list.view', 'sms_templates.create'],
            'sms_templates_edit'           => ['sms_templates.edit'],
            'sms_templates_active'         => ['sms_templates.activate'],
            'sms_templates_inactive'       => ['sms_templates.deactivate'],
            'view_inactive_sms_templates'  => ['sms_templates.list.view_inactive'],
            // Misspelled model-side gate — only mirrored if the row
            // somehow exists in the DB (it shouldn't, hence the bug).
            'view_inactive_smstemplates'   => ['sms_templates.list.view_inactive'],
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
