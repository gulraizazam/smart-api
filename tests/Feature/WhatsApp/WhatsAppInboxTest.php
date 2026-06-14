<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\UploadedFile;
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

    public function test_mark_read_clears_the_unread_count(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/read")->assertOk();

        $this->assertSame(0, $this->getJson('/api/whatsapp/conversations/unread-count')->json('data.count'));
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

    public function test_reply_media_rejects_a_non_audio_file(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $conversation = $this->conversationWithInbound('923001234567', 'Hello');

        $file = UploadedFile::fake()->create('not-audio.txt', 4, 'text/plain');
        $this->post("/api/whatsapp/conversations/{$conversation->id}/reply-media", ['file' => $file])
            ->assertStatus(422);
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
}
