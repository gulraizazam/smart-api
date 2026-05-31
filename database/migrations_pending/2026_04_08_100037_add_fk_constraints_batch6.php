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

    private function safeNullifyOrphans(string $table, string $column, string $refTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($refTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::table($table)->whereNotNull($column)->where($column, '!=', 0)
                ->whereNotIn($column, DB::table($refTable)->select('id'))
                ->update([$column => null]);
        } catch (\Throwable $e) {
            \Log::warning("Orphan nullify {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function safeZeroToNull(string $table, string $col): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
            return;
        }
        try {
            DB::table($table)->where($col, 0)->update([$col => null]);
        } catch (\Throwable $e) {
            \Log::warning("Zero->null {$table}.{$col}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeNullifyOrphans('activities', 'appointment_id', 'appointments');
        $this->safeNullifyOrphans('activities', 'invoice_id', 'invoices');
        $this->safeNullifyOrphans('plan_invoices', 'package_advance_id', 'package_advances');
        $this->safeNullifyOrphans('plan_invoices', 'package_id', 'packages');
        $this->safeNullifyOrphans('appointments_daily_stats', 'appointment_id', 'appointments');

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
                $this->safeZeroToNull($table, $col);
            }
        }

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
            if (strlen($fkName) > 64) {
                $fkName = substr($fkName, 0, 64);
            }
            $this->addFk($table, 'account_id', 'accounts', $fkName, 'RESTRICT');
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
            if (strlen($fkName) > 64) {
                $fkName = substr($fkName, 0, 64);
            }
            $this->dropFk($table, $fkName);
        }
    }
};
