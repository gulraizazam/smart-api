<?php

declare(strict_types=1);

namespace App\Http\Resources\Bundle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

final class BundleDatatableResource extends JsonResource
{
    /**
     * Transform a single bundle row for the datatable.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $servicesPrice = (float) ($this->services_price ?? 0);
        $bundlePrice = (float) $this->price;
        $savings = $servicesPrice - $bundlePrice;

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'total_services' => (int) $this->total_services,
            'regular_price'  => number_format($servicesPrice, 2),
            'price'          => number_format($bundlePrice, 2),
            'you_save'       => $savings > 0 ? number_format($savings, 2) : '',
            'apply_discount' => $this->apply_discount ? 'Yes' : 'No',
            'start'          => $this->start ? Carbon::parse($this->start)->format('D M, j Y') : null,
            'end'            => $this->end ? Carbon::parse($this->end)->format('D M, j Y') : null,
            'active'         => (int) $this->active,
            'created_at'     => $this->created_at,
        ];
    }
}
