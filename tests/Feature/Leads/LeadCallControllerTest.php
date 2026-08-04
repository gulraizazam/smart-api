<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Models\Leads;
use App\Models\User;
use App\Services\Voice\PlivoVoiceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the authenticated call-management surface on the leads screen.
 *
 * The three invariants regression-tested here are:
 *
 *   1. token/initiate require the `leads.call` permission — a role that
 *      can view leads but not call them must 403, not 200.
 *   2. tenant scoping — a user in account A cannot mint a Plivo token
 *      for or initiate a call against a lead in account B.
 *   3. initiate writes a lead_calls row before returning, so the caller
 *      can attach the row id to Plivo's custom_params (nothing works
 *      without this — every subsequent webhook joins on the id).
 */
class LeadCallControllerTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        Config::set('services.plivo.auth_id', 'test-auth-id');
        Config::set('services.plivo.auth_token', 'test-auth-token');
        Config::set('services.plivo.app_id', 'test-app-id');
        Config::set('services.plivo.caller_id', '+922135000000');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_token_endpoint_returns_403_without_leads_call_permission(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->actingAs($actor);
        $lead = $this->makeLead(accountId: 1);

        $response = $this->postJson("/api/leads/{$lead->id}/calls/token");

        $response->assertStatus(403);
        // If this ever flips to 401 the SPA will sign the user out —
        // guard against that regression explicitly.
        $this->assertNotSame(401, $response->getStatusCode());
    }

    public function test_token_endpoint_returns_403_across_tenants(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsCall($actor);
        $this->actingAs($actor);
        $foreignLead = $this->makeLead(accountId: 999);

        $response = $this->postJson("/api/leads/{$foreignLead->id}/calls/token");

        $response->assertStatus(403);
    }

    public function test_token_endpoint_mints_a_jwt_for_the_current_user(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsCall($actor);
        $this->actingAs($actor);
        $lead = $this->makeLead(accountId: 1);

        $fakePlivo = Mockery::mock(PlivoVoiceService::class);
        $fakePlivo->shouldReceive('mintBrowserToken')
            ->once()
            ->with(Mockery::on(fn (User $u) => $u->id === $actor->id))
            ->andReturn('the.jwt.payload');
        $this->app->instance(PlivoVoiceService::class, $fakePlivo);

        $response = $this->postJson("/api/leads/{$lead->id}/calls/token");

        $response->assertOk();
        $response->assertJsonPath('data.token', 'the.jwt.payload');
        $response->assertJsonPath('data.endpoint_username', 'agent-'.$actor->id);
    }

    public function test_initiate_writes_lead_calls_row_and_returns_id(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsCall($actor);
        $this->actingAs($actor);
        $lead = $this->makeLead(accountId: 1, phone: '+923005550000');

        $response = $this->postJson("/api/leads/{$lead->id}/calls/initiate");

        $response->assertOk();
        $callId = (int) $response->json('data.lead_call_id');
        $this->assertGreaterThan(0, $callId);

        $row = LeadCall::find($callId);
        $this->assertNotNull($row);
        $this->assertSame((int) $lead->id, (int) $row->lead_id);
        $this->assertSame((int) $actor->id, (int) $row->user_id);
        $this->assertSame('outbound', $row->direction);
        $this->assertSame('+923005550000', $row->to_number);
        $this->assertSame('+922135000000', $row->from_number);
        $this->assertSame('initiated', $row->status);

        // The custom_params round-trip is the only way the webhook joins
        // back to this row — pin that they're present in the response.
        $response->assertJsonPath('data.custom_params.x_lead_call_id', (string) $callId);
        $response->assertJsonPath('data.custom_params.x_lead_id', (string) $lead->id);
    }

    public function test_initiate_403s_when_lead_has_no_phone(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsCall($actor);
        $this->actingAs($actor);
        $lead = $this->makeLead(accountId: 1, phone: null);

        $response = $this->postJson("/api/leads/{$lead->id}/calls/initiate");

        // The form request's authorize() short-circuits on no-phone —
        // Laravel maps that to 403.
        $response->assertStatus(403);
    }

    public function test_set_outcome_only_by_placing_agent(): void
    {
        $actorA = User::factory()->create(['account_id' => 1]);
        $actorB = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsCall($actorA);
        $this->grantLeadsCall($actorB);
        $lead = $this->makeLead(accountId: 1);

        // Actor A places the call.
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $actorA->id,
            'direction' => 'outbound',
            'from_number' => '+922135000000',
            'to_number' => '+923005550000',
            'status' => 'completed',
        ]);

        // Actor B (not the placer) tries to set the outcome.
        $this->actingAs($actorB);
        $resp = $this->postJson("/api/leads/{$lead->id}/calls/{$call->id}/outcome", [
            'outcome' => 'interested',
        ]);
        $resp->assertStatus(403);

        // Actor A can.
        $this->actingAs($actorA);
        $resp = $this->postJson("/api/leads/{$lead->id}/calls/{$call->id}/outcome", [
            'outcome' => 'interested',
            'outcome_notes' => 'wants a callback tomorrow',
        ]);
        $resp->assertOk();
        $call->refresh();
        $this->assertSame('interested', $call->outcome);
        $this->assertSame('wants a callback tomorrow', $call->outcome_notes);
    }

    public function test_recording_url_returns_signed_url_when_recording_exists(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsDetail($actor);
        $this->actingAs($actor);
        $lead = $this->makeLead(accountId: 1);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $actor->id,
            'direction' => 'outbound',
            'from_number' => '+922135000000',
            'to_number' => '+923005550000',
            'status' => 'completed',
        ]);
        LeadRecording::create([
            'account_id' => 1,
            'lead_call_id' => $call->id,
            'lead_id' => $lead->id,
            'file_path' => "lead-recordings/1/{$lead->id}/{$call->id}.mp3",
            'file_size' => 1024,
            'mime_type' => 'audio/mpeg',
            'sha256' => str_repeat('a', 64),
            'duration_seconds' => 42,
            'uploaded_at' => now(),
        ]);

        $resp = $this->getJson("/api/leads/{$lead->id}/calls/{$call->id}/recording-url");

        $resp->assertOk();
        $url = (string) $resp->json('data.url');
        $this->assertStringContainsString('/api/lead-recordings/', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_recording_url_404s_when_no_recording_yet(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->grantLeadsDetail($actor);
        $this->actingAs($actor);
        $lead = $this->makeLead(accountId: 1);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $actor->id,
            'direction' => 'outbound',
            'from_number' => '+922135000000',
            'to_number' => '+923005550000',
            'status' => 'in_progress',
        ]);

        $resp = $this->getJson("/api/leads/{$lead->id}/calls/{$call->id}/recording-url");

        $resp->assertStatus(404);
    }

    // ------------------------------------------------------------------

    private function makeLead(int $accountId, ?string $phone = '+923005550000'): Leads
    {
        return Leads::create([
            'account_id' => $accountId,
            'name' => 'Test Lead',
            'phone' => $phone,
            'active' => 1,
        ]);
    }

    private function grantLeadsCall(User $user): void
    {
        $perm = $this->createPermission('leads.call');
        $role = $this->createRole('TestCanCall-'.$user->id);
        $role->givePermissionTo($perm);
        $this->assignRoleWithPivot($user, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantLeadsDetail(User $user): void
    {
        $perm = $this->createPermission('leads.detail.view');
        $role = $this->createRole('TestCanDetail-'.$user->id);
        $role->givePermissionTo($perm);
        $this->assignRoleWithPivot($user, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
