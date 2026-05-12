<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen `sort_no` from TINYINT UNSIGNED (max 255) to INT UNSIGNED on the
 * three taxonomy tables whose service-layer creators do
 * `$record->update(['sort_no' => $record->id])`.
 *
 * The legacy Admin\*Controller paths happened to never overflow because
 * they set sort_no to the position within an unpaginated list (tiny
 * numbers). The API-controller path used by the v2 SPA uses the row id
 * directly — every row created after id 255 fatals with
 * `SQLSTATE[22003] Out of range value for column 'sort_no'`, rolling the
 * transaction back and leaving the caller with a 500.
 *
 * The `services` table is the first to hit this in production (ids well
 * past 200k). Fix `lead_sources` and `lead_statuses` at the same time —
 * they carry the same bug latently, and the `ServiceService` / the two
 * Lead services use identical `sort_no = id` logic.
 *
 * Safe on production-sized tables: MariaDB 11 widens an unsigned integer
 * column in-place (INSTANT algorithm) for this kind of promotion — no
 * table rebuild. Down migration narrows back but the narrowing will
 * fail if any row already holds a value > 255, which is expected if
 * the bug has already been triggered; in that case the user should
 * accept the wider type.
 */
return new class extends Migration
{
    private const TABLES = ['services', 'lead_sources', 'lead_statuses'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY COLUMN `sort_no` INT UNSIGNED NULL",
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY COLUMN `sort_no` TINYINT UNSIGNED NULL",
            );
        }
    }
};
