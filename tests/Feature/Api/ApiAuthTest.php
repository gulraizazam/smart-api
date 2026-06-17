<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Contract for the `POST /api/login` endpoint exposed by
 * App\Http\Controllers\Api\AuthController::login.
 *
 * Round 4 Auth-M3 hardened this flow so that the API login is NOT a
 * bypass for the DB-backed per-account lockout that the web login
 * already enforces. Every gate below was added specifically for that
 * reason — the audit tagged them in the method's docblock.
 *
 * Pins:
 *   1. Missing/empty credentials produce a 422 with an envelope error.
 *   2. Valid credentials return 200 and reset `failed_login_attempts`. A
 *      bearer PAT is minted ONLY when the client opts in with `device_name`
 *      (mobile/stateless); the cookie-mode SPA omits it and gets NO token,
 *      so never-used PATs no longer accumulate (576 piled up on prod).
 *   3. Wrong password returns 401 and increments the failed counter.
 *   4. Inactive accounts return 401 and are counted against the
 *      lockout policy. Returning the same 401 as "wrong password"
 *      prevents an attacker from probing which emails belong to
 *      disabled accounts vs. active ones (OWASP user-enumeration
 *      guidance: collapse credential-stage failures to one status).
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // The throttle:5,1 middleware on the login route uses Laravel's
        // rate limiter, which is cache-backed; the array driver persists
        // across tests inside a PHPUnit run. Flushing here keeps each
        // test independent.
        Cache::flush();

    }

    public function test_login_rejects_missing_credentials_with_422(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'message', 'errors']);
        $response->assertJson(['success' => false]);
    }

    public function test_cookie_login_succeeds_without_minting_a_token(): void
    {
        // Cookie-mode SPA login (no device_name): authenticates via the web
        // session, NOT a bearer token. It must NOT mint a Sanctum PAT — those
        // accumulated 576-strong and never-used on prod (a latent credential
        // exposure). See AuthController::login.
        $user = User::factory()->admin()->create([
            'email' => 'cookielogin@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => 1,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'cookielogin@example.test',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.email', 'cookielogin@example.test');
        $this->assertNull(
            $response->json('data.api_token'),
            'cookie-mode login must not return a bearer token.',
        );
        $this->assertSame(
            0,
            PersonalAccessToken::count(),
            'cookie-mode login must not mint a never-used PAT.',
        );

        $user->refresh();
        $this->assertSame(0, (int) $user->failed_login_attempts);
    }

    public function test_login_with_device_name_mints_a_bearer_token(): void
    {
        // A stateless/mobile client opts into a bearer token by sending
        // `device_name` (the idiomatic Sanctum pattern). Only then is a PAT
        // minted + returned, named after the device.
        $user = User::factory()->admin()->create([
            'email' => 'bearerlogin@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => 1,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'bearerlogin@example.test',
            'password' => 'Secret123!',
            'device_name' => 'iphone-15',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $token = $response->json('data.api_token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertSame(1, PersonalAccessToken::count(), 'a device_name login mints exactly one token.');
        $this->assertSame('iphone-15', PersonalAccessToken::first()->name, 'token is named after the device.');

        $user->refresh();
        $this->assertSame(0, (int) $user->failed_login_attempts);
    }

    public function test_login_rejects_wrong_password_with_401_and_increments_failed_counter(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'wrongpass@example.test',
            'password' => Hash::make('Correct123!'),
            'active' => 1,
            'failed_login_attempts' => 0,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpass@example.test',
            'password' => 'NotTheRightPassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);

        $user->refresh();
        $this->assertSame(
            1,
            (int) $user->failed_login_attempts,
            'A rejected password must be counted against the lockout policy.',
        );
    }

    public function test_login_refuses_inactive_account_with_401(): void
    {
        User::factory()->admin()->create([
            'email' => 'disabled@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => 0,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'disabled@example.test',
            'password' => 'Secret123!',
        ]);

        // 401 (not 403) so the response shape matches "wrong password" —
        // an attacker probing the form gets no signal about which emails
        // map to disabled accounts vs. unknown ones.
        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_locked_account_is_rejected_via_api_even_with_correct_password(): void
    {
        // The audit pinned that the API login path must respect the
        // DB-backed lockout — it is NOT a bypass.
        User::factory()->admin()->create([
            'email' => 'locked@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => 1,
            'locked_until' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'locked@example.test',
            'password' => 'Secret123!',
        ]);

        $this->assertContains($response->status(), [401, 403, 423]);
        $response->assertJson(['success' => false]);
    }
}
