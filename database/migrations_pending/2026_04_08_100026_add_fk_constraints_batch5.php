<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $name): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY' LIMIT 1",
            [$table, $name]
        );
        return ! empty($rows);
    }

    private function addFk(string $table, string $column, string $refTable, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($refTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $t) use ($column, $refTable, $name, $onDelete) {
                $t->foreign($column, $name)->references('id')->on($refTable)->onDelete($onDelete);
            });
        } catch (\Throwable $e) {
            \Log::warning("Skipping FK {$name}: " . $e->getMessage());
        }
    }

    private function dropFk(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->foreignKeyExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropForeign {$name}: " . $e->getMessage());
        }
    }

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

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Log::warning('Statement failed: ' . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeModify('orders', 'location_id', 'INT(10) UNSIGNED NOT NULL');
        $this->safeModify('orders', 'payment_mode', 'INT(10) UNSIGNED NULL');
        $this->safeModify('orders', 'updated_by', 'INT(10) UNSIGNED NULL');
        $this->safeModify('memberships', 'patient_id', 'INT(10) UNSIGNED NULL');
        $this->safeModify('memberships', 'created_by', 'INT(10) UNSIGNED NOT NULL');
        $this->safeModify('memberships', 'updated_by', 'INT(10) UNSIGNED NULL');
        $this->safeModify('memberships', 'deleted_by', 'INT(10) UNSIGNED NULL');
        $this->safeModify('inventories', 'location_id', 'INT(10) UNSIGNED NULL');

        $this->safeStatement('DELETE FROM leads_services WHERE lead_id NOT IN (SELECT id FROM leads)');

        $this->addFk('users', 'account_id', 'accounts', 'fk_users_account', 'set null');
        $this->addFk('users', 'created_by', 'users', 'fk_users_creator', 'set null');

        $this->addFk('leads_services', 'lead_id', 'leads', 'fk_ls_lead', 'cascade');
        $this->addFk('leads_services', 'service_id', 'services', 'fk_ls_service', 'cascade');
        $this->addFk('leads_services', 'lead_status_id', 'lead_statuses', 'fk_ls_status', 'set null');
        $this->addFk('leads_services', 'child_service_id', 'services', 'fk_ls_child_service', 'set null');

        $this->addFk('sms_logs', 'lead_id', 'leads', 'fk_sms_lead', 'set null');
        $this->addFk('sms_logs', 'invoice_id', 'invoices', 'fk_sms_invoice', 'set null');
        $this->addFk('sms_logs', 'package_id', 'packages', 'fk_sms_package', 'set null');

        $this->addFk('orders', 'account_id', 'accounts', 'fk_orders_account', 'restrict');
        $this->addFk('orders', 'patient_id', 'users', 'fk_orders_patient', 'set null');
        $this->addFk('orders', 'created_by', 'users', 'fk_orders_creator', 'restrict');
        $this->addFk('orders', 'location_id', 'locations', 'fk_orders_location', 'restrict');

        $this->addFk('order_details', 'order_id', 'orders', 'fk_od_order', 'cascade');
        $this->addFk('order_details', 'product_id', 'products', 'fk_od_product', 'cascade');
        $this->addFk('order_details', 'account_id', 'accounts', 'fk_od_account', 'restrict');

        $this->addFk('memberships', 'membership_type_id', 'membership_types', 'fk_mem_type', 'restrict');
        $this->addFk('memberships', 'patient_id', 'users', 'fk_mem_patient', 'set null');
        $this->addFk('memberships', 'created_by', 'users', 'fk_mem_creator', 'restrict');

        $this->addFk('inventories', 'product_id', 'products', 'fk_inv_product', 'cascade');
        $this->addFk('inventories', 'warehouse_id', 'warehouses', 'fk_inv_warehouse', 'cascade');
        $this->addFk('inventories', 'location_id', 'locations', 'fk_inv_location', 'set null');

        $this->addFk('stocks', 'account_id', 'accounts', 'fk_stocks_account', 'restrict');
        $this->addFk('stocks', 'product_id', 'products', 'fk_stocks_product', 'cascade');
        $this->addFk('stocks', 'order_id', 'orders', 'fk_stocks_order', 'set null');

        $this->addFk('appointment_comments', 'appointment_id', 'appointments', 'fk_ac_appointment', 'cascade');
        $this->addFk('appointment_comments', 'created_by', 'users', 'fk_ac_creator', 'set null');
    }

    public function down(): void
    {
        $this->dropFk('users', 'fk_users_account');
        $this->dropFk('users', 'fk_users_creator');
        $this->dropFk('leads_services', 'fk_ls_lead');
        $this->dropFk('leads_services', 'fk_ls_service');
        $this->dropFk('leads_services', 'fk_ls_status');
        $this->dropFk('leads_services', 'fk_ls_child_service');
        $this->dropFk('sms_logs', 'fk_sms_lead');
        $this->dropFk('sms_logs', 'fk_sms_invoice');
        $this->dropFk('sms_logs', 'fk_sms_package');
        $this->dropFk('orders', 'fk_orders_account');
        $this->dropFk('orders', 'fk_orders_patient');
        $this->dropFk('orders', 'fk_orders_creator');
        $this->dropFk('orders', 'fk_orders_location');
        $this->dropFk('order_details', 'fk_od_order');
        $this->dropFk('order_details', 'fk_od_product');
        $this->dropFk('order_details', 'fk_od_account');
        $this->dropFk('memberships', 'fk_mem_type');
        $this->dropFk('memberships', 'fk_mem_patient');
        $this->dropFk('memberships', 'fk_mem_creator');
        $this->dropFk('inventories', 'fk_inv_product');
        $this->dropFk('inventories', 'fk_inv_warehouse');
        $this->dropFk('inventories', 'fk_inv_location');
        $this->dropFk('stocks', 'fk_stocks_account');
        $this->dropFk('stocks', 'fk_stocks_product');
        $this->dropFk('stocks', 'fk_stocks_order');
        $this->dropFk('appointment_comments', 'fk_ac_appointment');
        $this->dropFk('appointment_comments', 'fk_ac_creator');

        $this->safeModify('orders', 'location_id', 'BIGINT(20) UNSIGNED NOT NULL');
        $this->safeModify('orders', 'payment_mode', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('orders', 'updated_by', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('memberships', 'patient_id', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('memberships', 'created_by', 'BIGINT(20) UNSIGNED NOT NULL');
        $this->safeModify('memberships', 'updated_by', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('memberships', 'deleted_by', 'BIGINT(20) UNSIGNED NULL');
        $this->safeModify('inventories', 'location_id', 'BIGINT(20) UNSIGNED NULL');
    }
};
