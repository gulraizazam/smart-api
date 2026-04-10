<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted-at-rest cast that tolerates legacy plaintext rows.
 *
 * Behavior:
 *   - On set:  always encrypts via Crypt::encryptString (AES-256-CBC w/ HMAC).
 *   - On get:  attempts decryption; on DecryptException, returns the raw
 *              value as a legacy plaintext fallback. This lets us add the
 *              cast to columns whose existing rows are still cleartext
 *              without breaking reads. Once every row has been re-saved
 *              through the model, the legacy branch becomes dead code.
 *
 * Use this for sensitive columns being migrated from plaintext to ciphertext
 * without an immediate backfill (e.g., SMS-operator passwords, national IDs).
 *
 * Caveat: a row whose ciphertext is malformed will silently fall back to
 * "legacy plaintext" mode. The risk window is small because Laravel's
 * envelope format is highly distinctive (eyJpdiI6... base64 wrapper).
 */
class EncryptedLegacy implements CastsAttributes
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
        // Short-circuit on null AND empty string. Without this, an empty
        // cnic/password gets encrypted into a 200-byte ciphertext envelope
        // on every save, wasting storage and triggering spurious change
        // events in AuditTrails. Mirrors the get() short-circuit so the
        // roundtrip is symmetric.
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
