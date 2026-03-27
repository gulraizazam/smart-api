<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalAmount = (float) ($this->total_amount ?? 0);
        $currentBalance = (float) ($this->current_balance ?? 0);
        $consumedAmount = $totalAmount - $currentBalance;

        return [
            'id' => $this->id,
            'user_voucher_id' => $this->id,
            'name' => $this->voucher_name ?? '',
            'service' => '',
            'total_amount' => number_format($totalAmount, 2),
            'consumed_amount' => number_format($consumedAmount, 2),
            'balance' => number_format($currentBalance, 2),
            'amount' => $this->amount ?? 0,
            'startDate' => $this->start_date ?? '',
            'endDate' => $this->end_date ?? '',
            'created_at' => $this->created_at
                ? Carbon::parse($this->created_at)->format('D M, d Y h:i A')
                : '',
        ];
    }
}
