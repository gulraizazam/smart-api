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
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'price'          => number_format((float) $this->price, 2),
            'total_services' => (int) $this->total_services,
            'apply_discount' => $this->apply_discount ? 'Yes' : 'No',
            'start'          => $this->start ? Carbon::parse($this->start)->format('D M, j Y') : null,
            'end'            => $this->end ? Carbon::parse($this->end)->format('D M, j Y') : null,
            'active'         => (int) $this->active,
            'created_at'     => $this->created_at,
        ];
    }
}
