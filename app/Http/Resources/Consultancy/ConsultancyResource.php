<?php

declare(strict_types=1);

namespace App\Http\Resources\Consultancy;

use App\Enums\ConsultancyType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class ConsultancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->when($canViewContact, fn () => $this->patient?->phone),
            'patient_id' => $this->patient_id,
            'lead_id' => $this->lead_id,
            'consultancy_type' => $this->consultancy_type,
            'consultancy_type_label' => ConsultancyType::tryFrom($this->consultancy_type)?->label() ?? '',
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'city' => $this->location->city?->name,
            ]),
            'appointment_status' => $this->whenLoaded('appointment_status', fn () => [
                'id' => $this->appointment_status->id,
                'name' => $this->appointment_status->name,
            ]),
            'appointment_type' => $this->whenLoaded('appointment_type', fn () => [
                'id' => $this->appointment_type->id,
                'name' => $this->appointment_type->name,
            ]),
            'scheduled_date' => $this->scheduled_date
                ? Carbon::parse($this->scheduled_date)->format('Y-m-d')
                : null,
            'scheduled_time' => $this->scheduled_time
                ? Carbon::parse($this->scheduled_time)->format('H:i:s')
                : null,
            'scheduled_at' => $this->scheduled_date
                ? Carbon::parse($this->scheduled_date)->format('M j, Y')
                    . ' at ' . Carbon::parse($this->scheduled_time)->format('h:i A')
                : null,
            'arrived_at' => $this->arrived_at,
            'converted_at' => $this->converted_at,
            'patient' => $this->whenLoaded('patient', function () use ($canViewContact): array {
                $patient = [
                    'id' => $this->patient->id,
                    'name' => $this->patient->name,
                ];

                if ($canViewContact) {
                    $patient['phone'] = $this->patient->phone;
                }

                return $patient;
            }),
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
            ]),
            'created_by' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
