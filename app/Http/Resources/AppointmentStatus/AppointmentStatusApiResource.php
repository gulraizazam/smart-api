<?php

declare(strict_types=1);

namespace App\Http\Resources\AppointmentStatus;

use App\Models\AppointmentStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentStatusApiResource extends JsonResource
{
    /**
     * Dictionary of all statuses for parent name resolution. Populate from
     * the caller before returning a collection to avoid an N+1.
     *
     * @var array<int, AppointmentStatuses>|null
     */
    public static ?array $allStatuses = null;

    public function toArray(Request $request): array
    {
        /** @var AppointmentStatuses $status */
        $status = $this->resource;

        return [
            'id' => (int) $status->id,
            'name' => (string) $status->name,
            'parent_id' => $status->parent_id ? (int) $status->parent_id : null,
            'parent_name' => $this->resolveParentName(),
            'is_comment' => (bool) $status->is_comment,
            'is_default' => (bool) $status->is_default,
            'is_arrived' => (bool) $status->is_arrived,
            'is_converted' => (bool) $status->is_converted,
            'is_cancelled' => (bool) $status->is_cancelled,
            'is_unscheduled' => (bool) $status->is_unscheduled,
            'allow_message' => (bool) $status->allow_message,
            'sort_no' => $status->sort_no !== null ? (int) $status->sort_no : null,
            'active' => (bool) $status->active,
            'created_at' => $status->created_at?->toIso8601String(),
            'updated_at' => $status->updated_at?->toIso8601String(),
        ];
    }

    protected function resolveParentName(): ?string
    {
        /** @var AppointmentStatuses $status */
        $status = $this->resource;

        if (! $status->parent_id) {
            return null;
        }

        if (self::$allStatuses && array_key_exists($status->parent_id, self::$allStatuses)) {
            return (string) self::$allStatuses[$status->parent_id]->name;
        }

        return null;
    }
}
