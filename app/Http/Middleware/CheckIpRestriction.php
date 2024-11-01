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
              
            } else {
                $userRole = $user->role; // Assuming 'role' is the column name
            }
               
            $allowedIps = ['103.8.112.42','103.8.112.43','103.8.112.107','203.215.176.205','203.215.176.206','203.215.181.201','203.215.181.206','202.69.38.28','202.166.167.242','59.103.217.50'];

            // Check if the user's IP is in the allowed list
            if (($userRole == 'CSR' || $userRole == 'CSR Supervisor') && in_array($userIp, $allowedIps)) {
                return $next($request);
                 // Ensure this route exists
            }
            return redirect('unathorized');
            
        }
        
    }
}
