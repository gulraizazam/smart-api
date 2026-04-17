<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `services.sort_no` to INT UNSIGNED.
 *
 * Duplicating a service sets `sort_no = id`. Once the auto-increment crosses
 * 255, the existing TINYINT UNSIGNED column overflows (SQLSTATE 22003).
 * An earlier migration attempted this change but wrapped the ALTER in a
 * try/catch that silently swallowed failures on staging, leaving the column
 * narrow. This migration runs the ALTER unconditionally so any failure
 * surfaces immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services') || ! Schema::hasColumn('services', 'sort_no')) {
            return;
        }

        DB::statement('ALTER TABLE `services` MODIFY `sort_no` INT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('services') || ! Schema::hasColumn('services', 'sort_no')) {
            return;
        }

        DB::statement('ALTER TABLE `services` MODIFY `sort_no` TINYINT UNSIGNED NULL DEFAULT NULL');
    }
};
