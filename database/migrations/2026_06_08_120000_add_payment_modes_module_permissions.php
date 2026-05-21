<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Payment Modes module — fine-grained permission catalog
 * (`payment_modes.<action>`).
 *
 * Adds 8 new permissions plus a Finance-section group head for the SPA
 * `/payment-modes` page (cash / card / wire / etc. payment-mode catalog
 * used by invoices, plans, and the cash-flow ledger).
 *
 * Catalog (group head `payment_modes` + 8 children):
 *   - payment_modes.list.view           datatable + index page
 *   - payment_modes.list.view_inactive  filter inactive modes in the list
 *   - payment_modes.create
 *   - payment_modes.edit
 *   - payment_modes.destroy
 *   - payment_modes.activate
 *   - payment_modes.deactivate
 *   - payment_modes.sort                drag-and-drop reorder
 *
 * Mirror grants (no role loses access on day 1):
 *   payment_modes_manage          → payment_modes.list.view
 *   payment_modes_create          → payment_modes.create
 *   payment_modes_edit            → payment_modes.edit
 *   payment_modes_destroy         → payment_modes.destroy
 *   payment_modes_active          → payment_modes.activate
 *   payment_modes_inactive        → payment_modes.deactivate
 *   payment_modes_sort            → payment_modes.sort
 *   view_inactive_payment_modes   → payment_modes.list.view_inactive
 *
 * Companion migration `2026_06_08_130000_hide_legacy_payment_modes_group`
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
            ['name' => 'payment_modes.list.view',           'title' => 'View list'],
            ['name' => 'payment_modes.list.view_inactive',  'title' => 'See inactive payment modes'],
            ['name' => 'payment_modes.create',              'title' => 'Create'],
            ['name' => 'payment_modes.edit',                'title' => 'Edit'],
            ['name' => 'payment_modes.destroy',             'title' => 'Delete'],
            ['name' => 'payment_modes.activate',            'title' => 'Activate'],
            ['name' => 'payment_modes.deactivate',          'title' => 'Deactivate'],
            ['name' => 'payment_modes.sort',                'title' => 'Reorder'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'payment_modes_manage'        => ['payment_modes.list.view'],
            'payment_modes_create'        => ['payment_modes.create'],
            'payment_modes_edit'          => ['payment_modes.edit'],
            'payment_modes_destroy'       => ['payment_modes.destroy'],
            'payment_modes_active'        => ['payment_modes.activate'],
            'payment_modes_inactive'      => ['payment_modes.deactivate'],
            'payment_modes_sort'          => ['payment_modes.sort'],
            'view_inactive_payment_modes' => ['payment_modes.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'payment_modes'],
                [
                    'title' => 'Payment Modes',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Finance',
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

            Permission::where('name', 'payment_modes')
                ->where('main_group', 1)
                ->where('category', 'Finance')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
