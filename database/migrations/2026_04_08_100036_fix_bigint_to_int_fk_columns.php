<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix bigint(20) unsigned → int(10) unsigned on FK columns whose parent PK is int(10).
     * Required before FK constraints can be added (InnoDB requires exact type match).
     */
    public function up(): void
    {
        // Group by table for single ALTER per table (avoids multiple table rebuilds)
        $alterations = [
            // account_id → accounts.id (int unsigned)
            'cashflow_audit_logs' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'cashflow_notifications' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'cashflow_settings' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'cashflow_vendors' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'cash_pools' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'cash_transfers' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'category_requests' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'expense_categories' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'order_refunds' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL, MODIFY location_id INT(10) UNSIGNED NOT NULL, MODIFY patient_id INT(10) UNSIGNED NOT NULL',
            'order_refund_details' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'period_locks' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'staff_advances' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'staff_returns' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'vendor_requests' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'vendor_transactions' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL, MODIFY for_branch_id INT(10) UNSIGNED DEFAULT NULL',
            'vouchers' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',
            'working_day_exceptions' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',

            // expenses: account_id → accounts.id (int)
            'expenses' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL',

            // resource_time_offs: all 3 parents are int
            'resource_time_offs' => 'MODIFY account_id INT(10) UNSIGNED NOT NULL, MODIFY resource_id INT(10) UNSIGNED NOT NULL, MODIFY location_id INT(10) UNSIGNED NOT NULL',

            // activities: 6 columns all reference int parents
            'activities' => 'MODIFY appointment_id INT(10) UNSIGNED DEFAULT NULL, MODIFY centre_id INT(10) UNSIGNED DEFAULT NULL, MODIFY patient_id INT(10) UNSIGNED DEFAULT NULL, MODIFY plan_id INT(10) UNSIGNED DEFAULT NULL, MODIFY service_id INT(10) UNSIGNED DEFAULT NULL, MODIFY user_id INT(10) UNSIGNED DEFAULT NULL',

            // feedback: 4 columns reference int parents
            'feedback' => 'MODIFY patient_id INT(10) UNSIGNED DEFAULT NULL, MODIFY doctor_id INT(10) UNSIGNED DEFAULT NULL, MODIFY service_id INT(10) UNSIGNED DEFAULT NULL, MODIFY location_id INT(10) UNSIGNED DEFAULT NULL',

            // order_details: discount_id → discounts.id (int)
            'order_details' => 'MODIFY discount_id INT(10) UNSIGNED DEFAULT NULL',

            // services: parent_id self-ref (int)
            'services' => 'MODIFY parent_id INT(10) UNSIGNED DEFAULT NULL',

            // user_vouchers: user_id → users.id (int)
            'user_vouchers' => 'MODIFY user_id INT(10) UNSIGNED DEFAULT NULL',

            // voucher_has_locations: location_id, service_id → int parents
            'voucher_has_locations' => 'MODIFY location_id INT(10) UNSIGNED NOT NULL, MODIFY service_id INT(10) UNSIGNED NOT NULL',

            // warehouses: city_id, region_id → int parents
            'warehouses' => 'MODIFY city_id INT(10) UNSIGNED NOT NULL, MODIFY region_id INT(10) UNSIGNED NOT NULL',
        ];

        foreach ($alterations as $table => $sql) {
            DB::statement("ALTER TABLE `$table` $sql");
        }
    }

    public function down(): void
    {
        // Reverse is safe — int values fit in bigint
        $reversals = [
            'cashflow_audit_logs' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'cashflow_notifications' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'cashflow_settings' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'cashflow_vendors' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'cash_pools' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'cash_transfers' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'category_requests' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'expense_categories' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'order_refunds' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL, MODIFY location_id BIGINT(20) UNSIGNED NOT NULL, MODIFY patient_id BIGINT(20) UNSIGNED NOT NULL',
            'order_refund_details' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'period_locks' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'staff_advances' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'staff_returns' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'vendor_requests' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'vendor_transactions' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL, MODIFY for_branch_id BIGINT(20) UNSIGNED DEFAULT NULL',
            'vouchers' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'working_day_exceptions' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'expenses' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL',
            'resource_time_offs' => 'MODIFY account_id BIGINT(20) UNSIGNED NOT NULL, MODIFY resource_id BIGINT(20) UNSIGNED NOT NULL, MODIFY location_id BIGINT(20) UNSIGNED NOT NULL',
            'activities' => 'MODIFY appointment_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY centre_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY patient_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY plan_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY service_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY user_id BIGINT(20) UNSIGNED DEFAULT NULL',
            'feedback' => 'MODIFY patient_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY doctor_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY service_id BIGINT(20) UNSIGNED DEFAULT NULL, MODIFY location_id BIGINT(20) UNSIGNED DEFAULT NULL',
            'order_details' => 'MODIFY discount_id BIGINT(20) UNSIGNED DEFAULT NULL',
            'services' => 'MODIFY parent_id BIGINT(20) UNSIGNED DEFAULT NULL',
            'user_vouchers' => 'MODIFY user_id BIGINT(20) UNSIGNED DEFAULT NULL',
            'voucher_has_locations' => 'MODIFY location_id BIGINT(20) UNSIGNED NOT NULL, MODIFY service_id BIGINT(20) UNSIGNED NOT NULL',
            'warehouses' => 'MODIFY city_id BIGINT(20) UNSIGNED NOT NULL, MODIFY region_id BIGINT(20) UNSIGNED NOT NULL',
        ];

        foreach ($reversals as $table => $sql) {
            DB::statement("ALTER TABLE `$table` $sql");
        }
    }
};
