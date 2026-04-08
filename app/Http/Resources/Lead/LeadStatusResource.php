<?php

declare(strict_types=1);

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadStatusResource extends JsonResource
{
    /**
     * Dictionary of all statuses for parent name resolution.
     */
    public static ?array $allStatuses = null;

    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->resolveParentName(),
            'is_comment' => $this->is_comment ? 'Yes' : 'No',
            'is_default' => $this->is_default ? 'Yes' : 'No',
            'is_arrived' => $this->is_arrived ? 'Yes' : 'No',
            'is_converted' => $this->is_converted ? 'Yes' : 'No',
            'is_junk' => $this->is_junk ? 'Yes' : 'No',
            'is_booked' => $this->when(isset($this->is_booked), $this->is_booked ? 'Yes' : 'No'),
            'sort_no' => $this->sort_no,
            'active' => $this->active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function resolveParentName(): string
    {
        if (!$this->resource->parent_id) {
            return '-';
        }

        if (self::$allStatuses && array_key_exists($this->resource->parent_id, self::$allStatuses)) {
            return self::$allStatuses[$this->resource->parent_id]->name;
        }

        return '-';
    }
}
