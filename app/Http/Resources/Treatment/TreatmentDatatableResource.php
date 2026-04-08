<?php

declare(strict_types=1);

namespace App\Http\Resources\Treatment;

use App\Helpers\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a single appointment row for the treatments datatable.
 *
 * Expects the underlying model to have been selected with the custom aliases
 * produced by TreatmentService::getAppointments() and eager-loaded relations.
 */
final class TreatmentDatatableResource extends JsonResource
{
    private bool $canViewContact;
    private array $regions;
    private array $users;
    private array $appointmentStatuses;
    private mixed $unscheduledStatus;
    private mixed $cancelledStatus;

    /**
     * Inject lookup data needed for transformation.
     */
    public function setLookupData(
        bool $canViewContact,
        array $regions,
        array $users,
        array $appointmentStatuses,
        mixed $unscheduledStatus,
        mixed $cancelledStatus,
    ): self {
        $this->canViewContact = $canViewContact;
        $this->regions = $regions;
        $this->users = $users;
        $this->appointmentStatuses = $appointmentStatuses;
        $this->unscheduledStatus = $unscheduledStatus;
        $this->cancelledStatus = $cancelledStatus;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $appointment = $this->resource;

        $consultancyType = match ($appointment->consultancy_type) {
            'in_person' => 'In Person',
            'virtual'   => 'Virtual',
            default     => '',
        };

        $phoneNumber = $this->canViewContact
            ? ($appointment->patient->phone ?? '')
            : '***********';

        $scheduledDate = $appointment->scheduled_date
            ? Carbon::parse($appointment->scheduled_date)->format('M j, Y')
                . ' at '
                . Carbon::parse($appointment->scheduled_time)->format('h:i A')
            : '-';

        $appointmentStatusName = $this->resolveStatusName($appointment);

        return [
            'id'                              => $appointment->app_id,
            'patient_id'                      => $appointment->patient_id,
            'Patient_ID'                      => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
            'name'                            => $appointment->patient_name ?: ($appointment->patient->name ?? ''),
            'phone'                           => $phoneNumber,
            'scheduled_date'                  => $scheduledDate,
            'apt_scheduled_date'              => $appointment->scheduled_date,
            'doctor_id'                       => $appointment->doctor->name ?? 'N/A',
            'doctorId'                        => $appointment->doctor->id ?? 0,
            'region_id'                       => isset($this->regions[$appointment->region_id])
                ? $this->regions[$appointment->region_id]->name
                : 'N/A',
            'city_id'                         => $appointment->city->name ?? 'N/A',
            'cityId'                          => $appointment->city_id ?? 0,
            'location_id'                     => $appointment->location->name ?? 'N/A',
            'locationId'                      => $appointment->location_id ?? 'N/A',
            'service_id'                      => $appointment->service->name ?? 'N/A',
            'resource_id'                     => $appointment->resource_id ?? 0,
            'appointment_type_id'             => $appointment->appointment_type->name ?? '',
            'appointment_type'                => $appointment->appointment_type->id ?? 0,
            'consultancy_type'                => $consultancyType,
            'created_at'                      => Carbon::parse($appointment->app_created_at)->format('F j, Y h:i A'),
            'created_by'                      => $this->resolveUserName($appointment->app_created_by),
            'converted_by'                    => $this->resolveUserName($appointment->converted_by),
            'updated_by'                      => $this->resolveUserName($appointment->app_updated_by),
            'unscheduled_appointment_status'  => $this->unscheduledStatus,
            'cancelled_appointment_status'    => $this->cancelledStatus,
            'appointment_status_id'           => $appointmentStatusName,
            'appointment_status'              => $appointment->appointment_status_id,
            'invoice_id'                      => $appointment->invoice->id ?? 0,
            'invoice'                         => $appointment->invoice,
        ];
    }

    private function resolveStatusName(mixed $appointment): string
    {
        if (!$appointment->appointment_status_id || !$appointment->appointment_status) {
            return '';
        }

        $status = $appointment->appointment_status;

        if ($status->parent_id) {
            return $this->appointmentStatuses[$status->parent_id]->name ?? $status->name;
        }

        return $status->name;
    }

    private function resolveUserName(mixed $userId): string
    {
        if (!$userId) {
            return 'N/A';
        }

        return isset($this->users[$userId]) ? $this->users[$userId]->name : 'N/A';
    }
}
