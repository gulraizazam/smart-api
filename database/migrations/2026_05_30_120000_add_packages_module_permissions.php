<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Packages module — fine-grained permission catalog (`packages.<action>`).
 *
 * Adds 9 new permissions plus a Catalog-section group head for the SPA
 * `/packages` page (multi-service bundles, backed by `Api\PackagesController`
 * / `Api\BundlesController` / `BundleService`). The legacy `packages_*`
 * perms remain in place — they are still referenced by `PlanPolicy` and a
 * handful of patient-plan Blade gates, which the upcoming Plans audit
 * will sweep. The mirror keeps both modules in lock-step until then.
 *
 * Naming note: in this codebase the UI label "Packages" maps to the
 * `bundles` DB table; the literal `packages` DB table holds patient
 * plans (a different concept). The role-editor perm names track the SPA
 * label since that is what ops see.
 *
 * Catalog:
 *   - packages.list.view              datatable + filters
 *   - packages.list.view_inactive     show inactive packages
 *   - packages.detail.view            open the detail dialog
 *   - packages.create
 *   - packages.edit
 *   - packages.destroy
 *   - packages.activate
 *   - packages.deactivate
 *   - packages.sort                   drag-and-drop reorder
 *
 * Mirror grants (no role loses access on day 1):
 *   packages_manage         → packages.list.view, packages.detail.view
 *   packages_create         → packages.create
 *   packages_edit           → packages.edit
 *   packages_active         → packages.activate
 *   packages_inactive       → packages.deactivate
 *   packages_destroy        → packages.destroy
 *   packages_sort           → packages.sort
 *   view_inactive_packages  → packages.list.view_inactive
 *
 * No companion drop-legacy migration: `PlanPolicy::*` still consults
 * `packages_manage` / `packages_edit` / `packages_destroy`. Those gates
 * get rewritten in the Plans audit; once that lands, the legacy umbrella
 * can be hidden in a follow-up migration.
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
            ['name' => 'packages.list.view',           'title' => 'View list'],
            ['name' => 'packages.list.view_inactive', 'title' => 'See inactive packages'],
            ['name' => 'packages.detail.view',         'title' => 'View package detail'],
            ['name' => 'packages.create',              'title' => 'Create'],
            ['name' => 'packages.edit',                'title' => 'Edit'],
            ['name' => 'packages.destroy',             'title' => 'Delete'],
            ['name' => 'packages.activate',            'title' => 'Activate'],
            ['name' => 'packages.deactivate',          'title' => 'Deactivate'],
            ['name' => 'packages.sort',                'title' => 'Reorder'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'packages_manage'        => ['packages.list.view', 'packages.detail.view'],
            'packages_create'        => ['packages.create'],
            'packages_edit'          => ['packages.edit'],
            'packages_active'        => ['packages.activate'],
            'packages_inactive'      => ['packages.deactivate'],
            'packages_destroy'       => ['packages.destroy'],
            'packages_sort'          => ['packages.sort'],
            'view_inactive_packages' => ['packages.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'packages'],
                [
                    'title' => 'Packages',
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

            Permission::where('name', 'packages')
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
