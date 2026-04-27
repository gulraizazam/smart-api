<?php

declare(strict_types=1);

namespace App\Http\Resources\Treatment;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Standard treatment resource.
 *
 * Shape mirrors `App\Http\Resources\Consultancy\ConsultancyResource` so
 * the SPA's treatments module can reuse the same UI primitives the
 * consultations module already uses (avatar+name, doctor, location +
 * city, status, scheduled_at formatted, audit users for the row
 * expander, etc.). Treatment-specific extras (`resource_id` for the
 * machine, `consultancy_type_label` is intentionally absent) are added
 * on top.
 */
final class TreatmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('contact');

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'phone'                 => $this->when($canViewContact, fn () => $this->patient?->phone),
            'patient_id'            => $this->patient_id,
            'lead_id'               => $this->lead_id,
            'appointment_type_id'   => $this->appointment_type_id,
            'appointment_status_id' => $this->appointment_status_id,
            'service_id'            => $this->service_id,
            'doctor_id'             => $this->doctor_id,
            'location_id'           => $this->location_id,
            // Treatment-specific: which machine/operator resource is
            // booked. Null until a resource is picked at schedule time.
            'resource_id'           => $this->resource_id,
            'service'               => $this->whenLoaded('service', fn () => [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ]),
            'doctor'                => $this->whenLoaded('doctor', fn () => [
                'id'   => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'location'              => $this->whenLoaded('location', fn () => [
                'id'   => $this->location->id,
                'name' => $this->location->name,
                'city' => $this->location->city?->name,
            ]),
            'resource'              => $this->whenLoaded('resource', fn () => $this->resource ? [
                'id'   => $this->resource->id,
                'name' => $this->resource->name,
            ] : null),
            'appointment_status'    => $this->whenLoaded('appointment_status', fn () => [
                'id'   => $this->appointment_status->id,
                'name' => $this->appointment_status->name,
            ]),
            'appointment_type'      => $this->whenLoaded('appointment_type', fn () => [
                'id'   => $this->appointment_type->id,
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
            'arrived_at'            => $this->arrived_at,
            'converted_at'          => $this->converted_at,
            'patient'               => $this->whenLoaded('patient', function () use ($canViewContact): array {
                $patient = [
                    'id'   => $this->patient->id,
                    'name' => $this->patient->name,
                ];

                if ($canViewContact) {
                    $patient['phone'] = $this->patient->phone;
                }

                return $patient;
            }),
            'lead'                  => $this->whenLoaded('lead', fn () => [
                'id'   => $this->lead?->id,
                'name' => $this->lead?->name,
            ]),
            'created_by'            => $this->whenLoaded('user', fn () => $this->user ? [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'updated_by'            => $this->whenLoaded('user_updated_by', fn () => $this->user_updated_by ? [
                'id'   => $this->user_updated_by->id,
                'name' => $this->user_updated_by->name,
            ] : null),
            'rescheduled_by'        => $this->whenLoaded('user_converted_by', fn () => $this->user_converted_by ? [
                'id'   => $this->user_converted_by->id,
                'name' => $this->user_converted_by->name,
            ] : null),
            'created_at'            => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
