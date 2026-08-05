<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\InitiateCallRequest;
use App\Http\Requests\Leads\SetOutcomeRequest;
use App\Models\LeadCall;
use App\Models\LeadRecording;
use App\Models\Leads;
use App\Services\Voice\TelnyxVoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authenticated call-management surface for the leads screen.
 *
 * All endpoints live under `/api/leads/{lead}/calls/*` and are gated by
 * the `leads.call` permission (see LeadPolicy::call + the
 * add_leads_call_permission migration).
 */
class LeadCallController extends Controller
{
    public function __construct(private readonly TelnyxVoiceService $telnyx) {}

    /**
     * `POST /api/leads/{lead}/calls/token`
     * Mint a short-lived Telnyx login token for the current agent so the
     * browser SDK (`@telnyx/webrtc`) can register and place/receive calls.
     * The lead in the URL is used only for the permission scope check —
     * the same token drives the whole session and works for any lead the
     * agent has access to.
     */
    public function token(Leads $lead): JsonResponse
    {
        if (! Gate::allows('leads.call')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }
        $user = Auth::user();
        if ($user === null) {
            return $this->errorResponse('Unauthenticated.', 401);
        }
        if ((int) $lead->account_id !== (int) $user->account_id) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $minted = $this->telnyx->mintBrowserToken($user);
        } catch (\Throwable $e) {
            Log::error('Telnyx token mint failed', [
                'event' => 'telnyx.token.error',
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);
            return $this->errorResponse('Unable to initialise call session.', 502);
        }

        return $this->successResponse('Token issued.', [
            'login_token' => $minted['login_token'],
            'sip_username' => $minted['sip_username'],
            'caller_number' => (string) config('services.telnyx.caller_id'),
            'expires_in' => $minted['expires_in'],
        ]);
    }

