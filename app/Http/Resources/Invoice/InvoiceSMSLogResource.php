<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoice;

use App\Models\SMSLogs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSMSLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SMSLogs $log */
        $log = $this->resource;

        return [
            'id' => (int) $log->id,
            'invoice_id' => $log->invoice_id !== null ? (int) $log->invoice_id : null,
            'to' => $log->to,
            'text' => $log->text,
            'status' => $log->status !== null ? (int) $log->status : null,
            'sent_successfully' => (int) ($log->status ?? 0) === 1,
            'created_at' => $log->created_at?->toIso8601String(),
            'updated_at' => $log->updated_at?->toIso8601String(),
        ];
    }
}
