<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add FK constraints to packages, leads, invoice_details, package_bundles.
     *
     * Orphan cleanup:
     *   packages.appointment_id: 1 orphan → SET NULL
     *   leads.location_id: 69 orphans → SET NULL
     *   All other columns: 0 orphans
     *
     * Type fix: leads.location_id INT(11) → INT UNSIGNED (match locations.id)
     */
    public function up(): void
    {
        // ─── Step 1: Clean orphans ───
        DB::statement('UPDATE packages SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        DB::statement('UPDATE leads SET location_id = NULL WHERE location_id IS NOT NULL AND location_id NOT IN (SELECT id FROM locations)');

        // ─── Step 2: Fix type mismatch ───
        DB::statement('ALTER TABLE leads MODIFY location_id INT(10) UNSIGNED NULL');

        // ─── Step 3: Add FK constraints ───

        // packages (0 FKs currently → adding 3)
        Schema::table('packages', function (Blueprint $table) {
            $table->foreign('patient_id', 'fk_packages_patient')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->foreign('location_id', 'fk_packages_location')
                ->references('id')->on('locations')
                ->onDelete('set null');
            $table->foreign('appointment_id', 'fk_packages_appointment')
                ->references('id')->on('appointments')
                ->onDelete('set null');
        });

        // leads (has patient_id FK → adding 5 more)
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('service_id', 'fk_leads_service')
                ->references('id')->on('services')
                ->onDelete('set null');
            $table->foreign('location_id', 'fk_leads_location')
                ->references('id')->on('locations')
                ->onDelete('set null');
            $table->foreign('lead_source_id', 'fk_leads_source')
                ->references('id')->on('lead_sources')
                ->onDelete('set null');
            $table->foreign('lead_status_id', 'fk_leads_status')
                ->references('id')->on('lead_statuses')
                ->onDelete('set null');
            $table->foreign('created_by', 'fk_leads_creator')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // invoice_details.service_id (non-nullable → CASCADE)
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->foreign('service_id', 'fk_invoice_details_service')
                ->references('id')->on('services')
                ->onDelete('cascade');
        });

        // package_bundles.bundle_id (nullable → SET NULL)
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->foreign('bundle_id', 'fk_pkg_bundles_bundle')
                ->references('id')->on('bundles')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign('fk_packages_patient');
            $table->dropForeign('fk_packages_location');
            $table->dropForeign('fk_packages_appointment');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign('fk_leads_service');
            $table->dropForeign('fk_leads_location');
            $table->dropForeign('fk_leads_source');
            $table->dropForeign('fk_leads_status');
            $table->dropForeign('fk_leads_creator');
        });

        Schema::table('invoice_details', fn (Blueprint $table) => $table->dropForeign('fk_invoice_details_service'));
        Schema::table('package_bundles', fn (Blueprint $table) => $table->dropForeign('fk_pkg_bundles_bundle'));

        // Revert type
        DB::statement('ALTER TABLE leads MODIFY location_id INT(11) NULL');
    }
};
