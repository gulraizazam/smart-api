<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over Telnyx's REST + WebRTC surface.
 *
 * Everything the click-to-call feature needs on the server:
 *   • per-agent telephony credential (idempotent, cached by agent id) so the
 *     browser SDK can register a SIP endpoint,
 *   • short-lived login token minted from that credential (browser feeds it
 *     into `new TelnyxRTC({login_token})`),
 *   • recording start command sent when a call answers,
 *   • Ed25519 webhook signature verification (Telnyx uses libsodium detached
 *     signatures, NOT HMAC-SHA256 the way Plivo did),
 *   • streaming download of a completed recording.
 *
 * The class is deliberately stateless — dependencies come from
 * `config('services.telnyx')` and are re-read on every call so a `.env`
 * change + `config:clear` takes effect without a re-deploy.
 */
final class TelnyxVoiceService
{
    /** Cache key template for a per-agent telephony credential row. */
    private const CREDENTIAL_CACHE_KEY = 'telnyx:credential:%d';

    /** Credentials never expire on Telnyx's side; a month keeps cache warm. */
    private const CREDENTIAL_CACHE_TTL_SECONDS = 60 * 60 * 24 * 30;

    /**
     * Login-token lifetime (seconds). Telnyx caps at 3600 for login tokens.
     * 1 hour is plenty for a session-scoped mint; the SPA re-fetches on
     * refresh.
     */
    public const TOKEN_LIFETIME_SECONDS = 3600;

    public function __construct(
        private readonly ?PendingRequest $http = null,
        private readonly ?GuzzleClient $downloadHttp = null,
    ) {}

    // -------------------------------------------------------------------
    // Telephony credential + browser login token
    // -------------------------------------------------------------------

