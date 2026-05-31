<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `vouchers_*` umbrella from the role editor now that
 * the dotted `vouchers.*` catalog is in place and every gate
 * (UserVouchersController, UserVoucherService, 2 Admin FormRequests,
 * AssignVoucherRequest fallback, PatientService voucher row perms,
 * 4 Blade files, web route middleware, SPA route + sidebar) is pointed
 * at the new names by 2026_06_03_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * Note: `vouchers_manage` was also mapped from the Patients audit to
 * `patients.vouchers.view` / `patients.vouchers.view_history` /
 * `patients.voucher.assign` — those grants are independent of this
 * cleanup and remain intact.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'vouchers_manage',
            'vouchers_view',
            'vouchers_create',
            'vouchers_edit',
            'vouchers_destroy',
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
