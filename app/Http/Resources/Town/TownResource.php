<?php

declare(strict_types=1);

namespace App\Http\Resources\Town;

use App\Models\Towns;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TownResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Towns $town */
        $town = $this->resource;

        return [
            'id' => (int) $town->id,
            'name' => (string) $town->name,
            'city_id' => $town->city_id !== null ? (int) $town->city_id : null,
            'city_name' => $this->whenLoaded('city', fn (): ?string => $town->city?->name),
            'active' => (bool) $town->active,
            'created_at' => $town->created_at?->toIso8601String(),
            'updated_at' => $town->updated_at?->toIso8601String(),
        ];
    }
}
