<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Geography lookups — `regions.*` + `cities.*` + `towns.*`.
 *
 * Three Settings-section lookup tables that the centre / patient /
 * locations stack reads from. Same shape (8 actions each) except
 * `towns` has an Excel-import action where `regions` / `cities` have a
 * reorder action — both reflect the legacy reality of which rows were
 * actually seeded for each module.
 *
 * Catalog (3 group heads + 24 children):
 *   regions / cities (8 each)
 *     - <module>.list.view
 *     - <module>.list.view_inactive
 *     - <module>.create
 *     - <module>.edit
 *     - <module>.destroy
 *     - <module>.activate
 *     - <module>.deactivate
 *     - <module>.sort
 *   towns (8)
 *     - towns.list.view
 *     - towns.list.view_inactive
 *     - towns.create
 *     - towns.edit
 *     - towns.destroy
 *     - towns.activate
 *     - towns.deactivate
 *     - towns.import                  legacy `towns_import`
 *
 * Mirror is 1-to-1 for the underscore actions plus the orphan
 * `view_inactive_<module>` rows that the Models scopes already gate on.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Api\RegionsController` / `CitiesController` / `TownsController` plus
 * `RegionService` / `CityService` / `TownService`, the 9 geography
 * FormRequests, and the 3 Models' `scope*Active` methods still call the
 * underscore names. Companion `hide_legacy_geography_groups` migration
 * runs after the controller rewrite.
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
        $standard = static fn (string $module): array => [
            ['name' => "{$module}.list.view",          'title' => 'View list'],
            ['name' => "{$module}.list.view_inactive", 'title' => 'See inactive rows'],
            ['name' => "{$module}.create",             'title' => 'Create'],
            ['name' => "{$module}.edit",               'title' => 'Edit'],
            ['name' => "{$module}.destroy",            'title' => 'Delete'],
            ['name' => "{$module}.activate",           'title' => 'Activate'],
            ['name' => "{$module}.deactivate",         'title' => 'Deactivate'],
            ['name' => "{$module}.sort",               'title' => 'Reorder'],
        ];

        return [
            'regions' => ['title' => 'Regions', 'children' => $standard('regions')],
            'cities'  => ['title' => 'Cities',  'children' => $standard('cities')],
            'towns'   => [
                'title' => 'Towns',
                'children' => [
                    ['name' => 'towns.list.view',           'title' => 'View list'],
                    ['name' => 'towns.list.view_inactive',  'title' => 'See inactive rows'],
                    ['name' => 'towns.create',              'title' => 'Create'],
                    ['name' => 'towns.edit',                'title' => 'Edit'],
                    ['name' => 'towns.destroy',             'title' => 'Delete'],
                    ['name' => 'towns.activate',            'title' => 'Activate'],
                    ['name' => 'towns.deactivate',          'title' => 'Deactivate'],
                    ['name' => 'towns.import',              'title' => 'Import from Excel'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        $standard = static fn (string $m): array => [
            "{$m}_manage"             => ["{$m}.list.view"],
            "{$m}_create"             => ["{$m}.create"],
            "{$m}_edit"                => ["{$m}.edit"],
            "{$m}_destroy"            => ["{$m}.destroy"],
            "{$m}_active"             => ["{$m}.activate"],
            "{$m}_inactive"           => ["{$m}.deactivate"],
            "view_inactive_{$m}"      => ["{$m}.list.view_inactive"],
        ];

        return array_merge(
            // regions: standard + sort
            $standard('regions'),
            ['regions_sort' => ['regions.sort']],
            // cities: standard + sort
            $standard('cities'),
            ['cities_sort' => ['cities.sort']],
            // towns: standard (no sort row in DB) + import
            $standard('towns'),
            ['towns_import' => ['towns.import']],
        );
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
