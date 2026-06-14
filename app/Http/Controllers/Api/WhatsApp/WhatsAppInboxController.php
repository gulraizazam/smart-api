<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SendWhatsappReplyRequest;
use App\Http\Resources\WhatsApp\WhatsappConversationResource;
use App\Http\Resources\WhatsApp\WhatsappMessageResource;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $conversations = WhatsappConversation::query()
            ->with('lastMessage')
            ->withCount(['messages as unread_count' => function ($q): void {
                $q->where('direction', 'inbound')
                    ->where(function ($w): void {
                        $w->whereNull('whatsapp_conversations.last_read_at')
                            ->orWhereColumn('whatsapp_messages.created_at', '>', 'whatsapp_conversations.last_read_at');
                    });
            }])
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
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
        $latest = WhatsappMessage::query()
            ->where('direction', 'inbound')
            ->with('conversation:id,wa_id,profile_name')
            ->latest('id')
            ->first();

        return $this->successResponse('Unread conversations', [
            'count' => WhatsappConversation::unread()->count(),
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
     * One conversation + its latest 200 messages in chronological order.
     * Read-only — marking as read is the explicit POST below (no GET mutates).
     */
    public function show(int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        $messages = $conversation->messages()
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

        return $this->successResponse('Conversation', [
            'conversation' => new WhatsappConversationResource($conversation),
            'messages' => WhatsappMessageResource::collection($messages),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        WhatsappConversation::findOrFail($id)->update(['last_read_at' => now()]);

        return $this->successResponse('Marked read');
    }

    public function reply(SendWhatsappReplyRequest $request, int $id): JsonResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);

        if (! $conversation->windowIsOpen()) {
            return $this->errorResponse(
                'The 24-hour reply window is closed. It reopens when the customer messages again.',
                422,
            );
        }

        $message = $this->whatsApp->sendText($conversation->wa_id, $request->validated()['message']);

        if ($message === null) {
            return $this->errorResponse('WhatsApp is not configured on the server.', 503);
        }

        // Replying implies the thread has been read.
        $conversation->update(['last_read_at' => now()]);

        return $this->successResponse('Reply sent', new WhatsappMessageResource($message));
    }

    /**
     * Reply with a voice note (or other media). The recorded file is uploaded
     * to Meta and sent as an audio message — gated by whatsapp.inbox.reply and
     * the 24h window, exactly like a text reply.
     */
    public function replyMedia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:16384'], // 16 MB — WhatsApp's audio ceiling
        ]);

        // Content-sniffing (mimetypes:) is unreliable for opus-ogg (finfo often
        // reports application/ogg) and untestable with faked files, so gate on
        // the declared audio type. Low risk: the route is staff-only and the
        // bytes are forwarded to Meta, which validates the media itself.
        $file = $request->file('file');
        $mime = (string) $file->getClientMimeType();
        if (! str_starts_with($mime, 'audio/') && $mime !== 'application/ogg') {
            return $this->errorResponse('Only audio voice notes can be sent here.', 422);
        }

        $conversation = WhatsappConversation::findOrFail($id);

        if (! $conversation->windowIsOpen()) {
            return $this->errorResponse(
                'The 24-hour reply window is closed. It reopens when the customer messages again.',
                422,
            );
        }

        // opus-recorder always produces ogg/opus — the format WhatsApp renders
        // as a playable voice note. Send it as audio/ogg regardless of how
        // finfo labels the temp file.
        $message = $this->whatsApp->sendMedia(
            $conversation->wa_id,
            'audio',
            (string) file_get_contents($file->getRealPath()),
            'audio/ogg',
            'voice-note.ogg',
        );

        if ($message === null) {
            return $this->errorResponse('WhatsApp is not configured on the server.', 503);
        }

        $conversation->update(['last_read_at' => now()]);

        return $this->successResponse('Voice note sent', new WhatsappMessageResource($message));
    }

    /**
     * Stream a media file (image/voice note/video/document) for the inbox —
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
