<?php

declare(strict_types=1);

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'slug'                  => $this->slug,
            'parent_id'             => $this->parent_id,
            'is_parent'             => $this->parent_id === 0,
            'end_node'              => (bool) $this->end_node,
            'complimentory'         => (bool) $this->complimentory,
            'active'                => (bool) $this->active,
            'duration'              => $this->duration,
            'price'                 => (float) ($this->price ?? 0),
            'color'                 => $this->color,
            'description'           => $this->description,
            'tax_treatment_type_id' => $this->tax_treatment_type_id,
            'sort_no'               => $this->sort_no,
            'sort_number'           => $this->sort_number,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