    /**
     * `POST /api/leads/{lead}/calls/initiate`
     *
     * Bridge-based click-to-call flow (rewritten 2026-08-05):
     *   1. Ensure the agent's telephony credential exists so we know the
     *      SIP URI their browser is registered at.
     *   2. Insert a LeadCall row up-front so downstream webhooks have a
     *      stable id to key on (`client_state`).
     *   3. Originate BOTH outbound legs via the Call Control App:
     *        - customer PSTN leg  (to = normalized PK number)
     *        - agent SIP leg      (to = sip:{sip_username}@sip.telnyx.com)
     *      Both are Call Control legs, so they're bridgeable when
     *      TelnyxController sees both sides answer.
     *   4. Store both call_control_ids on the LeadCall row.
     *
     * Return payload includes just the lead_call_id — the browser doesn't
     * dial anything itself in this flow; it waits for the incoming SIP
     * call Telnyx delivers as the agent leg and auto-answers.
     */
    public function initiate(InitiateCallRequest $request, Leads $lead): JsonResponse
    {
        $user = Auth::user();

        $callerNumber = (string) config('services.telnyx.caller_id');
        $customerNumber = self::normalizePkPhone((string) $lead->phone);
        if ($customerNumber === '') {
            return $this->errorResponse('Lead phone is invalid.', 422);
        }

        // Fetch agent SIP URI FIRST — cheaper to fail early than to write
        // a LeadCall row we then can't dial to. Also warms the credential
        // cache so subsequent calls are faster.
        try {
            $agentSipUri = $this->telnyx->agentSipUri($user);
        } catch (\Throwable $e) {
            Log::error('Telnyx credential fetch failed', [
                'event' => 'telnyx.credential.error',
                'user_id' => $user?->id,
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);
            return $this->errorResponse('Unable to initialise call session.', 502);
        }

        $call = LeadCall::create([
            'account_id' => $lead->account_id,
            'lead_id' => $lead->id,
            'user_id' => $user?->id,
            'direction' => 'outbound',
            'provider' => 'telnyx',
            'from_number' => $callerNumber,
            'to_number' => $customerNumber,
            'status' => 'initiated',
            'initiated_at' => Carbon::now(),
        ]);

        $webhookUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/telnyx/voice';
        try {
            $customerLeg = $this->telnyx->originateOutbound(
                to: $customerNumber,
                from: $callerNumber,
                clientState: ['lead_call_id' => $call->id, 'side' => 'customer'],
                webhookUrl: $webhookUrl,
            );
            $agentLeg = $this->telnyx->originateOutbound(
                to: $agentSipUri,
                from: $callerNumber,
                clientState: ['lead_call_id' => $call->id, 'side' => 'agent'],
                webhookUrl: $webhookUrl,
            );
        } catch (\Throwable $e) {
            $call->update(['status' => 'failed']);
            Log::error('Telnyx bridge originate failed', [
                'event' => 'telnyx.originate.error',
                'lead_call_id' => $call->id,
                'message' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to place call.', 502);
        }

        $call->update([
            'telnyx_customer_ccid' => $customerLeg['call_control_id'],
            'telnyx_agent_ccid' => $agentLeg['call_control_id'],
        ]);

        return $this->successResponse('Call initiated.', [
            'lead_call_id' => $call->id,
        ]);
    }

    /**
     * Coerce a Pakistan phone number in any of the shapes our lead data
     * actually carries into E.164. Duplicated from telnyx-call.ts because
     * we want the server to be the authoritative source of truth for the
     * number that lands in `lead_calls.to_number` and gets dialed.
     *
     * Shapes handled:
     *   "3110022881"     10 digits, no leading zero  → +923110022881
     *   "03110022881"    canonical local format      → +923110022881
     *   "923110022881"   country code, no plus       → +923110022881
     *   "+923110022881"  already E.164               → pass through
     *   anything else                                → best-effort +digits
     */
    private static function normalizePkPhone(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, '+')) {
            return $trimmed;
        }
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '92') && strlen($digits) >= 11 && strlen($digits) <= 13) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+92'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '+92'.$digits;
        }
        return '+'.$digits;
    }

    /**
     * `GET /api/leads/{lead}/calls`
     * Call history for the drawer's Call History tab.
     */
    public function index(Leads $lead): JsonResponse
    {
        if (! Gate::allows('leads.detail.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }
        $user = Auth::user();
        if ($user === null || (int) $lead->account_id !== (int) $user->account_id) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $calls = LeadCall::query()
            ->where('lead_id', $lead->id)
            ->where('account_id', $lead->account_id)
            ->with(['agent:id,name', 'recording:id,lead_call_id,duration_seconds,uploaded_at'])
            ->orderByDesc('initiated_at')
            ->limit(50)
            ->get()
            ->map(fn (LeadCall $c) => [
                'id' => $c->id,
                'direction' => $c->direction,
                'status' => $c->status,
                'outcome' => $c->outcome,
                'outcome_notes' => $c->outcome_notes,
                'from_number' => $c->from_number,
                'to_number' => $c->to_number,
                'duration_seconds' => $c->duration_seconds,
                'initiated_at' => $c->initiated_at?->toIso8601String(),
                'answered_at' => $c->answered_at?->toIso8601String(),
                'ended_at' => $c->ended_at?->toIso8601String(),
                'agent' => $c->agent ? ['id' => $c->agent->id, 'name' => $c->agent->name] : null,
                'has_recording' => $c->recording !== null,
                'recording_duration_seconds' => $c->recording?->duration_seconds,
            ]);

        return $this->successResponse('Records found.', $calls);
    }

    /**
     * `GET /api/leads/{lead}/calls/{call}/recording-url`
     * Return a signed URL that streams the recording (5-min TTL).
     * Phase 1: files live on the local disk under storage/app/lead-recordings/*;
     * URL points at the existing LocalSignedFileController.
     */
    public function recordingUrl(Leads $lead, LeadCall $call): JsonResponse
    {
        if (! Gate::allows('leads.detail.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }
        $user = Auth::user();
        if ($user === null
            || (int) $lead->account_id !== (int) $user->account_id
            || (int) $call->lead_id !== (int) $lead->id
        ) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $recording = $call->recording()->first();
        if ($recording === null) {
            return $this->errorResponse('Recording not available yet.', 404);
        }

        // 5-minute signed URL to our own streaming route — signature IS
        // the authorization, so the stream route runs without session auth.
        $url = URL::temporarySignedRoute(
            'lead-recordings.stream',
            now()->addMinutes(5),
            ['recording' => $recording->id],
        );

        return $this->successResponse('Ok.', [
            'url' => $url,
            'mime_type' => $recording->mime_type,
            'duration_seconds' => $recording->duration_seconds,
            'expires_in_seconds' => 300,
        ]);
    }

    /**
     * `GET /api/lead-recordings/{recording}` (signed URL, no session auth)
     * Streams the MP3 from the local disk. The signature IS the auth —
     * `hasValidRelativeSignature` uses APP_KEY so an attacker without
     * the key can't forge a URL. The signature also pins the recording
     * id, so a URL minted for recording A can't be swapped to recording B.
     */
    public function streamRecording(Request $request, LeadRecording $recording): StreamedResponse
    {
        abort_unless($request->hasValidRelativeSignature(), 404);

        // Belt-and-suspenders: signature validity + file existence.
        abort_if($recording->file_path === '' || str_contains($recording->file_path, '..'), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($recording->file_path), 404);

        return $disk->response($recording->file_path, null, [
            'Content-Type' => $recording->mime_type ?: 'audio/mpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * `POST /api/leads/{lead}/calls/{call}/outcome`
     * Agent's post-call disposition.
     */
    public function setOutcome(SetOutcomeRequest $request, Leads $lead, LeadCall $call): JsonResponse
    {
        if ((int) $call->lead_id !== (int) $lead->id) {
            return $this->errorResponse('Not found.', 404);
        }

        DB::transaction(function () use ($call, $request): void {
            $call->update([
                'outcome' => $request->validated('outcome'),
                'outcome_notes' => $request->validated('outcome_notes'),
            ]);
        });

        return $this->successResponse('Outcome saved.', ['id' => $call->id]);
    }
}
