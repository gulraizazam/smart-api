<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound side of the WhatsApp Cloud API (Meta) integration — Phase 1.
 *
 * Meta's messaging rules: free-form text may only be sent within the 24-hour
 * customer-service window (24h since the customer's last inbound message,
 * tracked on whatsapp_conversations.last_inbound_at). Outside the window,
 * only pre-approved template messages are allowed. sendText() enforces this;
 * sendTemplate() does not need to.
 *
 * Every attempted send is recorded as an outbound whatsapp_messages row
 * (status accepted/failed, wamid from Meta's response) so delivery-status
 * webhooks can update it later by wamid.
 */
class WhatsAppService
{
    public const SERVICE_WINDOW_HOURS = WhatsappConversation::SERVICE_WINDOW_HOURS;

    protected mixed $token;

    protected mixed $phoneNumberId;

    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('whatsapp.token');
        $this->phoneNumberId = config('whatsapp.phone_number_id');
        $apiVersion = config('whatsapp.api_version');
        $this->baseUrl = "https://graph.facebook.com/{$apiVersion}/{$this->phoneNumberId}/messages";
    }

    /**
     * True while the 24h customer-service window is open — i.e. the customer
     * has sent us a message within the last 24 hours.
     */
    public function windowIsOpen(WhatsappConversation $conversation): bool
    {
        return $conversation->windowIsOpen();
    }

    /**
     * Send a free-form text message. Refused (returns null, no API call) when
     * the 24h window is closed — use sendTemplate() instead in that case.
     */
    public function sendText(string $waId, string $text): ?WhatsappMessage
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $conversation = WhatsappConversation::firstOrCreate(['wa_id' => $waId]);

        if ($conversation->isOptedOut()) {
            Log::warning('WhatsApp: sendText refused — customer opted out', ['wa_id' => $waId]);

            return null;
        }

        if (! $this->windowIsOpen($conversation)) {
            Log::warning('WhatsApp: sendText refused — 24h service window is closed', [
                'wa_id' => $waId,
                'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
            ]);

            return null;
        }

        return $this->dispatch($conversation, 'text', $text, [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    /**
     * Send a pre-approved template message. Allowed regardless of the 24h
     * window — this is the only way to reach a customer outside it.
     *
     * @param  array<int, array<string, mixed>>  $components  Meta template components (header/body parameters)
     */
    public function sendTemplate(string $waId, string $templateName, string $language = 'en', array $components = []): ?WhatsappMessage
    {
        // Free-tier backstop: templates are the only PAID surface. Refuse them
        // unless free-tier mode has been deliberately turned off.
        if (config('whatsapp.free_tier_only')) {
            Log::warning('WhatsApp: sendTemplate refused — free_tier_only is on (paid templates disabled)', [
                'wa_id' => $waId,
                'template' => $templateName,
            ]);

            return null;
        }

        if (! $this->isConfigured()) {
            return null;
        }

        $conversation = WhatsappConversation::firstOrCreate(['wa_id' => $waId]);

        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];
        if ($components !== []) {
            $template['components'] = $components;
        }

        return $this->dispatch($conversation, 'template', $templateName, [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /**
     * Send an outbound media message (voice note / image / video / document).
     * Two hops: upload the bytes to Meta's media endpoint for a media id, then
     * send a message referencing it. Window-gated exactly like sendText —
     * media replies are only allowed inside the 24h service window. The stored
     * outbound row keeps the media id in its payload so the inbox can play the
     * file back through the media proxy.
     *
     * @param  'audio'|'image'|'video'|'document'  $type
     */
    public function sendMedia(string $waId, string $type, string $binary, string $mime, string $filename, ?string $caption = null): ?WhatsappMessage
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $conversation = WhatsappConversation::firstOrCreate(['wa_id' => $waId]);

        if ($conversation->isOptedOut()) {
            Log::warning('WhatsApp: sendMedia refused — customer opted out', ['wa_id' => $waId]);

            return null;
        }

        if (! $this->windowIsOpen($conversation)) {
            Log::warning('WhatsApp: sendMedia refused — 24h service window is closed', ['wa_id' => $waId]);

            return null;
        }

        $base = 'https://graph.facebook.com/'.config('whatsapp.api_version')."/{$this->phoneNumberId}";

        try {
            $upload = $this->mediaClient()
                ->attach('file', $binary, $filename, ['Content-Type' => $mime])
                ->post("{$base}/media", ['messaging_product' => 'whatsapp', 'type' => $mime]);
        } catch (ConnectionException $e) {
            Log::error('WhatsApp: media upload connection error', ['wa_id' => $waId, 'type' => $type, 'error' => $e->getMessage()]);

            return $conversation->messages()->create([
                'wamid' => null, 'direction' => 'outbound', 'type' => $type,
                'body' => null, 'status' => 'failed', 'payload' => ['error' => 'connection_error'],
            ]);
        }

        $mediaId = $upload->json('id');

        if ($upload->failed() || ! is_string($mediaId) || $mediaId === '') {
            Log::error('WhatsApp: media upload failed', [
                'wa_id' => $waId,
                'type' => $type,
                'http_status' => $upload->status(),
            ]);

            return $conversation->messages()->create([
                'wamid' => null,
                'direction' => 'outbound',
                'type' => $type,
                'body' => null,
                'status' => 'failed',
                'payload' => ['error' => 'upload_failed', 'response' => $upload->json()],
            ]);
        }

        $media = ['id' => $mediaId];
        if ($type === 'document') {
            $media['filename'] = $filename;
        }
        if ($caption !== null && $caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption; // audio/voice notes can't carry a caption
        }

        try {
            $response = $this->client()->post("{$base}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $waId,
                'type' => $type,
                $type => $media,
            ]);
        } catch (ConnectionException $e) {
            Log::error('WhatsApp: media send connection error', ['wa_id' => $waId, 'type' => $type, 'error' => $e->getMessage()]);

            return $conversation->messages()->create([
                'wamid' => null, 'direction' => 'outbound', 'type' => $type,
                'body' => $caption, 'status' => 'failed', 'payload' => ['media_id' => $mediaId, 'error' => 'connection_error'],
            ]);
        }

        if ($response->failed()) {
            Log::error('WhatsApp: media send failed', [
                'wa_id' => $waId,
                'type' => $type,
                'http_status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return $conversation->messages()->create([
            'wamid' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'type' => $type,
            'body' => $caption, // null for voice notes; the caption otherwise
            'status' => $response->successful() ? 'accepted' : 'failed',
            'payload' => ['media_id' => $mediaId] + (array) $response->json(),
        ]);
    }

    /**
     * Resolve and download an inbound media object by its Meta media id.
     * Two hops, both bearer-auth'd: GET /{media-id} returns a short-lived
     * signed URL; GET that URL returns the bytes. Returns the binary + its
     * mime type, or null when not configured / Meta refused / media expired.
     *
     * @return array{body: string, mime: string}|null
     */
    public function fetchMedia(string $mediaId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $apiVersion = config('whatsapp.api_version');

        try {
            $meta = $this->client()->get("https://graph.facebook.com/{$apiVersion}/{$mediaId}");
            $url = $meta->json('url');

            if ($meta->failed() || ! is_string($url) || $url === '') {
                Log::error('WhatsApp: media URL resolve failed', [
                    'media_id' => $mediaId,
                    'http_status' => $meta->status(),
                ]);

                return null;
            }

            $binary = $this->mediaClient()->get($url);
        } catch (ConnectionException $e) {
            // Timeout reaching Meta — the proxy turns null into a graceful
            // "attachment unavailable" rather than hanging the request.
            Log::error('WhatsApp: media fetch connection error', ['media_id' => $mediaId, 'error' => $e->getMessage()]);

            return null;
        }

        if ($binary->failed()) {
            Log::error('WhatsApp: media download failed', [
                'media_id' => $mediaId,
                'http_status' => $binary->status(),
            ]);

            return null;
        }

        return [
            'body' => $binary->body(),
            'mime' => $meta->json('mime_type') ?: ($binary->header('Content-Type') ?: 'application/octet-stream'),
        ];
    }

    /**
     * Bearer-auth'd HTTP client for control-plane Graph calls (send a message,
     * resolve a media URL). Bounded timeouts so a stalled Meta endpoint can't
     * hang the agent's "Send" request until PHP's max_execution_time.
     */
    protected function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->connectTimeout((int) config('whatsapp.connect_timeout', 5))
            ->timeout((int) config('whatsapp.timeout', 10));
    }

    /**
     * Like client() but with a longer ceiling for media byte transfers
     * (uploading an attachment / downloading inbound media), which can be a
     * few MB on a slow link.
     */
    protected function mediaClient(): PendingRequest
    {
        return Http::withToken($this->token)
            ->connectTimeout((int) config('whatsapp.connect_timeout', 5))
            ->timeout((int) config('whatsapp.media_timeout', 30));
    }

    protected function isConfigured(): bool
    {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning('WhatsApp: token or phone number id not configured — send skipped');

            return false;
        }

        return true;
    }

    /**
     * POST to the Cloud API and record the attempt as an outbound message row.
     */
    protected function dispatch(WhatsappConversation $conversation, string $type, string $body, array $payload): WhatsappMessage
    {
        try {
            $response = $this->client()->post($this->baseUrl, $payload);
        } catch (ConnectionException $e) {
            // Timeout / network error reaching Meta — record a failed row so the
            // agent sees "Failed · Retry" instead of a 500, and can resend.
            Log::error('WhatsApp: send connection error', [
                'wa_id' => $conversation->wa_id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $conversation->messages()->create([
                'wamid' => null,
                'direction' => 'outbound',
                'type' => $type,
                'body' => $body,
                'status' => 'failed',
                'payload' => ['error' => 'connection_error'],
            ]);
        }

        if ($response->failed()) {
            Log::error('WhatsApp: send failed', [
                'wa_id' => $conversation->wa_id,
                'type' => $type,
                'http_status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return $conversation->messages()->create([
            'wamid' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'type' => $type,
            'body' => $body,
            'status' => $response->successful() ? 'accepted' : 'failed',
            'payload' => $response->json(),
        ]);
    }
}
