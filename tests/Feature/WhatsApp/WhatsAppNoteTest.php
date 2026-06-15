<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappConversation;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Internal notes on a conversation: add / list (with author) / delete, gated by
 * whatsapp.inbox.view, conversation-scoped (IDOR-safe).
 */
class WhatsAppNoteTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDefaultAccount();
        $this->seedUserTypes();
    }

    private function actAsAgent(): User
    {
        $user = User::factory()->create(['name' => 'Sara CSR']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->createPermission('whatsapp.inbox.view');
        $role = $this->createRole('wa-note-'.uniqid());
        $role->givePermissionTo('whatsapp.inbox.view');
        $user->assignRole($role->name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    public function test_add_list_and_delete_a_note_with_its_author(): void
    {
        $this->actAsAgent();
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567']);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/notes", ['body' => 'Wants laser, budget-conscious'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Wants laser, budget-conscious')
            ->assertJsonPath('data.author', 'Sara CSR');

        $note = $conversation->notes()->firstOrFail();

        $list = $this->getJson("/api/whatsapp/conversations/{$conversation->id}/notes")->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame('Sara CSR', $list[0]['author']);

        $this->deleteJson("/api/whatsapp/conversations/{$conversation->id}/notes/{$note->id}")->assertOk();
        $this->assertSame(0, $conversation->notes()->count());
    }

    public function test_store_validates_the_body(): void
    {
        $this->actAsAgent();
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567']);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/notes", ['body' => ''])->assertStatus(422);
    }

    public function test_notes_are_rejected_without_inbox_view(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user); // no whatsapp.inbox.view
        $conversation = WhatsappConversation::create(['wa_id' => '923001234567']);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/notes", ['body' => 'x'])->assertStatus(403);
    }
}
