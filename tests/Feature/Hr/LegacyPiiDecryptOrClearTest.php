<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins 2026_06_04_140000_decrypt_or_clear_legacy_pii:
 *   - current-key ciphertext  -> decrypted to plaintext
 *   - undecryptable envelope   -> cleared to NULL (unrecoverable, lost key)
 *   - already-plaintext value  -> left untouched
 * for users.cnic and employee_details.bank_account_number. Pure DML, so
 * the per-test transaction rolls it back.
 */
class LegacyPiiDecryptOrClearTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /** Envelope-shaped, starts with eyJpdiI6, but fails to decrypt. */
    private function undecryptableEnvelope(): string
    {
        return base64_encode((string) json_encode([
            'iv' => base64_encode(str_repeat('0', 16)),
            'value' => 'xx',
            'mac' => str_repeat('a', 64),
        ]));
    }

    private function makeUser(?string $cnic, string $suffix): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => 'PII '.$suffix,
            'email' => 'pii.'.$suffix.'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+9230066600'.substr($suffix, -2),
            'cnic' => $cnic,
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_06_04_140000_decrypt_or_clear_legacy_pii.php'))->up();
    }

    public function test_users_cnic_is_decrypted_cleared_or_left_per_value(): void
    {
        $decryptable = $this->makeUser(Crypt::encryptString('VALID-CNIC'), 'a01');
        $unreadable = $this->makeUser($this->undecryptableEnvelope(), 'a02');
        $plaintext = $this->makeUser('3520212345671', 'a03');

        $this->runMigration();

        $this->assertSame('VALID-CNIC', DB::table('users')->where('id', $decryptable)->value('cnic'));
        $this->assertNull(DB::table('users')->where('id', $unreadable)->value('cnic'));
        $this->assertSame('3520212345671', DB::table('users')->where('id', $plaintext)->value('cnic'));
    }

    public function test_employee_bank_account_number_is_cleared_when_unreadable(): void
    {
        $userId = $this->makeUser(null, 'b01');
        $edId = (int) DB::table('employee_details')->insertGetId([
            'user_id' => $userId,
            'account_id' => 1,
            'bank_account_number' => $this->undecryptableEnvelope(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertNull(DB::table('employee_details')->where('id', $edId)->value('bank_account_number'));
    }

    public function test_migration_is_idempotent(): void
    {
        $u = $this->makeUser(Crypt::encryptString('ABC123'), 'c01');

        $this->runMigration();
        $this->assertSame('ABC123', DB::table('users')->where('id', $u)->value('cnic'));

        // Second run: value is now plaintext, so it is left untouched.
        $this->runMigration();
        $this->assertSame('ABC123', DB::table('users')->where('id', $u)->value('cnic'));
    }
}
