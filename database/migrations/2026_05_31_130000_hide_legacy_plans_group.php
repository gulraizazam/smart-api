<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `plans_*` umbrella + its children from the role
 * editor now that the dotted `plans.*` catalog is in place and every
 * gate (PlanPolicy, Admin/PackagesController inline gates, Api/PlansController
 * permission maps, FormRequests, models, services, Blade templates,
 * SPA route) is pointed at the new names by 2026_05_31_120000.
 *
 * Strategy: status=0 the legacy rows (Spatie grants survive; role-editor
 * filter hides them). Reversible — `down()` flips status back to 1.
 *
 * `view_inactive_plans` is also flipped — successor is
 * `plans.list.view_inactive` (mirrored in Migration 1).
 *
 * Patient-card-specific perms (`patients_plan_*`) stay untouched — they
 * already have their own catalog under the Patients module.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'plans_manage',
            'plans_create',
            'plans_edit',
            'plans_active',
            'plans_inactive',
            'plans_destroy',
            'plans_cash_edit',
            'plans_cash_delete',
            'plans_cash_edit_payment_mode',
            'plans_cash_edit_amount',
            'plans_cash_edit_date',
            'plans_sms_log',
            'plans_edit_sold_by',
            'plans_service_delete',
            'plans_log',
            'plans_log_excel',
            'view_inactive_plans',
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
