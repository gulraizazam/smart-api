<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Services module — fine-grained permission catalog (`services.<action>`).
 *
 * Adds 11 new permissions plus a Catalog-section group head for the
 * /services SPA page (Catalog → Services). Replaces the legacy
 * `services_manage` umbrella + nine flat `services_*` siblings plus the
 * top-level `view_inactive_services` perm, which were scattered across
 * different parents and didn't co-locate cleanly in the role editor.
 *
 * Catalog (group head `services` + 11 children):
 *   - services.list.view           — datatable + filters
 *   - services.list.view_inactive  — show inactive rows in the list
 *   - services.detail.view         — open the description/instructions modal
 *   - services.create
 *   - services.edit                — edit + bundle-impact preview
 *   - services.destroy
 *   - services.activate            — flip a service to active
 *   - services.deactivate          — flip a service to inactive
 *   - services.duplicate
 *   - services.sort                — parent + child reorder
 *   - services.export              — PDF export
 *
 * Mirror grants (no role loses access on day 1):
 *   services_manage         → services.list.view, services.detail.view
 *   services_create         → services.create
 *   services_edit           → services.edit
 *   services_active         → services.activate
 *   services_inactive       → services.deactivate
 *   services_destroy        → services.destroy
 *   services_sort           → services.sort
 *   services_duplicate      → services.duplicate
 *   services_detail         → services.detail.view
 *   services_export         → services.export
 *   view_inactive_services  → services.list.view_inactive
 *
 * Admin roles also get a blanket grant on the full new catalog. The legacy
 * perms are left in place (status flip in the companion Migration 2 once
 * code + SPA gates are pointing at the new names).
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
            ['name' => 'services.list.view',           'title' => 'View list'],
            ['name' => 'services.list.view_inactive', 'title' => 'See inactive services'],
            ['name' => 'services.detail.view',         'title' => 'View instructions / description'],
            ['name' => 'services.create',              'title' => 'Create'],
            ['name' => 'services.edit',                'title' => 'Edit'],
            ['name' => 'services.destroy',             'title' => 'Delete'],
            ['name' => 'services.activate',            'title' => 'Activate'],
            ['name' => 'services.deactivate',          'title' => 'Deactivate'],
            ['name' => 'services.duplicate',           'title' => 'Duplicate'],
            ['name' => 'services.sort',                'title' => 'Reorder'],
            ['name' => 'services.export',              'title' => 'Export PDF'],
        ];
    }

    /**
     * Legacy perm name → list of new perm names that should mirror its grants.
     *
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'services_manage'        => ['services.list.view', 'services.detail.view'],
            'services_create'        => ['services.create'],
            'services_edit'          => ['services.edit'],
            'services_active'        => ['services.activate'],
            'services_inactive'      => ['services.deactivate'],
            'services_destroy'       => ['services.destroy'],
            'services_sort'          => ['services.sort'],
            'services_duplicate'     => ['services.duplicate'],
            'services_detail'        => ['services.detail.view'],
            'services_export'        => ['services.export'],
            'view_inactive_services' => ['services.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'services'],
                [
                    'title' => 'Services',
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

            // Admin auto-grant — every new perm is granted to admin roles.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Mirror legacy grants. For every role that already holds a
            // legacy perm, grant the corresponding new perms so day-1
            // behaviour is preserved while the rewrite lands.
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

            Permission::where('name', 'services')
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
