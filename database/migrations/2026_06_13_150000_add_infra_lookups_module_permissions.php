<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Infra lookups — `appointment_statuses.*` + `machine_types.*`
 * + `resource_types.*` + `resources.*`.
 *
 * Four Settings-section lookup tables that gate appointment / calendar
 * / resource configuration. Same shape as the geography and lead-lookup
 * catalogs (CRUD + activate/deactivate + list.view_inactive where the
 * legacy had an orphan view_inactive_* row).
 *
 * Naming normalisation: legacy `machineType_*` is camelCase, an
 * anomaly across the rest of the catalog. The dotted version uses
 * `machine_types.*` (snake_case + plural) to match the convention of
 * the surrounding modules. Mirror keeps the camelCase legacy names
 * grants intact.
 *
 * Catalog (4 group heads + 27 children):
 *   appointment_statuses (7) — incl. .list.view_inactive
 *   machine_types        (7) — incl. .list.view_inactive
 *   resource_types       (6) — no view_inactive orphan in DB
 *   resources            (7) — incl. .list.view_inactive
 *
 * Mirror is 1-to-1 for each module's underscore action perms plus the
 * orphan `view_inactive_<module>` rows.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Api\AppointmentStatusesController`, `Api\MachineTypesController`,
 * `Api\ResourcesController` (+ their Admin counterparts), `ResourceService`,
 * the 8 admin FormRequests, the Models' active-scopes, and the
 * Membership services that read inactive-centre / -resource visibility
 * all still call the underscore names. Companion hide-legacy migration
 * follows the controller rewrite.
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
     * Build the standard CRUD + activate/deactivate child list, with an
     * optional `.list.view_inactive` row when the legacy module had a
     * matching `view_inactive_<module>` orphan.
     *
     * @return list<array{name: string, title: string}>
     */
    private function standardChildren(string $module, bool $withViewInactive): array
    {
        $children = [
            ['name' => "{$module}.list.view",  'title' => 'View list'],
        ];

        if ($withViewInactive) {
            $children[] = ['name' => "{$module}.list.view_inactive", 'title' => 'See inactive rows'];
        }

        return array_merge($children, [
            ['name' => "{$module}.create",     'title' => 'Create'],
            ['name' => "{$module}.edit",       'title' => 'Edit'],
            ['name' => "{$module}.destroy",    'title' => 'Delete'],
            ['name' => "{$module}.activate",   'title' => 'Activate'],
            ['name' => "{$module}.deactivate", 'title' => 'Deactivate'],
        ]);
    }

    /**
     * @return array<string, array{title: string, children: list<array{name: string, title: string}>}>
     */
    private function modules(): array
    {
        return [
            'appointment_statuses' => [
                'title' => 'Appointment Statuses',
                'children' => $this->standardChildren('appointment_statuses', true),
            ],
            'machine_types' => [
                'title' => 'Machine Types',
                'children' => $this->standardChildren('machine_types', true),
            ],
            'resource_types' => [
                'title' => 'Resource Types',
                'children' => $this->standardChildren('resource_types', false),
            ],
            'resources' => [
                'title' => 'Resources',
                'children' => $this->standardChildren('resources', true),
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        /** @return array<string, list<string>> */
        $standard = static fn (string $legacyBase, string $dottedBase, bool $withViewInactive): array => array_merge(
            [
                "{$legacyBase}_manage"   => ["{$dottedBase}.list.view"],
                "{$legacyBase}_create"   => ["{$dottedBase}.create"],
                "{$legacyBase}_edit"     => ["{$dottedBase}.edit"],
                "{$legacyBase}_destroy"  => ["{$dottedBase}.destroy"],
                "{$legacyBase}_active"   => ["{$dottedBase}.activate"],
                "{$legacyBase}_inactive" => ["{$dottedBase}.deactivate"],
            ],
            $withViewInactive
                ? ["view_inactive_{$dottedBase}" => ["{$dottedBase}.list.view_inactive"]]
                : [],
        );

        return array_merge(
            $standard('appointment_statuses', 'appointment_statuses', true),
            // machineType uses camelCase legacy + plural dotted (machine_types)
            $standard('machineType', 'machine_types', false),
            ['view_inactive_machine_types' => ['machine_types.list.view_inactive']],
            $standard('resource_types', 'resource_types', false),
            $standard('resources', 'resources', true),
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
