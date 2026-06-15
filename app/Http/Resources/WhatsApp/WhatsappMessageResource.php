<?php

declare(strict_types=1);

namespace App\Http\Resources\WhatsApp;

use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chat-thread shape for the SPA inbox. Deliberately excludes the raw Meta
 * `payload` (PII minimization — the UI only needs what it renders).
 *
 * @property WhatsappMessage $resource
 */
class WhatsappMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'type' => $this->type,
            'body' => $this->displayBody(),
            'status' => $this->status,
            'media_url' => $this->mediaUrl(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Text the UI should show for the bubble. Normally the stored body, but a
     * reaction ('reaction' message) carries no body — the emoji lives in the
     * payload (`reaction.emoji`), so surface it here for existing AND future
     * reactions instead of the SPA falling back to a literal "[reaction]". An
     * empty emoji means the customer removed their reaction → null.
     */
    private function displayBody(): ?string
    {
        if ($this->type === 'reaction') {
            $emoji = $this->payload['reaction']['emoji'] ?? '';

            return $emoji !== '' ? $emoji : null;
        }

        return $this->body;
    }

    /**
     * Relative URL of our media-proxy endpoint for inbound media messages
     * (null for text, or when the stored payload carries no media id). The
     * SPA fetches it through the authenticated api client and renders the
     * bytes — it is never a bare cross-origin `<img src>`.
     */
    private function mediaUrl(): ?string
    {
        $mediaTypes = ['image', 'audio', 'voice', 'video', 'document', 'sticker'];

        if (! in_array($this->type, $mediaTypes, true)) {
            return null;
        }

        // Inbound stores the media id under the type key; the team's own
        // outbound voice notes store the uploaded id flat as `media_id`.
        $hasMedia = isset($this->payload[$this->type]['id']) || isset($this->payload['media_id']);
        if (! $hasMedia) {
            return null;
        }

        return "/api/whatsapp/conversations/{$this->whatsapp_conversation_id}/media/{$this->id}";
    }
}
