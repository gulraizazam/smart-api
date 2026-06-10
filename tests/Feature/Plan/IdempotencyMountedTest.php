<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression guard: the money-WRITE plan routes must carry the
 * `idempotent` (EnsureIdempotency) middleware so a double-click / lost-ACK
 * retry replays the first response instead of writing twice.
 *
 * The middleware BEHAVIOUR is proven in IdempotencyMiddlewareTest; this
 * pins that it stays MOUNTED on the routes that matter:
 *   - packages.savepackages         (plan create — duplicate plans)
 *   - packages.update_membership_plan (membership payment)
 *   - treatment.invoice.save        (session consume — double-billing)
 *
 * The SPA sends the Idempotency-Key header (random per mutation, or a
 * stable plan-create-<random_id> for plan create) — see src/lib/api.ts +
 * src/lib/plan-api.ts.
 */
class IdempotencyMountedTest extends TestCase
{
    public function test_money_write_routes_carry_idempotency_middleware(): void
    {
        $routeNames = [
            'admin.packages.savepackages',            // plan create — duplicate plans
            'admin.packages.update_membership_plan',  // membership payment
            'admin.treatment.invoice.save',           // session consume — double-billing
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} must be registered.");

            $middleware = implode(',', $route->gatherMiddleware());
            $this->assertTrue(
                str_contains($middleware, 'idempotent') || str_contains($middleware, 'EnsureIdempotency'),
                "Route {$routeName} must carry the idempotent middleware — a duplicate submit would write twice. Got: {$middleware}",
            );
        }
    }
}
