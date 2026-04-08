<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentsReportResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $firstInvoice = $this->hasInvoices->first();

        return [
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->name ?? 'N/A',
            'scheduled_date' => $this->scheduled_date,
            'scheduled_time' => $this->scheduled_time,
            'location' => $this->location?->name ?? 'N/A',
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'arrival_date_time' => $firstInvoice
                ? date('d-m-Y H:i:s', strtotime($firstInvoice->created_at))
                : 'N/A',
            'created_by' => $this->user?->name ?? 'N/A',
        ];
    }
}
