<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Services\Voice\TelnyxVoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pull a completed recording from the voice provider, hash it, store it,
 * insert the lead_recordings row.
 *
 * Shape copied from {@see RebuildDailyMetrics}: tries/backoff/timeout +
 * WithoutOverlapping keyed on the lead_call_id so two webhook
 * re-deliveries can't race each other to write different bytes to the
 * same file. The download itself is fully streamed (no full audio blob in
 * memory), so timeout is 300s for a worst-case 30-minute call over a slow
 * hop from the provider CDN.
 *
 * Idempotent on:
 *   • lead_call_id (unique constraint — a second row per call is
 *     structurally impossible),
 *   • sha256 (same audio bytes → we reuse the file the first job wrote
 *     instead of writing duplicates),
 *   • {provider}_recording_id (provider dedup — we've seen this recording).
 */
final class DownloadLeadRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        public readonly int $leadCallId,
        public readonly string $recordingUrl,
        public readonly string $providerRecordingId,
        public readonly int $durationSeconds,
        public readonly string $provider = 'telnyx',
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("lead-rec:{$this->leadCallId}"))
                ->expireAfter(600)
                ->dontRelease(),
        ];
    }

    public function handle(TelnyxVoiceService $telnyx): void
    {
        $call = LeadCall::find($this->leadCallId);
        if ($call === null) {
            Log::info('DownloadLeadRecording: LeadCall gone', [
                'event' => 'lead_recording.download.no_call',
                'lead_call_id' => $this->leadCallId,
            ]);
            return;
        }

        // Fast-path idempotency: already stored?
        $existing = LeadRecording::where('lead_call_id', $call->id)->first();
        if ($existing !== null) {
            Log::info('DownloadLeadRecording: already stored', [
                'event' => 'lead_recording.download.already_stored',
                'lead_call_id' => $call->id,
                'recording_id' => $existing->id,
            ]);
            return;
        }

        $relPath = sprintf(
            'lead-recordings/%d/%d/%d.mp3',
            (int) $call->account_id,
            (int) $call->lead_id,
            (int) $call->id,
        );

        $disk = Storage::disk('local');
        $absPath = $disk->path($relPath);

        // Ensure the target directory exists (Storage::put would auto-create,
        // but fopen won't).
        if (! is_dir(dirname($absPath))) {
            @mkdir(dirname($absPath), 0755, true);
        }

        $tmpPath = $absPath.'.part';
        $sink = fopen($tmpPath, 'wb');
        if ($sink === false) {
            throw new \RuntimeException("Cannot open sink for write: {$tmpPath}");
        }

        try {
            $stats = $telnyx->downloadRecording($this->recordingUrl, $sink);
        } finally {
            fclose($sink);
        }

        if ($stats['bytes'] <= 0) {
            @unlink($tmpPath);
            throw new \RuntimeException('Voice provider returned an empty recording body');
        }

        // Move into place atomically.
        if (! @rename($tmpPath, $absPath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to move recording into place: {$absPath}");
        }

        DB::transaction(function () use ($call, $relPath, $stats): void {
            // Race check: another worker may have inserted between our
            // fast-path check and here. Re-check inside the transaction
            // and, if a row exists now, drop the file we just wrote.
            $race = LeadRecording::where('lead_call_id', $call->id)->lockForUpdate()->first();
            if ($race !== null) {
                Storage::disk('local')->delete($relPath);
                return;
            }

            LeadRecording::create([
                'account_id' => $call->account_id,
                'lead_call_id' => $call->id,
                'lead_id' => $call->lead_id,
                'file_path' => $relPath,
                'file_size' => $stats['bytes'],
                'mime_type' => 'audio/mpeg',
                'sha256' => $stats['sha256'],
                'duration_seconds' => $this->durationSeconds > 0 ? $this->durationSeconds : 0,
                'provider' => $this->provider,
                'telnyx_recording_id' => $this->provider === 'telnyx' && $this->providerRecordingId !== ''
                    ? $this->providerRecordingId
                    : null,
                'uploaded_at' => Carbon::now(),
            ]);
        });

        Log::info('DownloadLeadRecording: stored', [
            'event' => 'lead_recording.download.stored',
            'lead_call_id' => $call->id,
            'lead_id' => $call->lead_id,
            'bytes' => $stats['bytes'],
            'sha256_prefix' => substr($stats['sha256'], 0, 12),
        ]);
    }
}
