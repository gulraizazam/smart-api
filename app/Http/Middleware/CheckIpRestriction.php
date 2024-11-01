<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckIpRestriction
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (method_exists($user, 'hasRole')) {
                $userRole = $user->getRoleNames()->first(); // Get the first role if multiple
            } else {
                // If role is a direct column in the user table
                
            $allowedIps = config("ip_restrictions.{$userRole}", []);

            // Check if the user's IP is in the allowed list
            if (in_array($request->ip(), $allowedIps)) {
                return $next($request);
            }

            // If the IP is not allowed, return an error response
            return redirect('unathorized');
        }
        return $next($request);
    }
}
