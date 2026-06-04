<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * The API was blanket-excluded from CSRF (`$except = ['/api/*']`), which
 * disabled CSRF for every cookie-authed SPA write (audit 2026-06). This
 * pins that the blanket exclusion is gone.
 *
 * NOTE: the behavioural cookie-path (cookie present + missing token => 419,
 * cookie present + valid token => pass) cannot be exercised here because the
 * middleware short-circuits under `runningUnitTests()`. That path is
 * validated by a live SPA login/click-through before deploy.
 */
class CsrfApiProtectionTest extends TestCase
{
    public function test_api_is_not_blanket_excluded_from_csrf(): void
    {
        $middleware = app(VerifyCsrfToken::class);

        $property = new \ReflectionProperty($middleware, 'except');
        $property->setAccessible(true);

        $this->assertNotContains(
            '/api/*',
            $property->getValue($middleware),
            'CSRF must not be blanket-disabled for the entire /api/* namespace.'
        );
    }
}
