<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Discounts module — fine-grained permission catalog (`discounts.<action>`).
 *
 * Adds 8 new permissions plus a Catalog-section group head for the SPA
 * `/discounts` page (also covers configurable Buy/Get discount rules
 * and centre/service allocation).
 *
 * The legacy `discounts_*` umbrella was already in place but mixed with
 * an inconsistent `view_inactive_discounts` token. Several backend
 * gates were also returning `401` instead of `403` for authz failures —
 * that gets fixed in the controller rewrite that lands alongside this
 * migration (a 401 force-logs-out the SPA, per the cross-cutting rule).
 *
 * Catalog (group head `discounts` + 8 children):
 *   - discounts.list.view              datatable + index page
 *   - discounts.list.view_inactive     show inactive discounts in the list
 *   - discounts.create
 *   - discounts.edit
 *   - discounts.destroy                single + bulk
 *   - discounts.activate
 *   - discounts.deactivate
 *   - discounts.allocate               centre/service allocation
 *
 * Mirror grants (no role loses access on day 1):
 *   discounts_manage         → discounts.list.view
 *   discounts_create         → discounts.create
 *   discounts_edit           → discounts.edit
 *   discounts_active         → discounts.activate
 *   discounts_inactive       → discounts.deactivate
 *   discounts_destroy        → discounts.destroy
 *   discounts_allocate       → discounts.allocate
 *   view_inactive_discounts  → discounts.list.view_inactive
 *
 * Companion migration `2026_06_01_130000_hide_legacy_discounts_group`
 * flips the legacy rows to status=0 once the gate rewrite is in place.
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
            ['name' => 'discounts.list.view',           'title' => 'View list'],
            ['name' => 'discounts.list.view_inactive',  'title' => 'See inactive discounts'],
            ['name' => 'discounts.create',              'title' => 'Create'],
            ['name' => 'discounts.edit',                'title' => 'Edit'],
            ['name' => 'discounts.destroy',             'title' => 'Delete'],
            ['name' => 'discounts.activate',            'title' => 'Activate'],
            ['name' => 'discounts.deactivate',          'title' => 'Deactivate'],
            ['name' => 'discounts.allocate',            'title' => 'Allocate to centres / services'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'discounts_manage'        => ['discounts.list.view'],
            'discounts_create'        => ['discounts.create'],
            'discounts_edit'          => ['discounts.edit'],
            'discounts_active'        => ['discounts.activate'],
            'discounts_inactive'      => ['discounts.deactivate'],
            'discounts_destroy'       => ['discounts.destroy'],
            'discounts_allocate'      => ['discounts.allocate'],
            'view_inactive_discounts' => ['discounts.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'discounts'],
                [
                    'title' => 'Discounts',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Catalog',
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

            Permission::where('name', 'discounts')
                ->where('main_group', 1)
                ->where('category', 'Catalog')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
