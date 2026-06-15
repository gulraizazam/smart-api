<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patients;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Support\WhatsAppOptOut;
use App\Support\WhatsAppPhoneMatch;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Inbound side of the WhatsApp Cloud API (Meta) integration — Phase 1.
 *
 * Public by design (no session/bearer auth): Meta's servers authenticate via
 * the verify-token handshake on GET and an X-Hub-Signature-256 HMAC (signed
 * with the Meta app secret) on every POST. CSRF is exempted for this URI in
 * App\Http\Middleware\VerifyCsrfToken.
 *
 * POST always answers 200 once the signature checks out — Meta retries
 * non-2xx deliveries with backoff, so per-message parse problems are logged
 * and skipped rather than failing the whole delivery. Duplicate deliveries
 * are idempotent via the wamid unique index.
 */
class WhatsAppWebhookController extends Controller
{
    /** Cache key holding the ISO timestamp of the last signed webhook POST. */
    public const LAST_WEBHOOK_KEY = 'whatsapp:last_webhook_at';

    /**
     * GET — Meta's one-time webhook verification handshake. Meta sends
     * hub.mode / hub.verify_token / hub.challenge (PHP exposes the dotted
     * keys as hub_mode etc.) and expects the raw challenge echoed back.
     */
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('whatsapp.verify_token');

