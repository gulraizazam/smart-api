<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert TEXT/LONGTEXT columns storing JSON data to proper JSON column type.
     *
     * Benefits: DB-level JSON validation, ability to create virtual columns
     * and indexes on JSON paths, better query support with JSON_EXTRACT.
     *
     * All data verified: 0 invalid JSON values across all columns.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cashflow_audit_logs MODIFY old_values JSON NULL');
        DB::statement('ALTER TABLE cashflow_audit_logs MODIFY new_values JSON NULL');
        DB::statement('ALTER TABLE cashflow_notifications MODIFY data JSON NULL');
        DB::statement('ALTER TABLE custom_form_feedback_details MODIFY content JSON NULL');
        DB::statement('ALTER TABLE custom_form_fields MODIFY content JSON NULL');
        DB::statement('ALTER TABLE period_locks MODIFY balance_snapshot JSON NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cashflow_audit_logs MODIFY old_values LONGTEXT NULL');
        DB::statement('ALTER TABLE cashflow_audit_logs MODIFY new_values LONGTEXT NULL');
        DB::statement('ALTER TABLE cashflow_notifications MODIFY data LONGTEXT NULL');
        DB::statement('ALTER TABLE custom_form_feedback_details MODIFY content TEXT NULL');
        DB::statement('ALTER TABLE custom_form_fields MODIFY content TEXT NULL');
        DB::statement('ALTER TABLE period_locks MODIFY balance_snapshot LONGTEXT NULL');
    }
};
