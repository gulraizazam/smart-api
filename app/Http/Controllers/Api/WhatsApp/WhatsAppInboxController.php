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
     * Count of conversations with unread inbound messages — the sidebar badge.
     */
    public function unreadCount(): JsonResponse
    {
        return $this->successResponse('Unread conversations', [
            'count' => WhatsappConversation::unread()->count(),
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
}
