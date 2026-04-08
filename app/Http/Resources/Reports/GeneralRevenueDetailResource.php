<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralRevenueDetailResource extends JsonResource
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
            'revenue_data' => collect($data['revenue_data'] ?? [])->map(fn (array $row) => [
                'patient_id' => $row['patient_id'],
                'patient' => $row['patient'],
                'gender' => $row['gender'],
                'phone' => $row['phone'],
                'transtype' => $row['transtype'],
                'payment_mode' => $row['payment_mode'],
                'cash_flow' => $row['cash_flow'],
                'revenue_cash_in' => round((float) ($row['revenue_cash_in'] ?? 0), 2),
                'revenue_card_in' => round((float) ($row['revenue_card_in'] ?? 0), 2),
                'revenue_bank_in' => round((float) ($row['revenue_bank_in'] ?? 0), 2),
                'refund_out' => round((float) ($row['refund_out'] ?? 0), 2),
                'balance' => round((float) ($row['Balance'] ?? 0), 2),
                'created_at' => $row['created_at'],
            ])->values()->all(),
        ];
    }
}
