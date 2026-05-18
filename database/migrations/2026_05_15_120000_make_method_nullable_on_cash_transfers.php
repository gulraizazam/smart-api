<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cash transfers no longer collect `method` from the SPA — it was noise
 * the operator never used (every legacy record always set it to
 * `physical_cash` because the form forced one, not because the value
 * meant anything). Make the column nullable so new transfers can be
 * recorded without a method while existing rows keep their values.
 *
 * The column is an ENUM, so use raw SQL — Doctrine DBAL doesn't preserve
 * MariaDB ENUM types through Schema::change() round-trips.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `cash_transfers` "
            . "MODIFY `method` ENUM('physical_cash', 'bank_deposit') NULL"
        );
    }

    public function down(): void
    {
        // Backfill any NULLs created since the column was relaxed so the
        // NOT NULL restore doesn't fail. `physical_cash` matches every
        // pre-2026-05-15 row's existing value.
        DB::table('cash_transfers')
            ->whereNull('method')
            ->update(['method' => 'physical_cash']);

        DB::statement(
            "ALTER TABLE `cash_transfers` "
            . "MODIFY `method` ENUM('physical_cash', 'bank_deposit') NOT NULL"
        );
    }
};
