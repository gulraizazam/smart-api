<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `payment_modes_*` umbrella + `view_inactive_payment_modes`
 * from the role editor now that the dotted `payment_modes.*` catalog is
 * in place and every gate (Admin/PaymentModesController, PaymentModeService,
 * RoleService exclude list, 4 Blade files, SPA route + sidebar) is
 * pointed at the new names by 2026_06_08_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'payment_modes_manage',
            'payment_modes_create',
            'payment_modes_edit',
            'payment_modes_destroy',
            'payment_modes_active',
            'payment_modes_inactive',
            'payment_modes_sort',
            'view_inactive_payment_modes',
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
