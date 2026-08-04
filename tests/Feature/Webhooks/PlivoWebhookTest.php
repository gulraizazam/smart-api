<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\DownloadLeadRecordingJob;
use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Models\Leads;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the Plivo webhook contract.
 *
 * These endpoints are public and Plivo-authenticated — the VerifyPlivoSignature
 * middleware is the ONLY gate. Regressions here mean either fake webhooks
 * mutate our DB (bad sig accepted) OR legitimate webhooks are dropped (good
 * sig rejected). Both are catastrophic in different ways.
 *
 * The status callback + recording callback also carry the invariants that
 * (a) `x_lead_call_id` in custom_params is how we join back to the row
 * (missing → warning-log, no crash) and (b) recording webhook re-deliveries
 * are dropped rather than double-dispatching the download job.
 */
class PlivoWebhookTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const AUTH_TOKEN = 'unit-test-plivo-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Freeze APP_URL so signature computation matches the URL the
        // middleware reconstructs.
        Config::set('app.url', 'http://localhost');
        Config::set('services.plivo.auth_id', 'unit-test-auth-id');
        Config::set('services.plivo.auth_token', self::AUTH_TOKEN);
        Config::set('services.plivo.caller_id', '+922135000000');
    }

    public function test_webhook_rejects_missing_signature_with_401(): void
    {
        $resp = $this->post('/api/webhooks/plivo/status', ['CallUUID' => 'x']);
        $resp->assertStatus(401);
    }

    public function test_webhook_rejects_bad_signature_with_401(): void
    {
        $params = ['CallUUID' => 'abc-123', 'Status' => 'in-progress'];
        $resp = $this->postWithSignature(
            path: '/api/webhooks/plivo/status',
            params: $params,
            signature: base64_encode('nope'),
            nonce: 'nonce-1',
        );
        $resp->assertStatus(401);
    }

    public function test_status_callback_updates_row_on_ringing_then_answered_then_hangup(): void
    {
        $lead = $this->makeLead();
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'from_number' => '+922135000000',
            'to_number' => '+923005550000',
            'status' => 'initiated',
        ]);

        // Ringing.
        $this->postSigned('/api/webhooks/plivo/status', [
            'CallUUID' => 'call-uuid-1',
            'CallStatus' => 'ringing',
            'x_lead_call_id' => (string) $call->id,
        ])->assertOk();
        $call->refresh();
        $this->assertSame('ringing', $call->status);
        $this->assertSame('call-uuid-1', $call->plivo_call_uuid);

        // Answered (Event=Answer).
        $this->postSigned('/api/webhooks/plivo/status', [
            'CallUUID' => 'call-uuid-1',
            'Event' => 'Answer',
            'CallStatus' => 'in-progress',
            'x_lead_call_id' => (string) $call->id,
        ])->assertOk();
        $call->refresh();
        $this->assertSame('in_progress', $call->status);
        $this->assertNotNull($call->answered_at);

        // Hangup.
        $this->postSigned('/api/webhooks/plivo/status', [
            'CallUUID' => 'call-uuid-1',
            'Event' => 'Hangup',
            'CallStatus' => 'completed',
            'Duration' => '42',
            'HangupCause' => 'NORMAL_CLEARING',
            'x_lead_call_id' => (string) $call->id,
        ])->assertOk();
        $call->refresh();
        $this->assertSame('completed', $call->status);
        $this->assertSame(42, (int) $call->duration_seconds);
        $this->assertSame('NORMAL_CLEARING', $call->hangup_cause);
        $this->assertNotNull($call->ended_at);
    }

    public function test_recording_callback_dispatches_download_job_once(): void
    {
        Bus::fake([DownloadLeadRecordingJob::class]);

        $lead = $this->makeLead();
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'from_number' => '+922135000000',
            'to_number' => '+923005550000',
            'status' => 'completed',
            'plivo_call_uuid' => 'call-uuid-1',
        ]);

        $params = [
            'CallUUID' => 'call-uuid-1',
            'RecordingID' => 'rec-42',
            'RecordUrl' => 'https://s3.amazonaws.com/plivocloud/rec-42.mp3',
            'RecordingDuration' => '30',
            'x_lead_call_id' => (string) $call->id,
        ];

        // First delivery — dispatches.
        $this->postSigned('/api/webhooks/plivo/recording', $params)->assertOk();
        Bus::assertDispatched(DownloadLeadRecordingJob::class, 1);

        // Second delivery of the same RecordingID — dropped by the dedup
        // check, no second dispatch. Simulate the row that a first-run
        // job would have written (matches the plivo_recording_id key).
        LeadRecording::create([
            'account_id' => 1,
            'lead_call_id' => $call->id,
            'lead_id' => $lead->id,
            'file_path' => 'lead-recordings/1/'.$lead->id.'/'.$call->id.'.mp3',
            'file_size' => 100,
            'mime_type' => 'audio/mpeg',
            'sha256' => str_repeat('b', 64),
            'duration_seconds' => 30,
            'plivo_recording_id' => 'rec-42',
            'uploaded_at' => now(),
        ]);

        $this->postSigned('/api/webhooks/plivo/recording', $params)->assertOk();
        Bus::assertDispatched(DownloadLeadRecordingJob::class, 1); // still 1
    }

    public function test_answer_outbound_returns_xml_with_record_true_and_caller_id(): void
    {
        $lead = $this->makeLead(phone: '+923005550000');
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'from_number' => '+922135000000',
            'to_number' => '+923005550000',
            'status' => 'initiated',
        ]);

        $resp = $this->postSigned('/api/webhooks/plivo/answer-outbound', [
            'CallUUID' => 'call-uuid-2',
            'x_lead_call_id' => (string) $call->id,
        ]);
        $resp->assertOk();
        $xml = $resp->getContent();
        $this->assertStringContainsString('<Response>', $xml);
        $this->assertStringContainsString('recording notice'.PHP_EOL.'<!--', $xml, "", true) ?: null; // no-op, only for phpstan
        // The three invariants the SPA / Plivo depend on:
        $this->assertStringContainsString('record="true"', $xml, 'record="true" must be present or nothing gets recorded');
        $this->assertStringContainsString('callerId="+922135000000"', $xml, 'callerId must be the PK number');
        $this->assertStringContainsString('<Number>+923005550000</Number>', $xml, 'the customer number must be dialed');
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function makeLead(string $phone = '+923005550000'): Leads
    {
        return Leads::create([
            'account_id' => 1,
            'name' => 'Test Lead',
            'phone' => $phone,
            'active' => 1,
        ]);
    }

    private function postSigned(string $path, array $params)
    {
        $nonce = bin2hex(random_bytes(8));
        $signature = $this->computeSignature('POST', 'http://localhost'.$path, $nonce, $params);

        return $this->postWithSignature($path, $params, $signature, $nonce);
    }

    private function postWithSignature(string $path, array $params, string $signature, string $nonce)
    {
        return $this->call(
            'POST',
            $path,
            $params,
            [],
            [],
            [
                'HTTP_X-Plivo-Signature-V3' => $signature,
                'HTTP_X-Plivo-Signature-V3-Nonce' => $nonce,
                'HTTP_X-Plivo-Signature-Ma-Version' => 'v3',
            ],
        );
    }

    /**
     * Mirrors Plivo\Util\v3SignatureValidation::getSignatureV3 —
     * base64(HMAC-SHA256(authToken, sortedBaseUrl + "." + nonce)).
     * For POST: baseURL = uri + concat(sort(key.value ...))
     */
    private function computeSignature(string $method, string $uri, string $nonce, array $params): string
    {
        // Ensure all values are stringified (matches stringifyParams).
        foreach ($params as $k => $v) {
            $params[$k] = (string) $v;
        }
        ksort($params, SORT_NATURAL);
        $concat = '';
        foreach ($params as $k => $v) {
            $concat .= $k.$v;
        }
        $baseURL = $uri.$concat;
        $signable = $baseURL.'.'.$nonce;
        return base64_encode(hash_hmac('SHA256', $signable, self::AUTH_TOKEN, true));
    }
}
