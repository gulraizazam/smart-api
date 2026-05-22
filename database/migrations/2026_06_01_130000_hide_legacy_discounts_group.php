<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `discounts_*` umbrella + `view_inactive_discounts`
 * from the role editor now that the dotted `discounts.*` catalog is in
 * place and every gate (Admin/Api DiscountsController, FormRequests,
 * DiscountService, PlanPolicy, RoleService, Blade templates, web/api
 * routes, SPA route + sidebar) is pointed at the new names by
 * 2026_06_01_120000.
 *
 * Strategy: status=0 the legacy rows (Spatie grants survive — `status`
 * only affects the role-editor UI filter, not Gate resolution). This
 * keeps the legacy voucher routes alive (they still piggyback on
 * `permission:discounts_manage` middleware until the Voucher Types
 * audit replaces them) while removing the duplicate rows from the
 * permissions UI. Reversible — `down()` flips status back to 1.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'discounts_manage',
            'discounts_create',
            'discounts_edit',
            'discounts_active',
            'discounts_inactive',
            'discounts_destroy',
            'discounts_allocate',
            'view_inactive_discounts',
        ];
    }

    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')
                ->whereIn('name', $this->legacyNames())
                ->update(['status' => 0]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')
                ->whereIn('name', $this->legacyNames())
                ->update(['status' => 1]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
