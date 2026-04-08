<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationsReportResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'schedule_date' => $data['schedule_date'] ?? null,
            'client_id' => $data['id'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'appointment_type' => $data['appointment_type'] ?? null,
            'appointment_slug' => $data['appointment_slug'] ?? null,
            'doctor_name' => $data['doctor_name'] ?? null,
            'service' => $data['service'] ?? null,
            'appointment_status_parent' => $data['appointment_status_parent'] ?? null,
            'appointment_status_child' => $data['appointment_status_child'] ?? null,
            'is_arrived' => $data['appointment_status_isarrived'] ?? null,
        ];
    }
}
