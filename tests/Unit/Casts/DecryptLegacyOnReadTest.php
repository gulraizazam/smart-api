<?php

declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Casts\DecryptLegacyOnRead;
use App\Models\EmployeeDetail;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Pins App\Casts\DecryptLegacyOnRead: lenient decrypt on read (never
 * throws on an undecryptable value, unlike the built-in `encrypted`
 * cast), PLAINTEXT on write.
 */
class DecryptLegacyOnReadTest extends TestCase
{
    private function cast(): DecryptLegacyOnRead
    {
        return new DecryptLegacyOnRead();
    }

    private function model(): EmployeeDetail
    {
        return new EmployeeDetail();
    }

    /** An envelope-shaped value with a bogus MAC — decryptable() fails. */
    private function undecryptableEnvelope(): string
    {
        return base64_encode((string) json_encode([
            'iv' => base64_encode(str_repeat('0', 16)),
            'value' => 'xx',
            'mac' => str_repeat('a', 64),
        ]));
    }

    public function test_get_decrypts_a_current_key_ciphertext(): void
    {
        $cipher = Crypt::encryptString('SECRET-123');
        $this->assertSame('SECRET-123', $this->cast()->get($this->model(), 'bank_account_number', $cipher, []));
    }

    public function test_get_returns_plaintext_unchanged(): void
    {
        $this->assertSame('PLAINTEXT-VAL', $this->cast()->get($this->model(), 'bank_account_number', 'PLAINTEXT-VAL', []));
    }

    public function test_get_returns_undecryptable_envelope_as_raw_without_throwing(): void
    {
        $garbage = $this->undecryptableEnvelope();
        // The built-in `encrypted` cast would throw DecryptException here;
        // this lenient cast must degrade to the raw value.
        $this->assertSame($garbage, $this->cast()->get($this->model(), 'bank_account_number', $garbage, []));
    }

    public function test_get_passes_null_and_empty_through(): void
    {
        $this->assertNull($this->cast()->get($this->model(), 'bank_account_number', null, []));
        $this->assertSame('', $this->cast()->get($this->model(), 'bank_account_number', '', []));
    }

    public function test_set_stores_plaintext_with_no_encryption(): void
    {
        // set() returns the value verbatim — nothing encrypted.
        $this->assertSame('PLAIN-WRITE', $this->cast()->set($this->model(), 'bank_account_number', 'PLAIN-WRITE', []));
    }
}
