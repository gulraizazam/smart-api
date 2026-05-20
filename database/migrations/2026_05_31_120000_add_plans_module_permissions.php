<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Plans module — fine-grained permission catalog (`plans.<action>`).
 *
 * Adds 19 new permissions plus a Catalog-section group head for the SPA
 * `/plans` page (global plans list — patient-card "Plans" tab keeps its
 * own `patients.plans.*` / `patients_plan_*` catalog, already audited).
 *
 * Why this module needed a heavy hand: most of the underlying
 * `Admin\PackagesController` methods (savepackages, updatepackages,
 * destroy, display, status, voucher reserve/refund, sold-by edit,
 * cascade delete, plan log, plan-log excel, package PDF) were
 * **completely ungated**. Any authenticated user could call them
 * directly bypassing the SPA-side `dt.permissions` UI hints. The
 * companion code rewrite (controllers, policy, SPA) closes those.
 *
 * Catalog (group head `plans` + 19 children):
 *   - plans.list.view             datatable + filters
 *   - plans.list.view_inactive    show inactive plans
 *   - plans.detail.view           display dialog
 *   - plans.create
 *   - plans.edit
 *   - plans.destroy
 *   - plans.activate
 *   - plans.deactivate
 *   - plans.transfer              transfer between patients
 *   - plans.cash.edit
 *   - plans.cash.edit_payment_mode
 *   - plans.cash.edit_amount
 *   - plans.cash.edit_date
 *   - plans.cash.delete
 *   - plans.service.delete
 *   - plans.sold_by.edit
 *   - plans.sms_log.view
 *   - plans.log.view
 *   - plans.log.export
 *   - plans.print                 print plan PDF
 *
 * Mirror grants (no role loses access on day 1):
 *   plans_manage                  → plans.list.view, plans.detail.view, plans.transfer
 *   plans_create                  → plans.create
 *   plans_edit                    → plans.edit
 *   plans_active                  → plans.activate
 *   plans_inactive                → plans.deactivate
 *   plans_destroy                 → plans.destroy
 *   plans_cash_edit               → plans.cash.edit
 *   plans_cash_delete             → plans.cash.delete
 *   plans_cash_edit_payment_mode  → plans.cash.edit_payment_mode
 *   plans_cash_edit_amount        → plans.cash.edit_amount
 *   plans_cash_edit_date          → plans.cash.edit_date
 *   plans_sms_log                 → plans.sms_log.view
 *   plans_edit_sold_by            → plans.sold_by.edit
 *   plans_service_delete          → plans.service.delete
 *   plans_log                     → plans.log.view, plans.print
 *   plans_log_excel               → plans.log.export
 *   view_inactive_plans           → plans.list.view_inactive
 *
 * Companion migration `2026_05_31_130000_hide_legacy_plans_group` flips
 * the legacy rows to status=0 once the rewrite lands.
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
            ['name' => 'plans.list.view',                'title' => 'View list'],
            ['name' => 'plans.list.view_inactive',      'title' => 'See inactive plans'],
            ['name' => 'plans.detail.view',              'title' => 'View plan detail'],
            ['name' => 'plans.create',                   'title' => 'Create'],
            ['name' => 'plans.edit',                     'title' => 'Edit'],
            ['name' => 'plans.destroy',                  'title' => 'Delete'],
            ['name' => 'plans.activate',                 'title' => 'Activate'],
            ['name' => 'plans.deactivate',               'title' => 'Deactivate'],
            ['name' => 'plans.transfer',                 'title' => 'Transfer between patients'],
            ['name' => 'plans.cash.edit',                'title' => 'Edit cash payment'],
            ['name' => 'plans.cash.edit_payment_mode',  'title' => 'Edit cash payment mode'],
            ['name' => 'plans.cash.edit_amount',         'title' => 'Edit cash amount'],
            ['name' => 'plans.cash.edit_date',           'title' => 'Edit cash date'],
            ['name' => 'plans.cash.delete',              'title' => 'Delete cash payment'],
            ['name' => 'plans.service.delete',           'title' => 'Delete service from plan'],
            ['name' => 'plans.sold_by.edit',             'title' => 'Edit Sold-By'],
            ['name' => 'plans.sms_log.view',             'title' => 'View SMS log'],
            ['name' => 'plans.log.view',                 'title' => 'View audit log'],
            ['name' => 'plans.log.export',               'title' => 'Export audit log to Excel'],
            ['name' => 'plans.print',                    'title' => 'Print plan PDF'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'plans_manage'                 => ['plans.list.view', 'plans.detail.view', 'plans.transfer'],
            'plans_create'                 => ['plans.create'],
            'plans_edit'                   => ['plans.edit'],
            'plans_active'                 => ['plans.activate'],
            'plans_inactive'               => ['plans.deactivate'],
            'plans_destroy'                => ['plans.destroy'],
            'plans_cash_edit'              => ['plans.cash.edit'],
            'plans_cash_delete'            => ['plans.cash.delete'],
            'plans_cash_edit_payment_mode' => ['plans.cash.edit_payment_mode'],
            'plans_cash_edit_amount'       => ['plans.cash.edit_amount'],
            'plans_cash_edit_date'         => ['plans.cash.edit_date'],
            'plans_sms_log'                => ['plans.sms_log.view'],
            'plans_edit_sold_by'           => ['plans.sold_by.edit'],
            'plans_service_delete'         => ['plans.service.delete'],
            'plans_log'                    => ['plans.log.view', 'plans.print'],
            'plans_log_excel'              => ['plans.log.export'],
            'view_inactive_plans'          => ['plans.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'plans'],
                [
                    'title' => 'Plans',
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

            Permission::where('name', 'plans')
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
