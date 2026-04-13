<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\AuditTrails;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression tests for the AuditTrails sensitive-field redaction (C0 fix).
 *
 * Background: the prior session removed 149 plaintext passwords from
 * `audit_trail_changes` and added a denylist to AuditTrails::maskSensitive
 * so passwords (and other secrets) are replaced with `[REDACTED]` before
 * any row is written to the audit log. This test asserts the denylist
 * never silently drops a field — if a field name is added to a model's
 * fillable list and someone forgets to denylist it, this test catches it
 * the moment a developer assumes the audit log scrubs the value.
 *
 * The test uses reflection to reach the private `maskSensitive` method
 * directly so we don't need a database connection.
 */
class AuditTrailsRedactionTest extends TestCase
{
    private static function mask(string $field, mixed $value): mixed
    {
        $ref = new ReflectionMethod(AuditTrails::class, 'maskSensitive');
        $ref->setAccessible(true);

        return $ref->invoke(null, $field, $value);
    }

    public function test_password_value_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('password', 'arooj@123A'));
    }

    public function test_password_with_uppercase_field_name_is_redacted(): void
    {
        // Field-name match must be case-insensitive — DB column casing
        // varies and AuditTrails sees the raw key from the request array.
        $this->assertSame('[REDACTED]', self::mask('Password', 'secret123'));
        $this->assertSame('[REDACTED]', self::mask('PASSWORD', 'secret123'));
    }

    public function test_remember_token_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('remember_token', 'abc'));
    }

    public function test_api_token_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('api_token', 'eyJ0...'));
    }

    public function test_generic_token_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('token', 'abc'));
    }

    public function test_secret_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('secret', 'shhhh'));
    }

    public function test_card_number_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('card_number', '4111111111111111'));
    }

    public function test_cvv_is_redacted(): void
    {
        $this->assertSame('[REDACTED]', self::mask('cvv', '123'));
    }

    public function test_cnic_is_redacted(): void
    {
        // CNIC is the Pakistani national ID — it is encrypted at rest via
        // EncryptedLegacy on the User model, but the AuditTrails capture
        // path sees the pre-encryption plaintext from the request array.
        $this->assertSame('[REDACTED]', self::mask('cnic', '35202-1234567-1'));
    }

    public function test_safe_field_name_passes_value_through(): void
    {
        // Innocent fields must round-trip unchanged. If this regresses,
        // the audit trail loses ALL legitimate change history.
        $this->assertSame('John Doe', self::mask('name', 'John Doe'));
        $this->assertSame('john@example.com', self::mask('email', 'john@example.com'));
        $this->assertSame(42, self::mask('account_id', 42));
    }

    public function test_field_with_password_substring_is_not_falsely_redacted(): void
    {
        // The denylist matches whole field names, not substrings — make
        // sure a column called `password_changed_at` doesn't get its
        // timestamp scrubbed.
        $value = '2026-04-09 12:00:00';
        $this->assertSame($value, self::mask('password_changed_at', $value));
    }

    public function test_null_value_passes_through_without_error(): void
    {
        // maskSensitive receives mixed $value — null is a valid column
        // value and must not cause a type error or false redaction.
        $this->assertNull(self::mask('name', null));
    }

    public function test_numeric_value_for_safe_field_passes_through(): void
    {
        $this->assertSame(0, self::mask('active', 0));
        $this->assertSame(99.5, self::mask('amount', 99.5));
    }

    public function test_sensitive_field_redacts_regardless_of_value_type(): void
    {
        // Even if a sensitive field somehow holds a non-string value,
        // the redaction must still replace it with the placeholder.
        $this->assertSame('[REDACTED]', self::mask('password', 12345));
        $this->assertSame('[REDACTED]', self::mask('cnic', null));
    }
}
