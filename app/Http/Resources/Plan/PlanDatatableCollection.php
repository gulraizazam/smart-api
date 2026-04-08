<?php

declare(strict_types=1);

namespace App\Http\Resources\Plan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Wraps a paginated collection of plan datatable rows with
 * meta, permissions, filter values, and active filters.
 */
final class PlanDatatableCollection extends ResourceCollection
{
    public $collects = PlanDatatableResource::class;

    private array $meta = [];
    private array $permissions = [];
    private array $filterValues = [];
    private array $activeFilters = [];

    public function setContext(
        array $meta,
        array $permissions,
        array $filterValues,
        array $activeFilters,
    ): self {
        $this->meta = $meta;
        $this->permissions = $permissions;
        $this->filterValues = $filterValues;
        $this->activeFilters = $activeFilters;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'meta'           => $this->meta,
            'data'           => $this->collection,
            'permissions'    => $this->permissions,
            'filter_values'  => $this->filterValues,
            'active_filters' => $this->activeFilters,
        ];
    }
}
