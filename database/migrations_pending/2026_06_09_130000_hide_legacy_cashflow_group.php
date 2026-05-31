<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `cashflow_*` underscore permissions from the role
 * editor now that the dotted `cashflow.*` catalog is in place and every
 * gate (16 controllers, CashFlowPolicy, 16 FormRequests, 5 services,
 * Middleware/CheckPermission, SPA routes + sidebar) is pointed at the
 * new names by 2026_06_09_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * Includes the `cashflow_staff_advance` bare token (older umbrella for
 * the staff-advance scope, used in some OR clauses) — it was mirrored
 * into `cashflow.staff_advance.view` so legacy grants still resolve.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'cashflow_manage',
            'cashflow_dashboard',
            'cashflow_fdm_view',
            'cashflow_audit_view',

            'cashflow_expense_view',
            'cashflow_expense_create',
            'cashflow_expense_edit',
            'cashflow_expense_approve',
            'cashflow_expense_reject',
            'cashflow_expense_void',
            'cashflow_expense_duplicate',
            'cashflow_expense_resubmit',
            'cashflow_expense_unflag',
            'cashflow_expense_export',

            'cashflow_transfer_view',
            'cashflow_transfer_create',
            'cashflow_transfer_edit',
            'cashflow_transfer_void',

            'cashflow_vendor_view',
            'cashflow_vendor_create',
            'cashflow_vendor_edit',
            'cashflow_vendor_toggle',
            'cashflow_vendor_deliver',
            'cashflow_vendor_manage',
            'cashflow_vendor_request',
            'cashflow_vendor_ledger_view',
            'cashflow_vendor_ledger_export',
            'cashflow_vendor_transaction',
            'cashflow_vendor_transaction_edit',
            'cashflow_vendor_transaction_delete',

            'cashflow_staff_advance',
            'cashflow_staff_advance_view',
            'cashflow_staff_advance_create',
            'cashflow_staff_advance_edit',
            'cashflow_staff_advance_void',
            'cashflow_staff_return_create',
            'cashflow_staff_return_void',
            'cashflow_staff_transfer_create',
            'cashflow_staff_transfer_void',

            'cashflow_reports',
            'cashflow_reports_export',

            'cashflow_settings',
            'cashflow_pool_manage',
            'cashflow_category_manage',
            'cashflow_period_lock',
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
