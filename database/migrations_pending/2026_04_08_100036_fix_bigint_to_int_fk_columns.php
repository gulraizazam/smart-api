<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeModify(string $table, string $column, string $sql): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$sql}");
        } catch (\Throwable $e) {
            \Log::warning("Skipping MODIFY {$table}.{$column}: " . $e->getMessage());
        }
    }

    /**
     * Fix bigint(20) unsigned -> int(10) unsigned on FK columns whose parent PK is int(10).
     */
    public function up(): void
    {
        $perColumn = [
            'cashflow_audit_logs' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'cashflow_notifications' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'cashflow_settings' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'cashflow_vendors' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'cash_pools' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'cash_transfers' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'category_requests' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'expense_categories' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'order_refunds' => [
                ['account_id', 'INT(10) UNSIGNED NOT NULL'],
                ['location_id', 'INT(10) UNSIGNED NOT NULL'],
                ['patient_id', 'INT(10) UNSIGNED NOT NULL'],
            ],
            'order_refund_details' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'period_locks' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'staff_advances' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'staff_returns' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'vendor_requests' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'vendor_transactions' => [
                ['account_id', 'INT(10) UNSIGNED NOT NULL'],
                ['for_branch_id', 'INT(10) UNSIGNED DEFAULT NULL'],
            ],
            'vouchers' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'working_day_exceptions' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'expenses' => [['account_id', 'INT(10) UNSIGNED NOT NULL']],
            'resource_time_offs' => [
                ['account_id', 'INT(10) UNSIGNED NOT NULL'],
                ['resource_id', 'INT(10) UNSIGNED NOT NULL'],
                ['location_id', 'INT(10) UNSIGNED NOT NULL'],
            ],
            'activities' => [
                ['appointment_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['centre_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['patient_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['plan_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['service_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['user_id', 'INT(10) UNSIGNED DEFAULT NULL'],
            ],
            'feedback' => [
                ['patient_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['doctor_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['service_id', 'INT(10) UNSIGNED DEFAULT NULL'],
                ['location_id', 'INT(10) UNSIGNED DEFAULT NULL'],
            ],
            'order_details' => [['discount_id', 'INT(10) UNSIGNED DEFAULT NULL']],
            'services' => [['parent_id', 'INT(10) UNSIGNED DEFAULT NULL']],
            'user_vouchers' => [['user_id', 'INT(10) UNSIGNED DEFAULT NULL']],
            'voucher_has_locations' => [
                ['location_id', 'INT(10) UNSIGNED NOT NULL'],
                ['service_id', 'INT(10) UNSIGNED NOT NULL'],
            ],
            'warehouses' => [
                ['city_id', 'INT(10) UNSIGNED NOT NULL'],
                ['region_id', 'INT(10) UNSIGNED NOT NULL'],
            ],
        ];

        foreach ($perColumn as $table => $cols) {
            foreach ($cols as [$col, $sql]) {
                $this->safeModify($table, $col, $sql);
            }
        }
    }

    public function down(): void
    {
        $perColumn = [
            'cashflow_audit_logs' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'cashflow_notifications' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'cashflow_settings' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'cashflow_vendors' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'cash_pools' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'cash_transfers' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'category_requests' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'expense_categories' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'order_refunds' => [
                ['account_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['location_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['patient_id', 'BIGINT(20) UNSIGNED NOT NULL'],
            ],
            'order_refund_details' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'period_locks' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'staff_advances' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'staff_returns' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'vendor_requests' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'vendor_transactions' => [
                ['account_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['for_branch_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
            ],
            'vouchers' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'working_day_exceptions' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'expenses' => [['account_id', 'BIGINT(20) UNSIGNED NOT NULL']],
            'resource_time_offs' => [
                ['account_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['resource_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['location_id', 'BIGINT(20) UNSIGNED NOT NULL'],
            ],
            'activities' => [
                ['appointment_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['centre_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['patient_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['plan_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['service_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['user_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
            ],
            'feedback' => [
                ['patient_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['doctor_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['service_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
                ['location_id', 'BIGINT(20) UNSIGNED DEFAULT NULL'],
            ],
            'order_details' => [['discount_id', 'BIGINT(20) UNSIGNED DEFAULT NULL']],
            'services' => [['parent_id', 'BIGINT(20) UNSIGNED DEFAULT NULL']],
            'user_vouchers' => [['user_id', 'BIGINT(20) UNSIGNED DEFAULT NULL']],
            'voucher_has_locations' => [
                ['location_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['service_id', 'BIGINT(20) UNSIGNED NOT NULL'],
            ],
            'warehouses' => [
                ['city_id', 'BIGINT(20) UNSIGNED NOT NULL'],
                ['region_id', 'BIGINT(20) UNSIGNED NOT NULL'],
            ],
        ];

        foreach ($perColumn as $table => $cols) {
            foreach ($cols as [$col, $sql]) {
                $this->safeModify($table, $col, $sql);
            }
        }
    }
};
