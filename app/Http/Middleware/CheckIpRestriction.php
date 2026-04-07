<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckIpRestriction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            $userRole = method_exists($user, 'hasRole')
                ? $user->getRoleNames()->first()
                : $user->role;

            $restrictedRoles = config('ip_restrictions.restricted_roles', []);
            $allowedIps      = config('ip_restrictions.allowed_ips', []);

            if (in_array($userRole, $restrictedRoles, true)) {
                if (!in_array($request->ip(), $allowedIps, true)) {
                    Auth::logout();
                    return redirect('unauthorized');
                }
            }
        }

        return $next($request);
    }
}
