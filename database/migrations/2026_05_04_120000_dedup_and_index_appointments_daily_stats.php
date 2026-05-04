<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `appointments_daily_stats` is keyed logically on
 * (appointment_id, scheduled_date) — the cron upserts a snapshot row
 * per consultation per day. The schema never enforced uniqueness, so
 * concurrent / re-run cron invocations have leaked duplicate rows
 * (881 found in dev as of 2026-05-04). Duplicates inflate the counts
 * read by the legacy Blade arrival reports and the API dashboard
 * arrival endpoints.
 *
 * Two-step migration:
 *   1. Dedup: keep the row with the highest id per (appointment_id,
 *      scheduled_date) — represents the most recent cron write.
 *   2. Add UNIQUE INDEX so the cron's upsert() actually dedupes at
 *      the DB level and concurrent runs can't reintroduce duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Dedup. Pick the canonical (highest id) row per pair, then
        //    delete every other row matching that pair.
        DB::statement(<<<'SQL'
            DELETE ads FROM appointments_daily_stats AS ads
            INNER JOIN (
                SELECT appointment_id, scheduled_date, MAX(id) AS keep_id
                FROM appointments_daily_stats
                GROUP BY appointment_id, scheduled_date
                HAVING COUNT(*) > 1
            ) AS dups
              ON dups.appointment_id = ads.appointment_id
             AND dups.scheduled_date = ads.scheduled_date
             AND dups.keep_id        <> ads.id
        SQL);

        // 2. Unique index on the dedup key. Idempotent against re-run.
        $exists = DB::selectOne(
            "SHOW INDEX FROM appointments_daily_stats WHERE Key_name = 'uniq_ads_appointment_date'"
        );
        if (! $exists) {
            Schema::table('appointments_daily_stats', function ($table): void {
                $table->unique(['appointment_id', 'scheduled_date'], 'uniq_ads_appointment_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('appointments_daily_stats', function ($table): void {
            $table->dropUnique('uniq_ads_appointment_date');
        });
        // Dedup is intentionally not reversed — the duplicates were
        // junk and shouldn't be reintroduced.
    }
};
