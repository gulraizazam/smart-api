<?php

declare(strict_types=1);

namespace App\Http\Resources\WhatsApp;

use App\Models\WhatsappNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property WhatsappNote $resource
 */
class WhatsappNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
