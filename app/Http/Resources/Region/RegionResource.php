<?php

declare(strict_types=1);

namespace App\Http\Resources\Region;

use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Regions $region */
        $region = $this->resource;

        return [
            'id' => (int) $region->id,
            'name' => (string) $region->name,
            'slug' => $region->slug,
            'is_root' => $region->slug === 'all',
            'active' => (bool) $region->active,
            'sort_number' => $region->sort_number !== null ? (int) $region->sort_number : null,
            'locations_count' => $this->whenCounted('locations'),
            'active_locations_count' => $this->whenCounted('locationsActive'),
            'created_at' => $region->created_at?->toIso8601String(),
            'updated_at' => $region->updated_at?->toIso8601String(),
        ];
    }
}
