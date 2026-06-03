<?php

declare(strict_types=1);

namespace App\Http\Resources\Treatment;

use App\Http\Resources\Concerns\ResolvesAppointmentRowFlags;
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
    // Shared `has_paid_invoice` / `has_children` resolution + the batch
    // `preload()` the list endpoint calls to avoid the per-row N+1.
    // Same trait the consultancy resource uses.
    use ResolvesAppointmentRowFlags;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('treatments.list.view_contact');

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
                // Flag fields so the SPA can distinguish system-managed
                // statuses (Arrived / Converted / Un-Scheduled / Cancelled)
                // from manual ones without doing a fragile substring
                // match on the name. Used by the row-level Schedule
                // affordance and the status dialog's lock state.
                'is_arrived'     => (bool) ($this->appointment_status->is_arrived ?? false),
                'is_converted'   => (bool) ($this->appointment_status->is_converted ?? false),
                'is_unscheduled' => (bool) ($this->appointment_status->is_unscheduled ?? false),
                'is_cancelled'   => (bool) ($this->appointment_status->is_cancelled ?? false),
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
            // Backend-computed lock signals — see ConsultancyResource for
            // the full rationale. has_paid_invoice mirrors
            // AppointmentService's paid-invoice lock; has_children
            // mirrors AppointmentHelper::isChildExists.
            'has_paid_invoice'      => $this->resolveHasPaidInvoice(),
            'has_children'          => $this->resolveHasChildren(),
            // True when a treatment-feedback row exists for this
            // appointment. TreatmentService::getTreatmentList eager-loads
            // `withCount('feedback')`, so this is a no-N+1 lookup. SPA
            // colours the row's Feedback star icon yellow when true.
            'has_feedback'          => (int) ($this->feedback_count ?? 0) > 0,
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
