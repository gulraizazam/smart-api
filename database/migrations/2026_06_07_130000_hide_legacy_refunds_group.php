<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `refunds_*` umbrella from the role editor now that
 * the dotted `refunds.*` catalog is in place and every gate (Admin + Api
 * RefundsController, RefundService.globalPermissions, 2 FormRequests,
 * InvoicePolicy + PatientPolicy refund methods, 4 Blade files, SPA route +
 * sidebar) is pointed at the new names by 2026_06_07_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * NOT touched: `patients_refund_manage`, `patients_refund_refund`,
 * `patient_refund` — patient-card scope owned by the Patients module.
 *
 * Historically only `refunds_manage` and `refunds_refund` were actually
 * seeded; the `_create/_edit/_destroy/_active/_inactive` rows are
 * referenced in code but never created. The whereIn covers them in case
 * any DB created them by hand.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'refunds_manage',
            'refunds_refund',
            'refunds_create',
            'refunds_edit',
            'refunds_destroy',
            'refunds_active',
            'refunds_inactive',
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
