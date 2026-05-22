<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Settings remainder — small / single-page modules folded into a single
 * cleanup migration.
 *
 * Six legacy umbrella groups that were each held only a `_manage`
 * umbrella (and at most one `*_edit` child) get a proper two-perm
 * dotted catalog. The cross-cutting `contact` umbrella (which gates
 * phone-number visibility across the whole app) gets normalised to
 * `contact.view` so the role editor surfaces it under Settings instead
 * of the existing "Business Ops" pseudo-category.
 *
 * NOT in scope: `show_inactive_records` group. Its 20 `view_inactive_*`
 * children are already mirrored per-module onto the `<module>.list.view_inactive`
 * dotted perms by the geography / lookup / patient / catalog / settings
 * migrations. The grouping itself was a role-editor UI convenience
 * bucket; the dotted catalogs make it redundant. The companion
 * hide-legacy migrations (one per module, run after the controller
 * rewrites) will hide those rows individually.
 *
 * Catalog (7 group heads + 9 children):
 *   settings (2)
 *     - settings.view
 *     - settings.edit
 *   google_reviews (1)
 *     - google_reviews.view
 *   user_operator_settings (2)
 *     - user_operator_settings.view
 *     - user_operator_settings.edit
 *   pabao_records (1)
 *     - pabao_records.view
 *   logs (1)
 *     - logs.view
 *   activity_logs (1)
 *     - activity_logs.view
 *   contact (1)
 *     - contact.view
 *
 * Mirror grants:
 *   settings_manage              → settings.view
 *   settings_edit                → settings.edit
 *   google_reviews_manage        → google_reviews.view
 *   user_operator_settings_manage → user_operator_settings.view
 *   user_operator_settings_edit  → user_operator_settings.edit
 *   pabao_records_manage         → pabao_records.view
 *   logs_manage                  → logs.view
 *   activity_logs_manage         → activity_logs.view
 *   contact                      → contact.view
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * many consumers (`SettingsController`, `LogsController`, `GoogleReviewsController`,
 * `UserOperatorSettingsController`, `OperatorSettingsController`,
 * `ActivityLogsApiController`, plus ~17 call sites checking
 * `Gate::allows('contact')` for phone visibility) still use the
 * underscore / bare names. Companion hide-legacy migration runs after
 * the consumer rewrite.
 *
 * Notable: `contact` is the bare gate name (no `_manage` suffix or
 * children) — that's already on the legacy DB row. The mirror keeps
 * the bare name working alongside the new `contact.view`. The legacy
 * row's category was "Business Ops"; the new `contact` group sits in
 * Settings to match the rest of the cleanup.
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
            'settings' => [
                'title' => 'Settings',
                'children' => [
                    ['name' => 'settings.view', 'title' => 'View settings'],
                    ['name' => 'settings.edit', 'title' => 'Edit settings'],
                ],
            ],
            'google_reviews' => [
                'title' => 'Google Reviews',
                'children' => [
                    ['name' => 'google_reviews.view', 'title' => 'View Google reviews'],
                ],
            ],
            'user_operator_settings' => [
                'title' => 'User Operator Settings',
                'children' => [
                    ['name' => 'user_operator_settings.view', 'title' => 'View'],
                    ['name' => 'user_operator_settings.edit', 'title' => 'Edit'],
                ],
            ],
            'pabao_records' => [
                'title' => 'Pabau Records',
                'children' => [
                    ['name' => 'pabao_records.view', 'title' => 'View Pabau records'],
                ],
            ],
            'logs' => [
                'title' => 'Logs',
                'children' => [
                    ['name' => 'logs.view', 'title' => 'View system logs'],
                ],
            ],
            'activity_logs' => [
                'title' => 'Activity Logs',
                'children' => [
                    ['name' => 'activity_logs.view', 'title' => 'View activity logs'],
                ],
            ],
            'contact' => [
                'title' => 'Contact',
                'children' => [
                    ['name' => 'contact.view', 'title' => 'View patient / lead phone numbers'],
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
            'settings_manage'              => ['settings.view'],
            'settings_edit'                => ['settings.edit'],

            'google_reviews_manage'        => ['google_reviews.view'],

            'user_operator_settings_manage' => ['user_operator_settings.view'],
            'user_operator_settings_edit'  => ['user_operator_settings.edit'],

            'pabao_records_manage'         => ['pabao_records.view'],

            'logs_manage'                  => ['logs.view'],

            'activity_logs_manage'         => ['activity_logs.view'],

            // The legacy `contact` row is BOTH a group head (main_group=1)
            // and a perm — the mirror picks up grants attached to that
            // same row regardless of which role the codebase pulls it as.
            'contact'                      => ['contact.view'],
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

            // Don't delete the `contact` group head — the legacy DB row
            // pre-dated this migration and still holds existing grants.
            // The other 6 group names were created fresh here; safe to
            // delete on rollback.
            Permission::whereIn('name', ['settings', 'google_reviews', 'user_operator_settings', 'pabao_records', 'logs', 'activity_logs'])
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
