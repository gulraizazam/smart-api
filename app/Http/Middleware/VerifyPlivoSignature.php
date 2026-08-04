<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Voice\PlivoVoiceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Verifies Plivo's V3 signature on every webhook hit.
 *
 * Plivo sends three headers with each callback:
 *   X-Plivo-Signature-V3          — HMAC-SHA256 of method|url|nonce|params
 *   X-Plivo-Signature-V3-Nonce    — the nonce Plivo used in the HMAC
 *   X-Plivo-Signature-Ma-Version  — expected "v3"
 *
 * A missing/bad signature returns 401 with no body — never expose why so
 * an attacker can't distinguish "no signature" from "bad signature".
 *
 * The URL fed to the verifier is the CANONICAL public URL of this
 * endpoint (scheme + host + path + query as configured on Plivo's side),
 * not whatever Laravel sees after Hostinger's reverse proxy. We reconstruct
 * it from APP_URL + the request path — mismatches almost always mean
 * APP_URL is wrong, not that Plivo lied.
 */
final class VerifyPlivoSignature
{
    public function __construct(private readonly PlivoVoiceService $plivo)
    {
    }

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $signature = (string) $request->header('X-Plivo-Signature-V3', '');
        $nonce = (string) $request->header('X-Plivo-Signature-V3-Nonce', '');
        if ($signature === '' || $nonce === '') {
            return $this->reject('missing_signature_headers');
        }

        // Public URL Plivo hit: APP_URL + the current path (+ query string).
        // Do NOT use $request->fullUrl() because Laravel behind Hostinger's
        // proxy sometimes reports http:// even though the real hit was https://.
        $appUrl = rtrim((string) config('app.url'), '/');
        $path = $request->getPathInfo();
        $query = $request->getQueryString();
        $canonicalUrl = $appUrl.$path.($query ? '?'.$query : '');

        // For POST callbacks Plivo signs body params; for GET it signs query.
        $params = $request->isMethod('POST')
            ? $request->post()
            : $request->query();

        $ok = $this->plivo->verifyWebhookSignature(
            $request->method(),
            $canonicalUrl,
            $nonce,
            $signature,
            is_array($params) ? $params : [],
        );

        if (! $ok) {
            Log::warning('Plivo signature failed', [
                'event' => 'plivo.signature.failed',
                'path' => $path,
                'method' => $request->method(),
                'canonical_url' => $canonicalUrl,
            ]);
            return $this->reject('signature_verification_failed');
        }

        return $next($request);
    }

    private function reject(string $reason): SymfonyResponse
    {
        // Empty body — never leak the reason to the caller.
        return new Response('', 401);
    }
}
