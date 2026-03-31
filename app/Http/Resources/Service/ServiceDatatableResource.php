<?php

declare(strict_types=1);

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceDatatableResource extends JsonResource
{
    /**
     * Transform a single service row for the datatable.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->resource['id'],
            'name'                  => $this->resource['name'],
            'slug'                  => $this->resource['slug'] ?? null,
            'parent_id'             => $this->resource['parent_id'],
            'is_parent'             => ($this->resource['parent_id'] ?? 0) === 0,
            'end_node'              => (int) ($this->resource['end_node'] ?? 0),
            'complimentory'         => (int) ($this->resource['complimentory'] ?? 0),
            'active'                => (int) ($this->resource['active'] ?? 0),
            'duration'              => $this->resource['duration'] ?? null,
            'price'                 => (float) ($this->resource['price'] ?? 0),
            'color'                 => $this->resource['color'] ?? null,
            'description'           => $this->resource['description'] ?? null,
            'tax_treatment_type_id' => $this->resource['tax_treatment_type_id'] ?? null,
            'sort_no'               => $this->resource['sort_no'] ?? null,
            'sort_number'           => $this->resource['sort_number'] ?? null,
            'account_id'            => $this->resource['account_id'] ?? null,
            'created_at'            => $this->resource['created_at'] ?? null,
            'updated_at'            => $this->resource['updated_at'] ?? null,
            'deleted_at'            => $this->resource['deleted_at'] ?? null,
        ];
    }
}
