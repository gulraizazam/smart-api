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
     * Write the lead_calls intent row before the browser dials; the
     * returned id gets base64-encoded into Telnyx's `client_state` on the
     * outbound leg so every subsequent webhook can find the row without
     * needing a call_control_id round-trip.
     */
    public function initiate(InitiateCallRequest $request, Leads $lead): JsonResponse
    {
        $user = Auth::user();

        $call = LeadCall::create([
            'account_id' => $lead->account_id,
            'lead_id' => $lead->id,
            'user_id' => $user?->id,
            'direction' => 'outbound',
            'provider' => 'telnyx',
            'from_number' => (string) config('services.telnyx.caller_id'),
            'to_number' => (string) $lead->phone,
            'status' => 'initiated',
            'initiated_at' => Carbon::now(),
        ]);

        return $this->successResponse('Call initiated.', [
            'lead_call_id' => $call->id,
            // Browser SDK passes this verbatim as `clientState` on newCall();
            // Telnyx echoes it on every webhook so TelnyxController can
            // resolve the LeadCall row without a call_control_id round-trip.
            'client_state' => TelnyxVoiceService::encodeClientState([
                'lead_call_id' => $call->id,
                'lead_id' => $lead->id,
            ]),
        ]);
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
