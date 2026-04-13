<?php

declare(strict_types=1);

namespace App\Http\Resources\ServiceBundle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceBundleDatatableResource extends JsonResource
{
    /**
     * Transform a single service bundle row for the datatable.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unitPrice = (float) ($this->service?->price ?? 0);
        $regularTotal = $unitPrice * $this->sessions;
        $savings = $regularTotal - (float) $this->price;

        return [
            'is_category'         => false,
            'id'                  => $this->id,
            'name'                => $this->sessions . 'x ' . ($this->service?->name ?? ''),
            'service_name'        => $this->service?->name ?? '',
            'category_name'       => $this->service?->parent?->name ?? '',
            'sessions'            => $this->sessions,
            'regular_price'       => number_format($regularTotal, 2),
            'price'               => number_format((float) $this->price, 2),
            'you_save'            => number_format($savings, 2),
            'active'              => (int) $this->active,
            'created_at'          => $this->created_at?->format('F d, Y h:i A'),
        ];
    }
}
