<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-apply: widen `users.cnic` from VARCHAR(15) to TEXT.
 *
 * The original `2026_04_09_120001_widen_users_cnic_for_encryption` migration
 * is marked as "Ran" in the `migrations` table on at least one environment,
 * but the column is still VARCHAR(15) — almost certainly because a SQL
 * dump / DB snapshot was restored *after* that migration ran, replacing
 * the schema while leaving the migrations row in place.
 *
 * Symptom: writes from the HR employee form fail with
 *   SQLSTATE[22001]: Data too long for column 'cnic' at row 1
 * because App\Casts\EncryptedLegacy turns the supplied CNIC into a ~280-char
 * Laravel encryption envelope before insert, which doesn't fit in
 * VARCHAR(15).
 *
 * This migration is **idempotent**: it inspects the live column type and
 * only issues the ALTER when it isn't already a TEXT-family column. Safe
 * to run regardless of whether the prior migration actually took effect
 * on a given DB.
 *
 * See also: 2026_04_09_100043_widen_encrypted_columns (sibling fix for
 * the other encrypted columns introduced in the same Round 4 pass).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'cnic')) {
            return;
        }

        $current = DB::selectOne("SHOW COLUMNS FROM `users` WHERE Field = 'cnic'");
        if ($current === null) {
            return;
        }

        $type = strtolower((string) ($current->Type ?? ''));

        // Already wide enough — anything in the TEXT family fits the
        // ~280-char encryption envelope with headroom.
        if (str_contains($type, 'text') || str_contains($type, 'mediumtext') || str_contains($type, 'longtext')) {
            return;
        }

        DB::statement('ALTER TABLE `users` MODIFY `cnic` TEXT NULL');
    }

    public function down(): void
    {
        // Intentionally no-op. Reverting to VARCHAR(15) would truncate any
        // ciphertext rows written since the widen — same reason the
        // original migration left `down()` empty.
    }
};
