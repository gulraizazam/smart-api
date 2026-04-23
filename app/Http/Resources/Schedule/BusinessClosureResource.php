<?php

declare(strict_types=1);

namespace App\Http\Resources\Schedule;

use App\Models\BusinessClosure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessClosureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var BusinessClosure $closure */
        $closure = $this->resource;

        $locations = $this->whenLoaded('locations', fn () => $closure->locations->map(fn ($loc) => [
            'id' => (int) $loc->id,
            'name' => (string) $loc->name,
        ])->values());

        $appliesToAllLocations = $this->whenLoaded(
            'locations',
            fn () => $closure->locations->isEmpty(),
        );

        return [
            'id' => (int) $closure->id,
            'title' => $closure->title,
            'start_date' => $closure->start_date?->toDateString(),
            'end_date' => $closure->end_date?->toDateString(),
            'duration_days' => $closure->start_date && $closure->end_date
                ? ((int) $closure->start_date->diffInDays($closure->end_date)) + 1
                : null,
            'applies_to_all_locations' => $appliesToAllLocations,
            'locations' => $locations,
            'created_by' => $this->whenLoaded('creator', fn () => $closure->creator ? [
                'id' => (int) $closure->creator->id,
                'name' => (string) $closure->creator->name,
            ] : null),
            'created_at' => $closure->created_at?->toIso8601String(),
            'updated_at' => $closure->updated_at?->toIso8601String(),
        ];
    }
}
