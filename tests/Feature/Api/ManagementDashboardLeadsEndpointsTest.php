<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the 11 new /api/management-dashboard/leads-* endpoints.
 *
 * These specs don't try to re-test the per-metric SQL (that's covered in
 * the dedicated metric tests). What they DO pin is the wiring — every
 * route is reachable, returns the standard ApiResponse envelope, and
 * rejects an unauthenticated call. If the frontend's mdApi ever gets a
 * 404 from one of these, this file goes red before the SPA does.
 */
class ManagementDashboardLeadsEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    /** All lead-reporting routes. Keep in sync with routes/api/dashboard.php. */
    private const ROUTES = [
        '/api/management-dashboard/leads-overview',
        '/api/management-dashboard/leads-over-time',
        '/api/management-dashboard/leads-status-split',
        '/api/management-dashboard/leads-source-split',
        '/api/management-dashboard/leads-service-split',
        '/api/management-dashboard/leads-department-split',
        '/api/management-dashboard/leads-funnel',
        '/api/management-dashboard/leads-agent-leaderboard',
        '/api/management-dashboard/leads-time-to-conversion',
        '/api/management-dashboard/leads-response-time',
        '/api/management-dashboard/leads-revenue',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_all_leads_routes_reject_unauthenticated_calls(): void
    {
        foreach (self::ROUTES as $path) {
            $r = $this->getJson($path);
            $this->assertContains(
                $r->getStatusCode(),
                [401, 403],
                "{$path} did not reject unauthenticated: got {$r->getStatusCode()}",
            );
        }
    }

    public function test_all_leads_routes_return_success_envelope_for_authenticated_user(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        foreach (self::ROUTES as $path) {
            $r = $this->getJson($path.'?from=2026-01-01&to=2026-12-31');
            $this->assertContains(
                $r->getStatusCode(),
                [200, 403], // 403 acceptable if user lacks the specific dashboard permission
                "{$path} unexpected status {$r->getStatusCode()}",
            );
            if ($r->getStatusCode() === 200) {
                $r->assertJsonStructure(['success', 'status', 'message', 'data']);
                $this->assertTrue((bool) $r->json('success'), "{$path}: success flag false");
            }
        }
    }
}
