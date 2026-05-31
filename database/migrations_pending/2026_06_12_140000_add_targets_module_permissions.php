<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Targets modules — `centre_targets.<action>` + `staff_targets.<action>`.
 *
 * Two tightly-paired People-sidebar entries; one migration covers both so
 * the role editor shows the new dotted catalog for the whole Targets
 * surface in a single step.
 *
 * centre_targets (group head + 7 children):
 *   - centre_targets.list.view
 *   - centre_targets.create
 *   - centre_targets.edit
 *   - centre_targets.destroy
 *   - centre_targets.activate
 *   - centre_targets.deactivate
 *   - centre_targets.allocate     allocate target to a centre / service set
 *
 * staff_targets (group head + 4 children):
 *   - staff_targets.list.view
 *   - staff_targets.create
 *   - staff_targets.edit
 *   - staff_targets.destroy
 *
 * Why centre_targets gets `.activate` / `.deactivate` / `.allocate` but
 * staff_targets doesn't: `CentreTargetService::permissions()` checks all
 * three legacy names (`centre_targets_active`, `_inactive`, `_allocate`)
 * but those rows were never seeded — so non-admins could never hold the
 * actions. The new catalog backfills the missing rows under the dotted
 * names so the next controller rewrite can stop relying on the
 * super-admin Gate::before fallback. staff_targets, by contrast, has no
 * controller / service layer (only the legacy Blade sidebar references
 * it) — keeping the new catalog minimal until that module gets a
 * real backend audit.
 *
 * Mirror grants:
 *   centre_targets_manage   → centre_targets.list.view
 *   centre_targets_create   → centre_targets.create
 *   centre_targets_edit     → centre_targets.edit
 *   centre_targets_destroy  → centre_targets.destroy
 *   staff_targets_manage    → staff_targets.list.view
 *   staff_targets_create    → staff_targets.create
 *   staff_targets_edit      → staff_targets.edit
 *   staff_targets_destroy   → staff_targets.destroy
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * the centre-target controllers / service still call the underscore
 * names. The `hide_legacy_targets_groups` companion runs after the
 * code rewrite.
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
            'centre_targets' => [
                'title' => 'Centre Targets',
                'children' => [
                    ['name' => 'centre_targets.list.view',  'title' => 'View list'],
                    ['name' => 'centre_targets.create',     'title' => 'Create'],
                    ['name' => 'centre_targets.edit',       'title' => 'Edit'],
                    ['name' => 'centre_targets.destroy',    'title' => 'Delete'],
                    ['name' => 'centre_targets.activate',   'title' => 'Activate'],
                    ['name' => 'centre_targets.deactivate', 'title' => 'Deactivate'],
                    ['name' => 'centre_targets.allocate',   'title' => 'Allocate to centres / services'],
                ],
            ],
            'staff_targets' => [
                'title' => 'Staff Targets',
                'children' => [
                    ['name' => 'staff_targets.list.view',  'title' => 'View list'],
                    ['name' => 'staff_targets.create',     'title' => 'Create'],
                    ['name' => 'staff_targets.edit',       'title' => 'Edit'],
                    ['name' => 'staff_targets.destroy',    'title' => 'Delete'],
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
            'centre_targets_manage'  => ['centre_targets.list.view'],
            'centre_targets_create'  => ['centre_targets.create'],
            'centre_targets_edit'    => ['centre_targets.edit'],
            'centre_targets_destroy' => ['centre_targets.destroy'],

            'staff_targets_manage'   => ['staff_targets.list.view'],
            'staff_targets_create'   => ['staff_targets.create'],
            'staff_targets_edit'     => ['staff_targets.edit'],
            'staff_targets_destroy'  => ['staff_targets.destroy'],
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
                        'category' => 'People',
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

            // Admin roles: every new perm across both sub-modules.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($allNewNames);
            }

            // Back-compat mirror.
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
                ->where('category', 'People')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
