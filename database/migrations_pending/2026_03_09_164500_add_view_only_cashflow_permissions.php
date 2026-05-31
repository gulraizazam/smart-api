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
            ['name' => 'cashflow_expense_view', 'title' => 'View Expenses'],
            ['name' => 'cashflow_transfer_view', 'title' => 'View Transfers'],
            ['name' => 'cashflow_vendor_view', 'title' => 'View Vendors'],
            ['name' => 'cashflow_staff_advance_view', 'title' => 'View Staff Advances'],
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
            'cashflow_expense_view',
            'cashflow_transfer_view',
            'cashflow_vendor_view',
            'cashflow_staff_advance_view',
        ])->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
