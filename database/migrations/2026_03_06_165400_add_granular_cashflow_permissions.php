<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        // Find the cashflow parent
        $parent = Permission::where('name', 'cashflow_manage')->first();
        if (!$parent) {
            return;
        }

        $maxSort = Permission::where('parent_id', $parent->id)->max('sort_order') ?? 0;

        $newPermissions = [
            ['name' => 'cashflow_transfer_edit', 'title' => 'Edit Cash Transfers'],
            ['name' => 'cashflow_transfer_void', 'title' => 'Void Cash Transfers'],
            ['name' => 'cashflow_staff_advance_edit', 'title' => 'Edit Staff Advances'],
            ['name' => 'cashflow_staff_advance_void', 'title' => 'Void Staff Advances/Returns'],
        ];

        foreach ($newPermissions as $index => $perm) {
            if (!Permission::where('name', $perm['name'])->exists()) {
                Permission::create([
                    'name' => $perm['name'],
                    'title' => $perm['title'],
                    'main_group' => 0,
                    'parent_id' => $parent->id,
                    'status' => 1,
                    'guard_name' => $guardName,
                    'sort_order' => $maxSort + $index + 1,
                ]);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'cashflow_transfer_edit',
            'cashflow_transfer_void',
            'cashflow_staff_advance_edit',
            'cashflow_staff_advance_void',
        ])->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