    /**
     * Idempotently ensure the agent has a telephony credential, return its
     * cached record.
     *
     * The credential's `tag` is `agent-{user_id}` (stable, per-tenant unique).
     * On the first mint we try `POST /v2/telephony_credentials`; if Telnyx
     * says "already exists" we look it up by tag filter. The full record
     * (id, sip_username, sip_password) gets cached to skip the round-trips
     * on subsequent mints.
     *
     * @return array{id:string,sip_username:string,name:string}
     */
    public function ensureCredentialForAgent(User $agent): array
    {
        $cacheKey = sprintf(self::CREDENTIAL_CACHE_KEY, $agent->id);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['id'], $cached['sip_username'])) {
            return $cached;
        }

        $tag = self::credentialTag($agent);

        // Look-up-by-tag first: a prior mint may have written the credential
        // but had its cache evicted. Cheaper than an idempotency dance.
        $found = $this->api()->get('/telephony_credentials', [
            'filter[tag]' => $tag,
        ]);
        if ($found->successful()) {
            $rows = (array) $found->json('data', []);
            if (! empty($rows) && is_array($rows[0]) && isset($rows[0]['id'])) {
                $record = $this->normalizeCredential($rows[0]);
                Cache::put($cacheKey, $record, self::CREDENTIAL_CACHE_TTL_SECONDS);
                return $record;
            }
        }

        // Not found — create it. Telephony credentials MUST be tied to a
        // Credential Connection; the Call Control App ID would 422.
        $created = $this->api()->post('/telephony_credentials', [
            'connection_id' => $this->requireConfig('credential_connection_id'),
            'name' => 'agent-'.$agent->id,
            'tag' => $tag,
        ]);
        if (! $created->successful()) {
            throw new \RuntimeException(
                'Telnyx credential create failed: '.$created->status().' '.$created->body(),
            );
        }
        $record = $this->normalizeCredential((array) $created->json('data', []));
        Cache::put($cacheKey, $record, self::CREDENTIAL_CACHE_TTL_SECONDS);
        return $record;
    }

    /**
     * Mint a short-lived login token the browser SDK uses to register.
     *
     * @return array{login_token:string,sip_username:string,expires_in:int}
     */
    public function mintBrowserToken(User $agent): array
    {
        $credential = $this->ensureCredentialForAgent($agent);

        // Telnyx returns the login token as plain text (application/text) in
        // the body — NOT as JSON. Http's ->body() gives us the raw string.
        $tokenResp = $this->api()->post(
            "/telephony_credentials/{$credential['id']}/token",
            ['ttl_seconds' => self::TOKEN_LIFETIME_SECONDS],
        );
        if (! $tokenResp->successful()) {
            throw new \RuntimeException(
                'Telnyx token mint failed: '.$tokenResp->status().' '.$tokenResp->body(),
            );
        }

        return [
            'login_token' => trim((string) $tokenResp->body()),
            'sip_username' => $credential['sip_username'],
            'expires_in' => self::TOKEN_LIFETIME_SECONDS,
        ];
    }

    /** The SIP URI a browser SDK gets when its credential registers. */
    public function agentSipUri(User $agent): string
    {
        $credential = $this->ensureCredentialForAgent($agent);
        return "sip:{$credential['sip_username']}@sip.telnyx.com";
    }

    // -------------------------------------------------------------------
    // Call Control commands
    // -------------------------------------------------------------------

    /**
     * Originate an outbound call leg via the Call Control App. Used for
     * BOTH the customer's PSTN leg (to = +92…) and the agent's SIP leg
     * (to = sip:username@sip.telnyx.com) — same API, different `to` shape.
     *
     * `clientState` is a small map ({lead_call_id, side}) that Telnyx
     * echoes on every subsequent webhook for this leg, so we can key our
     * lead_calls row without an extra round-trip.
     *
     * @param  array<string,int|string>  $clientState
     * @return array{call_control_id:string,call_leg_id:string}
     */
    public function originateOutbound(
        string $to,
        string $from,
        array $clientState = [],
        string $webhookUrl = '',
    ): array {
        $body = [
            'connection_id' => $this->requireConfig('call_control_app_id'),
            'to' => $to,
            'from' => $from,
            'timeout_secs' => 30,
        ];
        if ($clientState !== []) {
            $body['client_state'] = self::encodeClientState($clientState);
        }
        if ($webhookUrl !== '') {
            $body['webhook_url'] = $webhookUrl;
            $body['webhook_url_method'] = 'POST';
        }

        $resp = $this->api()->post('/calls', $body);
        if (! $resp->successful()) {
            throw new \RuntimeException(
                'Telnyx originate failed: '.$resp->status().' '.$resp->body(),
            );
        }

        $data = (array) $resp->json('data', []);
        return [
            'call_control_id' => (string) ($data['call_control_id'] ?? ''),
            'call_leg_id' => (string) ($data['call_leg_id'] ?? ''),
        ];
    }

    /**
     * Bridge two active Call Control legs into a single conversation.
     * Called from the answer webhook once BOTH the customer and agent
     * legs are answered. Telnyx replies 200 immediately; bridge event
     * arrives via webhook shortly after.
     *
     * Returns false on 4xx/5xx (logged inside) so the caller can decide
     * whether to retry or fail the LeadCall row.
     */
    public function bridgeCalls(string $ccidA, string $ccidB): bool
    {
        $resp = $this->api()->post("/calls/{$ccidA}/actions/bridge", [
            'call_control_id' => $ccidB,
        ]);
        if ($resp->successful()) {
            return true;
        }

        Log::warning('Telnyx bridge non-2xx', [
            'event' => 'telnyx.bridge.failed',
            'ccid_a' => $ccidA,
            'ccid_b' => $ccidB,
            'status' => $resp->status(),
            'body' => $resp->body(),
        ]);
        return false;
    }

    /**
     * Start recording a live call. Called from the answer webhook so we
     * only bill/record legs the customer actually picked up.
     *
     * @return bool true on 2xx, false on 4xx/5xx (already-recording is fine
     *              — Telnyx returns 422 in that case; we log + swallow).
     */
    public function startRecording(string $callControlId): bool
    {
        $resp = $this->api()->post("/calls/{$callControlId}/actions/record_start", [
            'format' => 'mp3',
            'channels' => 'single',
        ]);
        if ($resp->successful()) {
            return true;
        }

        // 422 = already recording (Telnyx enforces one recording per leg).
        // Treat as success — the leg IS being recorded, just not by this
        // call. Any other failure is worth a warning + returning false so
        // the caller can decide.
        if ($resp->status() === 422) {
            return true;
        }

        Log::warning('Telnyx record_start non-2xx', [
            'event' => 'telnyx.record_start.failed',
            'call_control_id' => $callControlId,
            'status' => $resp->status(),
            'body' => $resp->body(),
        ]);
        return false;
    }

    // -------------------------------------------------------------------
    // Webhook signature verification (Ed25519)
    // -------------------------------------------------------------------

    /**
     * Verify Telnyx's Ed25519 signature on a callback.
     *
     * Telnyx posts two headers per webhook:
     *   Telnyx-Signature-Ed25519-Signature   — base64 sig of `{ts}|{raw body}`
     *   Telnyx-Signature-Ed25519-Timestamp   — Unix seconds
     *
     * We reject anything older than `signature_max_age_seconds` (replay
     * protection) BEFORE running the signature check, so an attacker with
     * a valid old signature can't replay it forever.
     *
     * Requires ext-sodium (bundled with PHP 8.4; must be enabled server-side).
     */
    public function verifyWebhookSignature(
        string $signatureHeader,
        string $timestampHeader,
        string $rawBody,
    ): bool {
        if ($signatureHeader === '' || $timestampHeader === '') {
            return false;
        }

        if (! ctype_digit($timestampHeader)) {
            return false;
        }

        $maxAge = (int) $this->configValue('signature_max_age_seconds', 300);
        if ($maxAge > 0 && abs(time() - (int) $timestampHeader) > $maxAge) {
            return false;
        }

        $publicKeyB64 = $this->requireConfig('public_key');
        $publicKey = base64_decode($publicKeyB64, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            Log::warning('Telnyx public_key config is not a valid 32-byte Ed25519 key', [
                'event' => 'telnyx.pubkey.invalid',
                'bytes' => $publicKey === false ? 0 : strlen($publicKey),
            ]);
            return false;
        }

        $signature = base64_decode($signatureHeader, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $message = $timestampHeader.'|'.$rawBody;

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
        } catch (\Throwable $e) {
            Log::warning('Telnyx signature verify raised', [
                'event' => 'telnyx.signature.exception',
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // -------------------------------------------------------------------
    // Recording download
    // -------------------------------------------------------------------

    /**
     * Fetch the recording bytes from Telnyx's recording URL.
     *
     * The URL Telnyx puts in `call.recording.saved.payload.recording_urls.mp3`
     * is a pre-signed S3 URL — no auth header needed. Streams the response
     * to the given writable sink; returns [sha256 hex, byte count].
     *
     * @return array{sha256:string,bytes:int}
     */
    public function downloadRecording(string $url, $sink): array
    {
        $http = $this->downloadHttp ?? new GuzzleClient([
            'timeout' => 120,
            'connect_timeout' => 30,
        ]);

        $response = $http->request('GET', $url, [
            'stream' => true,
        ]);

        $body = $response->getBody();
        $ctx = hash_init('sha256');
        $bytes = 0;
        while (! $body->eof()) {
            $chunk = $body->read(64 * 1024);
            if ($chunk === '') {
                continue;
            }
            hash_update($ctx, $chunk);
            $bytes += strlen($chunk);
            fwrite($sink, $chunk);
        }

        return [
            'sha256' => hash_final($ctx),
            'bytes' => $bytes,
        ];
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Base64-encode a small associative array to pass as `client_state` on a
     * Telnyx call. Every subsequent webhook echoes this back, so it's how we
     * carry `lead_call_id` from the browser through to `call.recording.saved`.
     *
     * @param  array<string,int|string>  $payload
     */
    public static function encodeClientState(array $payload): string
    {
        return base64_encode((string) json_encode($payload));
    }

    /** @return array<string,mixed> */
    public static function decodeClientState(?string $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function credentialTag(User $agent): string
    {
        return 'agent-'.$agent->id;
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array{id:string,sip_username:string,name:string}
     */
    private function normalizeCredential(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'sip_username' => (string) ($row['sip_username'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }

    private function api(): PendingRequest
    {
        if ($this->http !== null) {
            return $this->http;
        }
        return Http::withToken($this->requireConfig('api_key'))
            ->baseUrl('https://api.telnyx.com/v2')
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function requireConfig(string $key): string
    {
        $value = Config::get('services.telnyx.'.$key);
        if (! is_string($value) || $value === '') {
            throw new \RuntimeException("services.telnyx.{$key} is not configured");
        }
        return $value;
    }

    private function configValue(string $key, mixed $default = null): mixed
    {
        return Config::get('services.telnyx.'.$key, $default);
    }
}
