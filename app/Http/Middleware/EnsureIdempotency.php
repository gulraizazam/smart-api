<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key middleware.
 *
 * When a state-changing request includes an `Idempotency-Key` header,
 * the eventual response is cached for 24 hours keyed on
 * (user, method+path, key). Subsequent requests with the same key
 * return the cached response instead of re-running the handler. This
 * catches:
 *
 *   - Operator double-click
 *   - Flaky network retry (lost ACK → SPA retries the POST)
 *   - SPA accidental double-submit
 *   - Adversarial replay (a captured-and-replayed valid POST returns
 *     the prior response, NOT a fresh second write)
 *
 * The middleware is OPT-IN: requests without the header fall through
 * to the handler unchanged. The SPA adopts at its own pace; backend
 * is ready for the header from the moment this ships.
 *
 * Security guarantees:
 *   - Key scoped per (user, method+path). One user cannot replay
 *     another's key. Cross-route key collision is impossible.
 *   - Payload-hash check. Same key with a different body → 409 — an
 *     attacker who captured a key cannot reuse it with a mutated
 *     amount / patient_id / payment_mode_id.
 *   - Strict key format: 8–128 URL-safe chars. Rejects garbage and
 *     prevents cache-key injection.
 *   - Only 2xx responses are cached. Errors don't poison the cache;
 *     the caller can retry after a fix.
 *   - In-flight reservation via `Cache::add` so two concurrent
 *     requests with the same key serialise: the second sees 409 and
 *     retries against the cached response once the first completes.
 *   - Replay attempts are logged at info level so operators can
 *     spot anomalous usage.
 *
 * Backed by file cache (per CLAUDE.md infra rule "no Redis"). File
 * cache supports atomic `add()` via flock, which is what the in-flight
 * reservation relies on.
 */
class EnsureIdempotency
{
    /** @var string */
    private const HEADER = 'Idempotency-Key';

    /** Cached response TTL — long enough to catch retries, short enough to bound storage. */
    private const TTL_SECONDS = 86_400; // 24h

    /** In-flight reservation TTL — a longer-running handler couldn't finish in 60s anyway. */
    private const INFLIGHT_TTL_SECONDS = 60;

    /** Allowed key length range. */
    private const KEY_MIN = 8;

    private const KEY_MAX = 128;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header(self::HEADER, '');

        // Opt-out: no header → handler runs unchanged. Existing callers
        // keep working until they choose to send the header.
        if ($key === '') {
            return $next($request);
        }

        if (! $this->isKeyWellFormed($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key must be 8-128 URL-safe characters (A-Z, a-z, 0-9, _ or -).',
            ], 400);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            // No auth context. Don't try to scope blindly — let the auth
            // middleware do its job (the route is already auth-gated so
            // this path is anomalous; just pass through).
            return $next($request);
        }

        $routeFingerprint = sha1($request->method().'|'.$request->path());
        $cacheKey = sprintf('idempotency:%d:%s:%s', $userId, $routeFingerprint, $key);
        $inflightKey = $cacheKey.':in-flight';

        $bodyHash = hash('sha256', $request->getContent() ?: '');

        // 1. Existing cached response → return it (or 409 on payload mismatch).
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->respondFromCache($cached, $bodyHash, $cacheKey);
        }

        // 2. Reserve the in-flight slot atomically. `Cache::add` only
        //    writes if the key doesn't exist — race-safe on the file
        //    driver (uses flock under the hood).
        $reserved = Cache::add($inflightKey, $userId, self::INFLIGHT_TTL_SECONDS);
        if (! $reserved) {
            Log::info('Idempotency: in-flight reservation already held', [
                'user_id' => $userId,
                'route' => $request->method().' '.$request->path(),
                'key' => $key,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'A previous request with this Idempotency-Key is still being processed — retry shortly.',
            ], 409);
        }

        // 3. Run the real handler. Always clear the reservation in finally
        //    so a thrown exception doesn't hold the slot for 60 seconds.
        try {
            $response = $next($request);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                Cache::put(
                    $cacheKey,
                    [
                        'status' => $status,
                        'body' => $this->serialiseBody($response),
                        'body_hash' => $bodyHash,
                        'headers' => $this->relevantHeaders($response),
                    ],
                    now()->addSeconds(self::TTL_SECONDS),
                );
            }

            return $response;
        } finally {
            Cache::forget($inflightKey);
        }
    }

    private function isKeyWellFormed(string $key): bool
    {
        $length = strlen($key);

        return $length >= self::KEY_MIN
            && $length <= self::KEY_MAX
            && preg_match('/^[A-Za-z0-9_-]+$/', $key) === 1;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function respondFromCache(array $cached, string $bodyHash, string $cacheKey): Response
    {
        // Payload mismatch — attacker (or buggy client) reused a key with
        // a different body. Reject hard.
        if (($cached['body_hash'] ?? null) !== $bodyHash) {
            Log::warning('Idempotency: key reused with different payload — refusing replay', [
                'cache_key' => $cacheKey,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key reused with a different request payload — refusing the replay.',
            ], 409);
        }

        $body = $cached['body'] ?? null;
        $status = (int) ($cached['status'] ?? IlluminateResponse::HTTP_OK);

        $response = is_array($body) || is_object($body)
            ? response()->json($body, $status)
            : response((string) $body, $status);

        // Hint to the SPA that the response was served from idempotency
        // cache so it can show "already processed" UX if it wants.
        $response->headers->set(self::HEADER.'-Replay', 'true');

        return $response;
    }

    private function serialiseBody(Response $response): mixed
    {
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if ($decoded !== null || json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $content;
    }

    /**
     * Only preserve content-type from the original response. Other
     * headers (Set-Cookie, Authorization, Cache-Control) are not
     * replayed — replayed responses are deliberately bare to avoid
     * leaking auth artefacts.
     *
     * @return array<string, string>
     */
    private function relevantHeaders(Response $response): array
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' ? [] : ['Content-Type' => $contentType];
    }
}
