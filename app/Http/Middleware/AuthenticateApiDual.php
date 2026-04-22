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
            return redirect()->route('login');
        }

        foreach (['api_passport', 'sanctum'] as $guard) {
            if (auth()->guard($guard)->check()) {
                Auth::setDefaultDriver($guard);

                return $next($request);
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
