<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => (int) $department->id,
            'name' => (string) $department->name,
            'active' => (bool) $department->active,
            'designations_count' => $this->whenCounted('designations'),
            'employees_count' => $this->whenCounted('employeeDetails'),
            'created_at' => $department->created_at?->toIso8601String(),
            'updated_at' => $department->updated_at?->toIso8601String(),
        ];
    }
}
