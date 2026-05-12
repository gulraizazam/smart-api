<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Search-bar perf migration for the unified plans/patients lookup.
 *
 * Two changes, both on the `users` table (patients are users with
 * user_type_id = 3):
 *
 *   1. **FULLTEXT index on `users.name`** — backs the token-aware
 *      MATCH(name) AGAINST('+shahid* +hussain*' IN BOOLEAN MODE) search.
 *      Replaces today's `LIKE '%shahid%'` table scan with O(log n)
 *      lookups, supports prefix wildcards per token, gives relevance
 *      ranking for free, and matches tokens in any order.
 *      Mirrors the leads.name FULLTEXT pattern from the 2026-04-18
 *      migration.
 *
 *   2. **`phone_normalized` column + B-tree index** — strips every
 *      non-digit from `phone` so prefix matches can use an index
 *      instead of `LIKE '%302...'` (also a leading-wildcard scan).
 *      Backfilled from existing rows in the same migration.
 *      Kept in sync going forward by a model `saving` event listener
 *      on the User/Patients model — the column is never written to
 *      directly by callers.
 *
 * Both changes use ALGORITHM=INPLACE on InnoDB 8 so the table isn't
 * rebuilt; DDL completes in a few seconds even at the current users
 * scale, with a brief LOCK=SHARED window where writes pause but reads
 * keep flowing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        // ── 1. FULLTEXT index on name ──────────────────────────────
        $hasFt = collect(DB::select('SHOW INDEX FROM users'))
            ->contains(fn ($r) => $r->Key_name === 'idx_users_name_ft');

        if (! $hasFt) {
            DB::statement('ALTER TABLE users ADD FULLTEXT INDEX idx_users_name_ft (name), ALGORITHM=INPLACE, LOCK=SHARED');
        }

        // ── 2. phone_normalized column + index ─────────────────────
        if (! Schema::hasColumn('users', 'phone_normalized')) {
            Schema::table('users', function ($table): void {
                // varchar(40) easily fits the longest phone we've seen plus
                // any historical formatting variations once stripped.
                // Nullable so backfill is a separate UPDATE; rows with no
                // phone stay null.
                $table->string('phone_normalized', 40)->nullable()->after('phone');
            });
        }

        // Backfill — strip every non-digit from `phone`. REGEXP_REPLACE
        // requires MariaDB 11+; if that's ever a concern this is the line
        // to swap to a chained REPLACE() pipeline. Run unconditionally so
        // re-running the migration after a partial failure is idempotent.
        DB::statement("
            UPDATE users
            SET phone_normalized = NULLIF(REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', ''), '')
            WHERE phone_normalized IS NULL
        ");

        // B-tree index for prefix lookup (`WHERE phone_normalized LIKE '302%'`).
        // Skip the leading-wildcard pattern entirely going forward.
        $hasPhoneIdx = collect(DB::select('SHOW INDEX FROM users'))
            ->contains(fn ($r) => $r->Key_name === 'idx_users_phone_normalized');

        if (! $hasPhoneIdx) {
            DB::statement('ALTER TABLE users ADD INDEX idx_users_phone_normalized (phone_normalized), ALGORITHM=INPLACE, LOCK=SHARED');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM users'));

        if ($indexes->contains(fn ($r) => $r->Key_name === 'idx_users_name_ft')) {
            DB::statement('ALTER TABLE users DROP INDEX idx_users_name_ft');
        }

        if ($indexes->contains(fn ($r) => $r->Key_name === 'idx_users_phone_normalized')) {
            DB::statement('ALTER TABLE users DROP INDEX idx_users_phone_normalized');
        }

        if (Schema::hasColumn('users', 'phone_normalized')) {
            Schema::table('users', function ($table): void {
                $table->dropColumn('phone_normalized');
            });
        }
    }
};
