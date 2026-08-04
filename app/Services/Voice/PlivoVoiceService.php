<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\LeadCall;
use App\Models\Leads;
use App\Models\User;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Plivo\Exceptions\PlivoRestException;
use Plivo\RestClient;
use Plivo\Util\AccessToken;
use Plivo\Util\v3SignatureValidation;

/**
 * Thin wrapper over Plivo's REST + XML surface.
 *
 * Everything the click-to-call feature needs on the server:
 *   • mint a per-agent JWT that lets the browser SDK register as a
 *     Plivo endpoint and place/receive calls,
 *   • generate the Answer XML Plivo requests when a call connects
 *     (outbound: recording notice + Dial to the customer, record=true
 *      inbound:  recording notice + Dial to the target agent),
 *   • verify incoming webhook signatures (Plivo signs every callback),
 *   • download a completed recording so we can store it locally / on R2.
 *
 * The class is deliberately stateless — dependencies come from
 * `config('services.plivo')` and are re-read on every call so a
 * `.env` change + `config:clear` takes effect without a re-deploy.
 */
final class PlivoVoiceService
{
    /**
     * Endpoint IDs are stable per Plivo account; cache them so a token
     * mint doesn't cost a round-trip after the first miss.
     */
    private const ENDPOINT_CACHE_KEY = 'plivo:endpoint:%d';

    private const ENDPOINT_CACHE_TTL_SECONDS = 60 * 60 * 24 * 30; // 30 days

    /**
     * Plivo AccessToken lifetime: needs to be in [180, 86400]. 1 hour is
     * plenty for a session-scoped mint; the SPA re-fetches on refresh.
     */
    public const TOKEN_LIFETIME_SECONDS = 3600;

    public function __construct(
        private readonly ?RestClient $client = null,
        private readonly ?HttpClient $http = null,
    ) {}

    // -------------------------------------------------------------------
    // Endpoint provisioning + browser JWT
    // -------------------------------------------------------------------

    /**
     * Idempotently ensure the agent has a Plivo endpoint, return its username.
     *
     * The username is `agent-{user_id}` (stable, opaque). Plivo endpoints
     * within an application must have unique usernames, so on the first
     * mint we try to create; if Plivo says "already exists" we look it up
     * by alias. The endpoint ID gets cached to skip both round-trips on
     * subsequent mints.
     */
    public function ensureEndpointForAgent(User $agent): string
    {
        $username = self::endpointUsername($agent);
        $cacheKey = sprintf(self::ENDPOINT_CACHE_KEY, $agent->id);
        if (Cache::has($cacheKey)) {
            return $username;
        }

        $client = $this->requireClient();
        $appId = $this->requireConfig('app_id');

        try {
            $client->endpoint->create(
                $username,
                self::endpointPassword($agent), // Plivo requires a password; not used since we auth via JWT
                'agent-'.$agent->id,            // alias
                $appId,
            );
        } catch (PlivoRestException $e) {
            // 409-ish: endpoint already exists — that's the happy path
            // on the second+ mint for a returning agent. Any other error
            // (401 auth, 5xx from Plivo) rethrows.
            $msg = (string) $e->getMessage();
            if (! str_contains(strtolower($msg), 'already') && ! str_contains(strtolower($msg), 'exist')) {
                throw $e;
            }
        }

        Cache::put($cacheKey, true, self::ENDPOINT_CACHE_TTL_SECONDS);

        return $username;
    }

    /**
     * Mint a short-lived JWT the browser SDK uses to register.
     * Both incoming and outgoing voice grants are on — the same agent
     * needs to answer inbound callbacks from customers they called.
     */
    public function mintBrowserToken(User $agent): string
    {
        $username = $this->ensureEndpointForAgent($agent);

        $token = new AccessToken(
            $this->requireConfig('auth_id'),
            $this->requireConfig('auth_token'),
            $username,
            (int) gmdate('U'),
            self::TOKEN_LIFETIME_SECONDS,
        );
        $token->addVoiceGrants(true, true);

        return $token->toJwt();
    }

    // -------------------------------------------------------------------
    // Answer XML
    // -------------------------------------------------------------------

    /**
     * Answer XML Plivo requests when the agent's outbound call connects.
     *
     * Emits: <Speak>recording notice</Speak><Dial …><Number>{to}</Number></Dial>
     *
     * `record="true"` records the full session (both legs) as an MP3;
     * `recordingCallbackUrl` is where Plivo POSTs the recording metadata
     * once the call ends. `callbackUrl` is our status callback for
     * ring/answer/hangup transitions. `callbackMethod=POST` is the
     * project-wide default.
     */
    public function answerXmlForOutbound(LeadCall $call, string $baseUrl): string
    {
        $callerId = $this->requireConfig('caller_id');
        $status = $baseUrl.'/api/webhooks/plivo/status';
        $recording = $baseUrl.'/api/webhooks/plivo/recording';

        $noticeInline = htmlspecialchars(
            'This call is being recorded for quality and training purposes.',
            ENT_QUOTES | ENT_XML1,
        );
        $toNumber = htmlspecialchars($call->to_number, ENT_QUOTES | ENT_XML1);
        $callerIdEsc = htmlspecialchars($callerId, ENT_QUOTES | ENT_XML1);
        $statusEsc = htmlspecialchars($status, ENT_QUOTES | ENT_XML1);
        $recordingEsc = htmlspecialchars($recording, ENT_QUOTES | ENT_XML1);

        // Plivo XML: <Response> is the root; <Dial record="true"> records
        // the full bridged session. record_file_format=mp3 for compact storage.
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Response>
    <Speak>{$noticeInline}</Speak>
    <Dial callerId="{$callerIdEsc}"
          record="true"
          recordFileFormat="mp3"
          recordingCallbackUrl="{$recordingEsc}"
          recordingCallbackMethod="POST"
          callbackUrl="{$statusEsc}"
          callbackMethod="POST"
          timeout="30"
          hangupOnStar="false">
        <Number>{$toNumber}</Number>
    </Dial>
</Response>
XML;
    }

