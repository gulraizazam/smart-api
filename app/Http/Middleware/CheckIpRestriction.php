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
                $userIp=$request->ip();
                dd($userIp);
            } else {
                $userRole = $user->role; // Assuming 'role' is the column name
            }
                
            $restrictedIps = config("ip_restrictions.restricted_ips.{$userRole}", []);

            // Check if the user's IP is in the allowed list
            if (($userRole == 'CSR' || $userRole == 'CSR Supervisor') && in_array($userIp, $restrictedIps)) {
                // Redirect to unauthorized page if the IP is restricted
                return redirect('unauthorized'); // Ensure this route exists
            }

            
        }
        return $next($request);
    }
}
