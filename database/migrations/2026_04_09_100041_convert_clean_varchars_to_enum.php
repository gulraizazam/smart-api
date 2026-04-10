<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert clean fixed-set varchar columns to native MySQL ENUM.
     *
     * Two columns qualify (verified via distinct value scan):
     *
     * 1. appointments.consultancy_type — 4 values:
     *    in_person (208,380), treatment (185,760), virtual (501), NULL (1)
     *    Currently varchar(20).
     *
     * 2. sms_logs.log_type — 4 values:
     *    sms (200,897), 2nd_sms (16,207), 3rd_sms (51), inventory (4,837)
     *    Currently varchar(100).
     *
     * NOT included (have messy/inconsistent values requiring cleanup first):
     * - activities.action — 24 distinct, mixed case ("Appointment Converted" vs "booked")
     * - activities.activity_type — 27 distinct, mixed conventions
     * - appointments.coming_from — 99.98% NULL, 1 active value ("plan")
     */
    public function up(): void
    {
        // appointments.consultancy_type
        DB::statement("ALTER TABLE appointments
            MODIFY consultancy_type ENUM('in_person', 'treatment', 'virtual') DEFAULT NULL");

        // sms_logs.log_type (preserves DEFAULT 'sms')
        DB::statement("ALTER TABLE sms_logs
            MODIFY log_type ENUM('sms', '2nd_sms', '3rd_sms', 'inventory') NOT NULL DEFAULT 'sms'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE appointments
            MODIFY consultancy_type VARCHAR(20) DEFAULT NULL");

        DB::statement("ALTER TABLE sms_logs
            MODIFY log_type VARCHAR(100) NOT NULL DEFAULT 'sms'");
    }
};
