<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversionReportResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'patient_id' => $data['patient_id'],
            'appointment_id' => $data['appointment_id'],
            'doctor' => $data['doctor'],
            'client' => $data['client'],
            'service' => $data['service'],
            'region' => $data['region'],
            'city' => $data['city'],
            'centre' => $data['centre'],
            'doi' => $data['doi'],
            'converted' => $data['converted'],
            'conversion_spend' => round((float) ($data['conversion_spend'] ?: 0), 2),
            'conversion_date' => $data['conversion_date'],
        ];
    }
}
