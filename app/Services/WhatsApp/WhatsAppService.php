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
