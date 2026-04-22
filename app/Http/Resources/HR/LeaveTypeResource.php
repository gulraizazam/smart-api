<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\LeaveType $type */
        $type = $this->resource;

        return [
            'id' => $type->id,
            'name' => $type->name,
            'is_paid' => (bool) $type->is_paid,
            'active' => (bool) $type->active,
            'created_at' => $type->created_at?->toIso8601String(),
            'updated_at' => $type->updated_at?->toIso8601String(),
        ];
    }
}
