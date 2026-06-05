<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — V2 (Passport) login enumeration.
 *
 * The /api/v2/auth/login endpoint used to return a distinct 403
 * ("account deactivated") and a "temporarily locked" message, letting an
 * attacker confirm an email exists. It now mirrors the Sanctum login: one
 * generic 401 for wrong-password / unknown / deactivated, so the response
 * never confirms account existence. (UserFactory password = "password".)
 */
class V2LoginEnumerationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private string $generic = 'Sign-in failed. Please check your credentials and try again.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_deactivated_account_returns_generic_401_not_403(): void
    {
        User::factory()->create([
            'account_id' => 1,
            'email' => 'deactivated@test.local',
            'active' => 0,
        ]);

        $response = $this->postJson('/api/v2/auth/login', [
            'email' => 'deactivated@test.local',
            'password' => 'password', // correct password, but account is inactive
        ]);

        $response->assertStatus(401)->assertJsonFragment(['message' => $this->generic]);
        $this->assertStringNotContainsStringIgnoringCase(
            'deactiv',
            (string) $response->getContent(),
            'Response must not reveal that the account exists but is deactivated.'
        );
    }

    public function test_wrong_password_returns_same_generic_401(): void
    {
        User::factory()->create([
            'account_id' => 1,
            'email' => 'active@test.local',
            'active' => 1,
        ]);

        $this->postJson('/api/v2/auth/login', [
            'email' => 'active@test.local',
            'password' => 'definitely-the-wrong-password',
        ])->assertStatus(401)->assertJsonFragment(['message' => $this->generic]);
    }

    public function test_unknown_email_returns_same_generic_401(): void
    {
        $this->postJson('/api/v2/auth/login', [
            'email' => 'nobody@test.local',
            'password' => 'whatever',
        ])->assertStatus(401)->assertJsonFragment(['message' => $this->generic]);
    }
}
