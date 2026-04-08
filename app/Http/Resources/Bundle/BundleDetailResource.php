<?php

declare(strict_types=1);

namespace App\Http\Resources\Bundle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BundleDetailResource extends JsonResource
{
    /**
     * Transform bundle detail data for the detail/view response.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'bundle'          => $data['bundle'] ?? null,
            'bundle_services' => $data['bundle_services'] ?? collect(),
            'relationships'   => $data['relationships'] ?? collect(),
        ];
    }
}
