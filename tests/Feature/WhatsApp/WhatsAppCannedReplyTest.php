<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappCannedReply;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Canned replies (saved quick-answers): account-scoped CRUD, list gated by
 * inbox.view and create/delete by inbox.reply, IDOR-safe delete.
 */
class WhatsAppCannedReplyTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // The factory user needs accounts(1) + user_types(1) FKs to resolve.
        $this->seedDefaultAccount();
        $this->seedUserTypes();
    }

    private function actAsAgentWith(array $permissions): void
    {
        $user = User::factory()->create();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('wa-canned-'.uniqid());
        $role->givePermissionTo($permissions);
        $user->assignRole($permissions === [] ? [] : $role->name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
    }

    public function test_lists_the_accounts_replies(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        WhatsappCannedReply::create(['account_id' => 1, 'title' => 'Prices', 'body' => 'Our prices…']);

        $rows = $this->getJson('/api/whatsapp/canned-replies')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('Prices', $rows[0]['title']);
    }

    public function test_store_creates_a_reply_for_this_account(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);

        $this->postJson('/api/whatsapp/canned-replies', ['title' => 'Hours', 'body' => 'We are open 10am–10pm'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Hours');

        $this->assertDatabaseHas('whatsapp_canned_replies', ['account_id' => 1, 'title' => 'Hours']);
    }

    public function test_store_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);

        $this->postJson('/api/whatsapp/canned-replies', ['title' => 'X', 'body' => 'Y'])
            ->assertStatus(403);
    }

    public function test_store_validates_input(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);

        $this->postJson('/api/whatsapp/canned-replies', ['title' => '', 'body' => ''])->assertStatus(422);
    }

    public function test_update_edits_an_own_account_reply(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $reply = WhatsappCannedReply::create(['account_id' => 1, 'title' => 'Hours', 'body' => 'old text']);

        $this->putJson("/api/whatsapp/canned-replies/{$reply->id}", ['title' => 'Opening hours', 'body' => 'We are open 10am–10pm'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Opening hours')
            ->assertJsonPath('data.body', 'We are open 10am–10pm');

        $this->assertDatabaseHas('whatsapp_canned_replies', [
            'id' => $reply->id, 'title' => 'Opening hours', 'body' => 'We are open 10am–10pm',
        ]);
    }

    public function test_update_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        $reply = WhatsappCannedReply::create(['account_id' => 1, 'title' => 'X', 'body' => 'Y']);

        $this->putJson("/api/whatsapp/canned-replies/{$reply->id}", ['title' => 'A', 'body' => 'B'])->assertStatus(403);
    }

    public function test_update_validates_input(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $reply = WhatsappCannedReply::create(['account_id' => 1, 'title' => 'X', 'body' => 'Y']);

        $this->putJson("/api/whatsapp/canned-replies/{$reply->id}", ['title' => '', 'body' => ''])->assertStatus(422);
    }

    public function test_update_404s_for_an_id_outside_the_account_scope(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);

        $this->putJson('/api/whatsapp/canned-replies/999999', ['title' => 'A', 'body' => 'B'])->assertStatus(404);
    }

    public function test_destroy_deletes_an_own_account_reply(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $reply = WhatsappCannedReply::create(['account_id' => 1, 'title' => 'Bye', 'body' => 'x']);

        $this->deleteJson("/api/whatsapp/canned-replies/{$reply->id}")->assertOk();
        $this->assertDatabaseMissing('whatsapp_canned_replies', ['id' => $reply->id]);
    }

    public function test_destroy_404s_for_an_id_outside_the_account_scope(): void
    {
        // BaseModel's account global scope means a reply not in the caller's
        // account (here: a non-existent id) is simply not found.
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);

        $this->deleteJson('/api/whatsapp/canned-replies/999999')->assertStatus(404);
    }
}
