<?php

declare(strict_types=1);

namespace App\Http\Resources\Plan;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $log = (array) $this->resource;

        return [
            'sr_no' => $log['sr no'] ?? null,
            'id' => $log['id'] ?? null,
            'action' => $log['action'] ?? null,
            'table' => $log['table'] ?? null,
            'user' => $log['user_id'] ?? null,
            'cash_flow' => $log['cash_flow'] ?? null,
            'cash_amount' => $log['cash_amount'] ?? null,
            'refund' => $log['refund'] ?? null,
            'adjustment' => $log['adjustment'] ?? null,
            'tax' => $log['tax'] ?? null,
            'cancel' => $log['cancel'] ?? null,
            'delete' => $log['delete'] ?? null,
            'refund_note' => $log['refund_note'] ?? null,
            'payment_mode_id' => $log['payment_mode_id'] ?? null,
            'appointment_type_id' => $log['appointment_type_id'] ?? null,
            'location_id' => $log['location_id'] ?? null,
            'created_at' => isset($log['created_at_orignal'])
                ? Carbon::parse($log['created_at_orignal'])->toIso8601String()
                : null,
            'updated_at' => isset($log['updated_at_orignal'])
                ? Carbon::parse($log['updated_at_orignal'])->toIso8601String()
                : null,
            'detail_log' => array_values($log['detail_log'] ?? []),
        ];
    }
}
