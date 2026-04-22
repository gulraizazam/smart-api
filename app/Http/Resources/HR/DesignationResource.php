<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Designation $designation */
        $designation = $this->resource;

        return [
            'id' => $designation->id,
            'name' => $designation->name,
            'department_id' => $designation->department_id !== null ? (int) $designation->department_id : null,
            'department' => $this->whenLoaded('department', fn () => $designation->department ? [
                'id' => (int) $designation->department->id,
                'name' => (string) $designation->department->name,
            ] : null),
            'active' => (bool) $designation->active,
            'created_at' => $designation->created_at?->toIso8601String(),
            'updated_at' => $designation->updated_at?->toIso8601String(),
        ];
    }
}
