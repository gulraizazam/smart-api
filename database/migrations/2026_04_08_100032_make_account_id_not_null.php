<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make account_id NOT NULL on all 28 tables where it's currently nullable.
     * Single-tenant system (account_id=1) — backfill NULLs before constraining.
     */
    public function up(): void
    {
        $tables = [
            'activities',           // 215K NULL rows — backfill
            'appointments',         // 14 NULL rows — backfill
            'appointment_statuses',
            'appointment_types',
            'bundles',
            'bundle_services_price_history',
            'cancellation_reasons',
            'cities',
            'discounts',
            'heavy_lifters',
            'invoice_statuses',
            'leads',
            'lead_comments',        // 273K NULL rows — backfill
            'lead_sources',
            'lead_statuses',
            'locations',
            'packages',
            'package_advances',
            'payment_modes',
            'regions',
            'resources',
            'resource_has_rota',
            'settings',
            'sms_templates',
            'towns',
            'users',                // 20 NULL rows — backfill
            'user_types',
        ];

        // Step 1: Backfill all NULL account_id to 1
        foreach ($tables as $table) {
            DB::table($table)->whereNull('account_id')->update(['account_id' => 1]);
        }

        // Step 2: ALTER to NOT NULL (all are int(10) unsigned)
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `$table` MODIFY account_id INT(10) UNSIGNED NOT NULL");
        }

        // vouchers has bigint(20) unsigned
        DB::table('vouchers')->whereNull('account_id')->update(['account_id' => 1]);
        DB::statement('ALTER TABLE vouchers MODIFY account_id BIGINT(20) UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        $tables = [
            'activities', 'appointments', 'appointment_statuses', 'appointment_types',
            'bundles', 'bundle_services_price_history', 'cancellation_reasons', 'cities',
            'discounts', 'heavy_lifters', 'invoice_statuses', 'leads', 'lead_comments',
            'lead_sources', 'lead_statuses', 'locations', 'packages', 'package_advances',
            'payment_modes', 'regions', 'resources', 'resource_has_rota', 'settings',
            'sms_templates', 'towns', 'users', 'user_types',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `$table` MODIFY account_id INT(10) UNSIGNED DEFAULT NULL");
        }
        DB::statement('ALTER TABLE vouchers MODIFY account_id BIGINT(20) UNSIGNED DEFAULT NULL');
    }
};
