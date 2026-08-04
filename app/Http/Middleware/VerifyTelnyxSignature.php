<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Voice\TelnyxVoiceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Verifies Telnyx's Ed25519 signature on every webhook hit.
 *
 * Telnyx sends two headers per callback:
 *   Telnyx-Signature-Ed25519-Signature  — base64 sig of `{ts}|{raw body}`
 *   Telnyx-Signature-Ed25519-Timestamp  — Unix seconds
 *
 * A missing/bad/replayed signature returns 401 with no body — never expose
 * why so an attacker can't distinguish "no signature" from "bad signature"
 * from "replayed".
 *
 * Critical detail: we sign against the RAW request body, so this middleware
 * must run BEFORE Laravel parses the JSON. It calls `$request->getContent()`
 * once (Symfony caches it) so downstream `$request->json()` still works.
 */
final class VerifyTelnyxSignature
{
    public function __construct(private readonly TelnyxVoiceService $telnyx)
    {
    }

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $signature = (string) $request->header('Telnyx-Signature-Ed25519-Signature', '');
        $timestamp = (string) $request->header('Telnyx-Signature-Ed25519-Timestamp', '');

        if ($signature === '' || $timestamp === '') {
            return $this->reject('missing_signature_headers');
        }

        // Raw body — Symfony caches this on first read so downstream
        // $request->json() / ->input() still work.
        $rawBody = (string) $request->getContent();

        $ok = $this->telnyx->verifyWebhookSignature($signature, $timestamp, $rawBody);
        if (! $ok) {
            Log::warning('Telnyx signature failed', [
                'event' => 'telnyx.signature.failed',
                'path' => $request->getPathInfo(),
                'method' => $request->method(),
                'body_bytes' => strlen($rawBody),
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
