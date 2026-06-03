<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Where the framework redirects an unauthenticated non-JSON (HTML)
     * request. JSON requests return null here so the parent throws an
     * AuthenticationException → JSON 401, which is what every SPA call
     * already expects. Non-JSON requests redirect to the SPA's own login
     * screen rather than the legacy Blade `route('login')` so the
     * redirect target survives Blade retirement.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            return rtrim((string) config('app.spa_url'), '/').'/login';
        }

        return null;
    }
}
