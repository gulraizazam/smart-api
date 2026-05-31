<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    private function addIndex(string $table, array|string $columns, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }

    public function up(): void
    {
        // appointments (FK'd) — add composites alongside
        $this->addIndex('appointments', ['account_id', 'deleted_at'], 'idx_appointments_account_deleted');
        $this->addIndex('appointments', ['patient_id', 'deleted_at'], 'idx_appointments_patient_deleted');
        $this->addIndex('appointments', ['location_id', 'deleted_at'], 'idx_appointments_location_deleted');

        // invoices — add composite before dropping single-col so FK stays covered
        $this->addIndex('invoices', ['account_id', 'deleted_at'], 'idx_invoices_account_deleted');
        $this->dropIndex('invoices', 'invoices_account_id_foreign');
        $this->addIndex('invoices', ['patient_id', 'deleted_at'], 'idx_invoices_patient_deleted');
        $this->dropIndex('invoices', 'invoices_patient_id_foreign');

        // invoice_details
        $this->addIndex('invoice_details', ['invoice_id', 'deleted_at'], 'idx_invoice_details_invoice_deleted');
        $this->dropIndex('invoice_details', 'invoice_details_invoice_id_foreign');

        // leads
        $this->addIndex('leads', ['account_id', 'deleted_at'], 'idx_leads_account_deleted');
        $this->dropIndex('leads', 'leads_account');
        $this->addIndex('leads', ['patient_id', 'deleted_at'], 'idx_leads_patient_deleted');
        $this->dropIndex('leads', 'leads_patient_id_foreign');

        // lead_comments
        $this->addIndex('lead_comments', ['lead_id', 'deleted_at'], 'idx_lead_comments_lead_deleted');
        $this->dropIndex('lead_comments', 'lead_comments_lead_id_foreign');
        $this->addIndex('lead_comments', ['account_id', 'deleted_at'], 'idx_lead_comments_account_deleted');
        $this->dropIndex('lead_comments', 'lead_comments_account');

        // package_bundles
        $this->addIndex('package_bundles', ['package_id', 'deleted_at'], 'idx_package_bundles_package_deleted');
        $this->dropIndex('package_bundles', 'package_bundles_package_id_foreign');

        // plan_invoices
        $this->addIndex('plan_invoices', ['account_id', 'deleted_at'], 'idx_plan_invoices_account_deleted');
        $this->dropIndex('plan_invoices', 'plan_invoices_account_id_index');
        $this->addIndex('plan_invoices', ['patient_id', 'deleted_at'], 'idx_plan_invoices_patient_deleted');
        $this->dropIndex('plan_invoices', 'plan_invoices_patient_id_index');

        // resource_has_rota_days
        $this->addIndex('resource_has_rota_days', ['resource_has_rota_id', 'deleted_at'], 'idx_rota_days_rota_deleted');
        $this->dropIndex('resource_has_rota_days', 'resource_has_rota_days_resource_has_rota_id_foreign');

        // sms_logs
        $this->addIndex('sms_logs', ['appointment_id', 'deleted_at'], 'idx_sms_logs_appointment_deleted');
        $this->dropIndex('sms_logs', 'sms_logs_appointment_id_foreign');
    }

    public function down(): void
    {
        $this->dropIndex('appointments', 'idx_appointments_account_deleted');
        $this->dropIndex('appointments', 'idx_appointments_patient_deleted');
        $this->dropIndex('appointments', 'idx_appointments_location_deleted');

        $this->dropIndex('invoices', 'idx_invoices_account_deleted');
        $this->addIndex('invoices', 'account_id', 'invoices_account_id_foreign');
        $this->dropIndex('invoices', 'idx_invoices_patient_deleted');
        $this->addIndex('invoices', 'patient_id', 'invoices_patient_id_foreign');

        $this->dropIndex('invoice_details', 'idx_invoice_details_invoice_deleted');
        $this->addIndex('invoice_details', 'invoice_id', 'invoice_details_invoice_id_foreign');

        $this->dropIndex('leads', 'idx_leads_account_deleted');
        $this->addIndex('leads', 'account_id', 'leads_account');
        $this->dropIndex('leads', 'idx_leads_patient_deleted');
        $this->addIndex('leads', 'patient_id', 'leads_patient_id_foreign');

        $this->dropIndex('lead_comments', 'idx_lead_comments_lead_deleted');
        $this->addIndex('lead_comments', 'lead_id', 'lead_comments_lead_id_foreign');
        $this->dropIndex('lead_comments', 'idx_lead_comments_account_deleted');
        $this->addIndex('lead_comments', 'account_id', 'lead_comments_account');

        $this->dropIndex('package_bundles', 'idx_package_bundles_package_deleted');
        $this->addIndex('package_bundles', 'package_id', 'package_bundles_package_id_foreign');

        $this->dropIndex('plan_invoices', 'idx_plan_invoices_account_deleted');
        $this->addIndex('plan_invoices', 'account_id', 'plan_invoices_account_id_index');
        $this->dropIndex('plan_invoices', 'idx_plan_invoices_patient_deleted');
        $this->addIndex('plan_invoices', 'patient_id', 'plan_invoices_patient_id_index');

        $this->dropIndex('resource_has_rota_days', 'idx_rota_days_rota_deleted');
        $this->addIndex('resource_has_rota_days', 'resource_has_rota_id', 'resource_has_rota_days_resource_has_rota_id_foreign');

        $this->dropIndex('sms_logs', 'idx_sms_logs_appointment_deleted');
        $this->addIndex('sms_logs', 'appointment_id', 'sms_logs_appointment_id_foreign');
    }
};
