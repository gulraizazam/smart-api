<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Models\Patients;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the Meta webhook contract (WhatsAppWebhookController):
 * verify-token handshake on GET, HMAC-signed POST receive, conversation +
 * message storage, and wamid idempotency (Meta redelivers until 2xx).
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_TOKEN = 'test-verify-token';

    private const APP_SECRET = 'test-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.verify_token' => self::VERIFY_TOKEN,
            'whatsapp.app_secret' => self::APP_SECRET,
        ]);
    }

    public function test_get_verification_handshake_returns_the_challenge(): void
    {
        $response = $this->get(
            '/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=1158201444'
        );

        $response->assertOk();
        $this->assertSame('1158201444', $response->getContent());
    }

    public function test_get_verification_rejects_a_wrong_token(): void
    {
        $response = $this->get(
            '/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong-token&hub.challenge=1158201444'
        );

        $response->assertForbidden();
    }

    public function test_inbound_text_message_creates_conversation_and_message(): void
    {
        $sentAt = now()->subMinutes(5)->startOfSecond();

        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567',
            wamid: 'wamid.TEST_INBOUND_1==',
            text: 'Hello, I would like to book a consultation',
            timestamp: $sentAt->timestamp,
        ))->assertOk();

        $conversation = WhatsappConversation::where('wa_id', '923001234567')->first();
        $this->assertNotNull($conversation);
        $this->assertSame('Test Patient', $conversation->profile_name);
        $this->assertSame($sentAt->timestamp, $conversation->last_inbound_at?->timestamp);

        $message = $conversation->messages()->first();
        $this->assertNotNull($message);
        $this->assertSame('wamid.TEST_INBOUND_1==', $message->wamid);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('text', $message->type);
        $this->assertSame('Hello, I would like to book a consultation', $message->body);
        $this->assertSame('received', $message->status);
    }

    public function test_a_signed_post_stamps_the_webhook_heartbeat(): void
    {
        Cache::forget(WhatsAppWebhookController::LAST_WEBHOOK_KEY);

        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567',
            wamid: 'wamid.HEARTBEAT==',
            text: 'Hi',
            timestamp: now()->timestamp,
        ))->assertOk();

        $this->assertNotNull(Cache::get(WhatsAppWebhookController::LAST_WEBHOOK_KEY));
    }

    public function test_an_unsigned_post_does_not_stamp_the_heartbeat(): void
    {
        Cache::forget(WhatsAppWebhookController::LAST_WEBHOOK_KEY);
        $payload = $this->inboundTextPayload(waId: '923001234567', wamid: 'wamid.X==', text: 'Hi', timestamp: now()->timestamp);

        $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', json_encode($payload), 'wrong-secret'),
        ])->assertStatus(403);

        $this->assertNull(Cache::get(WhatsAppWebhookController::LAST_WEBHOOK_KEY));
    }

    public function test_duplicate_wamid_does_not_create_a_second_message(): void
    {
        $payload = $this->inboundTextPayload(
            waId: '923001234567',
            wamid: 'wamid.TEST_DUPLICATE==',
            text: 'Hello',
            timestamp: now()->timestamp,
        );

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk(); // Meta redelivery — must stay 200

        $this->assertSame(1, WhatsappMessage::where('wamid', 'wamid.TEST_DUPLICATE==')->count());
        $this->assertSame(1, WhatsappMessage::count());
        $this->assertSame(1, WhatsappConversation::count());
    }

    public function test_post_with_an_invalid_signature_is_rejected_and_stores_nothing(): void
    {
        $payload = $this->inboundTextPayload(
            waId: '923001234567',
            wamid: 'wamid.TEST_FORGED==',
            text: 'Forged',
            timestamp: now()->timestamp,
        );

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', json_encode($payload), 'wrong-secret'),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame(0, WhatsappConversation::count());
    }

    public function test_status_update_marks_the_outbound_message_by_wamid(): void
    {
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567']);
        $conversation->messages()->create([
            'wamid' => 'wamid.TEST_OUTBOUND_1==',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Your appointment is confirmed',
            'status' => 'accepted',
        ]);

        $this->postSigned([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => '111222333'],
                        'statuses' => [[
                            'id' => 'wamid.TEST_OUTBOUND_1==',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '923001234567',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertSame('delivered', WhatsappMessage::where('wamid', 'wamid.TEST_OUTBOUND_1==')->value('status'));
    }

    public function test_failed_status_captures_metas_error_on_the_message(): void
    {
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567']);
        $conversation->messages()->create([
            'wamid' => 'wamid.TEST_FAILED==',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'We offer hydrafacial at a discount',
            'status' => 'sent',
            'payload' => ['messages' => [['id' => 'wamid.TEST_FAILED==']]],
        ]);

        $this->postSigned([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => '111222333'],
                        'statuses' => [[
                            'id' => 'wamid.TEST_FAILED==',
                            'status' => 'failed',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '16468605037',
                            'errors' => [[
                                'code' => 131049,
                                'title' => 'This message was not delivered to maintain healthy ecosystem engagement.',
                                'error_data' => ['details' => 'Meta chose not to deliver this message.'],
                            ]],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $message = WhatsappMessage::where('wamid', 'wamid.TEST_FAILED==')->first();
        $this->assertSame('failed', $message->status);
        $this->assertSame(131049, $message->payload['error']['code']);
        $this->assertSame('This message was not delivered to maintain healthy ecosystem engagement.', $message->payload['error']['title']);
        // The original send response is preserved alongside the captured error.
        $this->assertSame('wamid.TEST_FAILED==', $message->payload['messages'][0]['id']);
    }

    public function test_inbound_image_stores_type_and_caption_as_body(): void
    {
        $this->postSigned([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => '111222333'],
                        'contacts' => [['profile' => ['name' => 'Test Patient'], 'wa_id' => '923001234567']],
                        'messages' => [[
                            'from' => '923001234567',
                            'id' => 'wamid.IMG_IN==',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'image',
                            'image' => [
                                'id' => 'MEDIA_IN_1',
                                'mime_type' => 'image/jpeg',
                                'caption' => 'Here is my skin concern',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $message = WhatsappMessage::where('wamid', 'wamid.IMG_IN==')->first();
        $this->assertNotNull($message);
        $this->assertSame('image', $message->type);
        // Caption is preserved as the displayable body; the media id stays in payload.
        $this->assertSame('Here is my skin concern', $message->body);
        $this->assertSame('MEDIA_IN_1', $message->payload['image']['id']);
    }

    public function test_inbound_audio_without_caption_stores_null_body(): void
    {
        $this->postSigned([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => '111222333'],
                        'contacts' => [['profile' => ['name' => 'Test Patient'], 'wa_id' => '923001234567']],
                        'messages' => [[
                            'from' => '923001234567',
                            'id' => 'wamid.AUDIO_IN==',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'audio',
                            'audio' => ['id' => 'MEDIA_AUDIO_1', 'mime_type' => 'audio/ogg', 'voice' => true],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $message = WhatsappMessage::where('wamid', 'wamid.AUDIO_IN==')->first();
        $this->assertNotNull($message);
        $this->assertSame('audio', $message->type);
        $this->assertNull($message->body);
    }

    public function test_inbound_stop_opts_the_customer_out_and_start_resubscribes(): void
    {
        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567', wamid: 'wamid.STOP==', text: 'STOP', timestamp: now()->timestamp,
        ))->assertOk();

        $conversation = WhatsappConversation::where('wa_id', '923001234567')->first();
        $this->assertNotNull($conversation->opted_out_at, 'STOP should opt the customer out');

        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567', wamid: 'wamid.START==', text: 'start', timestamp: now()->timestamp,
        ))->assertOk();

        $this->assertNull($conversation->fresh()->opted_out_at, 'START should re-subscribe');
    }

    public function test_one_stop_delivery_stamps_the_window_anchor_and_opts_out_together(): void
    {
        // The webhook applies last_inbound_at + opt-out in a single UPDATE — a
        // STOP message must both stamp the 24h-window anchor AND opt the
        // customer out from the same delivery.
        $sentAt = now()->subMinutes(2)->startOfSecond();

        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567', wamid: 'wamid.STOP_ONE==', text: 'STOP', timestamp: $sentAt->timestamp,
        ))->assertOk();

        $conversation = WhatsappConversation::where('wa_id', '923001234567')->first();
        $this->assertSame($sentAt->timestamp, $conversation->last_inbound_at?->timestamp, 'window anchor stamped');
        $this->assertNotNull($conversation->opted_out_at, 'opted out in the same delivery');
    }

    public function test_an_ordinary_message_does_not_opt_the_customer_out(): void
    {
        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567', wamid: 'wamid.NORMAL==', text: 'please do not stop messaging me', timestamp: now()->timestamp,
        ))->assertOk();

        $this->assertNull(WhatsappConversation::where('wa_id', '923001234567')->value('opted_out_at'));
    }

    public function test_inbound_message_links_a_matching_patient_by_phone(): void
    {
        // wa_id 923001234567 must reconcile with the locally-stored 03001234567.
        $patient = Patients::factory()->create(['phone' => '03001234567']);

        $this->postSigned($this->inboundTextPayload(
            waId: '923001234567', wamid: 'wamid.MATCH==', text: 'Hi', timestamp: now()->timestamp,
        ))->assertOk();

        $this->assertSame(
            $patient->id,
            WhatsappConversation::where('wa_id', '923001234567')->value('patient_id'),
        );
    }

    public function test_inbound_message_from_an_unknown_number_leaves_patient_unlinked(): void
    {
        $this->postSigned($this->inboundTextPayload(
            waId: '923009999999', wamid: 'wamid.NOMATCH==', text: 'Hi', timestamp: now()->timestamp,
        ))->assertOk();

        $this->assertNull(WhatsappConversation::where('wa_id', '923009999999')->value('patient_id'));
    }

    /**
     * POST the payload signed the way Meta signs it: X-Hub-Signature-256 =
     * sha256 HMAC of the raw JSON body, keyed with the app secret.
     */
    private function postSigned(array $payload): TestResponse
    {
        return $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', json_encode($payload), self::APP_SECRET),
        ]);
    }

    /**
     * A Cloud API inbound text notification, shaped exactly like Meta's
     * entry[].changes[].value.messages[] webhook payload.
     */
    private function inboundTextPayload(string $waId, string $wamid, string $text, int $timestamp): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => '111222333'],
                        'contacts' => [[
                            'profile' => ['name' => 'Test Patient'],
                            'wa_id' => $waId,
                        ]],
                        'messages' => [[
                            'from' => $waId,
                            'id' => $wamid,
                            'timestamp' => (string) $timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
