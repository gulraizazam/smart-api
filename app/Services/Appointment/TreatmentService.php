<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Helpers\AppointmentHelper;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Resources;
use App\Models\Services;
use Illuminate\Support\Facades\Cache;

/**
 * Appointment-oriented TreatmentService (extends AppointmentService).
 *
 * Used by the /api/treatment/* routes for list/scheduled/non-scheduled/statistics
 * which delegate to AppointmentService base methods.
 *
 * Store/update/edit logic lives in Treatment\TreatmentService (consolidated).
 */
class TreatmentService extends AppointmentService
{
    private ?int $treatmentTypeId = null;

    protected function getTreatmentTypeId(): ?int
    {
        if ($this->treatmentTypeId === null) {
            $accountId = $this->getAccountId();
            $cacheKey  = "treatment_type_id_{$accountId}";

            $this->treatmentTypeId = Cache::remember($cacheKey, 3600, fn () =>
                AppointmentTypes::where([
                    'account_id' => $accountId,
                    'slug'       => 'treatment',
                ])->value('id')
            );
        }

        return $this->treatmentTypeId;
    }

    public function getTreatmentList(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        if (!$this->getTreatmentTypeId()) {
            throw AppointmentException::invalidType();
        }

        return $this->getAppointmentsList($filters, $this->treatmentTypeId);
    }

    public function createTreatment(array $data): mixed
    {
        if (!$this->getTreatmentTypeId()) {
            throw AppointmentException::invalidType();
        }

        $data['appointment_type_id'] = $this->getTreatmentTypeId();

        if (isset($data['service_id'])) {
            $service = Services::find($data['service_id']);
            if (!$service) {
                throw AppointmentException::invalidService();
            }
        }

        if (!isset($data['appointment_status_id'])) {
            $defaultStatus = AppointmentStatuses::where([
                'account_id' => $this->getAccountId(),
                'is_default' => 1,
            ])->first();

            if ($defaultStatus) {
                $data['appointment_status_id']      = $defaultStatus->id;
                $data['base_appointment_status_id'] = $defaultStatus->id;
            }
        }

        if (isset($data['appointment_status_id']) && !isset($data['base_appointment_status_id'])) {
            $data['base_appointment_status_id'] = $data['appointment_status_id'];
        }

        return $this->createAppointment($data);
    }

    public function getScheduledTreatments(array $filters): mixed
    {
        if (!$this->getTreatmentTypeId()) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->treatmentTypeId;

        return $this->getScheduledAppointments($filters);
    }

    public function getNonScheduledTreatments(array $filters): mixed
    {
        if (!$this->getTreatmentTypeId()) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->treatmentTypeId;

        return $this->getNonScheduledAppointments($filters);
    }

    public function getTreatmentStatistics(array $filters = []): mixed
    {
        if (!$this->getTreatmentTypeId()) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->treatmentTypeId;

        return $this->getAppointmentStatistics($filters);
    }

    public function getAvailableResources(int $locationId, ?int $serviceId = null): mixed
    {
        $cacheKey = "treatment_resources_{$this->getAccountId()}_{$locationId}_{$serviceId}";

        return Cache::remember($cacheKey, 1800, function () use ($locationId, $serviceId) {
            $query = Resources::where([
                'account_id'  => $this->getAccountId(),
                'location_id' => $locationId,
                'active'      => 1,
            ]);

            if ($serviceId) {
                $query->whereHas('machineType.services', fn ($q) => $q->where('service_id', $serviceId));
            }

            return $query->with('machineType')->get();
        });
    }

    public function getServicesByLocation(int $locationId): mixed
    {
        $cacheKey = "treatment_services_location_{$this->getAccountId()}_{$locationId}";

        return Cache::remember($cacheKey, 3600, fn () =>
            Services::whereHas('locations', fn ($q) => $q->where('location_id', $locationId))
                ->where('account_id', $this->getAccountId())
                ->where('active', 1)
                ->orderBy('name')
                ->get()
        );
    }

    public function validateResourceAvailability(
        int $resourceId,
        string $scheduledDate,
        string $scheduledTime,
        ?int $appointmentId = null,
    ): bool {
        $query = Appointments::where([
            'resource_id'    => $resourceId,
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => $scheduledTime,
        ]);

        if ($appointmentId) {
            $query->where('id', '!=', $appointmentId);
        }

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->getAccountId());
        if ($cancelledStatus) {
            $query->where('base_appointment_status_id', '!=', $cancelledStatus->id);
        }

        return !$query->exists();
    }
}
