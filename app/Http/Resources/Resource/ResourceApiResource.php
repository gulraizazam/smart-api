<?php

declare(strict_types=1);

namespace App\Http\Resources\Resource;

use App\Models\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the `Resources` Eloquent model (rooms/machines/etc.).
 * Named `ResourceApiResource` so the filename doesn't collide with the
 * `Resources` domain model when IDEs auto-import.
 */
class ResourceApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Resources $resource */
        $resource = $this->resource;

        return [
            'id' => (int) $resource->id,
            'name' => (string) $resource->name,
            'resource_type_id' => $resource->resource_type_id !== null ? (int) $resource->resource_type_id : null,
            'resource_type_name' => $this->whenLoaded('resourcetype', fn (): ?string => $resource->resourcetype?->name),
            'resource_type_slug' => $this->whenLoaded('resourcetype', fn (): ?string => $resource->resourcetype?->slug ?? null),
            'location_id' => $resource->location_id !== null ? (int) $resource->location_id : null,
            'location_name' => $this->whenLoaded('location', fn (): ?string => $resource->location?->name),
            'machine_type_id' => $resource->machine_type_id !== null ? (int) $resource->machine_type_id : null,
            'machine_type_name' => $this->whenLoaded('MachineType', fn (): ?string => $resource->MachineType?->name),
            'external_id' => $resource->external_id !== null ? (string) $resource->external_id : null,
            'active' => (bool) $resource->active,
            'created_at' => $resource->created_at?->toIso8601String(),
            'updated_at' => $resource->updated_at?->toIso8601String(),
        ];
    }
}
