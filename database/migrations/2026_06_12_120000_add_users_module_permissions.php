<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Users (Application Users / staff) module — fine-grained permission
 * catalog (`users.<action>`).
 *
 * Adds 8 new permissions plus a People-section group head for the SPA's
 * staff-management page. Mirrors the discounts / payment_modes shape.
 *
 * Catalog (group head `users` + 8 children):
 *   - users.list.view              datatable + index page
 *   - users.list.view_inactive     surface inactive rows in the list
 *   - users.create
 *   - users.edit
 *   - users.destroy
 *   - users.activate
 *   - users.deactivate
 *   - users.change_password        admin-reset another user's password
 *
 * IMPORTANT — partial migration: legacy `users_*` + `view_inactive_users`
 * rows stay alive at status=1 because every consumer
 * (Api\ApplicationUserController, ApplicationUserService, UserPolicy, the
 * PatientController access-scope gates, Admin\Patients\RefundsController,
 * Admin\PatientsController) still calls `Gate::allows('users_manage')`
 * etc. directly. A companion `hide_legacy_users_group` migration will
 * flip them to status=0 once those consumers point at the dotted
 * catalog. Mirror grants below keep every role's effective access
 * intact on day 1.
 *
 * Mirror grants (legacy → new):
 *   users_manage           → users.list.view
 *   users_create           → users.create
 *   users_edit             → users.edit
 *   users_destroy          → users.destroy
 *   users_active           → users.activate
 *   users_inactive         → users.deactivate
 *   users_change_password  → users.change_password
 *   view_inactive_users    → users.list.view_inactive
 *
 * NOTE: `users_manage` is ALSO over-applied in the codebase as a
 * patient-access-scope gate (see PatientController:311,724 and
 * Admin\Patients\RefundsController:297). That's a long-standing
 * misnomer — those call sites are checking "is this user a staff
 * member with full visibility" rather than "can the user CRUD the
 * staff list". Untangling that lives in the controller rewrite, not
 * here. The mirror grant keeps both intents working under the legacy
 * name until then.
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
            ['name' => 'users.list.view',           'title' => 'View list'],
            ['name' => 'users.list.view_inactive',  'title' => 'See inactive users'],
            ['name' => 'users.create',              'title' => 'Create'],
            ['name' => 'users.edit',                'title' => 'Edit'],
            ['name' => 'users.destroy',             'title' => 'Delete'],
            ['name' => 'users.activate',            'title' => 'Activate'],
            ['name' => 'users.deactivate',          'title' => 'Deactivate'],
            ['name' => 'users.change_password',     'title' => 'Change password (admin reset)'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'users_manage'          => ['users.list.view'],
            'users_create'          => ['users.create'],
            'users_edit'            => ['users.edit'],
            'users_destroy'         => ['users.destroy'],
            'users_active'          => ['users.activate'],
            'users_inactive'        => ['users.deactivate'],
            'users_change_password' => ['users.change_password'],
            'view_inactive_users'   => ['users.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'users'],
                [
                    'title' => 'Users',
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

            Permission::where('name', 'users')
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
