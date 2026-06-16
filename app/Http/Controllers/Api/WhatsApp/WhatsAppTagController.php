<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsApp\WhatsappTagResource;
use App\Models\WhatsappTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * WhatsApp chat tags (labels) — account-scoped, shared across the team. List is
 * gated by whatsapp.inbox.view (the route group); create/delete additionally
 * require whatsapp.inbox.reply. Tenant isolation via BaseModel's account global
 * scope (queries + findOrFail see only the caller's account).
 */
class WhatsAppTagController extends Controller
{
    /** Allowed tag colours — Badge design-token variants the SPA can render. */
    private const COLORS = ['brand', 'accent', 'success', 'warning', 'danger', 'neutral', 'outline'];

    public function index(): JsonResponse
    {
        $tags = WhatsappTag::orderBy('name')->get();

        return $this->successResponse('Tags', WhatsappTagResource::collection($tags));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('whatsapp_tags', 'name')->where('account_id', $request->user()->account_id),
            ],
            'color' => ['sometimes', Rule::in(self::COLORS)],
            'is_muting' => 'sometimes|boolean',
        ]);

        $tag = WhatsappTag::create([
            'account_id' => $request->user()->account_id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'neutral',
            'is_muting' => $request->boolean('is_muting'),
        ]);

        return $this->successResponse('Tag added', new WhatsappTagResource($tag));
    }

    // No destroy(): tag deletion is intentionally not supported. Tags are a fixed,
    // managed set — retire one via a migration, not at runtime (a hard delete here
    // would silently unmute every chat that carried the tag).
}
