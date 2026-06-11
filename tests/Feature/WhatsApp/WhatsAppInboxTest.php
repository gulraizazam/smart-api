<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
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
}