    /**
     * Answer XML Plivo requests when a customer's inbound call rings
     * our PK number. Match the from-number against `leads.phone`; if we
     * find a lead, route to its last-attempting agent (by SIP endpoint);
     * if not, fall through to a queue / voicemail (phase 1: hang up with
     * a polite Speak).
     */
    public function answerXmlForInbound(string $fromNumber, string $baseUrl): string
    {
        $callerId = htmlspecialchars($fromNumber, ENT_QUOTES | ENT_XML1);
        $noticeInline = htmlspecialchars(
            'This call is being recorded for quality and training purposes.',
            ENT_QUOTES | ENT_XML1,
        );
        $statusEsc = htmlspecialchars($baseUrl.'/api/webhooks/plivo/status', ENT_QUOTES | ENT_XML1);
        $recordingEsc = htmlspecialchars($baseUrl.'/api/webhooks/plivo/recording', ENT_QUOTES | ENT_XML1);

        $lead = Leads::query()
            ->whereNotNull('phone')
            ->where('phone', $fromNumber)
            ->orderByDesc('id')
            ->first();

        $agentEndpoint = null;
        if ($lead !== null) {
            $lastCall = LeadCall::query()
                ->where('lead_id', $lead->id)
                ->whereNotNull('user_id')
                ->orderByDesc('initiated_at')
                ->first();
            if ($lastCall !== null) {
                $agent = User::find($lastCall->user_id);
                if ($agent !== null) {
                    $agentEndpoint = htmlspecialchars(self::endpointUsername($agent), ENT_QUOTES | ENT_XML1);
                }
            }
        }

        if ($agentEndpoint === null) {
            // No routing target — polite hangup so the caller isn't
            // stuck listening to silence. Voicemail / queue is a
            // post-phase-1 upgrade.
            return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Response>
    <Speak>{$noticeInline}</Speak>
    <Speak>We could not connect your call. Please try again later.</Speak>
    <Hangup/>
</Response>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Response>
    <Speak>{$noticeInline}</Speak>
    <Dial callerId="{$callerId}"
          record="true"
          recordFileFormat="mp3"
          recordingCallbackUrl="{$recordingEsc}"
          recordingCallbackMethod="POST"
          callbackUrl="{$statusEsc}"
          callbackMethod="POST"
          timeout="30">
        <User>{$agentEndpoint}</User>
    </Dial>
</Response>
XML;
    }

    // -------------------------------------------------------------------
    // Webhook signature verification
    // -------------------------------------------------------------------

    /**
     * Verify Plivo's V3 signature on a callback.
     *
     * Plivo posts three headers we care about:
     *   X-Plivo-Signature-V3   — HMAC-SHA256 of (method|url|nonce|body params)
     *   X-Plivo-Signature-V3-Nonce — random nonce Plivo included in the sig
     *   X-Plivo-Signature-Ma-Version — expected "v3"
     *
     * The URL must be the exact URL Plivo was configured with (case, host,
     * query string) — mismatches almost always mean the app's public URL
     * was misconfigured, not that the signature is bad.
     */
    public function verifyWebhookSignature(
        string $method,
        string $url,
        string $nonce,
        string $signature,
        array $params,
    ): bool {
        try {
            return v3SignatureValidation::validateV3Signature(
                $method,
                $url,
                $nonce,
                $this->requireConfig('auth_token'),
                $signature,
                $params,
            );
        } catch (\Throwable $e) {
            Log::warning('Plivo signature verify raised', [
                'event' => 'plivo.signature.exception',
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // -------------------------------------------------------------------
    // Recording download
    // -------------------------------------------------------------------

    /**
     * Fetch the recording bytes from Plivo's CDN.
     * Streams the response to the given writable sink resource; returns
     * [sha256 hex, byte count] so the caller can persist metadata atomically.
     *
     * @return array{sha256:string,bytes:int}
     */
    public function downloadRecording(string $url, $sink): array
    {
        $http = $this->http ?? new HttpClient([
            'timeout' => 120,
            'connect_timeout' => 30,
        ]);

        $response = $http->request('GET', $url, [
            'auth' => [$this->requireConfig('auth_id'), $this->requireConfig('auth_token')],
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

    public static function endpointUsername(User $agent): string
    {
        return 'agent-'.$agent->id;
    }

    /**
     * Endpoint password is required by Plivo at create time but we never
     * use it — auth flows via short-lived JWT. Derive a deterministic
     * value from APP_KEY so the same endpoint can be re-created after a
     * cache wipe without breaking token mint.
     */
    private static function endpointPassword(User $agent): string
    {
        return substr(hash('sha256', 'plivo-ep:'.$agent->id.':'.config('app.key')), 0, 32);
    }

    private function requireClient(): RestClient
    {
        if ($this->client !== null) {
            return $this->client;
        }
        return new RestClient(
            $this->requireConfig('auth_id'),
            $this->requireConfig('auth_token'),
        );
    }

    private function requireConfig(string $key): string
    {
        $value = Config::get('services.plivo.'.$key);
        if (! is_string($value) || $value === '') {
            throw new \RuntimeException("services.plivo.{$key} is not configured");
        }
        return $value;
    }
}
