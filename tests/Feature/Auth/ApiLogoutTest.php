<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins POST /api/logout — the cookie- and bearer-mode SPA logout endpoint.
 *
 * Replaces the SPA's previous call against a 404: the SPA was already
 * POST-ing /api/logout (see crm-frontend src/lib/auth.tsx) but the route
 * didn't exist, so the server-side session lingered for SESSION_LIFETIME
 * after the user clicked "Logout" — until this test (and the matching
 * route) shipped.
 *
 * Contract:
 *   - Cookie mode: tears down the web session, rotates the session ID
 *     and the CSRF token. Idempotent — calling /api/logout from a
 *     client that's already logged out still 200s rather than wedging
 *     a stale tab in a "can't log out" loop.
 *   - Bearer mode: revokes the personal access token used for THIS
 *     request only — multi-device users keep their other sessions.
 *   - Passport mode: uses /api/v2/auth/logout instead, not this; passes
 *     through here as a no-op if accidentally hit.
 */
class ApiLogoutTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_api_logout_terminates_the_web_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertTrue(Auth::check());

        $response = $this->postJson('/api/logout');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Logged out.',
            'data' => null,
        ]);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_api_logout_is_idempotent_when_already_logged_out(): void
    {
        // No actingAs / no session — the SPA's stale-tab case. Must 200,
        // not 401: a logout endpoint that requires auth is a foot-gun
        // because a client whose session expired locally gets stuck.
        $response = $this->postJson('/api/logout');

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_api_logout_returns_json_not_a_redirect(): void
    {
        // Belt-and-suspenders: the legacy Blade /logout returns a 302
        // to '/'. The new endpoint is JSON-only by contract — pin it so
        // a future refactor can't silently re-route this to the Blade
        // controller.
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/logout');

        $this->assertNotEquals(302, $response->status());
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_api_logout_revokes_only_the_current_sanctum_token(): void
    {
        // Bearer mode: deleting all tokens on logout would log a multi-
        // device user out of every other device. The contract is
        // current-token-only.
        $user = User::factory()->create();
        $current = $user->createToken('current-device');
        $otherDevice = $user->createToken('other-device');

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$current->plainTextToken,
        ])->assertOk();

        // The token used for this request must be gone…
        $this->assertNull(
            PersonalAccessToken::find($current->accessToken->id),
            'Logout must revoke the personal access token used for the request.',
        );
        // …but every other token the user holds must survive.
        $this->assertNotNull(
            PersonalAccessToken::find($otherDevice->accessToken->id),
            'Logout must NOT revoke the user\'s other device tokens.',
        );
    }
}
