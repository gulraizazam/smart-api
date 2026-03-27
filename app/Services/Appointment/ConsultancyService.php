<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ConsultancyService extends AppointmentService
{
    private ?int $consultancyTypeId = null;

    private static array $resourceFields = [
        'resource_id',
        'resource_has_rota_day_id',
        'resource_has_rota_day_id_for_machine',
    ];

    protected function getConsultancyTypeId(): ?int
    {
        if ($this->consultancyTypeId !== null) {
            return $this->consultancyTypeId;
        }

        $accountId = $this->getAccountId();
        $cacheKey = "consultancy_type_id_{$accountId}";

        $this->consultancyTypeId = Cache::remember($cacheKey, 3600, fn () => AppointmentTypes::where([
            'account_id' => $accountId,
            'slug' => 'consultancy',
        ])->value('id'));

        return $this->consultancyTypeId;
    }

    private function ensureConsultancyTypeExists(): int
    {
        $typeId = $this->getConsultancyTypeId();

        if (!$typeId) {
            throw AppointmentException::invalidType();
        }

        return $typeId;
    }

    private function findConsultancyOrFail(int $id): Appointments
    {
        $appointment = Appointments::where([
            'id' => $id,
            'account_id' => $this->getAccountId(),
            'appointment_type_id' => $this->ensureConsultancyTypeExists(),
        ])->first();

        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        return $appointment;
    }

    private function stripResourceFields(array $data): array
    {
        return array_diff_key($data, array_flip(self::$resourceFields));
    }

    public function getConsultancyList(array $filters): Builder
    {
        $typeId = $this->ensureConsultancyTypeExists();

        return $this->getAppointmentsList($filters, $typeId);
    }

    public function createConsultancy(array $data): Appointments
    {
        $typeId = $this->ensureConsultancyTypeExists();

        $data = $this->stripResourceFields($data);
        $data['appointment_type_id'] = $typeId;
        $data['consultancy_type'] ??= 'in_person';

        if (!isset($data['appointment_status_id'])) {
            $defaultStatus = \App\Models\AppointmentStatuses::where([
                'account_id' => $this->getAccountId(),
                'is_default' => 1,
            ])->first();

            if ($defaultStatus) {
                $data['appointment_status_id'] = $defaultStatus->id;
                $data['base_appointment_status_id'] = $defaultStatus->id;
            }
        }

        if (isset($data['appointment_status_id']) && !isset($data['base_appointment_status_id'])) {
            $data['base_appointment_status_id'] = $data['appointment_status_id'];
        }

        return $this->createAppointment($data);
    }

    public function updateConsultancy(int $id, array $data): Appointments
    {
        $this->findConsultancyOrFail($id);

        $data = $this->stripResourceFields($data);

        return $this->updateAppointment($id, $data);
    }

    public function getScheduledConsultancies(array $filters): mixed  // Returns Collection via parent
    {
        $typeId = $this->ensureConsultancyTypeExists();

        $filters['appointment_type_id'] = $typeId;

        return $this->getScheduledAppointments($filters);
    }

    public function getNonScheduledConsultancies(array $filters): mixed  // Returns Collection via parent
    {
        $typeId = $this->ensureConsultancyTypeExists();

        $filters['appointment_type_id'] = $typeId;

        return $this->getNonScheduledAppointments($filters);
    }

    public function getConsultancyStatistics(array $filters = []): array
    {
        $typeId = $this->ensureConsultancyTypeExists();

        $filters['appointment_type_id'] = $typeId;

        return $this->getAppointmentStatistics($filters);
    }

    public function deleteConsultancy(int $id): bool
    {
        $this->findConsultancyOrFail($id);

        return $this->deleteAppointment($id);
    }

    public function scheduleConsultancy(int $id, array $data): Appointments
    {
        $this->findConsultancyOrFail($id);

        return $this->scheduleAppointment($id, $data);
    }
}
