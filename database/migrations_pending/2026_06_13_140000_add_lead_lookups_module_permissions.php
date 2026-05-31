<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lead lookups — `lead_sources.*` + `lead_statuses.*`.
 *
 * Two Settings-section lookup tables consumed by the Leads list +
 * forms (and the conversion / arrival reports). Same shape as the
 * geography lookups: 8 actions each (standard CRUD + activate/
 * deactivate + sort + list.view_inactive).
 *
 * Catalog (2 group heads + 16 children):
 *   <module>.list.view / .list.view_inactive
 *   <module>.create / .edit / .destroy
 *   <module>.activate / .deactivate / .sort
 *
 * Mirror is 1-to-1 — same `<m>_<action>` shape across legacy and
 * dotted catalogs, plus the orphan `view_inactive_<m>` rows.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Api\LeadSourcesController` / `Api\LeadStatusesController` /
 * `Admin\LeadSourcesController` / `Admin\LeadStatusesController` and 8
 * Lead FormRequests still call the underscore names. Companion
 * `hide_legacy_lead_lookups_groups` migration runs after the rewrite.
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
            'lead_sources'  => ['title' => 'Lead Sources',  'children' => $standard('lead_sources')],
            'lead_statuses' => ['title' => 'Lead Statuses', 'children' => $standard('lead_statuses')],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        $standard = static fn (string $m): array => [
            "{$m}_manage"        => ["{$m}.list.view"],
            "{$m}_create"        => ["{$m}.create"],
            "{$m}_edit"          => ["{$m}.edit"],
            "{$m}_destroy"       => ["{$m}.destroy"],
            "{$m}_active"        => ["{$m}.activate"],
            "{$m}_inactive"      => ["{$m}.deactivate"],
            "{$m}_sort"          => ["{$m}.sort"],
            "view_inactive_{$m}" => ["{$m}.list.view_inactive"],
        ];

        return array_merge($standard('lead_sources'), $standard('lead_statuses'));
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
