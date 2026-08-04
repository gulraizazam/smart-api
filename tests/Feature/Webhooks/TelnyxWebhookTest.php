<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Models\Leads;
use App\Models\User;
use App\Services\Voice\TelnyxVoiceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the Telnyx webhook surface.
 *
 * The invariants that MUST hold for click-to-call to survive prod:
 *
 *   1. Unsigned / bad-signature requests are rejected 401 — never leak why.
 *   2. `call.answered` events flip the LeadCall row to `in_progress` and
 *      stamp `answered_at`; missing the join key (client_state has no
 *      lead_call_id AND no matching call_control_id) is logged but 200s
 *      so Telnyx doesn't retry forever.
 *   3. `call.recording.saved` dispatches DownloadLeadRecordingJob exactly
 *      once — a re-delivery for the same recording_id is a no-op.
 */
class TelnyxWebhookTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    /**
     * A deterministic Ed25519 keypair fixed at test-setup time so we can
     * sign fake webhook bodies with the private half + configure the
     * public half on `services.telnyx.public_key`. Keeps sign/verify in
     * lockstep without hitting Telnyx.
     *
     * @var array{public:string,secret:string}
     */
    private array $keypair;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        $pair = sodium_crypto_sign_keypair();
        $this->keypair = [
            'public' => sodium_crypto_sign_publickey($pair),
            'secret' => sodium_crypto_sign_secretkey($pair),
        ];
        Config::set('services.telnyx.public_key', base64_encode($this->keypair['public']));
        Config::set('services.telnyx.api_key', 'KEY-test');
        Config::set('services.telnyx.connection_id', '123456');
        Config::set('services.telnyx.caller_id', '+14015982433');
        Config::set('services.telnyx.signature_max_age_seconds', 300);
    }

    public function test_missing_signature_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/telnyx/voice', [
            'data' => ['event_type' => 'call.answered', 'payload' => []],
        ]);

        $response->assertStatus(401);
        // 401 body must be empty — never leak the reason.
        $this->assertSame('', $response->getContent());
    }

    public function test_bad_signature_returns_401(): void
    {
        $body = json_encode(['data' => ['event_type' => 'call.answered', 'payload' => []]]);
        $response = $this->call(
            'POST',
            '/api/webhooks/telnyx/voice',
            [], [], [], [
                'HTTP_TELNYX-SIGNATURE-ED25519-SIGNATURE' => base64_encode(str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES)),
                'HTTP_TELNYX-SIGNATURE-ED25519-TIMESTAMP' => (string) time(),
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );

        $response->assertStatus(401);
    }

    public function test_call_answered_marks_row_in_progress_and_starts_recording(): void
    {
        $lead = $this->makeLead(1);
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'provider' => 'telnyx',
            'from_number' => '+14015982433',
            'to_number' => '+923005550000',
            'status' => 'ringing',
        ]);

        $fake = Mockery::mock(TelnyxVoiceService::class)->makePartial();
        $fake->shouldReceive('startRecording')
            ->once()
            ->with('ccid-42')
            ->andReturn(true);
        $this->app->instance(TelnyxVoiceService::class, $fake);

        $payload = [
            'data' => [
                'event_type' => 'call.answered',
                'payload' => [
                    'call_control_id' => 'ccid-42',
                    'call_leg_id' => 'leg-42',
                    'client_state' => base64_encode(json_encode(['lead_call_id' => $call->id])),
                ],
            ],
        ];
        $body = (string) json_encode($payload);
        $response = $this->postSigned('/api/webhooks/telnyx/voice', $body);

        $response->assertOk();
        $call->refresh();
        $this->assertSame('in_progress', $call->status);
        $this->assertNotNull($call->answered_at);
        $this->assertSame('ccid-42', $call->telnyx_call_control_id);
    }

    public function test_recording_saved_dispatches_download_job_once(): void
    {
        Queue::fake();

        $lead = $this->makeLead(1);
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'provider' => 'telnyx',
            'from_number' => '+14015982433',
            'to_number' => '+923005550000',
            'status' => 'completed',
        ]);

        $payload = [
            'data' => [
                'event_type' => 'call.recording.saved',
                'payload' => [
                    'recording_id' => 'rec-abc',
                    'recording_urls' => ['mp3' => 'https://cdn.telnyx.com/rec-abc.mp3'],
                    'duration_millis' => 42_000,
                    'client_state' => base64_encode(json_encode(['lead_call_id' => $call->id])),
                ],
            ],
        ];
        $body = (string) json_encode($payload);

        $this->postSigned('/api/webhooks/telnyx/voice', $body)->assertOk();
        Queue::assertPushed(\App\Jobs\DownloadLeadRecordingJob::class, 1);

        // Second delivery for the same recording_id → no additional dispatch.
        // Seed a LeadRecording with that telnyx_recording_id to simulate the
        // job having completed before the second webhook arrives.
        LeadRecording::create([
            'account_id' => 1,
            'lead_call_id' => $call->id,
            'lead_id' => $lead->id,
            'file_path' => 'lead-recordings/1/'.$lead->id.'/'.$call->id.'.mp3',
            'file_size' => 100,
            'mime_type' => 'audio/mpeg',
            'sha256' => str_repeat('x', 64),
            'duration_seconds' => 42,
            'telnyx_recording_id' => 'rec-abc',
            'provider' => 'telnyx',
            'uploaded_at' => now(),
        ]);

        Queue::fake(); // reset the counter for the second call
        $this->postSigned('/api/webhooks/telnyx/voice', $body)->assertOk();
        Queue::assertNotPushed(\App\Jobs\DownloadLeadRecordingJob::class);
    }

    public function test_unmatched_event_type_returns_200_and_does_nothing(): void
    {
        $body = (string) json_encode([
            'data' => ['event_type' => 'call.speak.started', 'payload' => []],
        ]);
        $this->postSigned('/api/webhooks/telnyx/voice', $body)->assertOk();
        // Nothing to assert on — just proving we don't 5xx and don't crash
        // on unknown types (Telnyx would retry forever otherwise).
    }

    // ------------------------------------------------------------------

    /**
     * Sign the body with the test private key + POST it — the request will
     * pass VerifyTelnyxSignature. Same code path production uses, minus
     * the network round-trip to Telnyx.
     */
    private function postSigned(string $uri, string $body): \Illuminate\Testing\TestResponse
    {
        $timestamp = (string) time();
        $signature = base64_encode(
            sodium_crypto_sign_detached($timestamp.'|'.$body, $this->keypair['secret']),
        );

        return $this->call(
            'POST',
            $uri,
            [], [], [], [
                'HTTP_TELNYX-SIGNATURE-ED25519-SIGNATURE' => $signature,
                'HTTP_TELNYX-SIGNATURE-ED25519-TIMESTAMP' => $timestamp,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body,
        );
    }

    private function makeLead(int $accountId): Leads
    {
        return Leads::create([
            'account_id' => $accountId,
            'name' => 'Test Lead',
            'phone' => '+923005550000',
            'active' => 1,
        ]);
    }
}
