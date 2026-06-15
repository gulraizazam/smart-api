<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins WhatsAppService's outbound contract: the 24h customer-service window
 * (free text only while open, templates always) and the outbound message row
 * recorded for every attempted send. Network is faked at the Http boundary —
 * no real Graph API calls.
 */
class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.token' => 'test-token',
            'whatsapp.phone_number_id' => '111222333',
        ]);
    }

    public function test_window_is_open_only_within_24h_of_last_inbound(): void
    {
        $service = new WhatsAppService;

        $never = WhatsappConversation::create(['wa_id' => '923000000001']);
        $recent = WhatsappConversation::create(['wa_id' => '923000000002', 'last_inbound_at' => now()->subHours(23)]);
        $stale = WhatsappConversation::create(['wa_id' => '923000000003', 'last_inbound_at' => now()->subHours(25)]);

        $this->assertFalse($service->windowIsOpen($never));
        $this->assertTrue($service->windowIsOpen($recent));
        $this->assertFalse($service->windowIsOpen($stale));
    }

    public function test_send_text_inside_window_posts_to_graph_and_stores_outbound_row(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.SENT_1==']],
            ]),
        ]);

        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subHour()]);

        $message = (new WhatsAppService)->sendText('923001234567', 'Your appointment is confirmed');

        $this->assertNotNull($message);
        $this->assertSame('wamid.SENT_1==', $message->wamid);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('text', $message->type);
        $this->assertSame('accepted', $message->status);
        $this->assertSame('Your appointment is confirmed', $message->body);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/111222333/messages')
                && $request['to'] === '923001234567'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Your appointment is confirmed';
        });
    }

    public function test_send_text_outside_the_window_is_refused_without_an_api_call(): void
    {
        Http::fake();

        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subHours(25)]);

        $message = (new WhatsAppService)->sendText('923001234567', 'Too late for free-form text');

        $this->assertNull($message);
        $this->assertSame(0, WhatsappMessage::count());
        Http::assertNothingSent();
    }

    public function test_send_template_is_refused_by_the_free_tier_backstop(): void
    {
        Http::fake();
        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subDays(3)]);

        // free_tier_only defaults true — templates (the paid surface) are blocked.
        $this->assertNull((new WhatsAppService)->sendTemplate('923001234567', 'appointment_reminder'));
        Http::assertNothingSent();
    }

    public function test_send_template_is_allowed_when_free_tier_is_deliberately_off(): void
    {
        config(['whatsapp.free_tier_only' => false]); // deliberate opt-in to paid templates
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.TPL_1==']],
            ]),
        ]);

        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subDays(3)]);

        $message = (new WhatsAppService)->sendTemplate('923001234567', 'appointment_reminder', 'en');

        $this->assertNotNull($message);
        $this->assertSame('template', $message->type);
        $this->assertSame('accepted', $message->status);
        $this->assertSame('wamid.TPL_1==', $message->wamid);

        Http::assertSent(function ($request): bool {
            return $request['type'] === 'template'
                && $request['template']['name'] === 'appointment_reminder'
                && $request['template']['language']['code'] === 'en';
        });
    }

    public function test_failed_send_is_recorded_as_a_failed_row(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid recipient']], 400),
        ]);

        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subHour()]);

        $message = (new WhatsAppService)->sendText('923001234567', 'Hello');

        $this->assertNotNull($message);
        $this->assertSame('failed', $message->status);
        $this->assertNull($message->wamid);
    }

    public function test_send_records_a_failed_row_on_a_connection_timeout(): void
    {
        // A timeout / network error reaching Meta must NOT bubble a 500 to the
        // agent — it records a failed row so they see "Failed · Retry".
        Http::fake(function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out');
        });

        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subHour()]);

        $message = (new WhatsAppService)->sendText('923001234567', 'Hello');

        $this->assertNotNull($message);
        $this->assertSame('failed', $message->status);
        $this->assertNull($message->wamid);
        $this->assertSame('connection_error', $message->payload['error']);
    }

    public function test_send_media_records_a_failed_row_on_a_connection_timeout(): void
    {
        Http::fake(function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out');
        });

        WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()->subHour()]);

        $message = (new WhatsAppService)->sendMedia('923001234567', 'audio', 'oggbytes', 'audio/ogg', 'voice-note.ogg');

        $this->assertNotNull($message);
        $this->assertSame('failed', $message->status);
        $this->assertSame('connection_error', $message->payload['error']);
    }

    public function test_send_is_skipped_when_credentials_are_not_configured(): void
    {
        config(['whatsapp.token' => '', 'whatsapp.phone_number_id' => '']);
        Http::fake();

        $this->assertNull((new WhatsAppService)->sendText('923001234567', 'Hello'));
        Http::assertNothingSent();
    }

    public function test_send_is_refused_when_the_customer_opted_out(): void
    {
        Http::fake();
        // Window open, but the customer texted STOP — Meta policy: no sends.
        WhatsappConversation::create([
            'wa_id' => '923001234567',
            'last_inbound_at' => now(),
            'opted_out_at' => now(),
        ]);

        $this->assertNull((new WhatsAppService)->sendText('923001234567', 'Hello'));
        $this->assertSame(0, WhatsappMessage::count());
        Http::assertNothingSent();
    }
}
