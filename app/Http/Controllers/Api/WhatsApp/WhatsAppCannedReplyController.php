<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsApp\WhatsappCannedReplyResource;
use App\Models\WhatsappCannedReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canned replies (saved quick-answers) for the WhatsApp inbox — account-scoped
 * and shared across the team. List is gated by whatsapp.inbox.view (the route
 * group); create/delete additionally require whatsapp.inbox.reply. Tenant
 * isolation is enforced by BaseModel's account global scope (queries + the
 * findOrFail below see only the caller's account — cross-account ids 404).
 */
class WhatsAppCannedReplyController extends Controller
{
    public function index(): JsonResponse
    {
        $replies = WhatsappCannedReply::orderBy('title')->get();

        return $this->successResponse('Canned replies', WhatsappCannedReplyResource::collection($replies));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'body' => 'required|string|max:1024',
        ]);

        $reply = WhatsappCannedReply::create([
            'account_id' => $request->user()->account_id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse('Canned reply added', new WhatsappCannedReplyResource($reply));
    }

    public function destroy(int $id): JsonResponse
    {
        WhatsappCannedReply::findOrFail($id)->delete();

        return $this->successResponse('Canned reply deleted');
    }
}
