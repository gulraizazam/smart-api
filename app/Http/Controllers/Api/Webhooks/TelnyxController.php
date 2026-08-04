<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadLeadRecordingJob;
use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Services\Voice\TelnyxVoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Telnyx webhook endpoints. All POST, all return `{"success":true}` (200) —
 * Telnyx re-delivers on 5xx and after a timeout, so we ack fast and do work
 * async when it's non-trivial.
 *
 * Signature verification runs in {@see \App\Http\Middleware\VerifyTelnyxSignature}
 * (Ed25519). By the time a request reaches this controller the body is
 * proven authentic; we still validate shape defensively.
 *
 * Route group: `/api/webhooks/telnyx/*`, middleware `telnyx.webhook`.
 */
class TelnyxController extends Controller
{
    public function __construct(private readonly TelnyxVoiceService $telnyx) {}

    /**
     * `POST /api/webhooks/telnyx/voice`
     * Every Call Control event (initiated / answered / hangup /
     * recording.saved / …) arrives on this one URL — Telnyx puts the type
     * in `data.event_type`. We dispatch on that; unknown types are ack'd
     * (so Telnyx doesn't retry forever) and structured-logged for follow-up.
     */
    public function voice(Request $request): JsonResponse
    {
        $eventType = (string) $request->input('data.event_type', '');
        $payload = (array) $request->input('data.payload', []);

        match ($eventType) {
            'call.initiated' => $this->onCallInitiated($payload),
            'call.answered' => $this->onCallAnswered($payload),
            'call.hangup' => $this->onCallHangup($payload),
            'call.recording.saved' => $this->onRecordingSaved($payload),
            default => Log::info('Telnyx event ignored', [
                'event' => 'telnyx.event.ignored',
                'event_type' => $eventType,
            ]),
        };

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------

    /**
     * `call.initiated` fires as soon as the browser SDK originates a call.
     * We use client_state (base64 JSON the SDK put on the leg) to find the
     * lead_calls row initiate() wrote, then patch in the call_control_id
     * so subsequent events can look it up if client_state ever drops.
     *
     * @param  array<string,mixed>  $payload
     */
    private function onCallInitiated(array $payload): void
    {
        $call = $this->resolveCall($payload);
        if ($call === null) {
            return;
        }

        $updates = [];
        if (($call->telnyx_call_control_id ?? null) === null && isset($payload['call_control_id'])) {
            $updates['telnyx_call_control_id'] = (string) $payload['call_control_id'];
        }
        if (($call->telnyx_call_leg_id ?? null) === null && isset($payload['call_leg_id'])) {
            $updates['telnyx_call_leg_id'] = (string) $payload['call_leg_id'];
        }
        if ($call->status === 'initiated' && ! empty($updates)) {
            $updates['status'] = 'ringing';
        }
        if ($updates !== []) {
            $call->update($updates);
        }
    }

    /**
     * `call.answered` fires when the far leg picks up. Two things happen:
     *   1. mark our row `in_progress` + stamp answered_at,
     *   2. fire-and-forget a `record_start` command against the leg so the
     *      audio is captured. We do this AFTER answer (not on initiate) so
     *      we don't record ringtone/voicemail-greeting audio we don't need.
     *
     * @param  array<string,mixed>  $payload
     */
    private function onCallAnswered(array $payload): void
    {
        $call = $this->resolveCall($payload);
        if ($call === null) {
            return;
        }

        $callControlId = (string) ($payload['call_control_id'] ?? $call->telnyx_call_control_id ?? '');

        DB::transaction(function () use ($call, $payload): void {
            $call->refresh();
            if ($call->answered_at === null) {
                $call->update([
                    'status' => 'in_progress',
                    'answered_at' => Carbon::now(),
                    'telnyx_call_control_id' => (string) ($payload['call_control_id']
                        ?? $call->telnyx_call_control_id
                        ?? ''),
                ]);
            }
        });

        if ($callControlId !== '') {
            // Best-effort — start_recording can 4xx (e.g. leg already gone),
            // that's logged inside the service but not fatal to the webhook.
            $this->telnyx->startRecording($callControlId);
        }
    }

    /**
     * `call.hangup` fires when either side ends. Populate ended_at,
     * duration, hangup cause, and map the terminal status.
     *
     * @param  array<string,mixed>  $payload
     */
    private function onCallHangup(array $payload): void
    {
        $call = $this->resolveCall($payload);
        if ($call === null) {
            return;
        }

        $cause = (string) ($payload['hangup_cause'] ?? '');
        $source = (string) ($payload['hangup_source'] ?? '');

        // Telnyx doesn't give us a duration directly; compute from
        // start_time / end_time if present, else fall back to answered_at.
        $duration = 0;
        $endTime = $payload['end_time'] ?? null;
        $startTime = $payload['start_time'] ?? null;
        if (is_string($endTime) && is_string($startTime)) {
            try {
                $duration = max(0, Carbon::parse($endTime)->diffInSeconds(Carbon::parse($startTime)));
            } catch (\Throwable) {
                $duration = 0;
            }
        } elseif ($call->answered_at !== null) {
            $duration = max(0, Carbon::now()->diffInSeconds($call->answered_at));
        }

        $mappedStatus = match ($cause) {
            'normal_clearing', 'call_rejected' => 'completed',
            'user_busy' => 'busy',
            'no_answer', 'no_user_response' => 'no_answer',
            'call_cancelled', 'originator_cancel' => 'canceled',
            default => 'completed',
        };
        // If we never saw an answer, "completed" is misleading — flag it.
        if ($call->answered_at === null && $mappedStatus === 'completed') {
            $mappedStatus = 'no_answer';
        }

        $call->update([
            'status' => $mappedStatus,
            'ended_at' => Carbon::now(),
            'duration_seconds' => $duration,
            'hangup_cause' => $cause !== '' ? substr($cause.($source !== '' ? ':'.$source : ''), 0, 64) : null,
        ]);
    }

    /**
     * `call.recording.saved` fires once Telnyx has finished producing the
     * recording file (they hold it on S3). We dedup on recording_id, then
     * dispatch the async job to stream the file into our disk.
     *
     * @param  array<string,mixed>  $payload
     */
    private function onRecordingSaved(array $payload): void
    {
        $call = $this->resolveCall($payload);
        if ($call === null) {
            return;
        }

        $recordingId = (string) ($payload['recording_id'] ?? $payload['id'] ?? '');

        // Telnyx's recording_urls block is keyed by format: { "mp3": "...", "wav": "..." }.
        // We requested mp3 in TelnyxVoiceService::startRecording, so that's
        // what we expect — fall back to any URL Telnyx gave us if the shape
        // ever changes.
        $urls = (array) ($payload['recording_urls'] ?? []);
        $recordingUrl = (string) ($urls['mp3'] ?? $urls['wav'] ?? $payload['download_url'] ?? '');

        $duration = (int) round((float) ($payload['duration_millis'] ?? 0) / 1000);
        if ($duration === 0 && isset($payload['duration_secs'])) {
            $duration = (int) $payload['duration_secs'];
        }

        if ($recordingUrl === '') {
            Log::warning('Telnyx recording.saved missing download URL', [
                'event' => 'telnyx.recording.no_url',
                'recording_id' => $recordingId,
                'lead_call_id' => $call->id,
            ]);
            return;
        }

        // Dedup: existing recording for this call OR same telnyx_recording_id
        // already seen → drop this delivery. Wrapped in an outer `where`
        // closure so the orWhere can't escape and match every row when the
        // recording_id branch is skipped.
        $existing = LeadRecording::query()
            ->where(function ($q) use ($call, $recordingId): void {
                $q->where('lead_call_id', $call->id);
                if ($recordingId !== '') {
                    $q->orWhere('telnyx_recording_id', $recordingId);
                }
            })
            ->first();
        if ($existing !== null) {
            return;
        }

        DB::afterCommit(function () use ($call, $recordingUrl, $recordingId, $duration): void {
            DownloadLeadRecordingJob::dispatch(
                leadCallId: $call->id,
                recordingUrl: $recordingUrl,
                providerRecordingId: $recordingId,
                durationSeconds: $duration,
                provider: 'telnyx',
            );
        });
        // Force afterCommit even outside an explicit transaction — matches
        // the pattern the Plivo controller used.
        DB::transaction(fn () => null);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Locate the LeadCall row this webhook is about, in preference order:
     *   1. `client_state` — base64 JSON we set at initiate time, echoed on
     *      every event; carries `lead_call_id`.
     *   2. `call_control_id` — Telnyx's identifier we stored on our row on
     *      the first event that arrived.
     *
     * Missing rows are structured-logged but not treated as errors — a
     * webhook can fire faster than DB replication, and Telnyx will retry.
     *
     * @param  array<string,mixed>  $payload
     */
    private function resolveCall(array $payload): ?LeadCall
    {
        $state = TelnyxVoiceService::decodeClientState(
            isset($payload['client_state']) ? (string) $payload['client_state'] : null,
        );
        $leadCallId = isset($state['lead_call_id']) ? (int) $state['lead_call_id'] : 0;
        if ($leadCallId > 0) {
            $call = LeadCall::find($leadCallId);
            if ($call !== null) {
                return $call;
            }
        }

        $callControlId = (string) ($payload['call_control_id'] ?? '');
        if ($callControlId !== '') {
            $call = LeadCall::where('telnyx_call_control_id', $callControlId)->first();
            if ($call !== null) {
                return $call;
            }
        }

        Log::info('Telnyx webhook: no matching LeadCall', [
            'event' => 'telnyx.webhook.no_row',
            'call_control_id' => $callControlId,
            'client_state_had_id' => $leadCallId > 0,
        ]);
        return null;
    }
}
