<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Round 4 Auth-C1 — progressive CAPTCHA gate on the login endpoint.
 *
 * Cloudflare Turnstile is picked for:
 *   • free, no per-site-key pricing tier to forget
 *   • no third-party tracking (GDPR-friendly)
 *   • server-side siteverify is a single POST — no SDK required
 *
 * Behaviour:
 *   • Disabled entirely unless BOTH env keys are configured. New deploys
 *     don't get captcha-prompted by accident.
 *   • Tracks failed attempts per source IP in the cache (TTL = WINDOW_MINUTES).
 *   • Once the IP has accumulated THRESHOLD misses inside the window, the
 *     next login attempt must carry a valid `cf-turnstile-response` token.
 *   • A successful sign-in (NOT just a valid captcha) clears the counter.
 *
 * Why per-IP rather than per-email? An attacker spreading guesses across
 * many email addresses wouldn't trip a per-email counter; an IP counter
 * catches that pattern while still tolerating occasional typos by a
 * single operator from a shared office NAT.
 */
final class LoginCaptchaGate
{
    /** Number of misses from an IP before captcha becomes required. */
    public const THRESHOLD = 3;

    /** Rolling window (minutes) during which misses accumulate. */
    public const WINDOW_MINUTES = 15;

    /** Cloudflare Turnstile siteverify endpoint. */
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Both keys must be set for the gate to activate. Kept as a runtime
     * check (rather than a constructor-time hard requirement) so local
     * dev boxes without Turnstile provisioned just skip the gate
     * entirely — no captcha challenge appears.
     */
    public function isEnabled(): bool
    {
        return ! empty(config('services.turnstile.site_key'))
            && ! empty(config('services.turnstile.secret_key'));
    }

    /** Public site key for the frontend widget (safe to expose). */
    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /** Is this IP currently past the miss threshold? */
    public function isRequired(string $ip): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }
        return $this->getMissCount($ip) >= self::THRESHOLD;
    }

    public function recordMiss(string $ip): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $key = $this->cacheKey($ip);
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addMinutes(self::WINDOW_MINUTES));
    }

    public function clear(string $ip): void
    {
        Cache::forget($this->cacheKey($ip));
    }

    /**
     * Validate a Turnstile token against Cloudflare's siteverify endpoint.
     * Returns true on a verifiable solve, false otherwise (missing token,
     * bad signature, expired token, network error).
     *
     * Fails closed: a network blip to Cloudflare returns false rather than
     * letting the login through — the cost is a user retry, not a bypass.
     */
    public function verify(?string $token, string $ip): bool
    {
        if (! $this->isEnabled() || $token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(4)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            if (! $response->ok()) {
                return false;
            }

            return (bool) ($response->json('success') ?? false);
        } catch (Throwable $e) {
            Log::warning('Turnstile siteverify failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getMissCount(string $ip): int
    {
        return (int) Cache::get($this->cacheKey($ip), 0);
    }

    private function cacheKey(string $ip): string
    {
        return 'login_captcha:misses:'.$ip;
    }
}
