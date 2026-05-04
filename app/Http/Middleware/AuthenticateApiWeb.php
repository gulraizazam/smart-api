<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class AuthenticateApiWeb
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (auth()->check()) {
            return $next($request);
        }

        if ($request->hasHeader('Authorization')) {
            if (auth()->guard('sanctum')->check()) {
                Auth::setDefaultDriver('sanctum');

                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid Token.',
                'data' => null,
                'errors' => [],
            ], 401);
        }

        // No Authorization header and no live web session — emit JSON 401
        // so the SPA's `auth:expired` listener can clear local state and
        // bounce to its own login. Redirecting to a Blade `route('login')`
        // here ties this middleware to the legacy admin frontend that
        // retires shortly.
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
            'errors' => [],
        ], 401);
    }
}
