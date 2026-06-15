<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Models\User;
use App\Models\Patients;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the team-facing inbox endpoints (WhatsAppInboxController): permission
 * gates (401/403), conversation list with unread counts + window state,
 * thread order, mark-read, and the reply rules (window closed => 422, no
 * API call; open => send via WhatsAppService and store outbound).
 */
class WhatsAppInboxTest extends TestCase
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

    private function actAsAgentWith(array $permissions): void
    {
        $user = User::factory()->create();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('wa-agent-'.uniqid());
        $role->givePermissionTo($permissions);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
    }

    private function conversationWithInbound(string $waId, string $body, int $minutesAgo = 5): WhatsappConversation
    {
        $conversation = WhatsappConversation::create([
            'wa_id' => $waId,
            'profile_name' => 'Test Patient',
            'last_inbound_at' => now()->subMinutes($minutesAgo),
        ]);
        $conversation->messages()->create([
            'wamid' => 'wamid.IN_'.$waId.'_'.$minutesAgo,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => $body,
            'status' => 'received',
            'created_at' => now()->subMinutes($minutesAgo),
        ]);

        return $conversation;
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $response = $this->getJson('/api/whatsapp/conversations');

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_authenticated_without_permission_gets_403(): void
    {
        $this->actAsAgentWith([]);

        $this->getJson('/api/whatsapp/conversations')->assertStatus(403);
    }

    public function test_list_returns_conversations_with_unread_count_and_window_state(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $this->conversationWithInbound('923001234567', 'Hello, I want to book');

        $response = $this->getJson('/api/whatsapp/conversations');

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertSame('923001234567', $row['wa_id']);
        $this->assertSame(1, $row['unread_count']);
        $this->assertTrue($row['window_open']);
        $this->assertSame('Hello, I want to book', $row['last_message']['body']);
    }

    public function test_unread_filter_and_unread_count_endpoint(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $this->conversationWithInbound('923001111111', 'Unread one');
        $read = $this->conversationWithInbound('923002222222', 'Already read');
        $read->update(['last_read_at' => now()]);

        $list = $this->getJson('/api/whatsapp/conversations?unread=1');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame('923001111111', $list->json('data.0.wa_id'));

        $count = $this->getJson('/api/whatsapp/conversations/unread-count');
        $count->assertOk();
        $this->assertSame(1, $count->json('data.count'));
    }

    public function test_health_requires_view_permission(): void
    {
        $this->actAsAgentWith([]);

        $this->getJson('/api/whatsapp/health')->assertStatus(403);
    }

    public function test_health_is_healthy_with_no_recent_outbound(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        Cache::forget(WhatsAppWebhookController::LAST_WEBHOOK_KEY);

        // Nothing has been sent, so nothing is expecting a callback.
        $this->getJson('/api/whatsapp/health')
            ->assertOk()
            ->assertJsonPath('data.healthy', true);
    }

    public function test_health_flags_stale_when_an_old_reply_got_no_callback(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        Cache::forget(WhatsAppWebhookController::LAST_WEBHOOK_KEY); // no heartbeat
        // Customer messaged 25 min ago; we replied 20 min ago; since then Meta
        // has sent NOTHING back (no delivery callback, no new inbound).
        $conversation = WhatsappConversation::create([
            'wa_id' => '923001234567',
            'profile_name' => 'Test',
            'last_inbound_at' => now()->subMinutes(25),
        ]);
        $this->inboundReceivedMinutesAgo($conversation, 'wamid.IN_old', 25);
        $this->outboundSentMinutesAgo($conversation, 'wamid.OUT_old', 20);

        $this->getJson('/api/whatsapp/health')
            ->assertOk()
            ->assertJsonPath('data.healthy', false);
    }

    public function test_health_recovers_after_a_cache_clear_when_a_newer_inbound_landed(): void
    {
        // Regression for the deploy false alarm: cache:clear wipes the
        // heartbeat, but a more recent inbound message proves webhooks still
        // flow, so an old reply must NOT raise the banner.
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        Cache::forget(WhatsAppWebhookController::LAST_WEBHOOK_KEY);
        $conversation = WhatsappConversation::create([
            'wa_id' => '923001234567',
            'profile_name' => 'Test',
            'last_inbound_at' => now()->subMinutes(2),
        ]);
        $this->outboundSentMinutesAgo($conversation, 'wamid.OUT_old', 20); // reply 20 min ago, still 'sent'
        $this->inboundReceivedMinutesAgo($conversation, 'wamid.IN_new', 2); // customer wrote back 2 min ago

        $this->getJson('/api/whatsapp/health')
            ->assertOk()
            ->assertJsonPath('data.healthy', true);
    }

    public function test_health_is_healthy_when_the_reply_was_delivered_even_with_no_cache(): void
    {
        // The reply's own delivered status is a status webhook — proves the
        // subscription is up without any cache heartbeat or new inbound.
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        Cache::forget(WhatsAppWebhookController::LAST_WEBHOOK_KEY);
        $conversation = WhatsappConversation::create([
            'wa_id' => '923001234567',
            'profile_name' => 'Test',
            'last_inbound_at' => now()->subHours(2),
        ]);
        $message = $conversation->messages()->create([
            'wamid' => 'wamid.OUT_delivered',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Delivered reply',
            'status' => 'delivered', // a status callback advanced it past 'sent'
        ]);
        WhatsappMessage::where('id', $message->id)->update(['created_at' => now()->subMinutes(20)]);

        $this->getJson('/api/whatsapp/health')
            ->assertOk()
            ->assertJsonPath('data.healthy', true);
    }

    public function test_number_quality_requires_view_permission(): void
    {
        $this->actAsAgentWith([]);

        $this->getJson('/api/whatsapp/number-quality')->assertStatus(403);
    }

    public function test_number_quality_returns_metas_rating(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        Cache::forget('whatsapp:number_quality');
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'quality_rating' => 'GREEN',
                'display_phone_number' => '+92 311 1113366',
                'id' => '111222333',
            ]),
        ]);

        $this->getJson('/api/whatsapp/number-quality')
            ->assertOk()
            ->assertJsonPath('data.quality_rating', 'GREEN')
            ->assertJsonPath('data.display_phone_number', '+92 311 1113366');
    }

    public function test_number_quality_is_null_when_meta_is_unreachable(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        Cache::forget('whatsapp:number_quality');
        Http::fake(['graph.facebook.com/*' => Http::response([], 500)]);

        $this->getJson('/api/whatsapp/number-quality')
            ->assertOk()
            ->assertJsonPath('data.quality_rating', null);
    }

    public function test_health_is_healthy_when_the_cache_heartbeat_is_recent(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = WhatsappConversation::create([
            'wa_id' => '923001234567',
            'profile_name' => 'Test',
            'last_inbound_at' => now()->subHour(),
        ]);
        $this->outboundSentMinutesAgo($conversation, 'wamid.OUT_old', 20);
        Cache::put(WhatsAppWebhookController::LAST_WEBHOOK_KEY, now()->subMinute()->toIso8601String());

        $this->getJson('/api/whatsapp/health')
            ->assertOk()
            ->assertJsonPath('data.healthy', true);
    }

    /**
     * Create a message and force its created_at into the past. Eloquent stamps
     * created_at = now() on insert even when supplied, so a raw update is the
     * reliable way to age a row in tests.
     */
    private function outboundSentMinutesAgo(WhatsappConversation $conversation, string $wamid, int $minutesAgo): void
    {
        $message = $conversation->messages()->create([
            'wamid' => $wamid,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'We replied earlier',
            'status' => 'sent',
        ]);
        WhatsappMessage::where('id', $message->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);
    }

    private function inboundReceivedMinutesAgo(WhatsappConversation $conversation, string $wamid, int $minutesAgo): void
    {
        $message = $conversation->messages()->create([
            'wamid' => $wamid,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Customer message',
            'status' => 'received',
        ]);
        WhatsappMessage::where('id', $message->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);
    }

    public function test_show_returns_messages_in_chronological_order(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'First message', 60);
        $conversation->messages()->create([
            'wamid' => 'wamid.IN_second',
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Second message',
            'status' => 'received',
            'created_at' => now()->subMinutes(30),
        ]);

        $response = $this->getJson("/api/whatsapp/conversations/{$conversation->id}");

        $response->assertOk();
        $bodies = array_column($response->json('data.messages'), 'body');
        $this->assertSame(['First message', 'Second message'], $bodies);
        $this->assertSame('923001234567', $response->json('data.conversation.wa_id'));
    }

    public function test_reaction_message_surfaces_the_emoji_as_its_body(): void
    {
        // A reaction arrives as a 'reaction' message with no body — the emoji is
        // in the payload. The resource must surface it so the SPA shows 👍, not
        // a literal "[reaction]".
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $conversation->messages()->create([
            'wamid' => 'wamid.REACT==',
            'direction' => 'inbound',
            'type' => 'reaction',
            'body' => null,
            'status' => 'received',
            'payload' => ['type' => 'reaction', 'reaction' => ['message_id' => 'wamid.X', 'emoji' => '👍🏻']],
        ]);

        $messages = $this->getJson("/api/whatsapp/conversations/{$conversation->id}")->assertOk()->json('data.messages');
        $reaction = collect($messages)->firstWhere('type', 'reaction');
        $this->assertSame('👍🏻', $reaction['body']);
    }

    public function test_removed_reaction_has_a_null_body(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $conversation->messages()->create([
            'wamid' => 'wamid.UNREACT==',
            'direction' => 'inbound',
            'type' => 'reaction',
            'body' => null,
            'status' => 'received',
            'payload' => ['type' => 'reaction', 'reaction' => ['message_id' => 'wamid.X', 'emoji' => '']],
        ]);

        $messages = $this->getJson("/api/whatsapp/conversations/{$conversation->id}")->assertOk()->json('data.messages');
        $this->assertNull(collect($messages)->firstWhere('type', 'reaction')['body']);
    }

    public function test_mark_read_clears_the_unread_count(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/read")->assertOk();

        $this->assertSame(0, $this->getJson('/api/whatsapp/conversations/unread-count')->json('data.count'));
    }

    public function test_mark_unread_reflags_a_read_conversation(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');
        // Read it first → 0 unread.
        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/read")->assertOk();
        $row = collect($this->getJson('/api/whatsapp/conversations')->json('data'))->firstWhere('id', $conversation->id);
        $this->assertSame(0, $row['unread_count']);

        // Mark unread → the latest inbound counts again (badge = 1).
        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/unread")->assertOk();
        $row = collect($this->getJson('/api/whatsapp/conversations')->json('data'))->firstWhere('id', $conversation->id);
        $this->assertSame(1, $row['unread_count']);
        $this->assertSame(1, $this->getJson('/api/whatsapp/conversations/unread-count')->json('data.count'));
    }

    public function test_mark_unread_is_a_no_op_when_there_is_no_inbound_message(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        // Outbound-only conversation: nothing inbound to flag unread.
        $conversation = WhatsappConversation::create(['wa_id' => '923009999999', 'profile_name' => 'X']);
        $conversation->messages()->create([
            'wamid' => 'wamid.OUT==', 'direction' => 'outbound', 'type' => 'text', 'body' => 'Hi', 'status' => 'sent',
        ]);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/unread")->assertOk();
        $row = collect($this->getJson('/api/whatsapp/conversations')->json('data'))->firstWhere('id', $conversation->id);
        $this->assertSame(0, $row['unread_count']);
    }

    public function test_mark_read_sends_a_read_receipt_to_meta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/read")->assertOk();

        // The customer sees blue ticks: a status=read POST for the inbound wamid.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/messages')
            && $r['status'] === 'read'
            && str_starts_with($r['message_id'], 'wamid.IN_'));
    }

    public function test_typing_sends_the_typing_indicator(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/typing")->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/messages')
            && $r['status'] === 'read'
            && ($r['typing_indicator']['type'] ?? null) === 'text');
    }

    public function test_typing_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/typing")->assertStatus(403);
    }

    public function test_reply_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/reply", ['message' => 'Hi!'])
            ->assertStatus(403);
    }

    public function test_reply_inside_window_sends_and_stores_outbound(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.REPLY_1==']],
            ]),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $response = $this->postJson("/api/whatsapp/conversations/{$conversation->id}/reply", [
            'message' => 'Thanks, booking you in.',
        ]);

        $response->assertOk();
        $this->assertSame('outbound', $response->json('data.direction'));
        $this->assertSame('accepted', $response->json('data.status'));
        $this->assertSame(1, $conversation->messages()->where('direction', 'outbound')->count());
        // Replying marks the thread read.
        $this->assertNotNull($conversation->fresh()->last_read_at);
    }

    public function test_reply_outside_window_is_422_and_sends_nothing(): void
    {
        Http::fake();
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Old message', 25 * 60);

        $response = $this->postJson("/api/whatsapp/conversations/{$conversation->id}/reply", [
            'message' => 'Too late',
        ]);

        $response->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::where('direction', 'outbound')->count());
    }

    public function test_reply_validates_the_message_body(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/reply", ['message' => ''])
            ->assertStatus(422);
        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/reply", [
            'message' => str_repeat('x', 5000),
        ])->assertStatus(422);
    }

    public function test_unread_count_returns_latest_inbound_summary_for_notifications(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $this->conversationWithInbound('923001111111', 'Older message', 30);
        $newer = $this->conversationWithInbound('923002222222', 'Newest message', 2);

        $response = $this->getJson('/api/whatsapp/conversations/unread-count');

        $response->assertOk();
        $response->assertJsonPath('data.count', 2);
        // The notifier keys off the single most recent inbound message.
        $response->assertJsonPath('data.latest.preview', 'Newest message');
        $response->assertJsonPath('data.latest.wa_id', '923002222222');
        $response->assertJsonPath('data.latest.conversation_id', $newer->id);
    }

    public function test_unread_count_latest_is_null_when_no_inbound_messages_exist(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);

        $this->getJson('/api/whatsapp/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.latest', null);
    }

    public function test_show_exposes_a_media_url_for_inbound_media_messages(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()]);
        $message = $conversation->messages()->create([
            'wamid' => 'wamid.IMG_RES',
            'direction' => 'inbound',
            'type' => 'image',
            'body' => 'Look at my skin',
            'status' => 'received',
            'payload' => ['type' => 'image', 'image' => ['id' => 'MEDIA_RES_1', 'mime_type' => 'image/jpeg']],
        ]);
        $text = $conversation->messages()->create([
            'wamid' => 'wamid.TXT_RES',
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'plain text',
            'status' => 'received',
            'payload' => ['type' => 'text', 'text' => ['body' => 'plain text']],
        ]);

        $messages = $this->getJson("/api/whatsapp/conversations/{$conversation->id}")
            ->assertOk()
            ->json('data.messages');

        $imageRow = collect($messages)->firstWhere('id', $message->id);
        $textRow = collect($messages)->firstWhere('id', $text->id);
        $this->assertSame(
            "/api/whatsapp/conversations/{$conversation->id}/media/{$message->id}",
            $imageRow['media_url'],
        );
        // Text messages must NOT get a media_url.
        $this->assertNull($textRow['media_url']);
    }

    public function test_media_endpoint_proxies_the_file_bytes_from_meta(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/whatsapp/media/abc123',
                'mime_type' => 'image/jpeg',
            ]),
            'lookaside.fbsbx.com/*' => Http::response('BINARYIMAGEBYTES', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view']);

        $conversation = WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()]);
        $message = $conversation->messages()->create([
            'wamid' => 'wamid.IMG_PROXY',
            'direction' => 'inbound',
            'type' => 'image',
            'body' => null,
            'status' => 'received',
            'payload' => ['type' => 'image', 'image' => ['id' => 'MEDIA_PROXY_1', 'mime_type' => 'image/jpeg']],
        ]);

        $response = $this->get("/api/whatsapp/conversations/{$conversation->id}/media/{$message->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('BINARYIMAGEBYTES', $response->getContent());
    }

    public function test_media_endpoint_is_permission_gated(): void
    {
        $this->actAsAgentWith([]); // authenticated but no whatsapp.inbox.view
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()]);
        $message = $conversation->messages()->create([
            'wamid' => 'wamid.IMG_GATE',
            'direction' => 'inbound',
            'type' => 'image',
            'status' => 'received',
            'payload' => ['type' => 'image', 'image' => ['id' => 'MEDIA_GATE_1']],
        ]);

        $this->get("/api/whatsapp/conversations/{$conversation->id}/media/{$message->id}")
            ->assertStatus(403);
    }

    public function test_reply_media_uploads_and_sends_a_voice_note_inside_the_window(): void
    {
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'UPLOAD_MEDIA_1']),
            'graph.facebook.com/*/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.VN_1']],
            ]),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        // Real bytes — a zero-byte fake trips attach()'s array_filter (drops empty contents).
        $file = UploadedFile::fake()->createWithContent('voice-note.ogg', 'OggS'.str_repeat('x', 200));
        $response = $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('data.direction', 'outbound');
        $response->assertJsonPath('data.type', 'audio');
        $response->assertJsonPath('data.status', 'accepted');

        // The sent voice note exposes a playback URL and stores the uploaded media id.
        $msgId = $response->json('data.id');
        $this->assertSame(
            "/api/whatsapp/conversations/{$conversation->id}/media/{$msgId}",
            $response->json('data.media_url'),
        );
        $this->assertSame('UPLOAD_MEDIA_1', WhatsappMessage::find($msgId)->payload['media_id']);
        Http::assertSentCount(2); // upload + send
    }

    public function test_reply_media_outside_window_is_422_and_sends_nothing(): void
    {
        Http::fake();
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Old', 25 * 60);

        $file = UploadedFile::fake()->create('voice-note.ogg', 12, 'audio/ogg');
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_reply_media_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $file = UploadedFile::fake()->create('voice-note.ogg', 12, 'audio/ogg');
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file])
            ->assertStatus(403);
    }

    public function test_reply_media_rejects_an_unsupported_file_type(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $file = UploadedFile::fake()->create('malware.exe', 4); // not a WhatsApp media type
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file])
            ->assertStatus(422);
    }

    public function test_reply_media_sends_a_photo_with_caption(): void
    {
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'IMG_UP_1']),
            'graph.facebook.com/*/messages' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.IMG==']]]),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');

        $file = UploadedFile::fake()->createWithContent('offer.jpg', 'JPEGBYTES');
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", [
            'file' => $file, 'caption' => 'Our June offers',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.body', 'Our June offers')
            ->assertJsonPath('data.status', 'accepted');

        Http::assertSent(fn ($r) => ($r['image']['caption'] ?? null) === 'Our June offers');
    }

    public function test_reply_media_sends_a_document_with_its_filename(): void
    {
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'DOC_UP_1']),
            'graph.facebook.com/*/messages' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.DOC==']]]),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');

        $file = UploadedFile::fake()->createWithContent('price-list.pdf', '%PDF-1.4 data');
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.type', 'document');

        Http::assertSent(fn ($r) => ($r['document']['filename'] ?? null) === 'price-list.pdf');
    }

    public function test_media_endpoint_serves_outbound_audio_by_stored_media_id(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://lookaside.fbsbx.com/voice', 'mime_type' => 'audio/ogg']),
            'lookaside.fbsbx.com/*' => Http::response('VOICEBYTES', 200, ['Content-Type' => 'audio/ogg']),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567', 'last_inbound_at' => now()]);
        $message = $conversation->messages()->create([
            'wamid' => 'wamid.VN_OUT',
            'direction' => 'outbound',
            'type' => 'audio',
            'status' => 'accepted',
            'payload' => ['media_id' => 'OUT_MEDIA_1'],
        ]);

        $response = $this->get("/api/whatsapp/conversations/{$conversation->id}/media/{$message->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/ogg');
        $this->assertSame('VOICEBYTES', $response->getContent());
    }

    public function test_reply_to_an_opted_out_customer_is_422_and_sends_nothing(): void
    {
        Http::fake();
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello'); // window open
        $conversation->update(['opted_out_at' => now()]);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/reply", ['message' => 'Hi'])
            ->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::where('direction', 'outbound')->count());
    }

    public function test_reply_media_to_an_opted_out_customer_is_422(): void
    {
        Http::fake();
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');
        $conversation->update(['opted_out_at' => now()]);

        $file = UploadedFile::fake()->createWithContent('voice-note.ogg', 'OggS'.str_repeat('x', 50));
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_list_exposes_the_opted_out_flag(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $optedOut = $this->conversationWithInbound('923001111111', 'STOP');
        $optedOut->update(['opted_out_at' => now()]);
        $this->conversationWithInbound('923002222222', 'Hello'); // still subscribed

        $rows = $this->getJson('/api/whatsapp/conversations')->assertOk()->json('data');
        $this->assertTrue(collect($rows)->firstWhere('wa_id', '923001111111')['opted_out']);
        $this->assertFalse(collect($rows)->firstWhere('wa_id', '923002222222')['opted_out']);
    }

    public function test_list_includes_the_matched_patient(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $patient = Patients::factory()->create(['name' => 'Ayesha Khan', 'phone' => '03001234567']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $conversation->update(['patient_id' => $patient->id]);

        $row = $this->getJson('/api/whatsapp/conversations')->assertOk()->json('data.0');
        $this->assertSame($patient->id, $row['patient']['id']);
        $this->assertSame('Ayesha Khan', $row['patient']['name']);
    }

    public function test_assign_sets_and_clears_the_assignee(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $staff = User::factory()->create(['name' => 'Sara CSR']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/assign", ['assigned_to_id' => $staff->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to.id', $staff->id)
            ->assertJsonPath('data.assigned_to.name', 'Sara CSR');
        $this->assertSame($staff->id, $conversation->fresh()->assigned_to_id);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/assign", ['assigned_to_id' => null])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', null);
        $this->assertNull($conversation->fresh()->assigned_to_id);
    }

    public function test_assign_rejects_a_nonexistent_user(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/assign", ['assigned_to_id' => 999999])
            ->assertStatus(422);
    }

    public function test_list_includes_the_assignee(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $staff = User::factory()->create(['name' => 'Sara CSR']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $conversation->update(['assigned_to_id' => $staff->id]);

        $row = $this->getJson('/api/whatsapp/conversations')->assertOk()->json('data.0');
        $this->assertSame($staff->id, $row['assigned_to']['id']);
        $this->assertSame('Sara CSR', $row['assigned_to']['name']);
    }

    public function test_search_filters_by_phone_and_patient_name(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $patient = Patients::factory()->create(['name' => 'Zara Malik', 'phone' => '03007654321']);
        $byPatient = $this->conversationWithInbound('923007654321', 'Hi');
        $byPatient->update(['patient_id' => $patient->id]);
        $this->conversationWithInbound('923009999999', 'Other'); // filtered out

        $byName = $this->getJson('/api/whatsapp/conversations?search=Zara')->assertOk()->json('data');
        $this->assertCount(1, $byName);
        $this->assertSame('923007654321', $byName[0]['wa_id']);

        $byPhone = $this->getJson('/api/whatsapp/conversations?search=7654321')->assertOk()->json('data');
        $this->assertCount(1, $byPhone);
        $this->assertSame('923007654321', $byPhone[0]['wa_id']);
    }

    public function test_resolve_and_reopen_a_conversation(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/resolve", ['resolved' => true])
            ->assertOk()->assertJsonPath('data.resolved', true);
        $this->assertNotNull($conversation->fresh()->resolved_at);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/resolve", ['resolved' => false])
            ->assertOk()->assertJsonPath('data.resolved', false);
        $this->assertNull($conversation->fresh()->resolved_at);
    }

    public function test_resolved_filter_returns_only_resolved(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $resolved = $this->conversationWithInbound('923001111111', 'Done');
        $resolved->update(['resolved_at' => now()]);
        $this->conversationWithInbound('923002222222', 'Open');

        $rows = $this->getJson('/api/whatsapp/conversations?resolved=1')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('923001111111', $rows[0]['wa_id']);
    }

    public function test_retry_resends_a_failed_text_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.RETRY_OK==']]]),
        ]);
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi'); // window open
        $failed = $conversation->messages()->create([
            'wamid' => null, 'direction' => 'outbound', 'type' => 'text', 'body' => 'See you at 5pm', 'status' => 'failed',
        ]);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/messages/{$failed->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.body', 'See you at 5pm');
        Http::assertSent(fn ($r) => ($r['text']['body'] ?? null) === 'See you at 5pm');
    }

    public function test_retry_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $failed = $conversation->messages()->create([
            'wamid' => null, 'direction' => 'outbound', 'type' => 'text', 'body' => 'x', 'status' => 'failed',
        ]);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/messages/{$failed->id}/retry")
            ->assertStatus(403);
    }

    public function test_tag_and_untag_a_conversation(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $tag = WhatsappTag::create(['account_id' => 1, 'name' => 'Interested', 'color' => 'accent']);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/tags", ['tag_id' => $tag->id])
            ->assertOk()
            ->assertJsonPath('data.tags.0.name', 'Interested');
        $this->assertSame(1, $conversation->tags()->count());

        $this->deleteJson("/api/whatsapp/conversations/{$conversation->id}/tags/{$tag->id}")->assertOk();
        $this->assertSame(0, $conversation->fresh()->tags()->count());
    }

    public function test_a_muting_tag_hides_the_chat_from_the_default_list_but_the_muted_filter_shows_it(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $spam = WhatsappTag::create(['account_id' => 1, 'name' => 'Spam', 'color' => 'danger', 'is_muting' => true]);
        $muted = $this->conversationWithInbound('923001111111', 'buy followers');
        $muted->tags()->attach($spam->id);
        $this->conversationWithInbound('923002222222', 'Hello'); // normal

        $default = $this->getJson('/api/whatsapp/conversations')->assertOk()->json('data');
        $this->assertCount(1, $default);
        $this->assertSame('923002222222', $default[0]['wa_id']);

        $mutedOnly = $this->getJson('/api/whatsapp/conversations?muted=1')->assertOk()->json('data');
        $this->assertCount(1, $mutedOnly);
        $this->assertSame('923001111111', $mutedOnly[0]['wa_id']);
        $this->assertTrue($mutedOnly[0]['muted']);
    }

    public function test_unread_count_excludes_muted_chats(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $spam = WhatsappTag::create(['account_id' => 1, 'name' => 'Spam', 'color' => 'danger', 'is_muting' => true]);
        $this->conversationWithInbound('923001111111', 'spam')->tags()->attach($spam->id);
        // An unmuted unread chat alongside the muted one — the count reflects
        // only the unmuted (the muted-exclusion closure is reused on the count).
        $this->conversationWithInbound('923002222222', 'real customer');

        $this->assertSame(1, $this->getJson('/api/whatsapp/conversations/unread-count')->json('data.count'));
    }

    public function test_mine_and_unassigned_filters(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $me = auth()->user();
        $mine = $this->conversationWithInbound('923001111111', 'Hi');
        $mine->update(['assigned_to_id' => $me->id]);
        $this->conversationWithInbound('923002222222', 'Hello'); // unassigned

        $mineRows = $this->getJson('/api/whatsapp/conversations?mine=1')->assertOk()->json('data');
        $this->assertCount(1, $mineRows);
        $this->assertSame('923001111111', $mineRows[0]['wa_id']);

        $unassigned = $this->getJson('/api/whatsapp/conversations?unassigned=1')->assertOk()->json('data');
        $this->assertCount(1, $unassigned);
        $this->assertSame('923002222222', $unassigned[0]['wa_id']);
    }

    public function test_list_includes_the_notes_count(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hi');
        $conversation->notes()->create(['body' => 'Called, interested', 'created_by' => auth()->id()]);

        $row = $this->getJson('/api/whatsapp/conversations')->assertOk()->json('data.0');
        $this->assertSame(1, $row['notes_count']);
    }
}
