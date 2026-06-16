<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappTag;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * WhatsApp chat tags: account-scoped CRUD, colour + muting flag, list gated by
 * inbox.view and create/delete by inbox.reply.
 */
class WhatsAppTagTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
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
        $role = $this->createRole('wa-tag-'.uniqid());
        $role->givePermissionTo($permissions);
        $user->assignRole($permissions === [] ? [] : $role->name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
    }

    public function test_lists_the_accounts_tags(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);
        WhatsappTag::create(['account_id' => 1, 'name' => 'Hot lead', 'color' => 'warning']);

        $rows = $this->getJson('/api/whatsapp/tags')->assertOk()->json('data');
        $this->assertSame('Hot lead', $rows[0]['name']);
        $this->assertSame('warning', $rows[0]['color']);
    }

    public function test_store_creates_a_tag_with_colour_and_muting(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);

        $this->postJson('/api/whatsapp/tags', ['name' => 'Spam', 'color' => 'danger', 'is_muting' => true])
            ->assertOk()
            ->assertJsonPath('data.name', 'Spam')
            ->assertJsonPath('data.color', 'danger')
            ->assertJsonPath('data.is_muting', true);

        $this->assertDatabaseHas('whatsapp_tags', ['account_id' => 1, 'name' => 'Spam', 'is_muting' => 1]);
    }

    public function test_store_rejects_a_bad_colour_and_a_duplicate_name(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        WhatsappTag::create(['account_id' => 1, 'name' => 'Booked', 'color' => 'success']);

        $this->postJson('/api/whatsapp/tags', ['name' => 'X', 'color' => 'rainbow'])->assertStatus(422);
        $this->postJson('/api/whatsapp/tags', ['name' => 'Booked'])->assertStatus(422); // duplicate
    }

    public function test_store_requires_the_reply_permission(): void
    {
        $this->actAsAgentWith(['whatsapp.inbox.view']);

        $this->postJson('/api/whatsapp/tags', ['name' => 'New'])->assertStatus(403);
    }

    public function test_tag_deletion_is_not_exposed(): void
    {
        // Tags are a fixed, managed set — there is no delete endpoint. The tag
        // (and any chats carrying it) must survive a DELETE attempt.
        $this->actAsAgentWith(['whatsapp.inbox.view', 'whatsapp.inbox.reply']);
        $tag = WhatsappTag::create(['account_id' => 1, 'name' => 'Spam', 'color' => 'danger', 'is_muting' => true]);

        $this->deleteJson("/api/whatsapp/tags/{$tag->id}")->assertNotFound();

        $this->assertDatabaseHas('whatsapp_tags', ['id' => $tag->id]);
    }
}
