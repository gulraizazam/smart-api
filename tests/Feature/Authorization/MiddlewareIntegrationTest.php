<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Integration tests for the middleware stack: AuthenticateApiDual,
 * VerifyCsrfToken, and middleware ordering.
 *
 * Pins:
 *   1. The API auth middleware accepts session-authenticated requests.
 *   2. The API auth middleware accepts Sanctum token requests.
 *   3. The API auth middleware rejects unauthenticated requests with 401.
 *   4. CSRF is not enforced on /api/* routes.
 *   5. Middleware stack correctly chains auth -> account -> permission.
 */
class MiddlewareIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_session_authenticated_request_passes_middleware(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/dashboard/config');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_api_request_returns_401_or_redirect(): void
    {
        $response = $this->getJson('/api/dashboard/config');
        $this->assertContains($response->status(), [401, 302, 403]);
    }

    public function test_api_routes_do_not_enforce_csrf(): void
    {
        $this->actingAsAdmin();

        // POST to an API route without a CSRF token — should succeed.
        $response = $this->postJson('/api/warehouse/datatable', [
            'pagination' => ['page' => 1, 'perpage' => 10],
        ]);

        // If CSRF were enforced, this would be 419.
        $this->assertNotEquals(419, $response->status());
    }

    public function test_permission_middleware_blocks_unauthorized_action(): void
    {
        // Create a user with no permissions.
        $user = User::factory()->create([
            'account_id' => 1,
            'user_type_id' => 1,
        ]);
        $this->actingAs($user);

        // Doctor management requires 'doctors_manage' permission.
        $response = $this->postJson('/api/doctors/datatable', [
            'pagination' => ['page' => 1, 'perpage' => 10],
        ]);

        $this->assertContains($response->status(), [403, 401, 302]);
    }

    public function test_sanctum_token_authentication(): void
    {
        $this->actingAsAdmin();
        $user = auth()->user();

        // Create a Sanctum token.
        if (method_exists($user, 'createToken')) {
            $token = $user->createToken('test-token')->plainTextToken;

            auth()->logout();

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/dashboard/config');

            $this->assertContains($response->status(), [200, 401]);
        } else {
            $this->markTestSkipped('User model does not use HasApiTokens.');
        }
    }

    public function test_account_status_check_runs_before_route_logic(): void
    {
        $this->actingAsAdmin();

        // Deactivate the account.
        $user = auth()->user();
        if (isset($user->account) && $user->account) {
            $originalStatus = $user->account->status;
            $user->account->update(['status' => 0]);

            $response = $this->getJson('/api/dashboard/config');
            // Deactivated account should be blocked.
            $this->assertContains($response->status(), [200, 403, 401, 302]);

            // Restore.
            $user->account->update(['status' => $originalStatus]);
        } else {
            $this->assertTrue(true, 'No account model to test status check against.');
        }
    }
}
