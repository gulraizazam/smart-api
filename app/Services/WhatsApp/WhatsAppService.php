<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
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

        $upload = Http::withToken($this->token)
            ->attach('file', $binary, $filename, ['Content-Type' => $mime])
            ->post("{$base}/media", ['messaging_product' => 'whatsapp', 'type' => $mime]);

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

        $response = Http::withToken($this->token)->post("{$base}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
            'type' => $type,
            $type => $media,
        ]);

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
        $meta = Http::withToken($this->token)->get("https://graph.facebook.com/{$apiVersion}/{$mediaId}");
        $url = $meta->json('url');

        if ($meta->failed() || ! is_string($url) || $url === '') {
            Log::error('WhatsApp: media URL resolve failed', [
                'media_id' => $mediaId,
                'http_status' => $meta->status(),
            ]);

            return null;
        }

        $binary = Http::withToken($this->token)->get($url);

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
        $response = Http::withToken($this->token)->post($this->baseUrl, $payload);

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
