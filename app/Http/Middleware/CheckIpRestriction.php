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
            
            $allowedIps = [
                '103.8.112.42', '103.8.112.43', '103.8.112.107', 
                '203.215.176.205', '203.215.176.206', '203.215.181.201', 
                '203.215.181.206', '202.69.38.28',
            ];
            
            // Check if the user has a restricted role
            if (method_exists($user, 'hasRole')) {
                $userRole = $user->getRoleNames()->first();
            } else {
                $userRole = $user->role;
            }
            $userIp = $request->ip();
            
            // If the role is 'CSR' or 'CSR Supervisor', apply IP restriction
            if (($userRole == 'CSR' || $userRole == 'CSR Supervisor')) {
               
                
                if (!in_array($userIp, $allowedIps)) {
                    Auth::logout();
                    return redirect('unauthorized');
                }
            }
        }

        return $next($request);
    }
}
