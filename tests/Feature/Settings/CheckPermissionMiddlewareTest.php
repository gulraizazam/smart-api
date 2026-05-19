<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the API contract of the custom `permission` middleware
 * (App\Http\Middleware\CheckPermission).
 *
 * Regression: on denial it unconditionally `redirect()->route('unauthorized')`
 * — a 302 to a Blade page. SPA fetch() can't follow that, so cashflow panels
 * (e.g. FDM cash) silently broke ("No branches assigned") instead of hitting
 * their explicit 403 handling. API / XHR callers must now get JSON 403; legacy
 * Blade web routes keep the human-facing redirect.
 */
class CheckPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Throwaway routes guarded by the real alias. A guest holds no
        // permission, so Gate::allows() is false → the denial branch runs.
        Route::middleware('permission:cf_mw_probe_perm')
            ->get('/api/_probe/check-permission', static fn () => response()->json(['ok' => true]));

        Route::middleware('permission:cf_mw_probe_perm')
            ->get('/_probe/check-permission-web', static fn () => 'ok');
    }

    public function test_api_request_gets_json_403_not_redirect(): void
    {
        $response = $this->getJson('/api/_probe/check-permission');

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_api_path_gets_json_403_even_without_json_accept_header(): void
    {
        // No Accept: application/json — still under api/*, so must be JSON 403,
        // never a 302 (this is exactly the shape the SPA XHR hit on staging).
        $response = $this->get('/api/_probe/check-permission');

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_legacy_web_request_still_redirects_to_unauthorized(): void
    {
        $response = $this->get('/_probe/check-permission-web');

        $response->assertStatus(302);
        $response->assertRedirect(route('unauthorized'));
    }
}
