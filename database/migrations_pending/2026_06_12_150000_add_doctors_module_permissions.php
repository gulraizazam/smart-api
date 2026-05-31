<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Doctors module — fine-grained permission catalog (`doctors.<action>`).
 *
 * Adds 9 new permissions plus a People-section group head for the SPA's
 * doctor-management page. Same shape as `users` plus `doctors.allocate`
 * (the assign-services-to-doctor surface — DoctorService::permissions()
 * already returns the legacy `doctors_allocate` flag and the
 * Api\DoctorController gates four allocate/list endpoints on it).
 *
 * Catalog (group head `doctors` + 9 children):
 *   - doctors.list.view
 *   - doctors.list.view_inactive
 *   - doctors.create
 *   - doctors.edit
 *   - doctors.destroy
 *   - doctors.activate
 *   - doctors.deactivate
 *   - doctors.change_password
 *   - doctors.allocate
 *
 * Mirror grants (1-to-1):
 *   doctors_manage          → doctors.list.view
 *   doctors_create          → doctors.create
 *   doctors_edit            → doctors.edit
 *   doctors_destroy         → doctors.destroy
 *   doctors_active          → doctors.activate
 *   doctors_inactive        → doctors.deactivate
 *   doctors_change_password → doctors.change_password
 *   doctors_allocate        → doctors.allocate
 *   view_inactive_doctors   → doctors.list.view_inactive
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Api\DoctorController` and `DoctorService` still call the underscore
 * names. Companion `hide_legacy_doctors_group` migration runs after the
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
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'doctors.list.view',           'title' => 'View list'],
            ['name' => 'doctors.list.view_inactive',  'title' => 'See inactive doctors'],
            ['name' => 'doctors.create',              'title' => 'Create'],
            ['name' => 'doctors.edit',                'title' => 'Edit'],
            ['name' => 'doctors.destroy',             'title' => 'Delete'],
            ['name' => 'doctors.activate',            'title' => 'Activate'],
            ['name' => 'doctors.deactivate',          'title' => 'Deactivate'],
            ['name' => 'doctors.change_password',     'title' => 'Change password (admin reset)'],
            ['name' => 'doctors.allocate',            'title' => 'Allocate services / centres'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'doctors_manage'          => ['doctors.list.view'],
            'doctors_create'          => ['doctors.create'],
            'doctors_edit'            => ['doctors.edit'],
            'doctors_destroy'         => ['doctors.destroy'],
            'doctors_active'          => ['doctors.activate'],
            'doctors_inactive'        => ['doctors.deactivate'],
            'doctors_change_password' => ['doctors.change_password'],
            'doctors_allocate'        => ['doctors.allocate'],
            'view_inactive_doctors'   => ['doctors.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'doctors'],
                [
                    'title' => 'Doctors',
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

            Permission::where('name', 'doctors')
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
