<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionByServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'],
            'amount' => round((float) ($data['amount'] ?? 0), 2),
        ];
    }
}
