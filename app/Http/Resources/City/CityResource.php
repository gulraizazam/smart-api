<?php

declare(strict_types=1);

namespace App\Http\Resources\City;

use App\Models\Cities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Cities $city */
        $city = $this->resource;

        return [
            'id' => (int) $city->id,
            'name' => (string) $city->name,
            'slug' => $city->slug,
            'region_id' => $city->region_id !== null ? (int) $city->region_id : null,
            'region_name' => $this->whenLoaded('region', fn (): ?string => $city->region?->name),
            'is_featured' => (bool) $city->is_featured,
            'active' => (bool) $city->active,
            'sort_number' => $city->sort_number !== null ? (int) $city->sort_number : null,
            'locations_count' => $this->whenCounted('locations'),
            'active_locations_count' => $this->whenCounted('locationsActive'),
            'created_at' => $city->created_at?->toIso8601String(),
            'updated_at' => $city->updated_at?->toIso8601String(),
        ];
    }
}
