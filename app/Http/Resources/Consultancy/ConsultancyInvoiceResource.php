<?php

declare(strict_types=1);

namespace App\Http\Resources\Consultancy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultancyInvoiceResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'price' => $this->resource['price'] ?? null,
            'tax' => $this->resource['tax'] ?? null,
            'tax_amt' => $this->resource['tax_amt'] ?? null,
            'settle_amount' => $this->resource['settle_amount'] ?? null,
            'outstanding' => $this->resource['outstanding'] ?? null,
            'invoice_status' => $this->resource['invoice_status'] ?? false,
            'service' => $this->resource['service'] ?? null,
            'appointment_type' => $this->resource['appointment_type'] ?? null,
            'location_info' => $this->resource['location_info'] ?? null,
            'discounts' => $this->resource['discounts'] ?? null,
            'payment_modes' => $this->resource['payment_modes'] ?? null,
            'cash' => $this->resource['cash'] ?? 0,
            'price_tax' => $this->resource['price_tax'] ?? 0,
            'patient' => $this->resource['patient'] ?? null,
            'doctor' => $this->resource['doctor'] ?? null,
            'account' => $this->resource['account'] ?? null,
            'appointment_id' => $this->resource['appointment_id'] ?? null,
        ];
    }
}
