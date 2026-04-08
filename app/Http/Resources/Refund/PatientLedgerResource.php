<?php

declare(strict_types=1);

namespace App\Http\Resources\Refund;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms patient ledger data.
 */
final class PatientLedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'patient' => [
                'id'   => $this->resource['patient']->id,
                'name' => $this->resource['patient']->name,
            ],
            'advances' => $this->resource['advances']->map(fn ($advance) => [
                'id'              => $advance->id,
                'cash_flow'       => $advance->cash_flow,
                'cash_amount'     => (float) $advance->cash_amount,
                'package_id'      => $advance->package_id,
                'is_refund'       => (bool) $advance->is_refund,
                'is_tax'          => (bool) $advance->is_tax,
                'is_adjustment'   => (bool) $advance->is_adjustment,
                'refund_note'     => $advance->refund_note,
                'created_at'      => $advance->created_at?->format('F j, Y h:i A') ?? '',
            ]),
        ];
    }
}
