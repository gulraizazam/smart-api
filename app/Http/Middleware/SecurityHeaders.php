<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers that the audit (2026-06) found
 * missing from api.cutera.pk. Additive and consumer-safe:
 *
 *   - No Content-Security-Policy is set here — api.cutera.pk serves JSON,
 *     not the SPA HTML, so a CSP belongs at the crm3 static-host layer.
 *   - Each header is only set if absent, so a controller that needs a
 *     different value (e.g. an embeddable widget) can still override.
 *   - HSTS is emitted only over HTTPS (browsers ignore it on plain HTTP).
 *
 * crm2 coexistence: this middleware lives in the crm3/api app only. crm2
 * is a separate application on its own host, so these headers never touch
 * what crm2 serves — nothing it reads from the shared DB is affected.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($defaults as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->secure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
