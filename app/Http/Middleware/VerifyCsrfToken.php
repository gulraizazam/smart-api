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
    // CSRF is enforced for cookie/session auth (the SPA). There is no
    // blanket /api/* bypass anymore (audit 2026-06) — stateless token
    // clients are handled by the no-session-cookie skip in handle().
    // The single targeted exception is the Meta WhatsApp webhook: Meta's
    // servers POST with no session and can never carry a CSRF token; the
    // request is authenticated by its X-Hub-Signature-256 HMAC instead
    // (WhatsAppWebhookController::signatureIsValid).
    protected $except = [
        'api/whatsapp/webhook',
        // Plivo posts server-to-server callbacks with no session cookie; the
        // plivo.webhook middleware verifies X-Plivo-Signature-V3 instead.
        'api/webhooks/plivo/*',
    ];

    public function handle($request, Closure $next): Response
    {
        // CSRF only matters for cookie/session auth, where the browser
        // auto-attaches the session cookie. A request with NO session
        // cookie is a stateless token client (Sanctum bearer / Passport /
        // mobile) or unauthenticated — neither can be CSRF'd — so skip the
        // token check. Cookie-authed requests (the SPA) still flow through
        // tokensMatch(); the SPA already sends the X-XSRF-TOKEN header.
        // (audit 2026-06: replaced the sanctum-only bypass and removed the
        // blanket /api/* exception that disabled CSRF for the whole API.)
        if (! $request->hasCookie(config('session.cookie'))) {
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
