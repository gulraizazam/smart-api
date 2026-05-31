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

    public function up(): void
    {
        // Group 1: int(11) → int(10) unsigned
        $this->safeModify('activities', 'lead_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('base_discount_services', 'service_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('base_discount_services', 'discount_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('discount_role', 'discount_id', 'INT(10) UNSIGNED NOT NULL');
        $this->safeModify('discount_role', 'role_id', 'INT(10) UNSIGNED NOT NULL');
        $this->safeModify('feedback', 'appointment_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('get_discount_services', 'service_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('get_discount_services', 'discount_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('invoices', 'package_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('leads', 'child_service_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('package_bundles', 'base_service_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('package_services', 'base_service_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('package_vouchers', 'package_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('package_vouchers', 'service_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('plan_invoices', 'location_id', 'INT(10) UNSIGNED NOT NULL');
        $this->safeModify('stocks', 'location_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('user_has_warehouses', 'user_id', 'INT(10) UNSIGNED NOT NULL');

        // Group 2: int(11) → bigint(20) unsigned
        $this->safeModify('package_vouchers', 'voucher_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('product_details', 'product_id', 'BIGINT(20) UNSIGNED NOT NULL');
        $this->safeModify('user_has_warehouses', 'warehouse_id', 'BIGINT(20) UNSIGNED NOT NULL');

        // Group 3: bigint(20) signed → bigint(20) unsigned
        $this->safeModify('discounts', 'customer_type_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('membership_types', 'parent_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('package_bundles', 'membership_code_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('package_bundles', 'membership_type_id', 'BIGINT(20) UNSIGNED NULL');
    }

    public function down(): void
    {
        $this->safeModify('activities', 'lead_id', 'INT(11) NULL');
        $this->safeModify('base_discount_services', 'service_id', 'INT(11) NULL');
        $this->safeModify('base_discount_services', 'discount_id', 'INT(11) NULL');
        $this->safeModify('discount_role', 'discount_id', 'INT(11) NOT NULL');
        $this->safeModify('discount_role', 'role_id', 'INT(11) NOT NULL');
        $this->safeModify('feedback', 'appointment_id', 'INT(11) NULL');
        $this->safeModify('get_discount_services', 'service_id', 'INT(11) NULL');
        $this->safeModify('get_discount_services', 'discount_id', 'INT(11) NULL');
        $this->safeModify('invoices', 'package_id', 'INT(11) NULL');
        $this->safeModify('leads', 'child_service_id', 'INT(11) NULL');
        $this->safeModify('package_bundles', 'base_service_id', 'INT(11) NULL');
        $this->safeModify('package_services', 'base_service_id', 'INT(11) NULL');
        $this->safeModify('package_vouchers', 'package_id', 'INT(11) NULL');
        $this->safeModify('package_vouchers', 'service_id', 'INT(11) NULL');
        $this->safeModify('plan_invoices', 'location_id', 'INT(11) NOT NULL');
        $this->safeModify('stocks', 'location_id', 'INT(11) NULL');
        $this->safeModify('user_has_warehouses', 'user_id', 'INT(11) NOT NULL');

        $this->safeModify('package_vouchers', 'voucher_id', 'INT(11) NULL');
        $this->safeModify('product_details', 'product_id', 'INT(11) NOT NULL');
        $this->safeModify('user_has_warehouses', 'warehouse_id', 'INT(11) NOT NULL');

        $this->safeModify('discounts', 'customer_type_id', 'BIGINT(20) NULL');
        $this->safeModify('membership_types', 'parent_id', 'BIGINT(20) NULL');
        $this->safeModify('package_bundles', 'membership_code_id', 'BIGINT(20) NULL');
        $this->safeModify('package_bundles', 'membership_type_id', 'BIGINT(20) NULL');
    }
};
