<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces the business rule that every treatment has at most one feedback
 * record. Prior to this migration, uniqueness was only guarded in
 * FeedbackService::ensureNoDuplicateFeedback(); nothing stopped a race
 * condition or a direct DB insert from creating duplicates.
 *
 * IMPORTANT — destructive step:
 * The up() method purges duplicate rows before adding the unique index.
 * For each appointment_id with >1 feedback row, it keeps the row with the
 * HIGHEST id (latest inserted) and hard-deletes the rest. The feedback
 * table has no soft-deletes, so this is irreversible. Back up the table
 * before running in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Purge duplicates, keep the most recent row per appointment_id.
        //    Exclude NULL appointment_ids — those are pre-linkage rows and
        //    don't participate in the unique constraint.
        DB::statement('
            DELETE f1
            FROM feedback f1
            INNER JOIN feedback f2
                ON f1.appointment_id = f2.appointment_id
                AND f1.id < f2.id
            WHERE f1.appointment_id IS NOT NULL
        ');

        // 2. Add the unique index. MariaDB treats multiple NULLs as distinct
        //    for unique indexes, so appointments with no feedback yet (or
        //    orphaned pre-linkage rows) remain unaffected.
        Schema::table('feedback', function (Blueprint $table): void {
            $table->unique('appointment_id', 'feedback_appointment_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropUnique('feedback_appointment_id_unique');
        });
    }
};
