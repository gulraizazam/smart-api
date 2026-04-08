<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add foreign key constraints to core business tables.
     *
     * Step 1: Clean up orphaned records (verified counts before migration).
     * Step 2: Add FK constraints with appropriate ON DELETE behavior:
     *   - Nullable FKs → SET NULL (parent deleted = child keeps existing, FK nulled)
     *   - Non-nullable FKs → CASCADE (parent deleted = children removed)
     *
     * Since all tables use SoftDeletes, FK ON DELETE only fires on hard deletes
     * (safety net against bugs), not on normal soft-delete operations.
     *
     * Orphan cleanup summary:
     *   invoices.appointment_id: 6 → SET NULL
     *   invoice_details.invoice_id: 2 → hard-delete (already soft-deleted orphans)
     *   package_bundles.package_id: 4 → SET NULL
     *   package_bundles.bundle_id: 330 → SET NULL
     *   package_services.package_id: 4 → SET NULL
     *   lead_comments.lead_id: 193 → hard-delete (already soft-deleted orphans)
     *   sms_logs.appointment_id: 11 → SET NULL
     */
    public function up(): void
    {
        // ─── Step 1: Clean orphaned records ───

        // Nullable columns: set orphan FK to NULL
        DB::statement('UPDATE invoices SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        DB::statement('UPDATE package_bundles SET package_id = NULL WHERE package_id IS NOT NULL AND package_id NOT IN (SELECT id FROM packages)');
        DB::statement('UPDATE package_bundles SET bundle_id = NULL WHERE bundle_id IS NOT NULL AND bundle_id NOT IN (SELECT id FROM bundles)');
        DB::statement('UPDATE package_services SET package_id = NULL WHERE package_id IS NOT NULL AND package_id NOT IN (SELECT id FROM packages)');
        DB::statement('UPDATE sms_logs SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');

        // Non-nullable columns: hard-delete ALL orphan rows (including already soft-deleted)
        // FK constraints check every row regardless of deleted_at, so soft-deleting isn't enough
        DB::statement("DELETE FROM invoice_details WHERE invoice_id NOT IN (SELECT id FROM invoices)");
        DB::statement("DELETE FROM lead_comments WHERE lead_id NOT IN (SELECT id FROM leads)");

        // ─── Step 2: Add FK constraints ───

        // invoice_details → invoices (non-nullable, CASCADE)
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->foreign('invoice_id', 'fk_invoice_details_invoice')
                ->references('id')->on('invoices')
                ->onDelete('cascade');
        });

        // lead_comments → leads (non-nullable, CASCADE)
        Schema::table('lead_comments', function (Blueprint $table) {
            $table->foreign('lead_id', 'fk_lead_comments_lead')
                ->references('id')->on('leads')
                ->onDelete('cascade');
        });

        // invoices → appointments (nullable, SET NULL)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('appointment_id', 'fk_invoices_appointment')
                ->references('id')->on('appointments')
                ->onDelete('set null');
        });

        // invoices → users (patient) (nullable, SET NULL)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('patient_id', 'fk_invoices_patient')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // package_advances → packages (nullable via composite index, SET NULL)
        Schema::table('package_advances', function (Blueprint $table) {
            $table->foreign('package_id', 'fk_pkg_advances_package')
                ->references('id')->on('packages')
                ->onDelete('set null');
        });

        // package_bundles → packages (nullable, SET NULL)
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->foreign('package_id', 'fk_pkg_bundles_package')
                ->references('id')->on('packages')
                ->onDelete('set null');
        });

        // package_services → packages (nullable, SET NULL)
        Schema::table('package_services', function (Blueprint $table) {
            $table->foreign('package_id', 'fk_pkg_services_package')
                ->references('id')->on('packages')
                ->onDelete('set null');
        });

        // leads → users (patient) (nullable, SET NULL)
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('patient_id', 'fk_leads_patient')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // sms_logs → appointments (nullable, SET NULL)
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->foreign('appointment_id', 'fk_sms_logs_appointment')
                ->references('id')->on('appointments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', fn (Blueprint $table) => $table->dropForeign('fk_invoice_details_invoice'));
        Schema::table('lead_comments', fn (Blueprint $table) => $table->dropForeign('fk_lead_comments_lead'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('fk_invoices_appointment'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('fk_invoices_patient'));
        Schema::table('package_advances', fn (Blueprint $table) => $table->dropForeign('fk_pkg_advances_package'));
        Schema::table('package_bundles', fn (Blueprint $table) => $table->dropForeign('fk_pkg_bundles_package'));
        Schema::table('package_services', fn (Blueprint $table) => $table->dropForeign('fk_pkg_services_package'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropForeign('fk_leads_patient'));
        Schema::table('sms_logs', fn (Blueprint $table) => $table->dropForeign('fk_sms_logs_appointment'));
    }
};
