<?php

namespace App\Http\Middleware;

use App\HelperModule\ApiHelper;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApiWeb
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            return $next($request);
        } elseif ($request->hasHeader('Authorization')) {
            if (auth()->guard('sanctum')->check()) {
                auth()->shouldUse('sanctum');

                return $next($request);
            }

            return ApiHelper::apiResponse(config('constants.api_status.unauthorized'), 'Invalid Token');
        }

        return redirect()->route('login');
    }
}
