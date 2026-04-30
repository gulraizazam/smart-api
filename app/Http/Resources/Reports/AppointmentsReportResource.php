<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentsReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // `first_invoice_at` is selected via a JOIN against an aggregate
        // subquery in AppointmentsReportService — avoids loading every
        // invoice row when we only need the earliest timestamp per row.
        $firstInvoiceAt = $this->resource->getAttribute('first_invoice_at');

        return [
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->name ?? 'N/A',
            'scheduled_date' => $this->scheduled_date
                ? date('Y-m-d', strtotime((string) $this->scheduled_date))
                : null,
            'scheduled_time' => $this->scheduled_time
                ? date('H:i', strtotime((string) $this->scheduled_time))
                : null,
            'location' => $this->location?->name ?? 'N/A',
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'arrival_date_time' => $firstInvoiceAt
                ? date('d-m-Y H:i:s', strtotime((string) $firstInvoiceAt))
                : 'N/A',
            'created_by' => $this->user?->name ?? 'N/A',
        ];
    }
}
