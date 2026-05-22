<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membership Types module — fine-grained permission catalog
 * (`membership_types.<action>`).
 *
 * Adds 6 new permissions plus a Catalog-section group head for the SPA
 * `/membership-types` page. Standardises the token shape to underscore-
 * separated `membership_types` (matches the `membership_types` DB table
 * and the SPA URL slug) — the legacy umbrella `membershiptypes_manage`
 * collapsed it without the underscore.
 *
 * Like Voucher Types, only `membershiptypes_manage` was actually seeded;
 * the `_create/_edit/_destroy/_active/_inactive` rows are referenced by
 * gate calls but never created, so they only resolve truthy for admins
 * (via wildcard). The companion code rewrite makes the new dotted perms
 * the canonical names so non-admin roles can be granted these actions.
 *
 * Catalog (group head `membership_types` + 6 children):
 *   - membership_types.list.view   datatable + index page
 *   - membership_types.create
 *   - membership_types.edit
 *   - membership_types.destroy
 *   - membership_types.activate
 *   - membership_types.deactivate
 *
 * Mirror grants (no role loses access on day 1):
 *   membershiptypes_manage    → membership_types.list.view
 *   membershiptypes_create    → membership_types.create
 *   membershiptypes_edit      → membership_types.edit
 *   membershiptypes_destroy   → membership_types.destroy
 *   membershiptypes_active    → membership_types.activate
 *   membershiptypes_inactive  → membership_types.deactivate
 *
 * Companion migration `2026_06_04_130000_hide_legacy_membershiptypes_group`
 * flips the legacy rows to status=0 once the rewrite lands.
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
            ['name' => 'membership_types.list.view',  'title' => 'View list'],
            ['name' => 'membership_types.create',     'title' => 'Create'],
            ['name' => 'membership_types.edit',       'title' => 'Edit'],
            ['name' => 'membership_types.destroy',    'title' => 'Delete'],
            ['name' => 'membership_types.activate',   'title' => 'Activate'],
            ['name' => 'membership_types.deactivate', 'title' => 'Deactivate'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'membershiptypes_manage'   => ['membership_types.list.view'],
            'membershiptypes_create'   => ['membership_types.create'],
            'membershiptypes_edit'     => ['membership_types.edit'],
            'membershiptypes_destroy'  => ['membership_types.destroy'],
            'membershiptypes_active'   => ['membership_types.activate'],
            'membershiptypes_inactive' => ['membership_types.deactivate'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'membership_types'],
                [
                    'title' => 'Membership Types',
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

            Permission::where('name', 'membership_types')
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
