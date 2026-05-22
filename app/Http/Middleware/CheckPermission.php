<?php

declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (Gate::allows($permission)) {
            return $next($request);
        }

        // API / JSON callers (the SPA) need a real 403 — a redirect to the
        // Blade `unauthorized` page is opaque to fetch() and a 401 would
        // trigger the SPA's auth:expired listener and force-log the user out.
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
                'data' => null,
            ], 403);
        }

        return redirect()->route('unauthorized');
    }
}
