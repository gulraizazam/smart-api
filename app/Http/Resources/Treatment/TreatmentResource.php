<?php

declare(strict_types=1);

namespace App\Http\Resources\Treatment;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Standard treatment resource for single appointment responses.
 */
final class TreatmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'patient_id'            => $this->patient_id,
            'appointment_type_id'   => $this->appointment_type_id,
            'appointment_status_id' => $this->appointment_status_id,
            'service_id'            => $this->service_id,
            'doctor_id'             => $this->doctor_id,
            'location_id'           => $this->location_id,
            'resource_id'           => $this->resource_id,
            'scheduled_date'        => $this->scheduled_date
                ? Carbon::parse($this->scheduled_date)->format('Y-m-d')
                : null,
            'scheduled_time'        => $this->scheduled_time
                ? Carbon::parse($this->scheduled_time)->format('H:i:s')
                : null,
            'name'                  => $this->name,
            'doctor'                => $this->whenLoaded('doctor', fn () => [
                'id'   => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'service'               => $this->whenLoaded('service', fn () => [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ]),
            'location'              => $this->whenLoaded('location', fn () => [
                'id'   => $this->location->id,
                'name' => $this->location->name,
            ]),
            'patient'               => $this->whenLoaded('patient', fn () => [
                'id'   => $this->patient->id,
                'name' => $this->patient->name,
            ]),
            'lead'                  => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead?->id,
            ]),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
