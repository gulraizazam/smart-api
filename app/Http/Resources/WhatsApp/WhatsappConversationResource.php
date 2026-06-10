<?php

declare(strict_types=1);

namespace App\Http\Resources\WhatsApp;

use App\Models\WhatsappConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversation-list shape for the SPA inbox. `window_open` /
 * `window_expires_at` surface the 24h service-window state so the composer
 * can lock itself; `unread_count` comes from the controller's withCount.
 *
 * @property WhatsappConversation $resource
 */
class WhatsappConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wa_id' => $this->wa_id,
            'profile_name' => $this->profile_name,
            'patient_id' => $this->patient_id,
            'last_inbound_at' => $this->last_inbound_at?->format('Y-m-d H:i:s'),
            'last_read_at' => $this->last_read_at?->format('Y-m-d H:i:s'),
            'window_open' => $this->windowIsOpen(),
            'window_expires_at' => $this->last_inbound_at
                ?->addHours(WhatsappConversation::SERVICE_WINDOW_HOURS)
                ->format('Y-m-d H:i:s'),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_message' => $this->whenLoaded(
                'lastMessage',
                fn () => new WhatsappMessageResource($this->lastMessage),
            ),
        ];
    }
}
