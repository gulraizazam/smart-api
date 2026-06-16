<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SendWhatsappReplyRequest;
use App\Http\Resources\WhatsApp\WhatsappConversationResource;
use App\Http\Resources\WhatsApp\WhatsappMessageResource;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\WhatsAppMediaType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

/**
 * Team-facing WhatsApp inbox (Phase 2) — list conversations, read a thread,
 * reply, mark read. Routes are gated by `permission:whatsapp.inbox.view`
 * (reply additionally by `whatsapp.inbox.reply`, re-checked in the
 * FormRequest). Sending itself goes through WhatsAppService, which owns the
 * 24h-window and policy rules.
 */
class WhatsAppInboxController extends Controller
{
    public function __construct(private WhatsAppService $whatsApp) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => 'sometimes|boolean',
            'resolved' => 'sometimes|boolean',
            'muted' => 'sometimes|boolean',
            'mine' => 'sometimes|boolean',
            'unassigned' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:100',
        ]);

        $conversations = WhatsappConversation::query()
            ->with(['lastMessage', 'patient', 'assignee', 'tags'])
            ->withCount(['messages as unread_count' => function ($q): void {
                $q->where('direction', 'inbound')
                    ->where(function ($w): void {
                        $w->whereNull('whatsapp_conversations.last_read_at')
                            ->orWhereColumn('whatsapp_messages.created_at', '>', 'whatsapp_conversations.last_read_at');
                    });
            }, 'notes'])
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->when($request->boolean('resolved'), fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to_id', $request->user()->id))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('assigned_to_id'))
            // "Spam"-style muting tags hide a chat from the default inbox; the
            // `muted` filter is the only view that shows them.
            ->when(
                $request->boolean('muted'),
                fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('is_muting', true)),
                fn ($q) => $q->whereDoesntHave('tags', fn ($t) => $t->where('is_muting', true)),
            )
            ->when($request->filled('search'), function ($q) use ($request): void {
                // Match the phone, the WhatsApp profile name, or the linked patient.
                $term = trim((string) $request->string('search'));
                $q->where(function ($w) use ($term): void {
                    $w->where('wa_id', 'like', "%{$term}%")
                        ->orWhere('profile_name', 'like', "%{$term}%")
                        ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc(
                WhatsappMessage::select('created_at')
                    ->whereColumn('whatsapp_conversation_id', 'whatsapp_conversations.id')
                    ->latest()
                    ->limit(1),
            )
            ->paginate((int) ($validated['per_page'] ?? 50));

        return $this->paginatedResponse('Conversations', $conversations, WhatsappConversationResource::class);
    }

    /**
     * Count of conversations with unread inbound messages (the sidebar badge)
     * plus a summary of the single most recent inbound message. The SPA polls
     * this app-wide and uses `latest.message_id` as the change signal that
     * drives the desktop notification + sound — so one cheap poll feeds the
     * badge, the tab-title count, and the alert.
     */
    public function unreadCount(): JsonResponse
    {
        // Muted ("Spam") chats never raise alerts or count toward the badge.
        $notMuted = fn ($q) => $q->whereDoesntHave('tags', fn ($t) => $t->where('is_muting', true));

        $latest = WhatsappMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', $notMuted)
            ->with('conversation:id,wa_id,profile_name')
            ->latest('id')
            ->first();

        return $this->successResponse('Unread conversations', [
            'count' => $notMuted(WhatsappConversation::unread())->count(),
            'latest' => $latest?->conversation ? [
                'message_id' => $latest->id,
                'conversation_id' => $latest->conversation->id,
                'wa_id' => $latest->conversation->wa_id,
                'profile_name' => $latest->conversation->profile_name,
                'preview' => $latest->body,
            ] : null,
        ]);
    }

    /**
     * Webhook health for the inbox banner. Meta has no "fetch past messages"
     * API, so we can't backfill a missed delivery — instead we detect when
     * deliveries have stopped arriving at all.
     *
     * The reliable signal: every outbound reply triggers Meta status callbacks
     * (sent/delivered/read) within seconds. So if the team sent a reply a while
     * ago and no webhook has landed since, the subscription is almost certainly
     * down. Quiet customer periods don't false-alarm — with no recent outbound,
     * nothing is expecting a callback.
     */
    public function health(): JsonResponse
    {
        $lastOutbound = WhatsappMessage::query()
            ->where('direction', 'outbound')
            ->latest('created_at')
            ->value('created_at');

        $lastWebhook = $this->lastWebhookActivityAt();

        // We only "expect" a callback once a reply has had time to be delivered.
        $awaitingCallback = $lastOutbound !== null && $lastOutbound->lt(now()->subMinutes(15));
        $stale = $awaitingCallback && ($lastWebhook === null || $lastWebhook->lt($lastOutbound));

        return $this->successResponse('WhatsApp health', [
            'last_webhook_at' => $lastWebhook?->toIso8601String(),
            'last_outbound_at' => $lastOutbound?->toIso8601String(),
            'healthy' => ! $stale,
        ]);
    }

    /**
     * Best estimate of "the last time Meta sent us anything". The cache
     * heartbeat catches every signed POST but is wiped by a deploy's
     * cache:clear, so we also derive it from durable DB facts that ONLY a
     * webhook can produce: an inbound message landing, or an outbound
     * message's status advancing past 'sent' (delivered/read/failed). Taking
     * the latest of the three keeps a deploy from raising a false alarm.
     */
    private function lastWebhookActivityAt(): ?Carbon
    {
        $iso = Cache::get(WhatsAppWebhookController::LAST_WEBHOOK_KEY);

        $candidates = array_filter([
            $iso ? Carbon::parse($iso) : null,
            WhatsappMessage::where('direction', 'inbound')->latest('created_at')->value('created_at'),
            WhatsappMessage::where('direction', 'outbound')
                ->whereIn('status', ['delivered', 'read', 'failed'])
                ->latest('updated_at')
                ->value('updated_at'),
        ]);

        $latest = null;
        foreach ($candidates as $candidate) {
            if ($latest === null || $candidate->gt($latest)) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    /**
     * Meta's quality rating (GREEN/YELLOW/RED) for the number we send from, so
     * the team can watch the number's health without logging into Meta. Read
     * straight from the Graph API and cached for an hour — the rating moves on
     * a rolling multi-day window, and reading it is free (not a message send).
     * Returns null quality when unconfigured or Meta is unreachable; the SPA
     * then simply hides the badge.
     */
    public function numberQuality(): JsonResponse
    {
        $data = Cache::remember('whatsapp:number_quality', now()->addHour(), function (): array {
            $phoneNumberId = (string) config('whatsapp.phone_number_id');
            $token = (string) config('whatsapp.token');

            if ($phoneNumberId === '' || $token === '') {
                return ['quality_rating' => null, 'display_phone_number' => null];
            }

            $version = (string) config('whatsapp.api_version');
            $response = Http::withToken($token)
                ->timeout(5)
                ->get("https://graph.facebook.com/{$version}/{$phoneNumberId}", [
                    'fields' => 'quality_rating,display_phone_number',
                ]);

            if (! $response->successful()) {
                return ['quality_rating' => null, 'display_phone_number' => null];
            }

            return [
                'quality_rating' => $response->json('quality_rating'),
                'display_phone_number' => $response->json('display_phone_number'),
            ];
        });

        return $this->successResponse('WhatsApp number quality', $data);
    }

    /**
     * One conversation + its latest 200 messages in chronological order.
     * Read-only — marking as read is the explicit POST below (no GET mutates).
     */
    public function show(int $id): JsonResponse
    {
        $conversation = WhatsappConversation::with(['patient', 'assignee', 'tags'])->findOrFail($id);

        $messages = $conversation->messages()
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

        // Whether older messages exist beyond this first page + the conversation's
        // full message count — drives the SPA's "Load older (N earlier)" control
        // and its "showing X of Y" progress.
        $total = $conversation->messages()->count();
        $hasMore = $messages->isNotEmpty()
            && $conversation->messages()->where('id', '<', $messages->first()->id)->exists();

        return $this->successResponse('Conversation', [
            'conversation' => new WhatsappConversationResource($conversation),
            'messages' => WhatsappMessageResource::collection($messages),
            'has_more' => $hasMore,
            'total' => $total,
        ]);
    }

    /**
     * One page of OLDER messages — the messages immediately before $before (the
     * oldest one the client currently has). Returns up to 50, oldest-first, plus
     * has_more so the SPA knows whether to keep offering "Load older".
     */
    public function olderMessages(int $id, Request $request): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        $validated = $request->validate([
            'before' => ['required', 'integer', 'min:1'],
        ]);

        $messages = $conversation->messages()
            ->where('id', '<', $validated['before'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $hasMore = $messages->isNotEmpty()
            && $conversation->messages()->where('id', '<', $messages->first()->id)->exists();

        return $this->successResponse('Older messages', [
            'messages' => WhatsappMessageResource::collection($messages),
            'has_more' => $hasMore,
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);
        $conversation->update(['last_read_at' => now()]);

        // Send the read receipt (blue ticks) for the latest inbound message —
        // best-effort, bounded by the service timeout; never blocks the read.
        $wamid = $this->latestInboundWamid($conversation);
        if ($wamid !== null) {
            $this->whatsApp->sendReadReceipt($wamid);
        }

        return $this->successResponse('Marked read');
    }

    /**
     * Show the "typing…" indicator to the customer while an agent composes a
     * reply (Meta bundles it into the mark-as-read call, referencing the latest
     * inbound message). It auto-clears after ~25s or when we send. No-op when
     * there's no inbound message to reference.
     */
    public function typing(int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);
        $wamid = $this->latestInboundWamid($conversation);
        if ($wamid !== null) {
            $this->whatsApp->sendReadReceipt($wamid, typing: true);
        }

        return $this->successResponse('Typing');
    }

    private function latestInboundWamid(WhatsappConversation $conversation): ?string
    {
        return $conversation->messages()
            ->where('direction', 'inbound')
            ->whereNotNull('wamid')
            ->latest('created_at')
            ->value('wamid');
    }

    /**
     * Re-flag a conversation as unread (a manual "come back to this" marker).
     * Sets last_read_at to just before the latest inbound message so exactly
     * that message counts as unread (badge = 1). A chat with no inbound message
     * has nothing to mark unread — no-op. Only affects our read tracking;
     * nothing is sent to the customer.
     */
    public function markUnread(int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        $lastInboundAt = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->value('created_at');

        if ($lastInboundAt !== null) {
            $conversation->update(['last_read_at' => $lastInboundAt->copy()->subSecond()]);
        }

        return $this->successResponse('Marked unread');
    }

    /**
     * Assign the conversation to a staff member (or unassign with null) so the
     * team can see who's handling each chat. The assignee must be an active
     * user in the WhatsApp account (FK-validated, tenant-scoped).
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('account_id', (int) config('whatsapp.account_id'))
                    ->whereNull('deleted_at'),
            ],
        ]);

        $conversation = WhatsappConversation::findOrFail($id);
        $conversation->update(['assigned_to_id' => $validated['assigned_to_id'] ?? null]);

        return $this->successResponse('Assignment updated', new WhatsappConversationResource(
            $conversation->load(['assignee', 'patient', 'lastMessage', 'tags']),
        ));
    }

    /**
     * Mark the conversation resolved/done (triage) or reopen it. A later inbound
     * message reopens it automatically (see the webhook).
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['resolved' => 'required|boolean']);

        $conversation = WhatsappConversation::findOrFail($id);
        $conversation->update(['resolved_at' => $validated['resolved'] ? now() : null]);

        return $this->successResponse('Conversation updated', new WhatsappConversationResource(
            $conversation->load(['assignee', 'patient', 'lastMessage', 'tags']),
        ));
    }

    /** Apply a tag to the conversation (idempotent). */
    public function tag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tag_id' => [
                'required',
                Rule::exists('whatsapp_tags', 'id')->where('account_id', $request->user()->account_id),
            ],
        ]);

        $conversation = WhatsappConversation::findOrFail($id);
        $conversation->tags()->syncWithoutDetaching([$validated['tag_id']]);

        return $this->successResponse('Tag applied', new WhatsappConversationResource(
            $conversation->load(['assignee', 'patient', 'lastMessage', 'tags']),
        ));
    }

    /** Remove a tag from the conversation. */
    public function untag(int $id, int $tagId): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);
        $conversation->tags()->detach($tagId);

        return $this->successResponse('Tag removed', new WhatsappConversationResource(
            $conversation->load(['assignee', 'patient', 'lastMessage', 'tags']),
        ));
    }

    public function reply(SendWhatsappReplyRequest $request, int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        if ($conversation->isOptedOut()) {
            return $this->errorResponse('This customer opted out of WhatsApp messages and cannot be contacted.', 422);
        }

        if (! $conversation->windowIsOpen()) {
            return $this->errorResponse(
                'The 24-hour reply window is closed. It reopens when the customer messages again.',
                422,
            );
        }

        $message = $this->whatsApp->sendText(
            $conversation->wa_id,
            $request->validated()['message'],
            $request->validated()['reply_to_wamid'] ?? null,
        );

        if ($message === null) {
            return $this->errorResponse('WhatsApp is not configured on the server.', 503);
        }

        // Replying implies the thread has been read.
        $conversation->update(['last_read_at' => now()]);

        return $this->successResponse('Reply sent', new WhatsappMessageResource($message));
    }

    /**
     * React to one of the customer's messages with an emoji (an agent 👍).
     * The target wamid must be an inbound message in THIS thread (no reacting to
     * arbitrary ids). An empty emoji removes our reaction. Gated by the same
     * opt-out + 24h window rules as a reply.
     */
    public function react(Request $request, int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        $validated = $request->validate([
            'wamid' => [
                'required', 'string',
                Rule::exists('whatsapp_messages', 'wamid')
                    ->where('whatsapp_conversation_id', $conversation->id)
                    ->where('direction', 'inbound'),
            ],
            'emoji' => ['nullable', 'string', 'max:8'],
        ]);

        if ($conversation->isOptedOut()) {
            return $this->errorResponse('This customer opted out of WhatsApp messages and cannot be contacted.', 422);
        }

        if (! $conversation->windowIsOpen()) {
            return $this->errorResponse(
                'The 24-hour reply window is closed. It reopens when the customer messages again.',
                422,
            );
        }

        $message = $this->whatsApp->sendReaction($conversation->wa_id, $validated['wamid'], $validated['emoji'] ?? '');

        if ($message === null) {
            return $this->errorResponse('Could not send the reaction.', 503);
        }

        return $this->successResponse('Reaction sent', new WhatsappMessageResource($message));
    }

    /**
     * Reply with media — a recorded voice note, a photo, a video, or a document
     * (with an optional caption). Gated by whatsapp.inbox.reply and the 24h
     * window, exactly like a text reply.
     */
    public function replyMedia(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:16384'], // 16 MB
            'caption' => ['nullable', 'string', 'max:1024'],
        ]);

        // Gate on the declared mime (content-sniffing is unreliable for opus-ogg
        // and untestable with faked files). Staff-only route; Meta validates the
        // bytes too.
        $file = $request->file('file');
        $mime = (string) $file->getClientMimeType();
        if (! WhatsAppMediaType::isSupported($mime)) {
            return $this->errorResponse('That file type can\'t be sent on WhatsApp.', 422);
        }

        $conversation = WhatsappConversation::findOrFail($id);

        if ($conversation->isOptedOut()) {
            return $this->errorResponse('This customer opted out of WhatsApp messages and cannot be contacted.', 422);
        }

        if (! $conversation->windowIsOpen()) {
            return $this->errorResponse(
                'The 24-hour reply window is closed. It reopens when the customer messages again.',
                422,
            );
        }

        $type = WhatsAppMediaType::fromMime($mime);
        // Voice notes from the recorder are ogg/opus — send that exact mime so
        // WhatsApp renders a playable voice note rather than a generic audio file.
        $sendMime = $type === 'audio' ? 'audio/ogg' : $mime;

        $message = $this->whatsApp->sendMedia(
            $conversation->wa_id,
            $type,
            (string) file_get_contents($file->getRealPath()),
            $sendMime,
            $file->getClientOriginalName() ?: 'file',
            $validated['caption'] ?? null,
        );

        if ($message === null) {
            return $this->errorResponse('WhatsApp is not configured on the server.', 503);
        }

        $conversation->update(['last_read_at' => now()]);

        return $this->successResponse('Media sent', new WhatsappMessageResource($message));
    }

    /**
     * Resend a failed outbound text message. Creates a fresh attempt (the
     * failed row stays for audit); gated by the same opt-out + 24h-window rules
     * as a normal reply.
     */
    public function retry(int $id, int $messageId): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        /** @var WhatsappMessage $failed */
        $failed = $conversation->messages()
            ->whereKey($messageId)
            ->where('direction', 'outbound')
            ->where('status', 'failed')
            ->where('type', 'text')
            ->firstOrFail();

        if ($conversation->isOptedOut()) {
            return $this->errorResponse('This customer opted out of WhatsApp messages and cannot be contacted.', 422);
        }
        if (! $conversation->windowIsOpen()) {
            return $this->errorResponse(
                'The 24-hour reply window is closed. It reopens when the customer messages again.',
                422,
            );
        }

        $message = $this->whatsApp->sendText($conversation->wa_id, (string) $failed->body);
        if ($message === null) {
            return $this->errorResponse('WhatsApp is not configured on the server.', 503);
        }

        return $this->successResponse('Message resent', new WhatsappMessageResource($message));
    }

    /**
     * Stream a media file (image/voice note/document/video) for the inbox —
     * inbound attachments AND the team's own outbound voice notes (for
     * playback). Meta media URLs are short-lived and bearer-auth'd, so the SPA
     * can't load them directly; this proxies the bytes through our own
     * permission-gated endpoint. Media older than ~30 days is purged by Meta
     * and returns 502.
     */
    public function media(int $id, int $messageId): Response|JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        /** @var WhatsappMessage $message */
        $message = $conversation->messages()->whereKey($messageId)->firstOrFail();

        // Inbound media stores the id under the type key; outbound (our send)
        // stores the uploaded media id flat as `media_id`.
        $mediaId = $message->payload[$message->type]['id'] ?? $message->payload['media_id'] ?? null;
        if ($mediaId === null) {
            return $this->errorResponse('This message has no downloadable media.', 404);
        }

        $media = $this->whatsApp->fetchMedia((string) $mediaId);
        if ($media === null) {
            return $this->errorResponse('Media is unavailable — it may have expired on WhatsApp.', 502);
        }

        return response($media['body'], 200, [
            'Content-Type' => $media['mime'],
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
