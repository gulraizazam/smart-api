<?php

use Illuminate\Database\Migrations\Migration;
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
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(id) ON DELETE {$onDelete}");
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
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropForeign {$name}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $constraints = [
            ['activities', 'appointment_id', 'appointments', true],
            ['activities', 'centre_id', 'locations', true],
            ['activities', 'invoice_id', 'invoices', true],
            ['activities', 'lead_status_id', 'lead_statuses', true],
            ['activities', 'package_id', 'packages', true],
            ['activities', 'patient_id', 'users', true],
            ['activities', 'plan_id', 'packages', true],
            ['activities', 'service_id', 'services', true],
            ['activities', 'user_id', 'users', true],
            ['appointments', 'base_appointment_status_id', 'appointment_statuses', true],
            ['appointments_daily_stats', 'appointment_id', 'appointments', true],
            ['appointments_daily_stats', 'appointment_status_id', 'appointment_statuses', true],
            ['appointments_daily_stats', 'centre_id', 'locations', true],
            ['appointments_daily_stats', 'user_id', 'users', true],
            ['appointment_statuses', 'appointment_type_id', 'appointment_types', true],
            ['appointment_statuses', 'parent_id', 'appointment_statuses', true],
            ['bundles', 'tax_treatment_type_id', 'tax_treatment_type', false],
            ['bundle_services_price_history', 'bundle_id', 'bundles', true],
            ['bundle_services_price_history', 'service_id', 'services', true],
            ['cancellation_reasons', 'appointment_type_id', 'appointment_types', true],
            ['centretargetmeta', 'centertarget_id', 'centertarget', false],
            ['centretargetmeta', 'location_id', 'locations', false],
            ['cities', 'region_id', 'regions', false],
            ['custom_form_feedbacks', 'custom_form_id', 'custom_forms', false],
            ['custom_form_feedback_details', 'custom_form_feedback_id', 'custom_form_feedbacks', false],
            ['custom_form_feedback_details', 'custom_form_field_id', 'custom_form_fields', false],
            ['custom_form_feedback_details', 'custom_form_id', 'custom_forms', false],
            ['custom_form_fields', 'user_form_id', 'custom_forms', false],
            ['doctor_has_services', 'service_id', 'services', false],
            ['doctor_has_services', 'user_id', 'users', false],
            ['documents', 'user_id', 'users', true],
            ['feedback', 'patient_id', 'users', true],
            ['feedback', 'doctor_id', 'users', true],
            ['feedback', 'service_id', 'services', true],
            ['feedback', 'location_id', 'locations', true],
            ['feedback', 'treatment_id', 'appointments', true],
            ['invoice_details', 'discount_id', 'discounts', true],
            ['leads', 'region_id', 'regions', true],
            ['leads', 'city_id', 'cities', true],
            ['leads', 'town_id', 'towns', true],
            ['lead_statuses', 'parent_id', 'lead_statuses', true],
            ['locations', 'city_id', 'cities', false],
            ['locations', 'region_id', 'regions', false],
            ['machine_type_has_services', 'machine_type_id', 'machine_types', false],
            ['machine_type_has_services', 'service_id', 'services', false],
            ['measurements', 'appointment_id', 'appointments', false],
            ['measurements', 'custom_form_feedback_id', 'custom_form_feedbacks', false],
            ['measurements', 'patient_id', 'users', false],
            ['measurements', 'service_id', 'services', false],
            ['measurements', 'user_id', 'users', false],
            ['medicals', 'appointment_id', 'appointments', false],
            ['medicals', 'custom_form_feedback_id', 'custom_form_feedbacks', false],
            ['medicals', 'patient_id', 'users', false],
            ['medicals', 'user_id', 'users', false],
            ['membership_type_has_discounts', 'discount_id', 'discounts', false],
            ['membership_type_has_discounts', 'membership_type_id', 'membership_types', false],
            ['orders', 'employee_id', 'users', true],
            ['order_details', 'discount_id', 'discounts', true],
            ['order_details', 'inventory_id', 'inventories', true],
            ['order_refunds', 'location_id', 'locations', false],
            ['order_refunds', 'order_id', 'orders', false],
            ['order_refunds', 'patient_id', 'users', false],
            ['order_refund_details', 'order_refund_id', 'order_refunds', false],
            ['order_refund_details', 'product_id', 'products', false],
            ['package_bundles', 'discount_id', 'discounts', true],
            ['package_bundles', 'location_id', 'locations', true],
            ['package_services', 'package_bundle_id', 'package_bundles', true],
            ['package_services', 'service_id', 'services', true],
            ['package_vouchers', 'main_service_id', 'services', true],
            ['package_vouchers', 'user_id', 'users', true],
            ['patient_notes', 'patient_id', 'users', false],
            ['plan_invoices', 'package_advance_id', 'package_advances', true],
            ['plan_invoices', 'package_id', 'packages', true],
            ['plan_invoices', 'patient_id', 'users', true],
            ['plan_invoices', 'payment_mode_id', 'payment_modes', true],
            ['products', 'brand_id', 'brands', false],
            ['products', 'location_id', 'locations', true],
            ['resources', 'resource_type_id', 'resource_types', true],
            ['resources', 'location_id', 'locations', true],
            ['resources', 'machine_type_id', 'machine_types', true],
            ['resource_has_rota', 'region_id', 'regions', true],
            ['resource_has_rota', 'city_id', 'cities', true],
            ['resource_has_rota', 'location_id', 'locations', true],
            ['resource_has_rota', 'resource_id', 'resources', true],
            ['resource_has_rota', 'resource_type_id', 'resource_types', true],
            ['resource_has_rota_days', 'resource_has_rota_id', 'resource_has_rota', true],
            ['resource_has_services', 'resource_id', 'resources', false],
            ['resource_has_services', 'service_id', 'services', false],
            ['resource_time_offs', 'resource_id', 'resources', false],
            ['resource_time_offs', 'location_id', 'locations', false],
            ['role_has_users', 'role_id', 'roles', false],
            ['role_has_users', 'user_id', 'users', false],
            ['services', 'parent_id', 'services', true],
            ['services', 'tax_treatment_type_id', 'tax_treatment_type', false],
            ['service_has_locations', 'location_id', 'locations', false],
            ['service_has_locations', 'service_id', 'services', false],
            ['staff_targets', 'location_id', 'locations', false],
            ['staff_targets', 'staff_id', 'users', false],
            ['staff_target_services', 'location_id', 'locations', false],
            ['staff_target_services', 'service_id', 'services', false],
            ['staff_target_services', 'staff_id', 'users', false],
            ['staff_target_services', 'staff_target_id', 'staff_targets', false],
            ['stocks', 'product_detail_id', 'product_details', true],
            ['student_verifications', 'membership_id', 'memberships', true],
            ['student_verifications', 'membership_type_id', 'membership_types', false],
            ['student_verifications', 'package_id', 'packages', true],
            ['student_verifications', 'patient_id', 'users', false],
            ['telecomprovidernumbers', 'telecomprovider_id', 'telecomproviders', false],
            ['towns', 'city_id', 'cities', true],
            ['user_has_locations', 'region_id', 'regions', false],
            ['user_operator_settings', 'operator_id', 'global_operator_settings', false],
            ['user_vouchers', 'user_id', 'users', true],
            ['user_vouchers', 'voucher_id', 'vouchers', true],
            ['users', 'resource_type_id', 'resource_types', true],
            ['users', 'user_type_id', 'user_types', true],
            ['vendor_requests', 'category_id', 'expense_categories', true],
            ['vendor_transactions', 'for_branch_id', 'locations', true],
            ['voucher_has_locations', 'location_id', 'locations', false],
            ['voucher_has_locations', 'service_id', 'services', false],
            ['voucher_has_locations', 'voucher_id', 'vouchers', false],
            ['warehouses', 'city_id', 'cities', false],
            ['warehouses', 'region_id', 'regions', false],
            ['base_discount_services', 'bundle_id', 'bundles', true],
            ['get_discount_services', 'base_service_id', 'services', true],
            ['get_discount_services', 'bundle_id', 'bundles', true],
            ['leads_services', 'consultancy_id', 'appointments', true],
        ];

        foreach ($constraints as [$table, $column, $refTable, $nullable]) {
            $fkName = "fk_{$table}_{$column}";
            if (strlen($fkName) > 64) {
                $fkName = substr($fkName, 0, 64);
            }
            $onDelete = $nullable ? 'SET NULL' : 'RESTRICT';
            $this->addFk($table, $column, $refTable, $fkName, $onDelete);
        }
    }

    public function down(): void
    {
        $constraints = [
            ['activities', 'appointment_id'], ['activities', 'centre_id'], ['activities', 'invoice_id'],
            ['activities', 'lead_status_id'], ['activities', 'package_id'], ['activities', 'patient_id'],
            ['activities', 'plan_id'], ['activities', 'service_id'], ['activities', 'user_id'],
            ['appointments', 'base_appointment_status_id'],
            ['appointments_daily_stats', 'appointment_id'], ['appointments_daily_stats', 'appointment_status_id'],
            ['appointments_daily_stats', 'centre_id'], ['appointments_daily_stats', 'user_id'],
            ['appointment_statuses', 'appointment_type_id'], ['appointment_statuses', 'parent_id'],
            ['bundles', 'tax_treatment_type_id'],
            ['bundle_services_price_history', 'bundle_id'], ['bundle_services_price_history', 'service_id'],
            ['cancellation_reasons', 'appointment_type_id'],
            ['centretargetmeta', 'centertarget_id'], ['centretargetmeta', 'location_id'],
            ['cities', 'region_id'],
            ['custom_form_feedbacks', 'custom_form_id'],
            ['custom_form_feedback_details', 'custom_form_feedback_id'], ['custom_form_feedback_details', 'custom_form_field_id'], ['custom_form_feedback_details', 'custom_form_id'],
            ['custom_form_fields', 'user_form_id'],
            ['doctor_has_services', 'service_id'], ['doctor_has_services', 'user_id'],
            ['documents', 'user_id'],
            ['feedback', 'patient_id'], ['feedback', 'doctor_id'], ['feedback', 'service_id'], ['feedback', 'location_id'], ['feedback', 'treatment_id'],
            ['invoice_details', 'discount_id'],
            ['leads', 'region_id'], ['leads', 'city_id'], ['leads', 'town_id'],
            ['lead_statuses', 'parent_id'],
            ['locations', 'city_id'], ['locations', 'region_id'],
            ['machine_type_has_services', 'machine_type_id'], ['machine_type_has_services', 'service_id'],
            ['measurements', 'appointment_id'], ['measurements', 'custom_form_feedback_id'], ['measurements', 'patient_id'], ['measurements', 'service_id'], ['measurements', 'user_id'],
            ['medicals', 'appointment_id'], ['medicals', 'custom_form_feedback_id'], ['medicals', 'patient_id'], ['medicals', 'user_id'],
            ['membership_type_has_discounts', 'discount_id'], ['membership_type_has_discounts', 'membership_type_id'],
            ['orders', 'employee_id'],
            ['order_details', 'discount_id'], ['order_details', 'inventory_id'],
            ['order_refunds', 'location_id'], ['order_refunds', 'order_id'], ['order_refunds', 'patient_id'],
            ['order_refund_details', 'order_refund_id'], ['order_refund_details', 'product_id'],
            ['package_bundles', 'discount_id'], ['package_bundles', 'location_id'],
            ['package_services', 'package_bundle_id'], ['package_services', 'service_id'],
            ['package_vouchers', 'main_service_id'], ['package_vouchers', 'user_id'],
            ['patient_notes', 'patient_id'],
            ['plan_invoices', 'package_advance_id'], ['plan_invoices', 'package_id'], ['plan_invoices', 'patient_id'], ['plan_invoices', 'payment_mode_id'],
            ['products', 'brand_id'], ['products', 'location_id'],
            ['resources', 'resource_type_id'], ['resources', 'location_id'], ['resources', 'machine_type_id'],
            ['resource_has_rota', 'region_id'], ['resource_has_rota', 'city_id'], ['resource_has_rota', 'location_id'], ['resource_has_rota', 'resource_id'], ['resource_has_rota', 'resource_type_id'],
            ['resource_has_rota_days', 'resource_has_rota_id'],
            ['resource_has_services', 'resource_id'], ['resource_has_services', 'service_id'],
            ['resource_time_offs', 'resource_id'], ['resource_time_offs', 'location_id'],
            ['role_has_users', 'role_id'], ['role_has_users', 'user_id'],
            ['services', 'parent_id'], ['services', 'tax_treatment_type_id'],
            ['service_has_locations', 'location_id'], ['service_has_locations', 'service_id'],
            ['staff_targets', 'location_id'], ['staff_targets', 'staff_id'],
            ['staff_target_services', 'location_id'], ['staff_target_services', 'service_id'], ['staff_target_services', 'staff_id'], ['staff_target_services', 'staff_target_id'],
            ['stocks', 'product_detail_id'],
            ['student_verifications', 'membership_id'], ['student_verifications', 'membership_type_id'], ['student_verifications', 'package_id'], ['student_verifications', 'patient_id'],
            ['telecomprovidernumbers', 'telecomprovider_id'],
            ['towns', 'city_id'],
            ['user_has_locations', 'region_id'],
            ['user_operator_settings', 'operator_id'],
            ['user_vouchers', 'user_id'], ['user_vouchers', 'voucher_id'],
            ['users', 'resource_type_id'], ['users', 'user_type_id'],
            ['vendor_requests', 'category_id'],
            ['vendor_transactions', 'for_branch_id'],
            ['voucher_has_locations', 'location_id'], ['voucher_has_locations', 'service_id'], ['voucher_has_locations', 'voucher_id'],
            ['warehouses', 'city_id'], ['warehouses', 'region_id'],
            ['base_discount_services', 'bundle_id'],
            ['get_discount_services', 'base_service_id'], ['get_discount_services', 'bundle_id'],
            ['leads_services', 'consultancy_id'],
        ];

        foreach ($constraints as [$table, $column]) {
            $fkName = "fk_{$table}_{$column}";
            if (strlen($fkName) > 64) {
                $fkName = substr($fkName, 0, 64);
            }
            $this->dropFk($table, $fkName);
        }
    }
};
