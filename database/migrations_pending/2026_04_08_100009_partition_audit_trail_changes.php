<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasPartitioning(string $table): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL LIMIT 1",
            [$table]
        );
        return ! empty($rows);
    }

    public function up(): void
    {
        if (! Schema::hasTable('audit_trail_changes')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE audit_trail_changes MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        } catch (\Throwable $e) {
            \Log::warning('Modify created_at skipped: ' . $e->getMessage());
        }

        // Only rebuild PK if composite not already there
        try {
            $pk = DB::select("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) AS cols FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = 'audit_trail_changes' AND constraint_name = 'PRIMARY'");
            $cols = $pk[0]->cols ?? '';
            if ($cols !== 'id,created_at') {
                DB::statement('ALTER TABLE audit_trail_changes DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');
            }
        } catch (\Throwable $e) {
            \Log::warning('Rebuild PK skipped: ' . $e->getMessage());
        }

        if ($this->hasPartitioning('audit_trail_changes')) {
            return;
        }

        try {
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
        } catch (\Throwable $e) {
            \Log::warning('Partitioning skipped (likely insufficient privileges): ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_trail_changes')) {
            return;
        }
        try {
            if ($this->hasPartitioning('audit_trail_changes')) {
                DB::statement('ALTER TABLE audit_trail_changes REMOVE PARTITIONING');
            }
        } catch (\Throwable $e) {
            \Log::warning('Remove partitioning skipped: ' . $e->getMessage());
        }
        try {
            DB::statement('ALTER TABLE audit_trail_changes DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        } catch (\Throwable $e) {
            \Log::warning('Restore PK skipped: ' . $e->getMessage());
        }
        try {
            DB::statement('ALTER TABLE audit_trail_changes MODIFY created_at TIMESTAMP NULL DEFAULT NULL');
        } catch (\Throwable $e) {
            \Log::warning('Revert created_at skipped: ' . $e->getMessage());
        }
    }
};
