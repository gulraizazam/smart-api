<?php

declare(strict_types=1);

namespace App\Http\Resources\Discount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DiscountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'type'             => $this->type,
            'amount'           => (float) ($this->amount ?? 0),
            'discount_type'    => $this->discount_type,
            'pre_days'         => (int) ($this->pre_days ?? 0),
            'post_days'        => (int) ($this->post_days ?? 0),
            'start'            => $this->start,
            'end'              => $this->end,
            'active'           => (bool) $this->active,
            'customer_type_id' => $this->customer_type_id,
            'account_id'       => $this->account_id,
            'created_at'       => $this->created_at?->format('F d,Y h:i A'),
        ];
    }
}
