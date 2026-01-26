<?php

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Helpers\AppointmentHelper;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ConsultancyService extends AppointmentService
{
    protected $consultancyTypeId;

    public function __construct()
    {
        parent::__construct();
        $this->consultancyTypeId = $this->getConsultancyTypeId();
    }

    protected function getConsultancyTypeId()
    {
        $cacheKey = "consultancy_type_id_{$this->account_id}";

        return Cache::remember($cacheKey, 3600, function () {
            $type = AppointmentTypes::where([
                'account_id' => $this->account_id,
                'slug' => 'consultancy'
            ])->first();

            return $type ? $type->id : null;
        });
    }

    public function getConsultancyList($filters)
    {
        if (!$this->consultancyTypeId) {
            throw AppointmentException::invalidType();
        }

        return $this->getAppointmentsList($filters, $this->consultancyTypeId);
    }

    public function createConsultancy(array $data)
    {
        if (!$this->consultancyTypeId) {
            throw AppointmentException::invalidType();
        }

        $data['appointment_type_id'] = $this->consultancyTypeId;
        $data['consultancy_type'] = $data['consultancy_type'] ?? 'in_person';
        
        unset($data['resource_id']);
        unset($data['resource_has_rota_day_id']);
        unset($data['resource_has_rota_day_id_for_machine']);
        
        if (!isset($data['appointment_status_id'])) {
            // Get default status for this account (not filtered by appointment_type_id)
            $defaultStatus = \App\Models\AppointmentStatuses::where([
                'account_id' => $this->account_id,
                'is_default' => 1
            ])->first();
            
            if ($defaultStatus) {
                $data['appointment_status_id'] = $defaultStatus->id;
                $data['base_appointment_status_id'] = $defaultStatus->id;
            }
        }
        
        // Always ensure base_appointment_status_id is set when appointment_status_id exists
        if (isset($data['appointment_status_id']) && !isset($data['base_appointment_status_id'])) {
            $data['base_appointment_status_id'] = $data['appointment_status_id'];
        }

        return $this->createAppointment($data);
    }

    public function updateConsultancy($id, array $data)
    {
        $appointment = Appointments::where([
            'id' => $id,
            'account_id' => $this->account_id,
            'appointment_type_id' => $this->consultancyTypeId
        ])->first();

        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        unset($data['resource_id']);
        unset($data['resource_has_rota_day_id']);
        unset($data['resource_has_rota_day_id_for_machine']);

        return $this->updateAppointment($id, $data);
    }

    public function getScheduledConsultancies($filters)
    {
        if (!$this->consultancyTypeId) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->consultancyTypeId;
        return $this->getScheduledAppointments($filters);
    }

    public function getNonScheduledConsultancies($filters)
    {
        if (!$this->consultancyTypeId) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->consultancyTypeId;
        return $this->getNonScheduledAppointments($filters);
    }

    public function getConsultancyStatistics($filters = [])
    {
        if (!$this->consultancyTypeId) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->consultancyTypeId;
        return $this->getAppointmentStatistics($filters);
    }

    public function deleteConsultancy($id)
    {
        $appointment = Appointments::where([
            'id' => $id,
            'account_id' => $this->getAccountId(),
            'appointment_type_id' => $this->consultancyTypeId
        ])->first();

        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        return $this->deleteAppointment($id);
    }

    public function scheduleConsultancy($id, array $data)
    {
        $appointment = Appointments::where([
            'id' => $id,
            'account_id' => $this->getAccountId(),
            'appointment_type_id' => $this->consultancyTypeId
        ])->first();

        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        return $this->scheduleAppointment($id, $data);
    }
}
