<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Locations (Centres) module — fine-grained permission catalog
 * (`locations.<action>`).
 *
 * Adds 8 new permissions plus a Settings-section group head for the SPA
 * `/centres` page. Locations underpin every centre-scoped surface in the
 * app (cashflow, FDM, scheduling, plans, treatments, reports), so this
 * catalog is one of the foundation pieces — gets done before the
 * remaining Settings entries.
 *
 * Why the orphan rows matter: `view_inactive_centres` is the gate name
 * actually used by `Models\Locations::scopeActive()`, `MembershipService`,
 * `MembershipTypeService`, `AdminMembershipService`, but
 * `Api\CentresController:64` checks `view_inactive_locations` instead —
 * a typo that's been silently denying non-admins from listing inactive
 * centres in the SPA. Mirroring `view_inactive_centres` → the new
 * dotted name backfills the broken consumer; the controller rewrite
 * will retire both legacy spellings.
 *
 * Catalog (group head `locations` + 8 children):
 *   - locations.list.view
 *   - locations.list.view_inactive    show deactivated centres in dropdowns
 *   - locations.create
 *   - locations.edit
 *   - locations.destroy
 *   - locations.activate
 *   - locations.deactivate
 *   - locations.sort                  reorder UI position
 *
 * Mirror grants (legacy → new):
 *   locations_manage        → locations.list.view
 *   locations_create        → locations.create
 *   locations_edit          → locations.edit
 *   locations_destroy       → locations.destroy
 *   locations_active        → locations.activate
 *   locations_inactive      → locations.deactivate
 *   locations_sort          → locations.sort
 *   view_inactive_centres   → locations.list.view_inactive
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Api\CentresController`, `Admin\LocationsController`, `LocationService`,
 * 4 Admin\*Centre*Request FormRequests, and 4 Membership services still
 * call the underscore names. Companion `hide_legacy_locations_group`
 * migration runs after the controller rewrite.
 *
 * Group head label is "Centres" (matches the UI sidebar) but the DB
 * name stays `locations` to align with the table / model / service
 * naming downstream. Both labels are unambiguous; the underlying
 * concept is the same.
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
            ['name' => 'locations.list.view',           'title' => 'View list'],
            ['name' => 'locations.list.view_inactive',  'title' => 'See inactive centres'],
            ['name' => 'locations.create',              'title' => 'Create'],
            ['name' => 'locations.edit',                'title' => 'Edit'],
            ['name' => 'locations.destroy',             'title' => 'Delete'],
            ['name' => 'locations.activate',            'title' => 'Activate'],
            ['name' => 'locations.deactivate',          'title' => 'Deactivate'],
            ['name' => 'locations.sort',                'title' => 'Reorder'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'locations_manage'      => ['locations.list.view'],
            'locations_create'      => ['locations.create'],
            'locations_edit'        => ['locations.edit'],
            'locations_destroy'     => ['locations.destroy'],
            'locations_active'      => ['locations.activate'],
            'locations_inactive'    => ['locations.deactivate'],
            'locations_sort'        => ['locations.sort'],
            'view_inactive_centres' => ['locations.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'locations'],
                [
                    'title' => 'Centres',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Settings',
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

            Permission::where('name', 'locations')
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
