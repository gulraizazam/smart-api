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
        $this->safeModify('product_details', 'id', 'INT(10) UNSIGNED NOT NULL AUTO_INCREMENT');

        // Group 1
        $g1nullable = [
            'activities' => ['account_id', 'invoice_id', 'lead_status_id', 'package_id', 'planId'],
            'appointments' => ['deleted_by'],
            'base_discount_services' => ['bundle_id'],
            'feedback' => ['treatment_id'],
            'get_discount_services' => ['base_service_id', 'bundle_id'],
            'leads_services' => ['lead_status_id'],
            'orders' => ['employee_id', 'patient_id', 'prescribed_by'],
            'package_services' => ['sold_by'],
            'package_vouchers' => ['main_service_id', 'user_id'],
            'plan_invoices' => ['package_advance_id', 'package_id'],
            'products' => ['location_id'],
            'stocks' => ['product_detail_id'],
            'transfer_products' => ['product_detail_id'],
            'users' => ['created_by'],
        ];
        foreach ($g1nullable as $table => $cols) {
            foreach ($cols as $col) {
                $this->safeModify($table, $col, 'INT(10) UNSIGNED NULL');
            }
        }

        $g1notnull = [
            'announcements' => ['user_id'],
            'notifications' => ['user_id'],
            'orders' => ['created_by'],
            'patient_notes' => ['created_by', 'patient_id'],
            'plan_invoices' => ['created_by'],
            'product_details' => ['account_id'],
        ];
        foreach ($g1notnull as $table => $cols) {
            foreach ($cols as $col) {
                $this->safeModify($table, $col, 'INT(10) UNSIGNED NOT NULL');
            }
        }

        $this->safeModify('orders', 'refund_order_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('order_details', 'inventory_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('stocks', 'order_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('transfer_products', 'child_product_id', 'BIGINT(20) UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        $this->safeModify('product_details', 'id', 'INT(11) NOT NULL AUTO_INCREMENT');

        $revertInt11Null = [
            'activities' => ['account_id', 'invoice_id', 'lead_status_id', 'package_id', 'planId'],
            'appointments' => ['deleted_by'],
            'base_discount_services' => ['bundle_id'],
            'feedback' => ['treatment_id'],
            'get_discount_services' => ['base_service_id', 'bundle_id'],
            'leads_services' => ['lead_status_id'],
            'orders' => ['employee_id', 'patient_id', 'prescribed_by'],
            'package_services' => ['sold_by'],
            'package_vouchers' => ['main_service_id', 'user_id'],
            'plan_invoices' => ['package_advance_id', 'package_id'],
            'products' => ['location_id'],
            'stocks' => ['product_detail_id'],
            'transfer_products' => ['product_detail_id'],
            'users' => ['created_by'],
        ];
        foreach ($revertInt11Null as $tbl => $cols) {
            foreach ($cols as $col) {
                $this->safeModify($tbl, $col, 'INT(11) NULL');
            }
        }

        $revertInt11NotNull = [
            'announcements' => ['user_id'],
            'notifications' => ['user_id'],
            'orders' => ['created_by'],
            'patient_notes' => ['created_by', 'patient_id'],
            'plan_invoices' => ['created_by'],
            'product_details' => ['account_id'],
        ];
        foreach ($revertInt11NotNull as $tbl => $cols) {
            foreach ($cols as $col) {
                $this->safeModify($tbl, $col, 'INT(11) NOT NULL');
            }
        }

        $this->safeModify('orders', 'refund_order_id', 'INT(11) NULL');
        $this->safeModify('order_details', 'inventory_id', 'INT(11) NULL');
        $this->safeModify('stocks', 'order_id', 'INT(11) NULL');
        $this->safeModify('transfer_products', 'child_product_id', 'INT(11) NOT NULL');
    }
};
