<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckIpRestriction
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Assuming a simple role-based check
            if ($user->hasRole(['CSR', 'CSR Supervisor', 'FDM'])) {
                $restrictedIps = ['103.8.112.42','103.8.112.43','103.8.112.107','203.215.176.205','203.215.176.206','203.215.181.201','203.215.181.206','202.69.38.28','59.103.217.50'];

                $userIp = $request->ip();

                if (!in_array($userIp, $restrictedIps)) {
                    // Log failed access attempt
                    \Log::warning('Unauthorized access attempt from IP: ' . $userIp . ' by user: ' . $user->email);

                    return redirect('unauthorized')->with('error', 'Access denied.');
                }
            }
        }

        return $next($request);
    }
}