<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add FK constraints to package_advances, invoices, invoice_details.
     *
     * Orphan cleanup:
     *   package_advances.payment_mode_id: 5,545 rows with value 0 → SET NULL
     *   package_advances.appointment_id: 21 orphans → SET NULL
     *   package_advances.invoice_id: 10 orphans → SET NULL
     *   invoice_details.package_service_id: 108 orphans → SET NULL
     */
    public function up(): void
    {
        // ─── Step 1: Clean orphans ───
        DB::statement('UPDATE package_advances SET payment_mode_id = NULL WHERE payment_mode_id = 0');
        DB::statement('UPDATE package_advances SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        DB::statement('UPDATE package_advances SET invoice_id = NULL WHERE invoice_id IS NOT NULL AND invoice_id NOT IN (SELECT id FROM invoices)');
        DB::statement('UPDATE invoice_details SET package_service_id = NULL WHERE package_service_id IS NOT NULL AND package_service_id NOT IN (SELECT id FROM package_services)');

        // ─── Step 2: Add FK constraints ───

        // package_advances (currently has 1 FK: package_id → adding 8 more)
        Schema::table('package_advances', function (Blueprint $table) {
            $table->foreign('patient_id', 'fk_pa_patient')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('account_id', 'fk_pa_account')
                ->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('appointment_id', 'fk_pa_appointment')
                ->references('id')->on('appointments')->onDelete('set null');
            $table->foreign('location_id', 'fk_pa_location')
                ->references('id')->on('locations')->onDelete('set null');
            $table->foreign('invoice_id', 'fk_pa_invoice')
                ->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('payment_mode_id', 'fk_pa_payment_mode')
                ->references('id')->on('payment_modes')->onDelete('set null');
            $table->foreign('appointment_type_id', 'fk_pa_appt_type')
                ->references('id')->on('appointment_types')->onDelete('set null');
            $table->foreign('created_by', 'fk_pa_creator')
                ->references('id')->on('users')->onDelete('set null');
        });

        // invoices (currently has 3 FKs → adding 5 more)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('account_id', 'fk_invoices_account')
                ->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('invoice_status_id', 'fk_invoices_status')
                ->references('id')->on('invoice_statuses')->onDelete('set null');
            $table->foreign('created_by', 'fk_invoices_creator')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('location_id', 'fk_invoices_location')
                ->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('doctor_id', 'fk_invoices_doctor')
                ->references('id')->on('users')->onDelete('restrict');
        });

        // invoice_details (currently has 2 FKs → adding 2 more)
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->foreign('package_id', 'fk_inv_details_package')
                ->references('id')->on('packages')->onDelete('set null');
            $table->foreign('package_service_id', 'fk_inv_details_pkg_svc')
                ->references('id')->on('package_services')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('package_advances', function (Blueprint $table) {
            $table->dropForeign('fk_pa_patient');
            $table->dropForeign('fk_pa_account');
            $table->dropForeign('fk_pa_appointment');
            $table->dropForeign('fk_pa_location');
            $table->dropForeign('fk_pa_invoice');
            $table->dropForeign('fk_pa_payment_mode');
            $table->dropForeign('fk_pa_appt_type');
            $table->dropForeign('fk_pa_creator');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('fk_invoices_account');
            $table->dropForeign('fk_invoices_status');
            $table->dropForeign('fk_invoices_creator');
            $table->dropForeign('fk_invoices_location');
            $table->dropForeign('fk_invoices_doctor');
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign('fk_inv_details_package');
            $table->dropForeign('fk_inv_details_pkg_svc');
        });
    }
};
