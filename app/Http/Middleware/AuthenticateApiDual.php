<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Dual-guard auth for the staged Sanctum → Passport migration.
 *
 * Tries guards in this order:
 *  1. `web` session — legacy Blade admin calling the same endpoint
 *  2. `api_passport` — new SPA tokens from /oauth/token (password grant)
 *  3. `sanctum` — live SPA tokens from /api/login during the cutover
 *
 * Apply via the `auth.api.dual` alias on any endpoint that needs to
 * accept either token type. Once the SPA is fully on Passport, swap
 * back to the single-guard `auth.common` (or auth('api_passport')) and
 * drop the Sanctum fallback.
 */
class AuthenticateApiDual
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (auth()->check()) {
            return $next($request);
        }

        if (! $request->hasHeader('Authorization')) {
            // Cookie-mode SPA: missing or expired session. The SPA's
            // `auth:expired` listener watches for 401 on `/api/*` and
            // bounces to its own login screen. Redirecting to a Blade
            // `route('login')` here would 500 once the legacy admin
            // frontend is retired and 302 to a dead Blade page today.
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
                'errors' => [],
            ], 401);
        }

        foreach (['sanctum', 'api_passport'] as $guard) {
            // Each guard is wrapped in its own try so a misconfigured
            // adapter (e.g. Passport's `league/oauth2-server` throwing
            // "Invalid key supplied" when the OAuth keypair isn't
            // present — common in test envs and during cutover) falls
            // through to the next guard instead of crashing the whole
            // request with a 500.
            try {
                if (auth()->guard($guard)->check()) {
                    Auth::setDefaultDriver($guard);

                    return $next($request);
                }
            } catch (\Throwable) {
                // Swallow + try the next guard. The final 401 below
                // is the right outcome if every guard fails.
                continue;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid Token.',
            'data' => null,
            'errors' => [],
        ], 401);
    }
}
