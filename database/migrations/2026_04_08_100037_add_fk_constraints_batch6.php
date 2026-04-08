<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add FK constraints for account_id + lookup/config table references.
     * Orphan cleanup: nullify orphaned nullable FKs, then add constraints.
     *
     * EXCLUDED from all FK batches:
     * - Polymorphic columns (entity_id, table_record_id, reference_id, model_id)
     * - String references (random_id, meta_lead_id, config_group_id, package_random_id)
     * - Archive tables (audit_trails_archive, sms_logs_archive, audit_trail_changes_archive)
     * - Audit trail change tables (7.5M + 4.2M rows — FK on audit_trail_id too expensive)
     * - Spatie tables (model_has_permissions, model_has_roles — managed by package)
     * - audit_trails (825K rows, polymorphic table_record_id + user refs may be deleted)
     */
    public function up(): void
    {
        // --- Orphan cleanup ---
        DB::table('activities')->whereNotNull('appointment_id')->where('appointment_id', '!=', 0)
            ->whereNotIn('appointment_id', DB::table('appointments')->select('id'))
            ->update(['appointment_id' => null]);

        DB::table('activities')->whereNotNull('invoice_id')->where('invoice_id', '!=', 0)
            ->whereNotIn('invoice_id', DB::table('invoices')->select('id'))
            ->update(['invoice_id' => null]);

        DB::table('plan_invoices')->whereNotNull('package_advance_id')
            ->whereNotIn('package_advance_id', DB::table('package_advances')->select('id'))
            ->update(['package_advance_id' => null]);

        DB::table('plan_invoices')->whereNotNull('package_id')
            ->whereNotIn('package_id', DB::table('packages')->select('id'))
            ->update(['package_id' => null]);

        DB::table('appointments_daily_stats')->whereNotNull('appointment_id')
            ->whereNotIn('appointment_id', DB::table('appointments')->select('id'))
            ->update(['appointment_id' => null]);

        // Zero → NULL for nullable FK columns that use 0 as "none"
        $zeroToNull = [
            ['activities', ['appointment_id', 'centre_id', 'patient_id', 'plan_id', 'service_id', 'user_id', 'invoice_id', 'lead_status_id', 'package_id']],
            ['feedback', ['patient_id', 'doctor_id', 'service_id', 'location_id', 'treatment_id']],
            ['appointments_daily_stats', ['appointment_id', 'user_id', 'centre_id', 'appointment_status_id']],
            ['appointment_statuses', ['appointment_type_id', 'parent_id']],
            ['cancellation_reasons', ['appointment_type_id']],
            ['leads', ['region_id', 'city_id', 'town_id']],
            ['documents', ['user_id']],
            ['invoice_details', ['discount_id']],
            ['orders', ['employee_id']],
            ['plan_invoices', ['package_advance_id', 'package_id', 'patient_id', 'payment_mode_id']],
            ['products', ['location_id']],
            ['resources', ['resource_type_id', 'location_id', 'machine_type_id']],
            ['resource_has_rota', ['region_id', 'city_id', 'location_id', 'resource_id', 'resource_type_id']],
            ['resource_has_rota_days', ['resource_has_rota_id']],
            ['services', ['parent_id']],
            ['stocks', ['product_detail_id']],
            ['student_verifications', ['membership_id', 'package_id']],
            ['towns', ['city_id']],
            ['package_bundles', ['discount_id', 'location_id']],
            ['package_services', ['package_bundle_id', 'service_id']],
            ['package_vouchers', ['main_service_id', 'user_id']],
            ['user_vouchers', ['user_id', 'voucher_id']],
            ['vendor_requests', ['category_id']],
            ['vendor_transactions', ['for_branch_id']],
            ['bundle_services_price_history', ['bundle_id', 'service_id']],
            ['base_discount_services', ['bundle_id']],
            ['get_discount_services', ['base_service_id', 'bundle_id']],
            ['leads_services', ['consultancy_id']],
        ];

        foreach ($zeroToNull as [$table, $cols]) {
            foreach ($cols as $col) {
                DB::table($table)->where($col, 0)->update([$col => null]);
            }
        }

        // --- account_id FK constraints (all NOT NULL, all RESTRICT) ---
        $accountFks = [
            'activities', 'appointments', 'appointment_statuses', 'appointment_types',
            'brands', 'bundles', 'bundle_services_price_history', 'cancellation_reasons',
            'cashflow_audit_logs', 'cashflow_notifications', 'cashflow_settings', 'cashflow_vendors',
            'cash_pools', 'cash_transfers', 'category_requests', 'centertarget', 'centretargetmeta',
            'cities', 'custom_forms', 'custom_form_feedbacks', 'custom_form_feedback_details',
            'custom_form_fields', 'discounts', 'expense_categories', 'expenses',
            'heavy_lifters', 'invoice_statuses', 'leads', 'lead_comments', 'lead_sources',
            'lead_statuses', 'locations', 'machine_types', 'order_refunds', 'order_refund_details',
            'packages', 'payment_modes', 'period_locks', 'plan_invoices', 'products',
            'product_details', 'regions', 'resources', 'resource_has_rota', 'resource_time_offs',
            'services', 'service_has_locations', 'settings', 'sms_templates', 'staff_advances',
            'staff_returns', 'staff_targets', 'staff_target_services', 'towns',
            'user_operator_settings', 'user_types', 'vendor_requests', 'vendor_transactions',
            'vouchers', 'warehouses', 'working_day_exceptions',
        ];

        foreach ($accountFks as $table) {
            $fkName = "fk_{$table}_account_id";
            // Truncate FK name if too long
            if (strlen($fkName) > 64) {
                $fkName = substr($fkName, 0, 64);
            }
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$fkName` FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT");
        }
    }

    public function down(): void
    {
        $accountFks = [
            'activities', 'appointments', 'appointment_statuses', 'appointment_types',
            'brands', 'bundles', 'bundle_services_price_history', 'cancellation_reasons',
            'cashflow_audit_logs', 'cashflow_notifications', 'cashflow_settings', 'cashflow_vendors',
            'cash_pools', 'cash_transfers', 'category_requests', 'centertarget', 'centretargetmeta',
            'cities', 'custom_forms', 'custom_form_feedbacks', 'custom_form_feedback_details',
            'custom_form_fields', 'discounts', 'expense_categories', 'expenses',
            'heavy_lifters', 'invoice_statuses', 'leads', 'lead_comments', 'lead_sources',
            'lead_statuses', 'locations', 'machine_types', 'order_refunds', 'order_refund_details',
            'packages', 'payment_modes', 'period_locks', 'plan_invoices', 'products',
            'product_details', 'regions', 'resources', 'resource_has_rota', 'resource_time_offs',
            'services', 'service_has_locations', 'settings', 'sms_templates', 'staff_advances',
            'staff_returns', 'staff_targets', 'staff_target_services', 'towns',
            'user_operator_settings', 'user_types', 'vendor_requests', 'vendor_transactions',
            'vouchers', 'warehouses', 'working_day_exceptions',
        ];

        foreach ($accountFks as $table) {
            $fkName = "fk_{$table}_account_id";
            if (strlen($fkName) > 64) $fkName = substr($fkName, 0, 64);
            Schema::table($table, fn($t) => $t->dropForeign($fkName));
        }
    }
};
