<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Phase-10 contract: every cashflow write surface has a route-level
 * `permission:` middleware as defense-in-depth. If a controller method
 * ever forgets its inline `Gate::allows` check, the middleware catches it.
 *
 * Before this phase, `transfers`, `vendors`, `vendor-requests`, and
 * `category-requests` groups had NO route middleware — they relied entirely
 * on inline controller checks. This test pins that a user with no relevant
 * slug now gets 403 at the route layer.
 */
class RouteMiddlewareGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        // Strip every role from the test user — they're now authenticated but
        // have no cashflow slug at all. Each route group's middleware should
        // reject them.
        auth()->user()->roles()->detach();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @dataProvider gatedRouteProvider */
    public function test_gated_routes_reject_users_without_any_cashflow_slug(
        string $method,
        string $uri,
    ): void {
        $response = $this->json($method, $uri);
        // Spatie's permission middleware may render either an HTTP 403 (when
        // an exception is thrown for API requests) or a 302 redirect to the
        // login route (when the unauthorized handler is configured for web).
        // Either is a "blocked at the route layer" outcome — what matters is
        // that the user did NOT reach the controller (which would return 422
        // for validation errors or 200/201 for happy paths).
        $this->assertContains(
            $response->status(),
            [302, 401, 403],
            "Route {$method} {$uri} should be blocked by middleware; got {$response->status()}.",
        );
    }

    public static function gatedRouteProvider(): array
    {
        return [
            // Transfers
            'transfers data' => ['GET', '/api/cashflow/transfers/data'],
            'transfers store' => ['POST', '/api/cashflow/transfers/store'],
            'transfers void' => ['POST', '/api/cashflow/transfers/1/void'],
            'transfers edit' => ['POST', '/api/cashflow/transfers/1/edit'],

            // Vendors
            'vendors data' => ['GET', '/api/cashflow/vendors/data'],
            'vendors overview' => ['GET', '/api/cashflow/vendors/overview'],
            'vendors store' => ['POST', '/api/cashflow/vendors/store'],
            'vendors update' => ['POST', '/api/cashflow/vendors/1/update'],
            'vendors purchase' => ['POST', '/api/cashflow/vendors/1/purchase'],
            'vendors transaction delete' => ['POST', '/api/cashflow/vendors/1/transactions/1/delete'],

            // Vendor Requests
            'vendor-requests data' => ['GET', '/api/cashflow/vendor-requests/data'],
            'vendor-requests store' => ['POST', '/api/cashflow/vendor-requests/store'],
            'vendor-requests approve' => ['POST', '/api/cashflow/vendor-requests/1/approve'],

            // Category Requests
            'category-requests data' => ['GET', '/api/cashflow/category-requests/data'],
            'category-requests store' => ['POST', '/api/cashflow/category-requests/store'],
            'category-requests approve' => ['POST', '/api/cashflow/category-requests/1/approve'],
        ];
    }
}
