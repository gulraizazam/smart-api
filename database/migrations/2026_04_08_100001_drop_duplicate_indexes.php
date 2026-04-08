<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop duplicate indexes across 6 tables.
     *
     * Each pair has two indexes on the same column(s) — we keep the *_foreign
     * named ones (Laravel convention) and drop the manually-added idx_* duplicates.
     * For package_advances.package_id (triple duplicate), we keep the composite
     * idx_pa_pkg_deleted (package_id, deleted_at) and drop all three single-column ones.
     *
     * Verified: NONE of these tables have actual FK constraints — only PRIMARY KEY
     * (and one UNIQUE on users). Dropping any index is safe.
     */
    public function up(): void
    {
        // ─── package_advances: 11 duplicate indexes → drop 12 indexes ───
        Schema::table('package_advances', function (Blueprint $table) {
            // Duplicate pairs: keep *_foreign, drop idx_*
            $table->dropIndex('idx_patient_id');
            $table->dropIndex('idx_payment_mode_id');
            $table->dropIndex('idx_account_id');
            $table->dropIndex('idx_created_by');
            $table->dropIndex('idx_updated_by');
            $table->dropIndex('idx_invoice_id');
            $table->dropIndex('idx_location_id');
            $table->dropIndex('idx_appointment_type_id');
            $table->dropIndex('idx_appointment_id');

            // Triple duplicate on package_id: keep idx_pa_pkg_deleted (composite),
            // drop all three single-column indexes
            $table->dropIndex('package_advances_package_id_foreign');
            $table->dropIndex('idx_package_advances_package_id');
            $table->dropIndex('idx_package_id');
        });

        // ─── sms_logs: 5 duplicate indexes ───
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex('idx_lead_id');
            $table->dropIndex('idx_appointment_id');
            $table->dropIndex('idx_created_by');
            $table->dropIndex('idx_invoice_id');
            $table->dropIndex('idx_package_id');
        });

        // ─── audit_trail_changes: 1 duplicate index ───
        Schema::table('audit_trail_changes', function (Blueprint $table) {
            $table->dropIndex('idx_audit_trail_id');
        });

        // ─── package_bundles: 1 duplicate index ───
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropIndex('idx_package_bundles_package_id');
        });

        // ─── package_services: 1 duplicate index ───
        Schema::table('package_services', function (Blueprint $table) {
            $table->dropIndex('idx_package_services_package_id');
        });

        // ─── users: 1 duplicate index ───
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_user_type');
        });
    }

    /**
     * Re-create the dropped indexes on rollback.
     */
    public function down(): void
    {
        Schema::table('package_advances', function (Blueprint $table) {
            $table->index('patient_id', 'idx_patient_id');
            $table->index('payment_mode_id', 'idx_payment_mode_id');
            $table->index('account_id', 'idx_account_id');
            $table->index('created_by', 'idx_created_by');
            $table->index('updated_by', 'idx_updated_by');
            $table->index('invoice_id', 'idx_invoice_id');
            $table->index('location_id', 'idx_location_id');
            $table->index('appointment_type_id', 'idx_appointment_type_id');
            $table->index('appointment_id', 'idx_appointment_id');
            $table->index('package_id', 'package_advances_package_id_foreign');
            $table->index('package_id', 'idx_package_advances_package_id');
            $table->index('package_id', 'idx_package_id');
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->index('lead_id', 'idx_lead_id');
            $table->index('appointment_id', 'idx_appointment_id');
            $table->index('created_by', 'idx_created_by');
            $table->index('invoice_id', 'idx_invoice_id');
            $table->index('package_id', 'idx_package_id');
        });

        Schema::table('audit_trail_changes', function (Blueprint $table) {
            $table->index('audit_trail_id', 'idx_audit_trail_id');
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->index('package_id', 'idx_package_bundles_package_id');
        });

        Schema::table('package_services', function (Blueprint $table) {
            $table->index('package_id', 'idx_package_services_package_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('user_type_id', 'idx_user_type');
        });
    }
};
