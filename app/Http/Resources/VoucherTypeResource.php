<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'amount' => $this->amount,
            'start' => $this->start,
            'end' => $this->end,
            'active' => (bool) $this->active,
            'discount_type' => $this->discount_type,
            'created_at' => $this->created_at,
        ];
    }
}
