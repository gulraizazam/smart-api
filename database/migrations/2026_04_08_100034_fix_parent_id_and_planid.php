<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix structural anomalies:
     * 1. appointment_statuses.parent_id: varchar(100) → int unsigned (self-ref FK)
     * 2. lead_statuses.parent_id: varchar(100) → int unsigned (self-ref FK)
     * 3. activities.planId: duplicate of plan_id — backfill plan_id, drop planId
     */
    public function up(): void
    {
        // --- parent_id fixes ---

        // Convert sentinel '0' to NULL (0 is not a valid parent_id)
        DB::table('appointment_statuses')
            ->where('parent_id', '0')
            ->update(['parent_id' => null]);

        DB::table('lead_statuses')
            ->where('parent_id', '0')
            ->update(['parent_id' => null]);

        // Convert varchar → int unsigned
        DB::statement('ALTER TABLE appointment_statuses MODIFY parent_id INT(10) UNSIGNED DEFAULT NULL');
        DB::statement('ALTER TABLE lead_statuses MODIFY parent_id INT(10) UNSIGNED DEFAULT NULL');

        // --- activities.planId dedup ---

        // Backfill plan_id from planId where plan_id is missing
        DB::statement('UPDATE activities SET plan_id = planId WHERE (plan_id IS NULL OR plan_id = 0) AND planId IS NOT NULL AND planId != 0');

        // Drop the camelCase duplicate
        DB::statement('ALTER TABLE activities DROP COLUMN planId');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE appointment_statuses MODIFY parent_id VARCHAR(100) DEFAULT NULL');
        DB::statement('ALTER TABLE lead_statuses MODIFY parent_id VARCHAR(100) DEFAULT NULL');
        DB::statement('ALTER TABLE activities ADD COLUMN planId INT(10) UNSIGNED DEFAULT NULL AFTER account_id');
        DB::statement('UPDATE activities SET planId = plan_id WHERE plan_id IS NOT NULL AND plan_id != 0');
    }
};
