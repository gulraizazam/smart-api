<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `activities.account_id` nullable.
 *
 * The earlier `add_lead_columns_to_activities_table` migration declared this
 * column nullable, but on some environments the physical schema is NOT NULL
 * (historical drift). Auth-event rows (login, logout, failed login, lockout)
 * written by App\Listeners\AuthActivityListener legitimately have no
 * associated account — Failed/Lockout happen before a user is identified —
 * so the column must allow NULL to record them.
 *
 * Safe on production-sized data: ALGORITHM=INPLACE, LOCK=NONE (MariaDB 11 /
 * MariaDB 10.6+). If the column is already NULLable (some envs) the
 * statement is skipped so the migration is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        if ($this->isNullable('activities', 'account_id')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `activities`
             MODIFY COLUMN `account_id` INT UNSIGNED NULL DEFAULT NULL,
             ALGORITHM=INPLACE, LOCK=NONE'
        );
    }

    public function down(): void
    {
        // No-op: re-tightening the column would fail on rows written by the
        // auth listener (account_id IS NULL). If you need to reverse this,
        // first delete or backfill those rows manually, then ALTER.
    }

    private function isNullable(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT IS_NULLABLE AS nul FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column],
        );

        return $row !== null && strtoupper((string) $row->nul) === 'YES';
    }
};
