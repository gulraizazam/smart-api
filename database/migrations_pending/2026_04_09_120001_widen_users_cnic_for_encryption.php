<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `users.cnic` from VARCHAR(15) to TEXT.
 *
 * Reason: cnic (Pakistani national ID) is being encrypted at rest via the
 * App\Casts\EncryptedLegacy cast on the User model. The encrypted envelope
 * is ~280 chars, which doesn't fit in VARCHAR(15). The cast and migration
 * MUST deploy together.
 *
 * Note: equality filtering on cnic (e.g., LeadService.php:1535) will return
 * false negatives for any row whose cnic has been encrypted. To restore
 * exact-match search, add a blind-index column (cnic_hash = HMAC-SHA256)
 * and rewrite the filter to use it. As of 2026-04-09, only ~7 users have
 * a populated cnic so the practical impact is negligible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'cnic')) {
            return;
        }
        DB::statement('ALTER TABLE `users` MODIFY `cnic` TEXT NULL');
    }

    public function down(): void
    {
        // Intentionally no-op: ciphertext rows would be truncated by reverting.
    }
};
