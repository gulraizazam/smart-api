<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cash Flow module — fine-grained permission catalog (`cashflow.<area>.<action>`).
 *
 * The Cash Flow module already had a granular `cashflow_<scope>_<action>`
 * permission set seeded across three prior migrations
 * (2026_03_06_165400, 2026_03_09_164500, 2026_03_10_103400). This audit
 * converts that flat snake_case shape into the standard dotted catalog
 * so the role editor + SPA gating stay consistent across modules.
 *
 * Catalog under category `Finance` (group head `cashflow` + 45 children):
 *   Dashboard / shell
 *     - cashflow.dashboard.view
 *     - cashflow.fdm.view             FDM-only landing
 *     - cashflow.audit.view           audit trail / movement logs
 *   Expense
 *     - cashflow.expense.view / .create / .edit / .approve / .reject
 *     - cashflow.expense.void / .duplicate / .resubmit / .unflag / .export
 *   Transfer (cash transfer between pools / locations)
 *     - cashflow.transfer.view / .create / .edit / .void
 *   Vendor
 *     - cashflow.vendor.view / .create / .edit / .toggle
 *     - cashflow.vendor.deliver / .manage / .request
 *     - cashflow.vendor.ledger.view / .ledger.export
 *     - cashflow.vendor.transaction.create / .edit / .delete
 *   Staff advance / return / transfer
 *     - cashflow.staff_advance.view / .create / .edit / .void
 *     - cashflow.staff_return.create / .void
 *     - cashflow.staff_transfer.create / .void
 *   Reports
 *     - cashflow.reports.view / .reports.export
 *   Settings / catalog
 *     - cashflow.settings.manage
 *     - cashflow.pool.manage
 *     - cashflow.category.manage
 *     - cashflow.period.lock
 *
 * Companion migration `2026_06_09_130000_hide_legacy_cashflow_group`
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
            ['name' => 'cashflow.manage',                  'title' => 'Manage everything (umbrella)'],
            ['name' => 'cashflow.dashboard.view',          'title' => 'View dashboard'],
            ['name' => 'cashflow.fdm.view',                'title' => 'View FDM cash flow'],
            ['name' => 'cashflow.audit.view',              'title' => 'View audit trail'],

            ['name' => 'cashflow.expense.view',            'title' => 'Expense · View'],
            ['name' => 'cashflow.expense.create',          'title' => 'Expense · Create'],
            ['name' => 'cashflow.expense.edit',            'title' => 'Expense · Edit'],
            ['name' => 'cashflow.expense.approve',         'title' => 'Expense · Approve'],
            ['name' => 'cashflow.expense.reject',          'title' => 'Expense · Reject'],
            ['name' => 'cashflow.expense.void',            'title' => 'Expense · Void'],
            ['name' => 'cashflow.expense.duplicate',       'title' => 'Expense · Duplicate voided'],
            ['name' => 'cashflow.expense.resubmit',        'title' => 'Expense · Resubmit rejected'],
            ['name' => 'cashflow.expense.unflag',          'title' => 'Expense · Remove flag'],
            ['name' => 'cashflow.expense.export',          'title' => 'Expense · Export'],

            ['name' => 'cashflow.transfer.view',           'title' => 'Transfer · View'],
            ['name' => 'cashflow.transfer.create',         'title' => 'Transfer · Create'],
            ['name' => 'cashflow.transfer.edit',           'title' => 'Transfer · Edit'],
            ['name' => 'cashflow.transfer.void',           'title' => 'Transfer · Void'],

            ['name' => 'cashflow.vendor.view',             'title' => 'Vendor · View'],
            ['name' => 'cashflow.vendor.create',           'title' => 'Vendor · Create'],
            ['name' => 'cashflow.vendor.edit',             'title' => 'Vendor · Edit'],
            ['name' => 'cashflow.vendor.toggle',           'title' => 'Vendor · Activate/Deactivate'],
            ['name' => 'cashflow.vendor.deliver',          'title' => 'Vendor · Deliver request'],
            ['name' => 'cashflow.vendor.manage',           'title' => 'Vendor · Manage catalog'],
            ['name' => 'cashflow.vendor.request',          'title' => 'Vendor · Create request'],
            ['name' => 'cashflow.vendor.ledger.view',      'title' => 'Vendor · View ledger'],
            ['name' => 'cashflow.vendor.ledger.export',    'title' => 'Vendor · Export ledger'],
            ['name' => 'cashflow.vendor.transaction.create', 'title' => 'Vendor · Add transaction'],
            ['name' => 'cashflow.vendor.transaction.edit',   'title' => 'Vendor · Edit transaction'],
            ['name' => 'cashflow.vendor.transaction.delete', 'title' => 'Vendor · Delete transaction'],

            ['name' => 'cashflow.staff_advance.view',      'title' => 'Staff advance · View'],
            ['name' => 'cashflow.staff_advance.create',    'title' => 'Staff advance · Create'],
            ['name' => 'cashflow.staff_advance.edit',      'title' => 'Staff advance · Edit'],
            ['name' => 'cashflow.staff_advance.void',      'title' => 'Staff advance · Void'],
            ['name' => 'cashflow.staff_return.create',     'title' => 'Staff return · Create'],
            ['name' => 'cashflow.staff_return.void',       'title' => 'Staff return · Void'],
            ['name' => 'cashflow.staff_transfer.create',   'title' => 'Staff transfer · Create'],
            ['name' => 'cashflow.staff_transfer.void',     'title' => 'Staff transfer · Void'],

            ['name' => 'cashflow.reports.view',            'title' => 'Reports · View'],
            ['name' => 'cashflow.reports.export',          'title' => 'Reports · Export'],

            ['name' => 'cashflow.settings.manage',         'title' => 'Settings · Manage'],
            ['name' => 'cashflow.pool.manage',             'title' => 'Pools · Manage'],
            ['name' => 'cashflow.category.manage',         'title' => 'Categories · Manage'],
            ['name' => 'cashflow.period.lock',             'title' => 'Period · Lock / unlock'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'cashflow_manage'                  => ['cashflow.manage'],
            'cashflow_dashboard'               => ['cashflow.dashboard.view'],
            'cashflow_fdm_view'                => ['cashflow.fdm.view'],
            'cashflow_audit_view'              => ['cashflow.audit.view'],

            'cashflow_expense_view'            => ['cashflow.expense.view'],
            'cashflow_expense_create'          => ['cashflow.expense.create'],
            'cashflow_expense_edit'            => ['cashflow.expense.edit'],
            'cashflow_expense_approve'         => ['cashflow.expense.approve'],
            'cashflow_expense_reject'          => ['cashflow.expense.reject'],
            'cashflow_expense_void'            => ['cashflow.expense.void'],
            'cashflow_expense_duplicate'       => ['cashflow.expense.duplicate'],
            'cashflow_expense_resubmit'        => ['cashflow.expense.resubmit'],
            'cashflow_expense_unflag'          => ['cashflow.expense.unflag'],
            'cashflow_expense_export'          => ['cashflow.expense.export'],

            'cashflow_transfer_view'           => ['cashflow.transfer.view'],
            'cashflow_transfer_create'         => ['cashflow.transfer.create'],
            'cashflow_transfer_edit'           => ['cashflow.transfer.edit'],
            'cashflow_transfer_void'           => ['cashflow.transfer.void'],

            'cashflow_vendor_view'             => ['cashflow.vendor.view'],
            'cashflow_vendor_create'           => ['cashflow.vendor.create'],
            'cashflow_vendor_edit'             => ['cashflow.vendor.edit'],
            'cashflow_vendor_toggle'           => ['cashflow.vendor.toggle'],
            'cashflow_vendor_deliver'          => ['cashflow.vendor.deliver'],
            'cashflow_vendor_manage'           => ['cashflow.vendor.manage'],
            'cashflow_vendor_request'          => ['cashflow.vendor.request'],
            'cashflow_vendor_ledger_view'      => ['cashflow.vendor.ledger.view'],
            'cashflow_vendor_ledger_export'    => ['cashflow.vendor.ledger.export'],
            'cashflow_vendor_transaction'      => ['cashflow.vendor.transaction.create'],
            'cashflow_vendor_transaction_edit' => ['cashflow.vendor.transaction.edit'],
            'cashflow_vendor_transaction_delete' => ['cashflow.vendor.transaction.delete'],

            'cashflow_staff_advance_view'      => ['cashflow.staff_advance.view'],
            'cashflow_staff_advance_create'    => ['cashflow.staff_advance.create'],
            'cashflow_staff_advance_edit'      => ['cashflow.staff_advance.edit'],
            'cashflow_staff_advance_void'      => ['cashflow.staff_advance.void'],
            'cashflow_staff_return_create'     => ['cashflow.staff_return.create'],
            'cashflow_staff_return_void'       => ['cashflow.staff_return.void'],
            'cashflow_staff_transfer_create'   => ['cashflow.staff_transfer.create'],
            'cashflow_staff_transfer_void'     => ['cashflow.staff_transfer.void'],

            'cashflow_reports'                 => ['cashflow.reports.view'],
            'cashflow_reports_export'          => ['cashflow.reports.export'],

            'cashflow_settings'                => ['cashflow.settings.manage'],
            'cashflow_pool_manage'             => ['cashflow.pool.manage'],
            'cashflow_category_manage'         => ['cashflow.category.manage'],
            'cashflow_period_lock'             => ['cashflow.period.lock'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'cashflow'],
                [
                    'title' => 'Cash Flow',
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

            Permission::where('name', 'cashflow')
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
