<?php

declare(strict_types=1);

namespace App\Http\Resources\Bundle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BundleFormDataResource extends JsonResource
{
    /**
     * Transform bundle form data for create/edit responses.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'bundle'               => $data['bundle'] ?? null,
            'services'             => $data['services'] ?? [],
            'bundle_services'      => $data['bundle_services'] ?? collect(),
            'relationships'        => $data['relationships'] ?? collect(),
            'tax_treatment_types'  => $data['tax_treatment_types'] ?? [],
        ];
    }
}
