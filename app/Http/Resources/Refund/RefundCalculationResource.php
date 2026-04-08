<?php

declare(strict_types=1);

namespace App\Http\Resources\Refund;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms refund calculation data for the refund creation form.
 */
final class RefundCalculationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->resource['id'],
            'refundable_amount'     => $this->resource['refundable_amount'],
            'cash_amount'           => $this->resource['cash_amount'],
            'is_adjustment_amount'  => $this->resource['is_adjustment_amount'],
            'documentationcharges'  => $this->resource['documentationcharges'],
            'document'              => $this->resource['document'],
            'return_tax_amount'     => $this->resource['return_tax_amount'],
            'date_backend'          => $this->resource['date_backend'],
            'paymentmodes'          => $this->resource['paymentmodes'],
        ];
    }
}
