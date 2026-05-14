<?php

declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Allow either a single slug or a pipe-separated list, e.g.
     *   `permission:cashflow_transfer_view`               → require that one slug
     *   `permission:cashflow_transfer_view|cashflow_manage` → require either
     *
     * The OR-form lets a group route gate on its specific view slug AND on
     * the global `cashflow_manage` super-slug without enumerating every
     * descendant. Mirrors Spatie's built-in `permission:` syntax so callers
     * don't have to learn a custom convention.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $allowed = false;
        foreach (explode('|', $permission) as $slug) {
            $slug = trim($slug);
            if ($slug === '') continue;
            if (Gate::allows($slug)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return redirect()->route('unauthorized');
        }

        return $next($request);
    }
}
