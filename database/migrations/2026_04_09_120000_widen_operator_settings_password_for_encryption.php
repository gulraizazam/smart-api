<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `password` columns on global_operator_settings and user_operator_settings
 * from VARCHAR(50) to TEXT.
 *
 * Reason: these columns currently store SMS-provider passwords in plaintext.
 * The application is being updated to encrypt them at rest via the
 * App\Casts\EncryptedLegacy cast (Laravel Crypt::encryptString → AES-256-CBC).
 * The encrypted envelope is ~280 chars (base64-encoded JSON), which does not
 * fit in VARCHAR(50). The cast and this migration MUST deploy together —
 * widen first, then enable cast — otherwise the next save throws "data too long".
 *
 * Down: not reversible (truncating ciphertext would corrupt rows).
 */
return new class extends Migration
{
    private array $tables = ['global_operator_settings', 'user_operator_settings'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'password')) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY `password` TEXT NULL");
        }
    }

    public function down(): void
    {
        // Intentionally no-op: rows may contain encrypted ciphertext that
        // would be truncated by reverting to VARCHAR(50). Manual operation
        // required if rollback is genuinely needed.
    }
};
