<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoice;

use App\Enums\AppointmentType;
use App\Models\Invoices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

/**
 * Compact shape for the invoices list — one row per invoice. For a full
 * payload (invoice + details + related entities) use `InvoiceDetailResource`.
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Invoices $invoice */
        $invoice = $this->resource;

        $appointmentTypeId = $invoice->appointment?->appointment_type_id;

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => sprintf('%05d', (int) $invoice->id),
            'total_price' => (float) $invoice->total_price,
            'is_exclusive' => (bool) $invoice->is_exclusive,
            'is_settlement' => (bool) ($invoice->is_settlement ?? false),
            'appointment_type_id' => $appointmentTypeId !== null ? (int) $appointmentTypeId : null,
            'appointment_type_label' => match ((int) ($appointmentTypeId ?? 0)) {
                AppointmentType::Consultancy->value => (string) Config::get('constants.Consultancy', 'Consultancy'),
                default => $appointmentTypeId !== null ? (string) Config::get('constants.Service', 'Service') : null,
            },
            'appointment_id' => $invoice->appointment_id !== null ? (int) $invoice->appointment_id : null,
            'package_id' => $invoice->package_id !== null ? (int) $invoice->package_id : null,
            'patient_id' => $invoice->patient_id !== null ? (int) $invoice->patient_id : null,
            'patient_name' => $this->whenLoaded('user', fn (): ?string => $invoice->user?->name),
            'location_id' => $invoice->location_id !== null ? (int) $invoice->location_id : null,
            'location_name' => $invoice->relationLoaded('appointment') && $invoice->appointment?->relationLoaded('location')
                ? $invoice->appointment->location?->name
                : null,
            // Service is sourced from `invoice_details` (authoritative — same
            // path the `service_id` query filter uses). Falls back to the
            // appointment's service for rows where the detail relation isn't
            // preloaded, so `show`-style payloads still resolve a name.
            'service_id' => $invoice->relationLoaded('invoiceDetailService') && $invoice->invoiceDetailService
                ? ($invoice->invoiceDetailService->service_id !== null ? (int) $invoice->invoiceDetailService->service_id : null)
                : ($invoice->relationLoaded('appointment') && $invoice->appointment
                    ? ($invoice->appointment->service_id !== null ? (int) $invoice->appointment->service_id : null)
                    : null),
            'service_name' => $invoice->relationLoaded('invoiceDetailService')
                && $invoice->invoiceDetailService?->relationLoaded('service')
                    ? $invoice->invoiceDetailService->service?->name
                    : ($invoice->relationLoaded('appointment') && $invoice->appointment?->relationLoaded('service')
                        ? $invoice->appointment->service?->name
                        : null),
            'invoice_status_id' => $invoice->invoice_status_id !== null ? (int) $invoice->invoice_status_id : null,
            'invoice_status_name' => $this->whenLoaded('invoicestatus', fn (): ?string => $invoice->invoicestatus?->name),
            'invoice_status_slug' => $this->whenLoaded('invoicestatus', fn (): ?string => $invoice->invoicestatus?->slug),
            'active' => (bool) $invoice->active,
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }
}
