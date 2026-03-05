<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashflowPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guardName = 'web';

        // Check if parent already exists to make seeder idempotent
        $existing = Permission::where('name', 'cashflow_manage')->first();
        if ($existing) {
            $this->command->info('Cashflow permissions already exist. Skipping.');
            return;
        }

        // Get the max sort_order from existing parent groups to place cashflow at the end
        $maxSort = Permission::where('main_group', 1)->max('sort_order') ?? 0;

        DB::transaction(function () use ($guardName, $maxSort) {
            // Create parent group permission
            $parent = Permission::create([
                'name' => 'cashflow_manage',
                'title' => 'Cash Flow Management',
                'main_group' => 1,
                'parent_id' => 0,
                'status' => 1,
                'guard_name' => $guardName,
                'sort_order' => $maxSort + 1,
            ]);

            // Child permissions under cashflow_manage
            $children = [
                ['name' => 'cashflow_dashboard', 'title' => 'View Cash Flow Dashboard'],
                ['name' => 'cashflow_fdm_view', 'title' => 'View FDM Screen (Branch Cash)'],
                ['name' => 'cashflow_expense_create', 'title' => 'Create Expenses'],
                ['name' => 'cashflow_expense_edit', 'title' => 'Edit Expenses (Admin)'],
                ['name' => 'cashflow_expense_approve', 'title' => 'Approve/Reject Expenses'],
                ['name' => 'cashflow_expense_void', 'title' => 'Void Expenses'],
                ['name' => 'cashflow_transfer_create', 'title' => 'Create Cash Transfers'],
                ['name' => 'cashflow_vendor_manage', 'title' => 'Manage Vendors'],
                ['name' => 'cashflow_vendor_ledger_view', 'title' => 'View Vendor Ledger'],
                ['name' => 'cashflow_vendor_transaction', 'title' => 'Record Vendor Transactions'],
                ['name' => 'cashflow_staff_advance', 'title' => 'Manage Staff Advances'],
                ['name' => 'cashflow_category_manage', 'title' => 'Manage Expense Categories'],
                ['name' => 'cashflow_pool_manage', 'title' => 'Manage Cash Pools'],
                ['name' => 'cashflow_period_lock', 'title' => 'Lock/Unlock Periods'],
                ['name' => 'cashflow_audit_view', 'title' => 'View Audit Trail'],
                ['name' => 'cashflow_settings', 'title' => 'Manage Cash Flow Settings'],
                ['name' => 'cashflow_reports', 'title' => 'View Cash Flow Reports'],
                ['name' => 'cashflow_reports_export', 'title' => 'Export Cash Flow Reports'],
            ];

            foreach ($children as $index => $child) {
                Permission::create([
                    'name' => $child['name'],
                    'title' => $child['title'],
                    'main_group' => 0,
                    'parent_id' => $parent->id,
                    'status' => 1,
                    'guard_name' => $guardName,
                    'sort_order' => $index + 1,
                ]);
            }

            // Clear Spatie permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        });

        $this->command->info('Cashflow permissions seeded successfully (1 parent + 18 children).');
    }
}
