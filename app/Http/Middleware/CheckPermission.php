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
        // `permission:a|b|c` is the conventional "any of these" form. Split on
        // `|` and allow the request if the user is granted ANY listed ability.
        //
        // Previously the entire pipe-delimited string was passed to
        // Gate::allows() as a single ability name — which never matches a real
        // permission, so it only ever passed via the Super-Admin Gate::before
        // bypass. Every NON-super-admin (Administrator, Finance, …) holding one
        // of the listed permissions was wrongly 403'd on multi-permission
        // routes (all of routes/cashflow.php's group middleware). Splitting
        // restores the intended semantics; a user holding none still gets 403,
        // and each ability still flows through the legacy↔dotted alias bridge.
        foreach (explode('|', $permission) as $ability) {
            $ability = trim($ability);
            if ($ability !== '' && Gate::allows($ability)) {
                return $next($request);
            }
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
