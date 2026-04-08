<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralRevenueSummaryResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'city' => $data['city'],
            'region' => $data['region'],
            'revenue_cash_in' => round((float) ($data['revenue_cash_in'] ?? 0), 2),
            'revenue_card_in' => round((float) ($data['revenue_card_in'] ?? 0), 2),
            'revenue_bank_in' => round((float) ($data['revenue_bank_in'] ?? 0), 2),
            'refund_out' => round((float) ($data['refund_out'] ?? 0), 2),
            'in_hand' => round((float) ($data['in_hand'] ?? 0), 2),
            'male_revenue' => round((float) ($data['male_revenue'] ?? 0), 2),
            'female_revenue' => round((float) ($data['female_revenue'] ?? 0), 2),
        ];
    }
}
