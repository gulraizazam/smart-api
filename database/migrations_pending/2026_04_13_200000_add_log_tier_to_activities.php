<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `log_tier` to `activities` plus a (log_tier, created_at) index.
 *
 * The column is nullable — rows written before this migration land with
 * NULL tier. A backfill command (`php artisan activities:backfill-tiers`)
 * classifies existing rows by mapping `activity_type` → tier. The
 * archive/prune command refuses to touch rows whose tier is NULL, so
 * NULL is the safe default for any new activity_type that hasn't been
 * added to App\Enums\ActivityLogTier::classify() yet.
 *
 * The index supports the prune scan:
 *   WHERE log_tier = ? AND created_at < ? ORDER BY id ASC LIMIT ?
 *
 * Production safety: ALGORITHM=INPLACE, LOCK=NONE for both column add
 * and index build. Reversible — down() drops both in reverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        if (! Schema::hasColumn('activities', 'log_tier')) {
            DB::statement(
                'ALTER TABLE `activities`
                 ADD COLUMN `log_tier` VARCHAR(20) NULL DEFAULT NULL
                 AFTER `activity_type`,
                 ALGORITHM=INPLACE, LOCK=NONE'
            );
        }

        if (! $this->indexExists('activities', 'idx_activities_tier_created')) {
            DB::statement(
                'ALTER TABLE `activities`
                 ADD INDEX `idx_activities_tier_created` (`log_tier`, `created_at`),
                 ALGORITHM=INPLACE, LOCK=NONE'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        if ($this->indexExists('activities', 'idx_activities_tier_created')) {
            DB::statement(
                'ALTER TABLE `activities`
                 DROP INDEX `idx_activities_tier_created`,
                 ALGORITHM=INPLACE, LOCK=NONE'
            );
        }

        if (Schema::hasColumn('activities', 'log_tier')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->dropColumn('log_tier');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName],
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
