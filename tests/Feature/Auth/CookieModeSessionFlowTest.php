<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the SANCTUM cookie-mode SPA auth boundary that survives the
 * AuthenticateApiWeb (`auth.common`) deletion at Blade cutover.
 *
 * The api middleware group in bootstrap/app.php prepends
 * EncryptCookies + AddQueuedCookiesToResponse + StartSession +
 * ShareErrorsFromSession + VerifyCsrfToken UNCONDITIONALLY (this app
 * does NOT wire Sanctum's EnsureFrontendRequestsAreStateful, so
 * SANCTUM_STATEFUL_DOMAINS is inert — the session stack runs for every
 * api request regardless of origin).
 *
 * That unconditional stack is what makes cookie-mode auth work:
 *   1. POST /api/login → Auth::guard('web')->login() writes the user
 *      id into the session (only persists because StartSession ran).
 *   2. GET /api/user (auth:sanctum) → the sanctum guard finds no Bearer
 *      token, falls back to config('sanctum.guard') === ['web'], and
 *      reads the same session → 200.
 *   3. Authenticated session also satisfies auth.api.dual's first
 *      `auth()->check()` branch — the surviving guard on every /api/*
 *      data route after auth.common is gone.
 *
 * If StartSession were removed from the api group when AuthenticateApiWeb
 * is deleted, login would 200 but /api/user and every dual route would
 * 401 — silent cookie-auth failure. This test fails loudly if that
 * happens.
 */
class CookieModeSessionFlowTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        Cache::flush();

        // A representative auth.api.dual route — the surviving guard on
        // /api/* data routes once auth.common (AuthenticateApiWeb) is
        // deleted. Self-contained so the assertion does not depend on
        // any particular real route's middleware on a given day.
        Route::middleware('auth.api.dual')->get(
            '/api/__test/dual-probe',
            fn () => response()->json(['ok' => true]),
        );
    }

    public function test_csrf_cookie_then_login_then_user_authenticates_via_session(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'cookieflow@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => 1,
        ]);

        // 1. CSRF cookie dance — seeds XSRF-TOKEN + session cookie.
        $this->get('/sanctum/csrf-cookie')->assertNoContent();

        // 2. Cookie-mode login (no Authorization header). Establishes the
        //    web session via Auth::guard('web')->login().
        $login = $this->postJson('/api/login', [
            'email' => 'cookieflow@example.test',
            'password' => 'Secret123!',
        ]);
        $login->assertStatus(200);
        $login->assertJson(['success' => true]);

        // 3. GET /api/user with NO Authorization header — must resolve the
        //    user from the session the api-group StartSession maintains,
        //    via the sanctum guard's `web` fallback.
        $me = $this->getJson('/api/user');
        $me->assertStatus(200);
        $me->assertJsonPath('data.email', 'cookieflow@example.test');
    }

    public function test_session_user_passes_dual_guard_without_token_or_auth_common(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'dualcookie@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => 1,
        ]);

        // Simulate the established cookie-mode session.
        $this->actingAs($user, 'web');

        // No Authorization header — pure session. This is the path that
        // must keep working after auth.common is removed; auth.api.dual's
        // first branch is `auth()->check()` against the web guard.
        $response = $this->getJson('/api/__test/dual-probe');

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_no_session_no_token_yields_json_401_on_user(): void
    {
        // The cookie-mode "expired session" path the SPA's auth:expired
        // listener depends on — must be a JSON 401, never a Blade 302.
        // /api/user is guarded by auth:sanctum (framework Authenticate
        // middleware), so the body is the bare {message} the framework
        // emits for JSON clients — the SPA only keys off the 401 status.
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }
}
