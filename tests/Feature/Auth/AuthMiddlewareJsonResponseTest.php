<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins that the auth middlewares (Authenticate, AuthenticateApiDual)
 * emit JSON 401 instead of redirecting to the legacy Blade
 * `route('login')` when authentication fails.
 *
 * The redirect path was a soft tie to the Blade frontend that retires
 * shortly: once the legacy /login route is deleted, a redirect-based
 * middleware would throw RouteNotFoundException (HTTP 500) and break
 * every SPA call with an expired session — instead of the 401 the SPA's
 * `auth:expired` listener already expects.
 *
 * Probe routes are registered in setUp() rather than reusing real
 * /api/* routes so the assertion is self-contained and independent of
 * whichever real route happens to be wired to a given middleware on
 * any given day.
 */
class AuthMiddlewareJsonResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth.api.dual')->get(
            '/api/__test/auth-dual',
            fn () => response()->json(['ok' => true]),
        );
        Route::middleware('auth')->get(
            '/api/__test/auth',
            fn () => response()->json(['ok' => true]),
        );
    }

    public function test_dual_returns_json_401_when_session_and_token_missing(): void
    {
        $response = $this->getJson('/api/__test/auth-dual');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
            'errors' => [],
        ]);
    }

    public function test_dual_does_not_redirect_to_route_login(): void
    {
        // Plain GET (no Accept header). Pre-fix this would 302 to
        // route('login'); after the fix it must NOT redirect, since
        // the named route disappears at Blade cutover.
        $response = $this->get('/api/__test/auth-dual');

        $this->assertNotEquals(
            302,
            $response->status(),
            'AuthenticateApiDual must not 302 to route(\'login\') — that route dies at Blade cutover.',
        );
    }

    public function test_dual_lets_authenticated_session_users_through(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/__test/auth-dual');

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_authenticate_returns_json_401_for_json_clients(): void
    {
        $response = $this->getJson('/api/__test/auth');

        $response->assertStatus(401);
    }

    public function test_authenticate_redirects_html_clients_to_spa_login_not_blade(): void
    {
        // Non-JSON requests should land on the SPA's own /login screen,
        // not on the legacy Blade route('login'). This branch is only
        // reachable by direct browser hits (the SPA always sends
        // Accept: application/json) but it's the documented contract
        // and we want it to survive Blade retirement.
        $response = $this->get('/api/__test/auth');

        $response->assertRedirect('/admin-v2/login');
    }
}
