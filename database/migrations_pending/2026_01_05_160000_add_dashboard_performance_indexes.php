<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes to improve dashboard overdue treatments and unattended payments queries
     */
    public function up()
    {
        // Index for appointments - used in overdue treatments query
        Schema::table('appointments', function (Blueprint $table) {
            // Composite index for filtering treatment appointments by type, status, location
            $table->index(['appointment_type_id', 'base_appointment_status_id', 'location_id', 'patient_id', 'scheduled_date'], 'idx_apt_treatment_lookup');
        });

        // Index for package_advances - used in balance calculations
        Schema::table('package_advances', function (Blueprint $table) {
            // Composite index for balance calculation queries
            $table->index(['patient_id', 'cash_flow', 'is_cancel', 'is_tax', 'is_adjustment', 'is_refund'], 'idx_pa_balance_calc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_apt_treatment_lookup');
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->dropIndex('idx_pa_balance_calc');
        });
    }
};
