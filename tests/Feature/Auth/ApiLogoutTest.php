<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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

    protected function tearDown(): void
    {
        // Clean up the fixed filter dir used by the AUTH-2 test in case the
        // assertion failed and logout did NOT remove it.
        File::deleteDirectory(storage_path('settings'.DIRECTORY_SEPARATOR.'cutover-filter-test'));
        parent::tearDown();
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

    public function test_api_logout_clears_the_users_on_disk_filter_store(): void
    {
        // AUTH-2: the kiosk filter Valuestore lives in storage/settings/<randId>,
        // where <randId> is a per-user token kept in the session. Logout must wipe
        // that directory — the legacy web LoginController::logout did via
        // Filters::remove_filters(); without it those files orphan on disk after
        // sign-out. We seed the session pointer so getRandId() resolves to a known
        // dir, pre-create it, then assert logout removed it.
        $user = User::factory()->create();
        $randId = 'cutover-filter-test';
        $dir = storage_path('settings'.DIRECTORY_SEPARATOR.$randId);

        File::ensureDirectoryExists($dir);
        File::put($dir.DIRECTORY_SEPARATOR.'leads.json', '{"foo":"bar"}');
        $this->assertDirectoryExists($dir);

        $this->actingAs($user)
            ->withSession([$user->id => $randId])
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDirectoryDoesNotExist(
            $dir,
            'POST /api/logout must clear the user\'s on-disk filter store (AUTH-2).',
        );
    }
}
