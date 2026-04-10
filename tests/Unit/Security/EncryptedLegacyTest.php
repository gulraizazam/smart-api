<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Casts\EncryptedLegacy;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Regression tests for App\Casts\EncryptedLegacy.
 *
 * The cast lets us add column-level encryption (`'cnic' => EncryptedLegacy`,
 * `'password' => EncryptedLegacy` on operator settings, etc.) without
 * requiring a backfill — old plaintext rows are returned verbatim and
 * silently re-encrypted on the next save. This file pins the contract:
 *
 *   1. Round-trip a plaintext value through set→get and assert the
 *      stored shape is encrypted (Laravel envelope) and the recovered
 *      shape matches the input.
 *   2. Legacy plaintext rows pass through `get()` unchanged.
 *   3. Empty/null inputs short-circuit on both directions (no wasted
 *      ciphertext envelope, no AuditTrails spam).
 */
class EncryptedLegacyTest extends TestCase
{
    private EncryptedLegacy $cast;
    private User $modelStub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new EncryptedLegacy();
        // The cast contract requires a Model instance but never reads
        // attributes off it — an unsaved User stub is sufficient.
        $this->modelStub = new User();
    }

    public function test_set_produces_laravel_encryption_envelope(): void
    {
        $stored = $this->cast->set($this->modelStub, 'cnic', '35202-1234567-1', []);

        $this->assertIsString($stored);
        // Laravel's Crypt envelope is base64-encoded JSON beginning with
        // `eyJpdiI` (the base64 of `{"iv"`). If this prefix changes,
        // the cast is no longer producing a real ciphertext.
        $this->assertStringStartsWith('eyJ', $stored);
        // The ciphertext must NOT contain the plaintext.
        $this->assertStringNotContainsString('35202-1234567-1', $stored);
    }

    public function test_get_round_trips_a_freshly_encrypted_value(): void
    {
        $plaintext = 'super-secret-token';
        $stored = $this->cast->set($this->modelStub, 'token', $plaintext, []);
        $recovered = $this->cast->get($this->modelStub, 'token', $stored, []);

        $this->assertSame($plaintext, $recovered);
    }

    public function test_legacy_plaintext_row_is_returned_unchanged(): void
    {
        // This is the whole point of EncryptedLegacy: a row that has not
        // yet been re-saved through the model still contains plaintext.
        // The cast must return it as-is rather than throwing.
        $legacy = 'plaintext-from-2019';
        $recovered = $this->cast->get($this->modelStub, 'cnic', $legacy, []);

        $this->assertSame($legacy, $recovered);
    }

    public function test_null_short_circuits_on_set(): void
    {
        // No 200-byte envelope written for an empty cnic — keeps DB rows
        // small and avoids spurious AuditTrails entries.
        $this->assertNull($this->cast->set($this->modelStub, 'cnic', null, []));
    }

    public function test_empty_string_short_circuits_on_set(): void
    {
        $this->assertSame('', $this->cast->set($this->modelStub, 'cnic', '', []));
    }

    public function test_null_short_circuits_on_get(): void
    {
        $this->assertNull($this->cast->get($this->modelStub, 'cnic', null, []));
    }

    public function test_empty_string_short_circuits_on_get(): void
    {
        $this->assertSame('', $this->cast->get($this->modelStub, 'cnic', '', []));
    }

    public function test_each_set_produces_a_distinct_ciphertext(): void
    {
        // Crypt::encryptString uses a random IV per call — encrypting
        // the same plaintext twice must yield different envelopes.
        // Without this property an attacker who sees two equal
        // ciphertexts learns the plaintexts are equal.
        $a = $this->cast->set($this->modelStub, 'cnic', 'same-input', []);
        $b = $this->cast->set($this->modelStub, 'cnic', 'same-input', []);

        $this->assertNotSame($a, $b);
        // But both must decrypt back to the same plaintext.
        $this->assertSame(
            $this->cast->get($this->modelStub, 'cnic', $a, []),
            $this->cast->get($this->modelStub, 'cnic', $b, []),
        );
    }
}
