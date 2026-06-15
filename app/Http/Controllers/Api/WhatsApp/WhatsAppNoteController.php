<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsApp\WhatsappNoteResource;
use App\Models\WhatsappConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal notes on a WhatsApp conversation — private team annotations, never
 * sent to the customer. Conversation-scoped (notes are reached only via their
 * conversation, so destroy is IDOR-safe). Gated by whatsapp.inbox.view.
 */
class WhatsAppNoteController extends Controller
{
    public function index(int $id): JsonResponse
    {
        $notes = WhatsappConversation::findOrFail($id)
            ->notes()
            ->with('author:id,name')
            ->latest()
            ->get();

        return $this->successResponse('Notes', WhatsappNoteResource::collection($notes));
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $note = WhatsappConversation::findOrFail($id)->notes()->create([
            'body' => $validated['body'],
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse('Note added', new WhatsappNoteResource($note->load('author:id,name')));
    }

    public function destroy(int $id, int $noteId): JsonResponse
    {
        WhatsappConversation::findOrFail($id)
            ->notes()
            ->whereKey($noteId)
            ->firstOrFail()
            ->delete();

        return $this->successResponse('Note deleted');
    }
}
