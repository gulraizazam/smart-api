<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Pins the baseline security headers added by SecurityHeaders middleware
 * (audit 2026-06). Headers must be present even on an unauthenticated 401,
 * since the middleware runs ahead of auth.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_present_on_api_responses(): void
    {
        $response = $this->getJson('/api/user'); // 401 unauthenticated — headers still apply

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull(
            $response->headers->get('Permissions-Policy'),
            'Permissions-Policy header must be present.'
        );
    }
}
