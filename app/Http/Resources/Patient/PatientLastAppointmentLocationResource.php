<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientLastAppointmentLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'location_id' => (int) $this->resource['location_id'],
            'location_name' => $this->resource['location_name'] ?? null,
            'location_slug' => $this->resource['location_slug'] ?? null,
            'last_appointment_at' => $this->resource['last_appointment_at'] ?? null,
        ];
    }
}
