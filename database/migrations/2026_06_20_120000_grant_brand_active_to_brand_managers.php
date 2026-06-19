<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Restore brand-status-toggle access after the BrandsController::status gate was
 * corrected from the WRONG slug `product_active` to `brand_active` (2026-06-20 QA #20).
 *
 * `brand_active` had ZERO role grants — nobody ever needed it, because the toggle
 * wrongly checked `product_active`. So the slug fix on its own would lock the
 * brand-managing roles (Administrator, HRM, Stocks) out of a feature they had.
 * This grants `brand_active` to every role that manages brands (holds
 * `brand_manage`), restoring their prior access through the correct, brand-scoped
 * permission. Super-Admin bypasses via Gate::before, so granting it there is a
 * harmless no-op.
 *
 * Additive + reversible. `brand_active` had 0 grants before this migration, so
 * down() removes exactly the grants up() added (those mirroring brand_manage).
 */
return new class extends Migration
{
    public function up(): void
    {
        $brandActive = (int) DB::table('permissions')->where('name', 'brand_active')->where('guard_name', 'web')->value('id');
        $brandManage = (int) DB::table('permissions')->where('name', 'brand_manage')->where('guard_name', 'web')->value('id');

        if ($brandActive === 0 || $brandManage === 0) {
            return; // catalog not in the expected shape — no-op
        }

        $managerRoleIds = DB::table('role_has_permissions')->where('permission_id', $brandManage)->pluck('role_id');

        foreach ($managerRoleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $brandActive, 'role_id' => $roleId],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $brandActive = (int) DB::table('permissions')->where('name', 'brand_active')->where('guard_name', 'web')->value('id');
        $brandManage = (int) DB::table('permissions')->where('name', 'brand_manage')->where('guard_name', 'web')->value('id');

        if ($brandActive === 0 || $brandManage === 0) {
            return;
        }

        // brand_active had 0 grants before this migration; remove the grants we
        // added (those mirroring brand_manage-holders).
        $managerRoleIds = DB::table('role_has_permissions')->where('permission_id', $brandManage)->pluck('role_id');

        DB::table('role_has_permissions')
            ->where('permission_id', $brandActive)
            ->whereIn('role_id', $managerRoleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
