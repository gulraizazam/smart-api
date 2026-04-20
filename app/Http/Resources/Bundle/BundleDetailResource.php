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
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $bundle = $data['bundle'] ?? null;

        if ($bundle !== null) {
            $rawCreated = $bundle->getRawOriginal('created_at');
            $bundle = $bundle->toArray();
            $bundle['created_at'] = $rawCreated !== null ? (string) $rawCreated : null;
        }

        return [
            'bundle'          => $bundle,
            'bundle_services' => $data['bundle_services'] ?? collect(),
            'relationships'   => $data['relationships'] ?? collect(),
        ];
    }
}
