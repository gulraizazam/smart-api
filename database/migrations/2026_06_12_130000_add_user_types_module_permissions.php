<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * User Types module — fine-grained permission catalog (`user_types.<action>`).
 *
 * Pairs with the users module catalog (2026_06_12_120000) and completes
 * the staff/people CRUD perms in the People sidebar section.
 *
 * Why this catalog had to be designed (not just renamed): the original
 * `user_types_manage` group seeded only TWO rows (`user_types_manage`
 * umbrella + `user_types_edit`), yet `Api\UserTypeController` checks
 * `user_types_create`, `user_types_destroy`, `user_types_active`, and
 * `user_types_inactive` directly. Those gates always resolved false for
 * non-admin roles because the permission rows didn't exist — actions
 * worked for super admins only via the Gate::before short-circuit.
 *
 * The new catalog adds the missing rows under explicit dotted names
 * (admins still see everything; non-admins now CAN be granted each
 * action individually).
 *
 * Catalog (group head `user_types` + 6 children):
 *   - user_types.list.view
 *   - user_types.create
 *   - user_types.edit
 *   - user_types.destroy
 *   - user_types.activate
 *   - user_types.deactivate
 *
 * Mirror (kept narrow — `user_types_manage` was effectively view-only
 * for any non-admin role since the action perms didn't exist in the DB
 * for them to hold):
 *   user_types_manage → user_types.list.view
 *   user_types_edit   → user_types.edit
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Admin\UserTypesController` and `Api\UserTypeController` still call
 * `Gate::allows('user_types_manage')` / `user_types_edit`. The
 * companion `hide_legacy_user_types_group` migration is deferred until
 * the controllers switch to the dotted catalog. The orphan gate names
 * the controller references (`user_types_create` / `_destroy` /
 * `_active` / `_inactive`) intentionally remain absent from the DB —
 * those call sites should be re-pointed at the dotted perms in the
 * rewrite, not back-seeded under the legacy names.
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
            ['name' => 'user_types.list.view',  'title' => 'View list'],
            ['name' => 'user_types.create',     'title' => 'Create'],
            ['name' => 'user_types.edit',       'title' => 'Edit'],
            ['name' => 'user_types.destroy',    'title' => 'Delete'],
            ['name' => 'user_types.activate',   'title' => 'Activate'],
            ['name' => 'user_types.deactivate', 'title' => 'Deactivate'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'user_types_manage' => ['user_types.list.view'],
            'user_types_edit'   => ['user_types.edit'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'user_types'],
                [
                    'title' => 'User Types',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'People',
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

            Permission::where('name', 'user_types')
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
