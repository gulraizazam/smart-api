<?php

declare(strict_types=1);

namespace App\Http\Resources\Refund;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Wraps a collection of refund datatable rows with meta, permissions, and filters.
 */
final class RefundDatatableCollection extends ResourceCollection
{
    public $collects = RefundDatatableResource::class;

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
