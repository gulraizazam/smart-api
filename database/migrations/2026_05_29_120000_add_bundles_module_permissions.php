<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bundles module — fine-grained permission catalog (`bundles.<action>`).
 *
 * Adds 10 new permissions plus a Catalog-section group head for the SPA
 * `/bundles` page (single-service × N-sessions bundles, backed by
 * `ServiceBundlesController`). Previously the page was gated entirely on
 * shared `packages_*` perms, which conflated this module with the
 * multi-service "packages" module (`BundlesController` → SPA `/packages`).
 * Splitting them gives ops independent control per module and clears up
 * the naming confusion before the Packages audit lands.
 *
 * Catalog:
 *   - bundles.list.view              datatable + filters
 *   - bundles.list.view_inactive     show inactive bundles in the list
 *   - bundles.detail.view            open the detail dialog
 *   - bundles.create                 create + bulk create
 *   - bundles.edit                   edit a bundle
 *   - bundles.destroy                delete (single + bulk)
 *   - bundles.activate
 *   - bundles.deactivate
 *   - bundles.sort                   category reorder
 *   - bundles.bulk_create            optional separate gate kept distinct
 *                                    from `.create` since bulk creates
 *                                    can land dozens of rows at once.
 *
 * Mirror grants (no role loses access on day 1):
 *   packages_manage         → bundles.list.view, bundles.detail.view
 *   packages_create         → bundles.create, bundles.bulk_create
 *   packages_edit           → bundles.edit, bundles.sort
 *   packages_active         → bundles.activate
 *   packages_inactive       → bundles.deactivate
 *   packages_destroy        → bundles.destroy
 *   view_inactive_packages  → bundles.list.view_inactive
 *
 * Why no companion "drop legacy" migration: the `packages_*` perms remain
 * in use by `BundlesController` / `PackagesController` (the multi-service
 * bundles flow). They get audited separately in the Packages module. The
 * mirror just gives both modules independent gate names from now on.
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
            ['name' => 'bundles.list.view',           'title' => 'View list'],
            ['name' => 'bundles.list.view_inactive', 'title' => 'See inactive bundles'],
            ['name' => 'bundles.detail.view',         'title' => 'View bundle detail'],
            ['name' => 'bundles.create',              'title' => 'Create'],
            ['name' => 'bundles.bulk_create',         'title' => 'Bulk create'],
            ['name' => 'bundles.edit',                'title' => 'Edit'],
            ['name' => 'bundles.destroy',             'title' => 'Delete'],
            ['name' => 'bundles.activate',            'title' => 'Activate'],
            ['name' => 'bundles.deactivate',          'title' => 'Deactivate'],
            ['name' => 'bundles.sort',                'title' => 'Reorder'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'packages_manage'        => ['bundles.list.view', 'bundles.detail.view'],
            'packages_create'        => ['bundles.create', 'bundles.bulk_create'],
            'packages_edit'          => ['bundles.edit', 'bundles.sort'],
            'packages_active'        => ['bundles.activate'],
            'packages_inactive'      => ['bundles.deactivate'],
            'packages_destroy'       => ['bundles.destroy'],
            'view_inactive_packages' => ['bundles.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'bundles'],
                [
                    'title' => 'Bundles',
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

            // Mirror legacy grants.
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

            Permission::where('name', 'bundles')
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
