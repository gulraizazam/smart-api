<?php

declare(strict_types=1);

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceFormDataResource extends JsonResource
{
    /**
     * Transform service form data for create/edit/duplicate responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'parent_services'          => $data['parent_services'] ?? [],
            'service'                  => $data['service'] ?? null,
            'durations'                => $data['durations'] ?? [],
            'tax_treatment_types'      => $data['tax_treatment_types'] ?? [],
            'select_tax_treatment_type' => $data['select_tax_treatment_type'] ?? 3,
        ];
    }
}
