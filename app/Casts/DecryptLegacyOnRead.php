<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Transitional cast for columns being moved OFF encryption back to
 * plaintext (cnic, employee bank_account_number) on the DB shared by
 * crm2 + crm3.
 *
 *   - get(): if the stored value is a still-readable legacy ciphertext
 *     envelope, decrypt it; otherwise return it verbatim. This tolerates
 *     BOTH leftover ciphertext (written by an app that hasn't switched
 *     yet, or a row encrypted under a now-lost key) AND new plaintext —
 *     so neither app's reads ever break during the rollout, and an
 *     undecryptable value degrades to its raw string instead of throwing
 *     (unlike Laravel's built-in `encrypted` cast).
 *   - set(): stores the value as-is (PLAINTEXT). No encryption.
 *
 * Once both apps use this cast and the one-off data migration has
 * converted existing rows, every value is plaintext and the get()
 * decrypt branch is dead code — the cast can later be dropped entirely.
 *
 * Distinct from App\Casts\EncryptedLegacy (which still ENCRYPTS on set);
 * that one stays in use for columns that remain encrypted.
 */
class DecryptLegacyOnRead implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
