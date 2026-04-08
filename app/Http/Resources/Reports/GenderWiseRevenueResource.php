<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenderWiseRevenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'male_revenue' => round((float) ($data['male_revenue'] ?? 0), 2),
            'female_revenue' => round((float) ($data['female_revenue'] ?? 0), 2),
            'unknown_gender_revenue' => round((float) ($data['unknown_gender_revenue'] ?? 0), 2),
            'total_revenue' => round((float) ($data['total_revenue'] ?? 0), 2),
            'male_count' => (int) ($data['male_count'] ?? 0),
            'female_count' => (int) ($data['female_count'] ?? 0),
            'unknown_gender_count' => (int) ($data['unknown_gender_count'] ?? 0),
            'total_count' => (int) ($data['total_count'] ?? 0),
        ];
    }
}
