<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vouchers module — fine-grained permission catalog (`vouchers.<action>`).
 *
 * Adds 5 new permissions plus a Catalog-section group head for the SPA
 * `/vouchers` page (the user-issued voucher datatable — distinct from
 * Voucher Types, which is the catalogue side audited separately).
 *
 * Note: the patient-card "Vouchers" tab has its own scoped catalog
 * (`patients.vouchers.view`, `patients.vouchers.view_history`,
 * `patients.voucher.assign`) — those came in via the Patients audit
 * (migration 2026_05_20_120000) and are not touched here.
 *
 * Catalog (group head `vouchers` + 5 children):
 *   - vouchers.list.view     datatable + index page
 *   - vouchers.detail.view   single voucher show / view-history modal
 *   - vouchers.create
 *   - vouchers.edit
 *   - vouchers.destroy
 *
 * Mirror grants (no role loses access on day 1):
 *   vouchers_manage   → vouchers.list.view
 *   vouchers_view     → vouchers.detail.view
 *   vouchers_create   → vouchers.create
 *   vouchers_edit     → vouchers.edit
 *   vouchers_destroy  → vouchers.destroy
 *
 * Companion migration `2026_06_03_130000_hide_legacy_vouchers_group`
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
            ['name' => 'vouchers.list.view',    'title' => 'View list'],
            ['name' => 'vouchers.detail.view',  'title' => 'View voucher detail / history'],
            ['name' => 'vouchers.create',       'title' => 'Issue voucher'],
            ['name' => 'vouchers.edit',         'title' => 'Edit voucher'],
            ['name' => 'vouchers.destroy',      'title' => 'Delete voucher'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'vouchers_manage'   => ['vouchers.list.view'],
            'vouchers_view'     => ['vouchers.detail.view'],
            'vouchers_create'   => ['vouchers.create'],
            'vouchers_edit'     => ['vouchers.edit'],
            'vouchers_destroy'  => ['vouchers.destroy'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'vouchers'],
                [
                    'title' => 'Vouchers',
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

            Permission::where('name', 'vouchers')
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
