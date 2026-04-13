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
        $this->safeStatement('UPDATE package_vouchers SET service_id = NULL WHERE service_id IS NOT NULL AND service_id NOT IN (SELECT id FROM services)');
        $this->safeStatement('UPDATE package_vouchers SET voucher_id = NULL WHERE voucher_id IS NOT NULL AND voucher_id NOT IN (SELECT id FROM vouchers)');
        $this->safeStatement('UPDATE leads SET child_service_id = NULL WHERE child_service_id = 0 OR (child_service_id IS NOT NULL AND child_service_id NOT IN (SELECT id FROM services))');
        $this->safeStatement('UPDATE package_bundles SET membership_code_id = NULL WHERE membership_code_id IS NOT NULL AND membership_code_id NOT IN (SELECT id FROM memberships)');

        $this->addFk('activities', 'lead_id', 'leads', 'fk_activities_lead', 'set null');
        $this->addFk('base_discount_services', 'service_id', 'services', 'fk_base_disc_svc_service', 'set null');
        $this->addFk('base_discount_services', 'discount_id', 'discounts', 'fk_base_disc_svc_discount', 'set null');
        $this->addFk('discount_role', 'discount_id', 'discounts', 'fk_disc_role_discount', 'cascade');
        $this->addFk('discount_role', 'role_id', 'roles', 'fk_disc_role_role', 'cascade');
        $this->addFk('feedback', 'appointment_id', 'appointments', 'fk_feedback_appointment', 'set null');
        $this->addFk('get_discount_services', 'service_id', 'services', 'fk_get_disc_svc_service', 'set null');
        $this->addFk('get_discount_services', 'discount_id', 'discounts', 'fk_get_disc_svc_discount', 'set null');
        $this->addFk('invoices', 'package_id', 'packages', 'fk_invoices_package', 'set null');
        $this->addFk('leads', 'child_service_id', 'services', 'fk_leads_child_service', 'set null');
        $this->addFk('package_bundles', 'base_service_id', 'services', 'fk_pkg_bundles_base_svc', 'set null');
        $this->addFk('package_bundles', 'membership_code_id', 'memberships', 'fk_pkg_bundles_membership', 'set null');
        $this->addFk('package_bundles', 'membership_type_id', 'membership_types', 'fk_pkg_bundles_memtype', 'set null');
        $this->addFk('package_services', 'base_service_id', 'services', 'fk_pkg_svc_base_service', 'set null');
        $this->addFk('package_vouchers', 'package_id', 'packages', 'fk_pkg_vouchers_package', 'set null');
        $this->addFk('package_vouchers', 'service_id', 'services', 'fk_pkg_vouchers_service', 'set null');
        $this->addFk('package_vouchers', 'voucher_id', 'vouchers', 'fk_pkg_vouchers_voucher', 'set null');
        $this->addFk('plan_invoices', 'location_id', 'locations', 'fk_plan_inv_location', 'restrict');
        $this->addFk('product_details', 'product_id', 'products', 'fk_prod_details_product', 'cascade');
        $this->addFk('stocks', 'location_id', 'locations', 'fk_stocks_location', 'set null');
        $this->addFk('user_has_warehouses', 'user_id', 'users', 'fk_user_wh_user', 'cascade');
        $this->addFk('user_has_warehouses', 'warehouse_id', 'warehouses', 'fk_user_wh_warehouse', 'cascade');
        $this->addFk('discounts', 'customer_type_id', 'membership_types', 'fk_discounts_custtype', 'set null');
        $this->addFk('membership_types', 'parent_id', 'membership_types', 'fk_memtypes_parent', 'set null');
    }

    public function down(): void
    {
        $this->dropFk('activities', 'fk_activities_lead');
        $this->dropFk('base_discount_services', 'fk_base_disc_svc_service');
        $this->dropFk('base_discount_services', 'fk_base_disc_svc_discount');
        $this->dropFk('discount_role', 'fk_disc_role_discount');
        $this->dropFk('discount_role', 'fk_disc_role_role');
        $this->dropFk('feedback', 'fk_feedback_appointment');
        $this->dropFk('get_discount_services', 'fk_get_disc_svc_service');
        $this->dropFk('get_discount_services', 'fk_get_disc_svc_discount');
        $this->dropFk('invoices', 'fk_invoices_package');
        $this->dropFk('leads', 'fk_leads_child_service');
        $this->dropFk('package_bundles', 'fk_pkg_bundles_base_svc');
        $this->dropFk('package_bundles', 'fk_pkg_bundles_membership');
        $this->dropFk('package_bundles', 'fk_pkg_bundles_memtype');
        $this->dropFk('package_services', 'fk_pkg_svc_base_service');
        $this->dropFk('package_vouchers', 'fk_pkg_vouchers_package');
        $this->dropFk('package_vouchers', 'fk_pkg_vouchers_service');
        $this->dropFk('package_vouchers', 'fk_pkg_vouchers_voucher');
        $this->dropFk('plan_invoices', 'fk_plan_inv_location');
        $this->dropFk('product_details', 'fk_prod_details_product');
        $this->dropFk('stocks', 'fk_stocks_location');
        $this->dropFk('user_has_warehouses', 'fk_user_wh_user');
        $this->dropFk('user_has_warehouses', 'fk_user_wh_warehouse');
        $this->dropFk('discounts', 'fk_discounts_custtype');
        $this->dropFk('membership_types', 'fk_memtypes_parent');
    }
};
