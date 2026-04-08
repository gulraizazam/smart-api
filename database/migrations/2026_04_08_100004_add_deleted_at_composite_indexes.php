<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add deleted_at to composite indexes on high-row-count SoftDelete tables.
     *
     * For tables WITHOUT FK constraints: REPLACE single-column index with (column, deleted_at)
     * composite. The composite still satisfies queries on just `column` (leftmost prefix rule).
     *
     * For tables WITH FK constraints (appointments): ADD composite alongside existing FK index.
     * The optimizer will prefer the composite for SoftDelete queries.
     */
    public function up(): void
    {
        // ─── appointments (391K rows) — HAS FK constraints, ADD composites alongside ───
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['account_id', 'deleted_at'], 'idx_appointments_account_deleted');
            $table->index(['patient_id', 'deleted_at'], 'idx_appointments_patient_deleted');
            $table->index(['location_id', 'deleted_at'], 'idx_appointments_location_deleted');
        });

        // ─── invoices (245K rows) — no FK constraints ───
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_account_id_foreign');
            $table->index(['account_id', 'deleted_at'], 'idx_invoices_account_deleted');

            $table->dropIndex('invoices_patient_id_foreign');
            $table->index(['patient_id', 'deleted_at'], 'idx_invoices_patient_deleted');
        });

        // ─── invoice_details (245K rows) — no FK constraints ───
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropIndex('invoice_details_invoice_id_foreign');
            $table->index(['invoice_id', 'deleted_at'], 'idx_invoice_details_invoice_deleted');
        });

        // ─── leads (512K rows) — no FK constraints (only referenced BY appointments) ───
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_account');
            $table->index(['account_id', 'deleted_at'], 'idx_leads_account_deleted');

            $table->dropIndex('leads_patient_id_foreign');
            $table->index(['patient_id', 'deleted_at'], 'idx_leads_patient_deleted');
        });

        // ─── lead_comments (272K rows) — no FK constraints ───
        Schema::table('lead_comments', function (Blueprint $table) {
            $table->dropIndex('lead_comments_lead_id_foreign');
            $table->index(['lead_id', 'deleted_at'], 'idx_lead_comments_lead_deleted');

            $table->dropIndex('lead_comments_account');
            $table->index(['account_id', 'deleted_at'], 'idx_lead_comments_account_deleted');
        });

        // ─── package_bundles (139K rows) — no FK constraints ───
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropIndex('package_bundles_package_id_foreign');
            $table->index(['package_id', 'deleted_at'], 'idx_package_bundles_package_deleted');
        });

        // ─── plan_invoices (52K rows) — no FK constraints ───
        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->dropIndex('plan_invoices_account_id_index');
            $table->index(['account_id', 'deleted_at'], 'idx_plan_invoices_account_deleted');

            $table->dropIndex('plan_invoices_patient_id_index');
            $table->index(['patient_id', 'deleted_at'], 'idx_plan_invoices_patient_deleted');
        });

        // ─── resource_has_rota_days (233K rows) — no FK constraints of its own ───
        // (referenced BY appointments but that FK is on appointments table, not here)
        Schema::table('resource_has_rota_days', function (Blueprint $table) {
            $table->dropIndex('resource_has_rota_days_resource_has_rota_id_foreign');
            $table->index(['resource_has_rota_id', 'deleted_at'], 'idx_rota_days_rota_deleted');
        });

        // ─── sms_logs (871K rows) — no FK constraints ───
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex('sms_logs_appointment_id_foreign');
            $table->index(['appointment_id', 'deleted_at'], 'idx_sms_logs_appointment_deleted');
        });
    }

    public function down(): void
    {
        // ─── appointments: just drop the added composites ───
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_account_deleted');
            $table->dropIndex('idx_appointments_patient_deleted');
            $table->dropIndex('idx_appointments_location_deleted');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_account_deleted');
            $table->index('account_id', 'invoices_account_id_foreign');
            $table->dropIndex('idx_invoices_patient_deleted');
            $table->index('patient_id', 'invoices_patient_id_foreign');
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_details_invoice_deleted');
            $table->index('invoice_id', 'invoice_details_invoice_id_foreign');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_account_deleted');
            $table->index('account_id', 'leads_account');
            $table->dropIndex('idx_leads_patient_deleted');
            $table->index('patient_id', 'leads_patient_id_foreign');
        });

        Schema::table('lead_comments', function (Blueprint $table) {
            $table->dropIndex('idx_lead_comments_lead_deleted');
            $table->index('lead_id', 'lead_comments_lead_id_foreign');
            $table->dropIndex('idx_lead_comments_account_deleted');
            $table->index('account_id', 'lead_comments_account');
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropIndex('idx_package_bundles_package_deleted');
            $table->index('package_id', 'package_bundles_package_id_foreign');
        });

        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_plan_invoices_account_deleted');
            $table->index('account_id', 'plan_invoices_account_id_index');
            $table->dropIndex('idx_plan_invoices_patient_deleted');
            $table->index('patient_id', 'plan_invoices_patient_id_index');
        });

        Schema::table('resource_has_rota_days', function (Blueprint $table) {
            $table->dropIndex('idx_rota_days_rota_deleted');
            $table->index('resource_has_rota_id', 'resource_has_rota_days_resource_has_rota_id_foreign');
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex('idx_sms_logs_appointment_deleted');
            $table->index('appointment_id', 'sms_logs_appointment_id_foreign');
        });
    }
};
