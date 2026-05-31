<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeModify(string $table, string $column, string $sql): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$sql}");
        } catch (\Throwable $e) {
            \Log::warning("Skipping MODIFY {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Log::warning('Statement failed: ' . $e->getMessage());
        }
    }

    /**
     * Fix structural anomalies:
     * 1. appointment_statuses.parent_id: varchar(100) -> int unsigned (self-ref FK)
     * 2. lead_statuses.parent_id: varchar(100) -> int unsigned (self-ref FK)
     * 3. activities.planId: duplicate of plan_id - backfill plan_id, drop planId
     */
    public function up(): void
    {
        if (Schema::hasTable('appointment_statuses') && Schema::hasColumn('appointment_statuses', 'parent_id')) {
            try {
                DB::table('appointment_statuses')->where('parent_id', '0')->update(['parent_id' => null]);
            } catch (\Throwable $e) {
                \Log::warning('Backfill appointment_statuses.parent_id: ' . $e->getMessage());
            }
        }
        if (Schema::hasTable('lead_statuses') && Schema::hasColumn('lead_statuses', 'parent_id')) {
            try {
                DB::table('lead_statuses')->where('parent_id', '0')->update(['parent_id' => null]);
            } catch (\Throwable $e) {
                \Log::warning('Backfill lead_statuses.parent_id: ' . $e->getMessage());
            }
        }

        $this->safeModify('appointment_statuses', 'parent_id', 'INT(10) UNSIGNED DEFAULT NULL');
        $this->safeModify('lead_statuses', 'parent_id', 'INT(10) UNSIGNED DEFAULT NULL');

        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'planId') && Schema::hasColumn('activities', 'plan_id')) {
            $this->safeStatement('UPDATE activities SET plan_id = planId WHERE (plan_id IS NULL OR plan_id = 0) AND planId IS NOT NULL AND planId != 0');
        }

        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'planId')) {
            try {
                DB::statement('ALTER TABLE activities DROP COLUMN planId');
            } catch (\Throwable $e) {
                \Log::warning('Skipping DROP COLUMN activities.planId: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $this->safeModify('appointment_statuses', 'parent_id', 'VARCHAR(100) DEFAULT NULL');
        $this->safeModify('lead_statuses', 'parent_id', 'VARCHAR(100) DEFAULT NULL');

        if (Schema::hasTable('activities') && ! Schema::hasColumn('activities', 'planId')) {
            try {
                DB::statement('ALTER TABLE activities ADD COLUMN planId INT(10) UNSIGNED DEFAULT NULL AFTER account_id');
            } catch (\Throwable $e) {
                \Log::warning('Skipping ADD COLUMN activities.planId: ' . $e->getMessage());
            }
        }
        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'planId') && Schema::hasColumn('activities', 'plan_id')) {
            $this->safeStatement('UPDATE activities SET planId = plan_id WHERE plan_id IS NOT NULL AND plan_id != 0');
        }
    }
};
