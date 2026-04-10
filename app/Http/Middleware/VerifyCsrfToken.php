<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/api/*',
    ];

    public function handle($request, Closure $next): Response
    {
        // Round 4 Auth-C3 — the previous version unconditionally bypassed
        // CSRF whenever an `Authorization` header was present. That made
        // every cookie-authed POST CSRF-able from any origin: a victim's
        // browser running attacker JS could attach `Authorization: Bearer
        // anything` and the request would go through with the victim's
        // session cookie. We now only bypass CSRF for bearer-only API
        // calls (no session cookie present), and only when Sanctum
        // actually validates the token.
        if ($request->hasHeader('Authorization')
            && ! $request->hasCookie(config('session.cookie'))
            && auth()->guard('sanctum')->check()
        ) {
            return $next($request);
        }

        if (
            $this->isReading($request) ||
            $this->runningUnitTests() ||
            $this->inExceptArray($request) ||
            $this->tokensMatch($request)
        ) {
            return tap($next($request), function ($response) use ($request) {
                if ($this->shouldAddXsrfTokenCookie()) {
                    $this->addCookieToResponse($request, $response);
                }
            });
        }

        throw new TokenMismatchException('CSRF token mismatch.');
    }
}
