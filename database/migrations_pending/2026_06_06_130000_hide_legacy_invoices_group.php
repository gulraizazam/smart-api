<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `invoices_*` umbrella from the role editor now that
 * the dotted `invoices.*` catalog is in place and every gate
 * (Admin + Api InvoicesController, InvoicePolicy, 4 Blade files, web
 * route middleware, SPA route + sidebar) is pointed at the new names
 * by 2026_06_06_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * NOT touched: `appointments_invoice`, `appointments_invoice_display`,
 * `patient_invoices`, `consultancy_invoice` — those belong to the
 * Consultations / Patients modules and have their own audits.
 *
 * Historically only `invoices_manage` was actually seeded; the
 * `_create/_destroy/_export` rows are referenced in code but never
 * created. The whereIn covers them in case any DB created them by hand.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'invoices_manage',
            'invoices_create',
            'invoices_destroy',
            'invoices_export',
            'invoices_cancel',
            'invoices_log',
            'invoices_sms_log',
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
