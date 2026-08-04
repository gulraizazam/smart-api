<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadLeadRecordingJob;
use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Services\Voice\PlivoVoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Plivo webhook endpoints. All are POST, all return XML (for answer/hangup)
 * or empty 200 (for status/recording — Plivo just wants an ack).
 *
 * The route group hangs off `/api/webhooks/plivo/*` and is protected by the
 * `plivo.webhook` middleware — {@see \App\Http\Middleware\VerifyPlivoSignature} —
 * which rejects any unsigned or badly-signed request with 401.
 */
class PlivoController extends Controller
{
    public function __construct(private readonly PlivoVoiceService $plivo) {}

    /**
     * `POST /api/webhooks/plivo/answer-outbound`
     * Plivo hits this when the SPA's outbound call is being placed. We
     * return the XML that instructs Plivo to play the recording notice
     * and bridge to the customer's number with record=true.
     */
    public function answerOutbound(Request $request): Response
    {
        $callUuid = $request->input('CallUUID');
        $leadCallId = $request->input('x_lead_call_id');

        $call = null;
        if ($leadCallId !== null && ctype_digit((string) $leadCallId)) {
            $call = LeadCall::find((int) $leadCallId);
        }
        if ($call === null && is_string($callUuid)) {
            $call = LeadCall::where('plivo_call_uuid', $callUuid)->first();
        }
        if ($call === null) {
            Log::warning('Plivo answer-outbound: no LeadCall row found', [
                'event' => 'plivo.answer.no_row',
                'call_uuid' => $callUuid,
                'lead_call_id' => $leadCallId,
            ]);
            // Return a benign hangup rather than a 500 — 500 makes Plivo retry.
            return $this->xml(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<Response>
    <Hangup/>
</Response>
XML);
        }

        if (is_string($callUuid) && $callUuid !== '' && $call->plivo_call_uuid === null) {
            $call->update(['plivo_call_uuid' => $callUuid]);
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        return $this->xml($this->plivo->answerXmlForOutbound($call, $baseUrl));
    }

    /**
     * `POST /api/webhooks/plivo/answer-inbound`
     * Plivo hits this when a customer dials our PK number. We look up
     * the lead by phone, route to its last-attempting agent's endpoint,
     * or fall back to a polite hangup.
     */
    public function answerInbound(Request $request): Response
    {
        $from = (string) $request->input('From', '');
        $baseUrl = rtrim((string) config('app.url'), '/');
        return $this->xml($this->plivo->answerXmlForInbound($from, $baseUrl));
    }

    /**
     * `POST /api/webhooks/plivo/status`
     * Plivo posts call status transitions here. We update the matching
     * lead_calls row in place; empty 200 back either way (never make
     * Plivo retry for a state we've already processed).
     */
    public function callStatus(Request $request): Response
    {
        $callUuid = (string) $request->input('CallUUID', '');
        if ($callUuid === '') {
            return new Response('', 200);
        }

        $leadCallId = $request->input('x_lead_call_id');
        $call = null;
        if ($leadCallId !== null && ctype_digit((string) $leadCallId)) {
            $call = LeadCall::find((int) $leadCallId);
        }
        if ($call === null) {
            $call = LeadCall::where('plivo_call_uuid', $callUuid)->first();
        }
        if ($call === null) {
            Log::info('Plivo status: no LeadCall row for CallUUID', [
                'event' => 'plivo.status.no_row',
                'call_uuid' => $callUuid,
            ]);
            return new Response('', 200);
        }

        $status = (string) $request->input('Status', '');
        $callStatus = (string) $request->input('CallStatus', $status);
        $duration = (int) $request->input('Duration', $request->input('BillDuration', 0));
        $hangupCause = (string) $request->input('HangupCause', '');
        $event = (string) $request->input('Event', '');

        $updates = ['plivo_call_uuid' => $callUuid];

        // Plivo's vocabulary: Status = ringing/in-progress/completed/etc,
        // Event = StartApp/Answer/Hangup/etc. Prefer whichever is set.
        $mapped = match (true) {
            $event === 'Answer' || $callStatus === 'in-progress' => 'in_progress',
            $event === 'Hangup' || $callStatus === 'completed' => 'completed',
            $callStatus === 'ringing' => 'ringing',
            $callStatus === 'busy' => 'busy',
            $callStatus === 'no-answer' => 'no_answer',
            $callStatus === 'failed' => 'failed',
            $callStatus === 'canceled' || $callStatus === 'cancelled' => 'canceled',
            default => null,
        };
        if ($mapped !== null) {
            $updates['status'] = $mapped;
        }

        if ($event === 'Answer' && $call->answered_at === null) {
            $updates['answered_at'] = Carbon::now();
        }
        if ($event === 'Hangup' || in_array($mapped, ['completed', 'busy', 'no_answer', 'failed', 'canceled'], true)) {
            $updates['ended_at'] = Carbon::now();
            if ($duration > 0) {
                $updates['duration_seconds'] = $duration;
            }
            if ($hangupCause !== '') {
                $updates['hangup_cause'] = $hangupCause;
            }
        }

        $call->update($updates);

        return new Response('', 200);
    }

    /**
     * `POST /api/webhooks/plivo/recording`
     * Plivo posts recording metadata after a call finishes. We dedup on
     * `RecordingID` (Plivo may re-post if we return non-2xx), then
     * dispatch the async download job. Empty 200 back.
     */
    public function recording(Request $request): Response
    {
        $callUuid = (string) $request->input('CallUUID', '');
        $recordingId = (string) $request->input('RecordingID', '');
        $recordingUrl = (string) $request->input('RecordUrl', $request->input('RecordingUrl', ''));
        $duration = (int) round((float) $request->input('RecordingDuration', $request->input('Duration', 0)));

        if ($callUuid === '' || $recordingUrl === '') {
            Log::warning('Plivo recording webhook missing required fields', [
                'event' => 'plivo.recording.missing_fields',
                'has_uuid' => $callUuid !== '',
                'has_url' => $recordingUrl !== '',
            ]);
            return new Response('', 200);
        }

        $leadCallId = $request->input('x_lead_call_id');
        $call = null;
        if ($leadCallId !== null && ctype_digit((string) $leadCallId)) {
            $call = LeadCall::find((int) $leadCallId);
        }
        if ($call === null) {
            $call = LeadCall::where('plivo_call_uuid', $callUuid)->first();
        }
        if ($call === null) {
            Log::info('Plivo recording: no LeadCall row', [
                'event' => 'plivo.recording.no_row',
                'call_uuid' => $callUuid,
            ]);
            return new Response('', 200);
        }

        // Dedup: if we already have a recording for this call OR we've
        // seen this exact Plivo RecordingID, drop this delivery.
        $existing = LeadRecording::where('lead_call_id', $call->id)
            ->orWhere(function ($q) use ($recordingId): void {
                if ($recordingId !== '') {
                    $q->where('plivo_recording_id', $recordingId);
                }
            })
            ->first();
        if ($existing !== null) {
            return new Response('', 200);
        }

        DB::afterCommit(function () use ($call, $recordingUrl, $recordingId, $duration): void {
            DownloadLeadRecordingJob::dispatch(
                leadCallId: $call->id,
                recordingUrl: $recordingUrl,
                plivoRecordingId: $recordingId,
                durationSeconds: $duration,
            );
        });

        // No open transaction? afterCommit fires immediately. Wrap in a
        // trivial transaction to force the dispatch to defer until this
        // request's response has flushed.
        DB::transaction(fn () => null);

        return new Response('', 200);
    }

    private function xml(string $xml): Response
    {
        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
