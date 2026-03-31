<?php

declare(strict_types=1);

namespace App\Http\Resources\Discount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DiscountAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'discount_id'   => $this->discount_id,
            'location_id'   => $this->location_id,
            'service_id'    => $this->service_id,
            'type'          => $this->type,
            'amount'        => $this->amount !== null ? (float) $this->amount : null,
            'slug'          => $this->slug,
            'location_name' => $this->whenLoaded('location', fn () => $this->location?->city?->name . ' - ' . $this->location?->name),
            'service_name'  => $this->whenLoaded('service', fn () => $this->service?->name),
        ];
    }
}
