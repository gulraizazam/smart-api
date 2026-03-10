<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $parent = Permission::where('name', 'cashflow_manage')->first();
        if (!$parent) {
            return;
        }

        $guardName = 'web';
        $maxSort = Permission::where('parent_id', $parent->id)->max('sort_order') ?? 0;

        $newPerms = [
            // Expenses – granular
            ['name' => 'cashflow_expense_reject', 'title' => 'Reject Expenses'],
            ['name' => 'cashflow_expense_resubmit', 'title' => 'Resubmit Rejected Expenses'],
            ['name' => 'cashflow_expense_unflag', 'title' => 'Remove Flag from Expenses'],
            ['name' => 'cashflow_expense_duplicate', 'title' => 'Duplicate Voided Expenses'],
            ['name' => 'cashflow_expense_export', 'title' => 'Export Expenses'],

            // Transfers – granular
            ['name' => 'cashflow_transfer_edit', 'title' => 'Edit Transfers'],
            ['name' => 'cashflow_transfer_void', 'title' => 'Void Transfers'],

            // Vendors – granular
            ['name' => 'cashflow_vendor_create', 'title' => 'Create Vendors'],
            ['name' => 'cashflow_vendor_edit', 'title' => 'Edit Vendors'],
            ['name' => 'cashflow_vendor_toggle', 'title' => 'Activate/Deactivate Vendors'],
            ['name' => 'cashflow_vendor_transaction_edit', 'title' => 'Edit Vendor Purchases'],
            ['name' => 'cashflow_vendor_transaction_delete', 'title' => 'Delete Vendor Purchases'],
            ['name' => 'cashflow_vendor_ledger_export', 'title' => 'Export Vendor Ledger'],

            // Staff Advances – granular
            ['name' => 'cashflow_staff_advance_create', 'title' => 'Create Staff Advances'],
            ['name' => 'cashflow_staff_advance_edit', 'title' => 'Edit Staff Advances'],
            ['name' => 'cashflow_staff_advance_void', 'title' => 'Void Staff Advances'],
            ['name' => 'cashflow_staff_return_create', 'title' => 'Record Staff Returns'],
            ['name' => 'cashflow_staff_return_void', 'title' => 'Void Staff Returns'],
        ];

        foreach ($newPerms as $i => $perm) {
            if (!Permission::where('name', $perm['name'])->exists()) {
                Permission::create([
                    'name' => $perm['name'],
                    'title' => $perm['title'],
                    'main_group' => 0,
                    'parent_id' => $parent->id,
                    'status' => 1,
                    'guard_name' => $guardName,
                    'sort_order' => $maxSort + $i + 1,
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'cashflow_expense_reject',
            'cashflow_expense_resubmit',
            'cashflow_expense_unflag',
            'cashflow_expense_duplicate',
            'cashflow_expense_export',
            'cashflow_transfer_edit',
            'cashflow_transfer_void',
            'cashflow_vendor_create',
            'cashflow_vendor_edit',
            'cashflow_vendor_toggle',
            'cashflow_vendor_transaction_edit',
            'cashflow_vendor_transaction_delete',
            'cashflow_vendor_ledger_export',
            'cashflow_staff_advance_create',
            'cashflow_staff_advance_edit',
            'cashflow_staff_advance_void',
            'cashflow_staff_return_create',
            'cashflow_staff_return_void',
        ])->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
