<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes to `activities` to support the activity-log report
 * and per-entity history reads.
 *
 * Before this migration the table had only the `id` primary key. Every query
 * in App\Services\ActivityLogService + App\Http\Controllers\Admin\Reports\
 * ActivitylogsReportController was a full table scan. The report filters
 * always scope by (account_id, created_at range) and optionally by one of
 * patient_id / lead_id / created_by / activity_type / centre_id / service_id.
 *
 * Index choices — all leading with account_id (tenant guard is always
 * applied), then the optional filter column, then the sort column:
 *
 *   idx_activities_acct_created   (account_id, created_at)
 *     → default report query (no optional filter)
 *
 *   idx_activities_acct_patient_id_desc (account_id, patient_id, id)
 *     → patient-card history (ORDER BY id DESC)
 *
 *   idx_activities_acct_lead_id_desc (account_id, lead_id, id)
 *     → lead timeline (ORDER BY id DESC)
 *
 *   idx_activities_acct_createdby_created (account_id, created_by, created_at)
 *     → "filter by user" in report
 *
 *   idx_activities_acct_type_created (account_id, activity_type, created_at)
 *     → "filter by activity type" in report
 *
 * Deliberately NOT added:
 *   - (account_id, updated_at): Activity model has $timestamps=false so
 *     updated_at is only populated by legacy writers; indexing a mostly-null
 *     column wastes space. The OR-on-updated_at branch in buildQuery() should
 *     be removed in a follow-up — tracked separately.
 *   - (account_id, centre_id, ...), (account_id, service_id, ...): lower hit
 *     rate; MariaDB 11 index_merge handles them acceptably for now. Revisit if
 *     EXPLAIN shows scans on those filters.
 *
 * Production safety:
 *   - Uses ALGORITHM=INPLACE, LOCK=NONE so the ALTER is online on InnoDB
 *     (MariaDB 11+). Writes continue during the build.
 *   - Each CREATE INDEX is guarded by an information_schema check so the
 *     migration is idempotent and safe to re-run.
 *
 * Rollback: `php artisan migrate:rollback` drops the five indexes (also
 * online). The ALTERs in down() mirror up() and are non-destructive — no
 * data is touched.
 */
return new class extends Migration
{
    private const INDEXES = [
        'idx_activities_acct_created' => '(`account_id`, `created_at`)',
        'idx_activities_acct_patient_id_desc' => '(`account_id`, `patient_id`, `id`)',
        'idx_activities_acct_lead_id_desc' => '(`account_id`, `lead_id`, `id`)',
        'idx_activities_acct_createdby_created' => '(`account_id`, `created_by`, `created_at`)',
        'idx_activities_acct_type_created' => '(`account_id`, `activity_type`, `created_at`)',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if ($this->indexExists('activities', $name)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `activities` ADD INDEX `{$name}` {$columns}, ALGORITHM=INPLACE, LOCK=NONE"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (! $this->indexExists('activities', $name)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `activities` DROP INDEX `{$name}`, ALGORITHM=INPLACE, LOCK=NONE"
            );
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
