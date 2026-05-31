<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `voucher_types_*` umbrella from the role editor now
 * that the dotted `voucher_types.*` catalog is in place and every gate
 * (Admin\VouchersController, VoucherTypeService, 6 FormRequests, sidebar
 * Blades, voucherTypes index route, SPA route + sidebar) is pointed at
 * the new names by 2026_06_02_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * Historically only `voucher_types_manage` was actually seeded (the
 * `_create/_edit/_destroy/_active/_inactive/_allocate/_assign` rows are
 * referenced in code but were never inserted). The `whereIn` still
 * covers them in case any environment created them by hand — flipping
 * non-existent rows is a no-op.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'voucher_types_manage',
            'voucher_types_create',
            'voucher_types_edit',
            'voucher_types_active',
            'voucher_types_inactive',
            'voucher_types_destroy',
            'voucher_types_allocate',
            'voucher_types_assign',
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
