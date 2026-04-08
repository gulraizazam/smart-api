<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MembershipTypeResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'period'      => $this->period,
            'amount'      => $this->amount,
            'active'      => (bool) $this->active,
            'parent_id'   => $this->parent_id,
            'parent_name' => $this->whenLoaded('parent', fn () => $this->parent?->name, '-'),
            'has_children' => $this->whenLoaded('children', fn () => $this->children->isNotEmpty(), false),
            'children'    => $this->whenLoaded('children', fn () => $this->children->map(fn ($child) => [
                'id'         => $child->id,
                'name'       => $child->name,
                'period'     => $child->period,
                'amount'     => $child->amount,
                'active'     => (bool) $child->active,
                'created_at' => $child->created_at?->format('F j,Y h:i A'),
            ])),
            'discounts'   => $this->whenLoaded('discounts', fn () => $this->discounts->map(fn ($d) => [
                'id'   => $d->id,
                'name' => $d->name,
            ])),
            'created_at'  => $this->created_at?->format('F j,Y h:i A'),
        ];
    }
}
