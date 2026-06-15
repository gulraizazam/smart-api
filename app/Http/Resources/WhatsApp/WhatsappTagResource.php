<?php

declare(strict_types=1);

namespace App\Http\Resources\WhatsApp;

use App\Models\WhatsappTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property WhatsappTag $resource
 */
class WhatsappTagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'is_muting' => (bool) $this->is_muting,
        ];
    }
}
