<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Convert the formerly-encrypted PII columns (users.cnic,
 * employee_details.bank_account_number) to PLAINTEXT — encryption was
 * removed per product decision (2026-06-04). Pairs with the cast swap
 * to App\Casts\DecryptLegacyOnRead on User / Patients / EmployeeDetail.
 *
 * Per stored value (read raw via DB::table so the cast doesn't re-touch
 * it):
 *   - Laravel ciphertext envelope (base64 of {"iv":...}, i.e. starts
 *     with "eyJpdiI6") that DECRYPTS with the current key -> store the
 *     decrypted plaintext.
 *   - Ciphertext envelope that FAILS to decrypt -> encrypted under a key
 *     this app no longer has (the lost external key from the APP_KEY
 *     incident); the value is unrecoverable -> CLEAR to NULL so it can be
 *     re-entered as plaintext (product decision: clear the unreadable).
 *   - Anything that is NOT an envelope -> already plaintext, left as-is.
 *
 * On the prod-mirror local DB the split was: cnic 7 decryptable / 95
 * cleared, bank_account_number 0 / 95 cleared.
 *
 * Rows are collected up-front (not chunk-while-updating) so clearing a
 * value to NULL can't shift a LIMIT/OFFSET window and skip rows — the
 * sets are tiny (~100 each).
 *
 * Writes bypass the model cast (raw DB::table), so no re-encryption.
 * Idempotent: a re-run sees plaintext (left) or NULL (filtered out).
 *
 * Down: irreversible — cleared values were already unrecoverable, and
 * decrypted values are intentionally plaintext now.
 */
return new class extends Migration
{
    /** [table, primary key, value column] */
    private array $targets = [
        ['users', 'id', 'cnic'],
        ['employee_details', 'id', 'bank_account_number'],
    ];

    public function up(): void
    {
        foreach ($this->targets as [$table, $pk, $col]) {
            $rows = DB::table($table)
                ->whereNotNull($col)
                ->where($col, '<>', '')
                ->orderBy($pk)
                ->pluck($col, $pk);

            $decrypted = 0;
            $cleared = 0;
            $left = 0;

            foreach ($rows as $id => $value) {
                $value = (string) $value;

                // Only touch Laravel ciphertext envelopes; leave plaintext alone.
                if (! str_starts_with($value, 'eyJpdiI6')) {
                    $left++;
                    continue;
                }

                try {
                    $plain = Crypt::decryptString($value);
                    DB::table($table)->where($pk, $id)->update([$col => $plain]);
                    $decrypted++;
                } catch (DecryptException) {
                    DB::table($table)->where($pk, $id)->update([$col => null]);
                    $cleared++;
                }
            }

            \Log::info('decrypt_or_clear_legacy_pii', [
                'table'          => $table,
                'column'         => $col,
                'decrypted'      => $decrypted,
                'cleared'        => $cleared,
                'left_plaintext' => $left,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible — see class docblock.
    }
};
