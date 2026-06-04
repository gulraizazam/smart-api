<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The `api` rate limiter was defined but never attached to the API
 * middleware group (audit 2026-06), so ~538 mutating endpoints had no
 * ceiling. These pin that it is now wired + applies the generous default /
 * tight-on-heavy shape.
 */
class ApiRateLimitTest extends TestCase
{
    public function test_api_group_applies_the_throttle_middleware(): void
    {
        $groups = $this->app['router']->getMiddlewareGroups();

        $this->assertContains(
            'throttle:api',
            $groups['api'] ?? [],
            'The API middleware group must apply the throttle:api limiter.'
        );
    }

    public function test_api_limiter_is_generous_by_default_and_tight_on_heavy_paths(): void
    {
        $resolver = RateLimiter::limiter('api');
        $this->assertNotNull($resolver, 'The "api" rate limiter must be registered.');

        $normal = $resolver(Request::create('/api/leads', 'GET'));
        $heavy = $resolver(Request::create('/api/leads/datatable', 'POST'));
        $export = $resolver(Request::create('/api/memberships/export/pdf', 'GET'));

        $this->assertSame(300, $normal->maxAttempts, 'Default API limit should be generous.');
        $this->assertSame(30, $heavy->maxAttempts, 'datatable endpoints should be tightly limited.');
        $this->assertSame(30, $export->maxAttempts, 'export endpoints should be tightly limited.');
    }
}
