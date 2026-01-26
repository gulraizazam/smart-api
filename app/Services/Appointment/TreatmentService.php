<?php

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Helpers\AppointmentHelper;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\Services;
use App\Models\Resources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TreatmentService extends AppointmentService
{
    protected $treatmentTypeId;

    public function __construct()
    {
        parent::__construct();
        $this->treatmentTypeId = $this->getTreatmentTypeId();
    }

    protected function getTreatmentTypeId()
    {
        $cacheKey = "treatment_type_id_{$this->account_id}";

        return Cache::remember($cacheKey, 3600, function () {
            $type = AppointmentTypes::where([
                'account_id' => $this->account_id,
                'slug' => 'treatment'
            ])->first();

            return $type ? $type->id : null;
        });
    }

    public function getTreatmentList($filters)
    {
        if (!$this->treatmentTypeId) {
            throw AppointmentException::invalidType();
        }

        return $this->getAppointmentsList($filters, $this->treatmentTypeId);
    }

    public function createTreatment(array $data)
    {
        if (!$this->treatmentTypeId) {
            throw AppointmentException::invalidType();
        }

        $data['appointment_type_id'] = $this->treatmentTypeId;

        if (isset($data['service_id'])) {
            $service = Services::find($data['service_id']);
            if (!$service) {
                throw AppointmentException::invalidService();
            }
        }

        if (!isset($data['appointment_status_id'])) {
            // Get default status for this account
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

    public function updateTreatment($id, array $data)
    {
        $appointment = Appointments::where([
            'id' => $id,
            'account_id' => $this->account_id,
            'appointment_type_id' => $this->treatmentTypeId
        ])->first();

        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        return $this->updateAppointment($id, $data);
    }

    public function getScheduledTreatments($filters)
    {
        if (!$this->treatmentTypeId) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->treatmentTypeId;
        return $this->getScheduledAppointments($filters);
    }

    public function getNonScheduledTreatments($filters)
    {
        if (!$this->treatmentTypeId) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->treatmentTypeId;
        return $this->getNonScheduledAppointments($filters);
    }

    public function getTreatmentStatistics($filters = [])
    {
        if (!$this->treatmentTypeId) {
            throw AppointmentException::invalidType();
        }

        $filters['appointment_type_id'] = $this->treatmentTypeId;
        return $this->getAppointmentStatistics($filters);
    }

    public function getAvailableResources($location_id, $service_id = null)
    {
        $cacheKey = "treatment_resources_{$this->account_id}_{$location_id}_{$service_id}";

        return Cache::remember($cacheKey, 1800, function () use ($location_id, $service_id) {
            $query = Resources::where([
                'account_id' => $this->account_id,
                'location_id' => $location_id,
                'active' => 1
            ]);

            if ($service_id) {
                $query->whereHas('machineType.services', function ($q) use ($service_id) {
                    $q->where('service_id', $service_id);
                });
            }

            return $query->with('machineType')->get();
        });
    }

    public function getServicesByLocation($location_id)
    {
        $cacheKey = "treatment_services_location_{$this->account_id}_{$location_id}";

        return Cache::remember($cacheKey, 3600, function () use ($location_id) {
            return Services::whereHas('locations', function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            })->where('account_id', $this->account_id)
              ->where('active', 1)
              ->orderBy('name')
              ->get();
        });
    }

    public function validateResourceAvailability($resource_id, $scheduled_date, $scheduled_time, $appointment_id = null)
    {
        $query = Appointments::where([
            'resource_id' => $resource_id,
            'scheduled_date' => $scheduled_date,
            'scheduled_time' => $scheduled_time
        ]);

        if ($appointment_id) {
            $query->where('id', '!=', $appointment_id);
        }

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->account_id);
        if ($cancelledStatus) {
            $query->where('base_appointment_status_id', '!=', $cancelledStatus->id);
        }

        return !$query->exists();
    }
}
