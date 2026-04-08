<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Partition audit_trail_changes by quarter using RANGE on created_at.
     *
     * Benefits:
     *   - Partition pruning on date-range queries (reporting, archival)
     *   - Instant DROP PARTITION instead of batched DELETE for old data
     *   - Smaller per-partition indexes → faster lookups
     *
     * Current state: 7.5M rows, ~590 MB (data + index)
     *
     * Steps:
     *   1. Make created_at NOT NULL (no NULLs exist, verified)
     *   2. Rebuild PK as (id, created_at) — MariaDB requires partition key in PK
     *   3. Apply RANGE(TO_DAYS(created_at)) partitioning by quarter
     *
     * Eloquent compatibility: Eloquent uses WHERE id = ? for find/update/delete,
     * which still works with composite PK. Bulk inserts via ::insert() unaffected.
     */
    public function up(): void
    {
        // Step 1: Make created_at NOT NULL (required for PK inclusion)
        DB::statement('ALTER TABLE audit_trail_changes MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

        // Step 2: Change PK to include created_at (required for partitioning)
        DB::statement('ALTER TABLE audit_trail_changes DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');

        // Step 3: Apply RANGE partitioning by quarter using UNIX_TIMESTAMP
        // (TIMESTAMP columns require UNIX_TIMESTAMP, not TO_DAYS)
        DB::statement("
            ALTER TABLE audit_trail_changes
            PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
                PARTITION p2024_q3 VALUES LESS THAN (1727722800),
                PARTITION p2024_q4 VALUES LESS THAN (1735671600),
                PARTITION p2025_q1 VALUES LESS THAN (1743447600),
                PARTITION p2025_q2 VALUES LESS THAN (1751310000),
                PARTITION p2025_q3 VALUES LESS THAN (1759258800),
                PARTITION p2025_q4 VALUES LESS THAN (1767207600),
                PARTITION p2026_q1 VALUES LESS THAN (1774983600),
                PARTITION p2026_q2 VALUES LESS THAN (1782846000),
                PARTITION p2026_q3 VALUES LESS THAN (1790794800),
                PARTITION p2026_q4 VALUES LESS THAN (1798743600),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");
    }

    public function down(): void
    {
        // Remove partitioning and restore original PK
        DB::statement('ALTER TABLE audit_trail_changes REMOVE PARTITIONING');
        DB::statement('ALTER TABLE audit_trail_changes DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        DB::statement('ALTER TABLE audit_trail_changes MODIFY created_at TIMESTAMP NULL DEFAULT NULL');
    }
};
