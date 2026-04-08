<?php

declare(strict_types=1);

namespace App\Http\Resources\Refund;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a single refund row for the datatable.
 * Receives pre-computed array data, not an Eloquent model.
 */
final class RefundDatatableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Data is already a formatted array from the service layer
        return $this->resource;
    }
}
