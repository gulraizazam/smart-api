<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Make the Warehouse + User-Type permission switches actually work (user
 * decision, 2026-06-20). The WarehouseController and UserTypeController gate on
 * these slugs, but the catalog rows never existed, so every switch was an
 * always-deny no-op (only Super-Admin passed). This creates the missing rows so
 * the switches can be granted to a role in the editor.
 *
 *  - Warehouse: the whole module is missing → create the parent group
 *    (warehouse_manage) + its CRUD children (create/edit/destroy/active).
 *  - User-Types: parent group (user_types_manage) + edit already exist → add the
 *    4 missing children (create/destroy/active/inactive) under it.
 *
 * Additive + reversible: down() removes any role grants then the rows, with
 * ->delete() (NOT truncate — truncate breaks migrate:rollback).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── Warehouse: parent group + children ──
        DB::table('permissions')->updateOrInsert(
            ['name' => 'warehouse_manage', 'guard_name' => 'web'],
            ['title' => 'Warehouse', 'main_group' => 1, 'status' => 1, 'category' => 'Inventory', 'parent_id' => 0, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
        );
        $warehouseId = (int) DB::table('permissions')->where('name', 'warehouse_manage')->where('guard_name', 'web')->value('id');

        foreach (['warehouse_create' => 'Create', 'warehouse_edit' => 'Edit', 'warehouse_destroy' => 'Delete', 'warehouse_active' => 'Activate'] as $name => $title) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['title' => $title, 'main_group' => 0, 'status' => 1, 'category' => 'Inventory', 'parent_id' => $warehouseId, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        // ── User-Types: add the 4 missing children under the existing parent ──
        $userTypeId = (int) DB::table('permissions')->where('name', 'user_types_manage')->where('guard_name', 'web')->value('id');
        if ($userTypeId > 0) {
            foreach (['user_types_create' => 'Create', 'user_types_destroy' => 'Delete', 'user_types_active' => 'Activate', 'user_types_inactive' => 'Inactivate'] as $name => $title) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['title' => $title, 'main_group' => 0, 'status' => 1, 'category' => 'System', 'parent_id' => $userTypeId, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = [
            'warehouse_create', 'warehouse_edit', 'warehouse_destroy', 'warehouse_active', 'warehouse_manage',
            'user_types_create', 'user_types_destroy', 'user_types_active', 'user_types_inactive',
        ];

        $ids = DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
