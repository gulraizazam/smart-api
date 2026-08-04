<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\DownloadLeadRecordingJob;
use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Models\Leads;
use App\Models\User;
use App\Services\Voice\TelnyxVoiceService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the recording-download job's happy path + idempotency.
 *
 * Two invariants:
 *
 *   1. Happy path — job pulls bytes from TelnyxVoiceService::downloadRecording,
 *      writes them to storage/app/lead-recordings/{account}/{lead}/{call}.mp3,
 *      and inserts a lead_recordings row with the correct sha256 + size.
 *
 *   2. Idempotent — if a lead_recordings row already exists for the call
 *      (webhook re-delivery race), the job returns without writing a
 *      second file OR inserting a second row (the unique constraint would
 *      catch it, but the job should short-circuit gracefully).
 */
class DownloadLeadRecordingJobTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_happy_path_writes_file_and_row_with_correct_sha256(): void
    {
        $lead = Leads::create([
            'account_id' => 1,
            'name' => 'Test',
            'phone' => '+923005550000',
            'active' => 1,
        ]);
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'from_number' => '+14015982433',
            'to_number' => '+923005550000',
            'status' => 'completed',
        ]);

        $payload = str_repeat("abc123-", 100); // small deterministic MP3-ish body
        $expectedSha = hash('sha256', $payload);
        $expectedBytes = strlen($payload);

        // Mock the service so we don't reach the network.
        $mock = Mockery::mock(TelnyxVoiceService::class);
        $mock->shouldReceive('downloadRecording')
            ->once()
            ->andReturnUsing(function (string $url, $sink) use ($payload, $expectedSha, $expectedBytes) {
                fwrite($sink, $payload);
                return ['sha256' => $expectedSha, 'bytes' => $expectedBytes];
            });
        $this->app->instance(TelnyxVoiceService::class, $mock);

        $job = new DownloadLeadRecordingJob(
            leadCallId: $call->id,
            recordingUrl: 'https://telnyx-cdn/rec.mp3',
            providerRecordingId: 'rec-1',
            durationSeconds: 42,
            provider: 'telnyx',
        );
        $job->handle($mock);

        // File landed at the expected path.
        $relPath = "lead-recordings/1/{$lead->id}/{$call->id}.mp3";
        $this->assertTrue(
            Storage::disk('local')->exists($relPath),
            "expected file at {$relPath}",
        );

        // Row has correct metadata.
        $rec = LeadRecording::where('lead_call_id', $call->id)->firstOrFail();
        $this->assertSame($relPath, $rec->file_path);
        $this->assertSame($expectedBytes, (int) $rec->file_size);
        $this->assertSame($expectedSha, $rec->sha256);
        $this->assertSame(42, (int) $rec->duration_seconds);
        $this->assertSame('rec-1', $rec->telnyx_recording_id);
        $this->assertSame('telnyx', $rec->provider);

        Storage::disk('local')->delete($relPath); // tidy up
    }

    public function test_idempotent_when_recording_row_already_exists(): void
    {
        $lead = Leads::create([
            'account_id' => 1,
            'name' => 'Test',
            'phone' => '+923005550000',
            'active' => 1,
        ]);
        $agent = User::factory()->create(['account_id' => 1]);
        $call = LeadCall::create([
            'account_id' => 1,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'from_number' => '+14015982433',
            'to_number' => '+923005550000',
            'status' => 'completed',
        ]);
        LeadRecording::create([
            'account_id' => 1,
            'lead_call_id' => $call->id,
            'lead_id' => $lead->id,
            'file_path' => 'lead-recordings/1/'.$lead->id.'/'.$call->id.'.mp3',
            'file_size' => 999,
            'mime_type' => 'audio/mpeg',
            'sha256' => str_repeat('c', 64),
            'duration_seconds' => 10,
            'telnyx_recording_id' => 'rec-1',
            'provider' => 'telnyx',
            'uploaded_at' => now(),
        ]);

        // Should NEVER be called on the fast-path idempotency check.
        $mock = Mockery::mock(TelnyxVoiceService::class);
        $mock->shouldNotReceive('downloadRecording');
        $this->app->instance(TelnyxVoiceService::class, $mock);

        $job = new DownloadLeadRecordingJob(
            leadCallId: $call->id,
            recordingUrl: 'https://telnyx-cdn/rec.mp3',
            providerRecordingId: 'rec-1',
            durationSeconds: 42,
            provider: 'telnyx',
        );
        $job->handle($mock);

        // Still exactly one row.
        $this->assertSame(1, LeadRecording::where('lead_call_id', $call->id)->count());
    }

    public function test_soft_handles_a_missing_lead_call_row(): void
    {
        $mock = Mockery::mock(TelnyxVoiceService::class);
        $mock->shouldNotReceive('downloadRecording');
        $this->app->instance(TelnyxVoiceService::class, $mock);

        $job = new DownloadLeadRecordingJob(
            leadCallId: 999999999,
            recordingUrl: 'https://telnyx-cdn/rec.mp3',
            providerRecordingId: 'rec-missing',
            durationSeconds: 0,
            provider: 'telnyx',
        );
        // Must not throw — the webhook may fire a race where the row was
        // deleted between webhook post and job execution.
        $job->handle($mock);

        $this->assertSame(0, LeadRecording::where('telnyx_recording_id', 'rec-missing')->count());
    }
}
