<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthenticateApiWeb
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse|JsonResponse
    {
        if (auth()->check()) {
            return $next($request);
        }

        if ($request->hasHeader('Authorization')) {
            if (auth()->guard('sanctum')->check()) {
                auth()->shouldUse('sanctum');

                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid Token.',
                'data' => null,
                'errors' => [],
            ], 401);
        }

        return redirect()->route('login');
    }
}
