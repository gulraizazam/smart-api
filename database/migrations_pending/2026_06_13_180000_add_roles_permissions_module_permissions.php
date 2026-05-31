<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Meta perms — `roles.*` + `permissions.*`.
 *
 * The two Settings entries that gate the role editor itself.
 * Granted to roles managers only (typically Administrator + Super-Admin)
 * since changes here ripple to every other module's effective access.
 *
 * Catalog (2 group heads + 10 children):
 *   roles (6)
 *     - roles.list.view
 *     - roles.create
 *     - roles.edit
 *     - roles.destroy
 *     - roles.destroy_bulk
 *     - roles.duplicate
 *   permissions (4)
 *     - permissions.list.view
 *     - permissions.create
 *     - permissions.edit
 *     - permissions.destroy
 *
 * Mirror is 1-to-1 against the legacy underscore catalog.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `UserPolicy::createRole/updateRole/createPermission/updatePermission`,
 * `RoleService::getUserPermissions`, `PermissionService::getPermissions`,
 * `Api\PermissionController`, and the role editor UI still call the
 * underscore names. Companion `hide_legacy_meta_groups` migration runs
 * after the controller rewrite.
 *
 * Note: a stray row `eeeee` exists under `permissions_manage` (id=1) —
 * looks like a developer's test artifact. Out of scope for this
 * migration; flag for cleanup as part of the controller rewrite or via
 * a separate housekeeping migration.
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
            'roles' => [
                'title' => 'Roles',
                'children' => [
                    ['name' => 'roles.list.view',     'title' => 'View list'],
                    ['name' => 'roles.create',        'title' => 'Create'],
                    ['name' => 'roles.edit',          'title' => 'Edit'],
                    ['name' => 'roles.destroy',       'title' => 'Delete'],
                    ['name' => 'roles.destroy_bulk',  'title' => 'Delete (bulk)'],
                    ['name' => 'roles.duplicate',     'title' => 'Duplicate'],
                ],
            ],
            'permissions' => [
                'title' => 'Permissions',
                'children' => [
                    ['name' => 'permissions.list.view',  'title' => 'View list'],
                    ['name' => 'permissions.create',     'title' => 'Create'],
                    ['name' => 'permissions.edit',       'title' => 'Edit'],
                    ['name' => 'permissions.destroy',    'title' => 'Delete'],
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
            'roles_manage'         => ['roles.list.view'],
            'roles_create'         => ['roles.create'],
            'roles_edit'           => ['roles.edit'],
            'roles_destroy'        => ['roles.destroy'],
            'roles_destroy_bulk'   => ['roles.destroy_bulk'],
            'roles_duplicate'      => ['roles.duplicate'],

            'permissions_manage'   => ['permissions.list.view'],
            'permissions_create'   => ['permissions.create'],
            'permissions_edit'     => ['permissions.edit'],
            'permissions_destroy'  => ['permissions.destroy'],
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
