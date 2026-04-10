<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `services.sort_no` from `tinyint(3) unsigned` (max 255) to
 * `int unsigned` (max ~4.2B).
 *
 * Background: App\Models\Services::createRecord() sets
 * `sort_no = $record->id` immediately after insert. Any production DB
 * that has ever held more than 255 services — even briefly, because
 * MySQL auto_increment advances on rolled-back inserts too — will
 * crash the next service create with:
 *
 *   SQLSTATE[22003]: Out of range value for column 'sort_no' at row 1
 *
 * This surfaced in Tests\Feature\Settings\ServiceCrudTest under the
 * full test suite: early tests bumped the auto_increment counter past
 * 255 via transactional inserts that rolled back, and by the time
 * ServiceCrudTest ran the next service id exceeded the tinyint cap.
 *
 * The test was the canary — the same failure mode ships to production
 * on any instance where the services audit trail has ever had more
 * than ~255 rows written and rolled back, which is unavoidable in any
 * long-lived DB. Widening `sort_no` to `int unsigned` is the correct
 * fix; the column already stores a row id, which is itself `int
 * unsigned`, so the types now line up.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Doctrine DBAL is not available on this project so Schema::table
        // cannot alter an existing column's type. Drop to raw SQL — the
        // statement is identical on MySQL / MariaDB and preserves the
        // existing NOT NULL + default=0 constraints from the original
        // schema.
        DB::statement('ALTER TABLE `services` MODIFY `sort_no` INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        // Narrowing is lossy — refuse rather than silently truncate. If
        // you really need to roll back, do so manually after confirming
        // `MAX(sort_no) < 256`.
        DB::statement('ALTER TABLE `services` MODIFY `sort_no` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0');
    }
};
