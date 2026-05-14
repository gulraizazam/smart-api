<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoice;

use App\Enums\AppointmentType;
use App\Models\Invoices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

/**
 * Full payload for a single invoice — invoice + invoice_details + related
 * patient / location / service / status / account / doctor references.
 * Mirrors the legacy `displayInvoice` response but with a whitelisted
 * JSON shape and proper typing.
 */
class InvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Invoices $invoice */
        $invoice = $this->resource;

        $appointment = $invoice->appointment;
        $detail = $invoice->invoiceDetailService;
        $appointmentTypeId = $appointment?->appointment_type_id;

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => sprintf('%05d', (int) $invoice->id),
            'total_price' => (float) $invoice->total_price,
            'is_exclusive' => (bool) $invoice->is_exclusive,
            'is_settlement' => (bool) ($invoice->is_settlement ?? false),
            'appointment_id' => $invoice->appointment_id !== null ? (int) $invoice->appointment_id : null,
            'appointment_type_id' => $appointmentTypeId !== null ? (int) $appointmentTypeId : null,
            'appointment_type_label' => match ((int) ($appointmentTypeId ?? 0)) {
                AppointmentType::Consultancy->value => (string) Config::get('constants.Consultancy', 'Consultancy'),
                default => $appointmentTypeId !== null ? (string) Config::get('constants.Service', 'Service') : null,
            },
            'package_id' => $invoice->package_id !== null ? (int) $invoice->package_id : null,
            'active' => (bool) $invoice->active,

            'patient' => $invoice->relationLoaded('user') && $invoice->user
                ? [
                    'id' => (int) $invoice->user->id,
                    'name' => $invoice->user->name,
                    'phone' => $invoice->user->phone,
                    'email' => $invoice->user->email ?? null,
                    'address' => $invoice->user->address ?? null,
                ]
                : null,

            // The print page reads `fdo_phone / ntn / stn / city.name` from
            // this block to compose the header strip — consistent with the
            // consultation- and treatment-invoice receipts. They're surfaced
            // here so the SPA print route doesn't need a second fetch.
            'location' => $invoice->relationLoaded('location') && $invoice->location
                ? [
                    'id' => (int) $invoice->location->id,
                    'name' => $invoice->location->name,
                    'address' => $invoice->location->address ?? null,
                    'fdo_phone' => $invoice->location->fdo_phone ?? null,
                    'ntn' => $invoice->location->ntn ?? null,
                    'stn' => $invoice->location->stn ?? null,
                    'city' => $invoice->location->relationLoaded('city') && $invoice->location->city
                        ? ['id' => (int) $invoice->location->city->id, 'name' => $invoice->location->city->name]
                        : null,
                ]
                : null,

            'invoice_status' => $invoice->relationLoaded('invoicestatus') && $invoice->invoicestatus
                ? [
                    'id' => (int) $invoice->invoicestatus->id,
                    'name' => $invoice->invoicestatus->name,
                    'slug' => $invoice->invoicestatus->slug,
                ]
                : null,

            'account' => $invoice->relationLoaded('account') && $invoice->account
                ? [
                    'id' => (int) $invoice->account->id,
                    'name' => $invoice->account->name ?? null,
                    'email' => $invoice->account->email ?? null,
                ]
                : null,

            'doctor' => $appointment && $appointment->relationLoaded('doctor') && $appointment->doctor
                ? [
                    'id' => (int) $appointment->doctor->id,
                    'name' => $appointment->doctor->name ?? null,
                ]
                : null,

            'detail' => $detail ? [
                'service_id' => $detail->service_id !== null ? (int) $detail->service_id : null,
                'service_name' => $detail->relationLoaded('service') ? $detail->service?->name : null,
                'discount_id' => $detail->discount_id !== null ? (int) $detail->discount_id : null,
                'discount_type' => $detail->discount_type,
                'discount_price' => $detail->discount_price !== null ? (float) $detail->discount_price : null,
                'service_price' => $detail->service_price !== null ? (float) $detail->service_price : null,
                'net_amount' => $detail->net_amount !== null ? (float) $detail->net_amount : null,
                'tax_exclusive_serviceprice' => $detail->tax_exclusive_serviceprice !== null ? (float) $detail->tax_exclusive_serviceprice : null,
                'tax_percentage' => $detail->tax_percentage !== null ? (float) $detail->tax_percentage : null,
                'tax_price' => $detail->tax_price !== null ? (float) $detail->tax_price : null,
                'tax_including_price' => $detail->tax_including_price !== null ? (float) $detail->tax_including_price : null,
                'is_exclusive' => (bool) $detail->is_exclusive,
                'package_id' => $detail->package_id !== null ? (int) $detail->package_id : null,
            ] : null,

            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }
}
