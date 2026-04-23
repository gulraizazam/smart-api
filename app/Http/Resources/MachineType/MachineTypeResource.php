<?php

declare(strict_types=1);

namespace App\Http\Resources\MachineType;

use App\Models\MachineType;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MachineTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var MachineType $machineType */
        $machineType = $this->resource;

        return [
            'id' => (int) $machineType->id,
            'name' => (string) $machineType->name,
            'active' => (bool) $machineType->active,
            'services' => $this->whenLoaded(
                'services',
                fn (): array => $machineType->services->map(fn (Services $service): array => [
                    'id' => (int) $service->id,
                    'name' => (string) $service->name,
                    'parent_id' => $service->parent_id !== null ? (int) $service->parent_id : null,
                ])->values()->all(),
            ),
            'service_ids' => $this->whenLoaded(
                'services',
                fn (): array => $machineType->services->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ),
            'created_at' => $machineType->created_at?->toIso8601String(),
            'updated_at' => $machineType->updated_at?->toIso8601String(),
        ];
    }
}
