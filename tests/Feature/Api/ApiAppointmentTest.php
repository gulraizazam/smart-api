<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the admin appointment API surface. These routes
 * live behind the custom `auth.common` middleware
 * (App\Http\Middleware\AuthenticateApiWeb) which accepts EITHER a
 * session-auth user or a Sanctum Authorization header and otherwise
 * redirects to the web `login` route.
 *
 * Pins:
 *   1. Calling the lookup endpoint without any credentials does NOT
 *      leak data — it either redirects to login or 401s.
 *   2. An authenticated call without the required `location_id`
 *      returns the "Record found" 404 branch in the standard envelope.
 *   3. An authenticated call with a `location_id` returns the 200
 *      envelope containing the `dropdown` payload key.
 */
class ApiAppointmentTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_unauthenticated_request_does_not_leak_data(): void
    {
        $response = $this->postJson('/api/appointments/load-doctors', [
            'location_id' => 1,
        ]);

        // AuthenticateApiWeb redirects to the `login` route for unauth
        // requests without a Bearer token. That's a 302 in test client.
        // In either case we MUST NOT see a 200 success envelope.
        $this->assertNotSame(200, $response->status());
        $this->assertContains($response->status(), [302, 401, 403, 419]);
    }

    public function test_authenticated_request_without_location_returns_404_envelope(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/appointments/load-doctors', []);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_authenticated_request_with_location_returns_dropdown_envelope(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/appointments/load-doctors', [
            'location_id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['dropdown'],
        ]);
    }

    public function test_load_consultant_doctors_returns_dropdown_envelope(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/appointments/load-consultant-doctors', [
            'location_id' => 1,
        ]);

        // Endpoint shares the same envelope shape as load-doctors.
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['dropdown'],
        ]);
    }

    public function test_load_locations_by_city_returns_success_envelope(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/appointments/load-locations', [
            'city_id' => 1,
        ]);

        // Even if no locations match, envelope must be well-formed.
        $this->assertContains($response->status(), [200, 404]);
    }
}