        if ($request->query('hub_mode') === 'subscribe'
            && $verifyToken !== ''
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'));
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — message + status notifications.
     */
    public function receive(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            Log::warning('WhatsApp webhook: invalid or missing signature', ['ip' => $request->ip()]);

            return $this->errorResponse('Invalid signature', 403);
        }

        // Heartbeat for the inbox health check: every signed POST from Meta
        // (inbound message OR status callback) stamps "webhooks are live". The
        // inbox warns the team when this goes quiet while replies are flowing —
        // the signal that Meta silently dropped our webhook subscription.
        Cache::put(self::LAST_WEBHOOK_KEY, now()->toIso8601String(), now()->addDays(30));

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $this->storeInboundMessages($value);
                $this->applyStatusUpdates($value);
            }
        }

        return $this->successResponse('Webhook processed');
    }

    /**
     * Every POST from Meta is signed: X-Hub-Signature-256 = sha256 HMAC of
     * the raw body, keyed with the app secret. Reject anything unsigned or
     * mis-signed — and everything when no secret is configured (fail closed).
     */
    protected function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('whatsapp.app_secret');
        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    /**
     * Store each inbound message, create/refresh its conversation, and stamp
     * last_inbound_at (the 24h service-window anchor).
     */
    protected function storeInboundMessages(array $value): void
    {
        $contacts = collect($value['contacts'] ?? []);

        foreach ($value['messages'] ?? [] as $message) {
            $waId = $message['from'] ?? null;
            $wamid = $message['id'] ?? null;

            if (! $waId || ! $wamid) {
                Log::warning('WhatsApp webhook: inbound message missing from/id — skipped', ['message' => $message]);

                continue;
            }

            // Meta redelivers until it gets a 2xx — same wamid may arrive again.
            if (WhatsappMessage::where('wamid', $wamid)->exists()) {
                continue;
            }

            $conversation = WhatsappConversation::firstOrCreate(
                ['wa_id' => $waId],
                ['profile_name' => $contacts->firstWhere('wa_id', $waId)['profile']['name'] ?? null],
            );

            // TODO(Phase 2 — patient matching): patients live in the shared
            // `users` table (Patients model, user_type_id = 3), which stores
            // `phone` (raw) and `phone_normalized` (digits-only, kept in sync
            // by a model hook and indexed for search). wa_id is E.164 digits
            // without '+', so matching is wa_id vs users.phone_normalized
            // (mind the country-code prefix), scoped to account_id, writing
            // the match to whatsapp_conversations.patient_id. Not in Phase 1.

            $body = $this->messageBody($message);

            try {
                $conversation->messages()->create([
                    'wamid' => $wamid,
                    'direction' => 'inbound',
                    'type' => $message['type'] ?? 'unknown',
                    'body' => $body,
                    'status' => 'received',
                    'payload' => $message,
                ]);
            } catch (QueryException) {
                // Concurrent redelivery lost the race to the wamid unique
                // index — the message is already stored; nothing to do.
                continue;
            }

            // Apply every conversation-level change from this message in ONE
            // UPDATE: stamp the 24h-window anchor, honour STOP/START opt-out,
            // and reopen a resolved chat so the team sees the new message.
            $mutations = [
                // Meta sends an epoch timestamp (UTC); express it in the app
                // timezone so it round-trips the datetime column like every
                // other Eloquent timestamp in this app.
                'last_inbound_at' => isset($message['timestamp'])
                    ? Carbon::createFromTimestamp((int) $message['timestamp'], config('app.timezone'))
                    : now(),
            ];

            // Meta-policy opt-out: STOP stops all future sends; START re-subscribes.
            // Single source for the keywords is the WhatsAppOptOut support helper.
            if (WhatsAppOptOut::isOptOut($body)) {
                $mutations['opted_out_at'] = now();
            } elseif (WhatsAppOptOut::isOptIn($body)) {
                $mutations['opted_out_at'] = null;
            }

            // A new inbound message reopens a resolved chat.
            if ($conversation->resolved_at !== null) {
                $mutations['resolved_at'] = null;
            }

            $conversation->update($mutations);

            if ($conversation->patient_id === null) {
                $this->matchPatient($conversation);
            }
        }
    }

    /**
     * Best-effort link of a conversation to a patient by phone number, scoped
     * to the WhatsApp account. Exact-match on the phone_normalized candidate
     * forms (indexed); leaves patient_id null when nothing matches.
     */
    private function matchPatient(WhatsappConversation $conversation): void
    {
        $candidates = WhatsAppPhoneMatch::candidates(
            $conversation->wa_id,
            (string) config('whatsapp.country_code'),
        );
        if ($candidates === []) {
            return;
        }

        $patientId = Patients::query()
            ->where('account_id', (int) config('whatsapp.account_id'))
            ->whereNull('deleted_at')
            ->whereIn('phone_normalized', $candidates)
            ->value('id');

        if ($patientId !== null) {
            $conversation->update(['patient_id' => $patientId]);
        }
    }

    /**
     * Display text for a stored inbound message: the text body, else a media
     * caption when the customer attached one (image/video/document carry an
     * optional caption; audio/voice/sticker don't). Null when neither is
     * present — the SPA then renders the media itself plus a type label.
     */
    private function messageBody(array $message): ?string
    {
        if (isset($message['text']['body'])) {
            return $message['text']['body'];
        }

        $type = $message['type'] ?? null;

        return $type !== null ? ($message[$type]['caption'] ?? null) : null;
    }

    /**
     * Delivery-status notifications (sent/delivered/read/failed) for our
     * outbound messages — match by wamid and update the stored row. On a
     * 'failed' status we also capture Meta's error (code/title/detail) onto
     * the message payload and log it, so the team sees WHY a send bounced
     * instead of a bare "Failed".
     */
    protected function applyStatusUpdates(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $status) {
            $wamid = $status['id'] ?? null;
            $state = $status['status'] ?? null;

            if (! $wamid || ! $state) {
                continue;
            }

            $message = WhatsappMessage::where('wamid', $wamid)->first();
            if (! $message) {
                continue;
            }

            $attributes = ['status' => $state];

            if ($state === 'failed' && ! empty($status['errors'])) {
                $error = $status['errors'][0];
                $detail = [
                    'code' => $error['code'] ?? null,
                    'title' => $error['title'] ?? null,
                    'detail' => $error['error_data']['details'] ?? ($error['message'] ?? null),
                ];
                $attributes['payload'] = array_merge((array) $message->payload, ['error' => $detail]);
                Log::warning('WhatsApp outbound message failed', ['wamid' => $wamid] + $detail);
            }

            $message->update($attributes);
        }
    }
}
