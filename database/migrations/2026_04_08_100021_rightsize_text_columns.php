<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert oversized TEXT columns to VARCHAR where actual data is small.
     *
     * TEXT columns are stored off-page in InnoDB, causing extra I/O for reads.
     * VARCHAR is stored inline, much faster for small values.
     *
     * Column data verified: all actual values fit within the new limits.
     */
    public function up(): void
    {
        // sms_logs: phone number, mask code, and small metadata
        DB::statement('ALTER TABLE sms_logs MODIFY `to` VARCHAR(50) NULL');
        DB::statement('ALTER TABLE sms_logs MODIFY mask VARCHAR(20) NULL');
        DB::statement('ALTER TABLE sms_logs MODIFY sms_data VARCHAR(100) NULL');

        // appointments.reason: max 291 chars
        DB::statement('ALTER TABLE appointments MODIFY reason VARCHAR(500) NULL');

        // sms_logs.error_msg: max 308 chars
        DB::statement('ALTER TABLE sms_logs MODIFY error_msg VARCHAR(500) NULL');

        // locations.address: max 55 chars
        DB::statement('ALTER TABLE locations MODIFY address VARCHAR(191) NULL');

        // users.address: max 36 chars
        DB::statement('ALTER TABLE users MODIFY address VARCHAR(191) NULL');

        // packages.plan_name: max 103 chars
        DB::statement('ALTER TABLE packages MODIFY plan_name VARCHAR(191) NULL');

        // patient_notes.note: max 13 chars
        DB::statement('ALTER TABLE patient_notes MODIFY note VARCHAR(500) NULL');

        // lead_comments.comment: max 168 chars
        DB::statement('ALTER TABLE lead_comments MODIFY comment VARCHAR(500) NULL');

        // locations.google_map: max 170 chars
        DB::statement('ALTER TABLE locations MODIFY google_map VARCHAR(500) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sms_logs MODIFY `to` TEXT NULL');
        DB::statement('ALTER TABLE sms_logs MODIFY mask TEXT NULL');
        DB::statement('ALTER TABLE sms_logs MODIFY sms_data TEXT NULL');
        DB::statement('ALTER TABLE appointments MODIFY reason TEXT NULL');
        DB::statement('ALTER TABLE sms_logs MODIFY error_msg TEXT NULL');
        DB::statement('ALTER TABLE locations MODIFY address TEXT NULL');
        DB::statement('ALTER TABLE users MODIFY address TEXT NULL');
        DB::statement('ALTER TABLE packages MODIFY plan_name TEXT NULL');
        DB::statement('ALTER TABLE patient_notes MODIFY note TEXT NULL');
        DB::statement('ALTER TABLE lead_comments MODIFY comment TEXT NULL');
        DB::statement('ALTER TABLE locations MODIFY google_map TEXT NULL');
    }
};
