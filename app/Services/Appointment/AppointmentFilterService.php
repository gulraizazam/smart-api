<?php

namespace App\Services\Appointment;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AppointmentFilterService
{
    private $userId;
    private $accountId;
    private $filename = 'appointments';

    public function __construct()
    {
        $this->userId = Auth::id();
        $this->accountId = Auth::user()->account_id;
    }

    /**
     * Build optimized query filters for appointments datatable
     */
    public function buildFilters($query, array $filters)
    {
        // Apply basic where conditions
        $whereConditions = $this->buildWhereConditions($filters);
        if (!empty($whereConditions)) {
            $query->where($whereConditions);
        }

        // Apply date filters directly to query (not through where array)
        $this->applyDateFilters($query, $filters);

        // Apply status filters
        $statusIds = $this->getStatusIdsToFilter($filters);
        if (!empty($statusIds)) {
            $query->whereIn('appointments.base_appointment_status_id', $statusIds);
        }

        // Apply location filter
        $this->applyLocationFilter($query, $filters);

        // Apply name search filter
        $this->applyNameFilter($query, $filters);

        return $query;
    }

    /**
     * Build where conditions array
     */
    private function buildWhereConditions(array $filters): array
    {
        $where = [];

        // Patient ID filter
        if ($this->hasFilter($filters, 'patient_id')) {
            $patientId = GeneralFunctions::patientSearch($filters['patient_id']);
            $where[] = ['users.id', '=', $patientId];
            $this->saveFilter('patient_id', $patientId);
        }

        // Phone filter
        if ($this->hasFilter($filters, 'phone')) {
            $where[] = ['users.phone', 'like', '%' . $filters['phone'] . '%'];
            $this->saveFilter('phone', $filters['phone']);
        }

        // Doctor filter
        if ($this->hasFilter($filters, 'doctor_id')) {
            $where[] = [['doctor_id' => $filters['doctor_id']]];
            $this->saveFilter('doctor_id', $filters['doctor_id']);
        }

        // Service filter
        if ($this->hasFilter($filters, 'service_id')) {
            $where[] = [['service_id' => $filters['service_id']]];
            $this->saveFilter('service_id', $filters['service_id']);
        }

        // Created by filter
        if ($this->hasFilter($filters, 'created_by')) {
            $where[] = [['appointments.created_by' => $filters['created_by']]];
            $this->saveFilter('created_by', $filters['created_by']);
        }

        // Converted by filter
        if ($this->hasFilter($filters, 'converted_by')) {
            $where[] = [['appointments.converted_by' => $filters['converted_by']]];
            $this->saveFilter('converted_by', $filters['converted_by']);
        }

        // Updated by filter
        if ($this->hasFilter($filters, 'updated_by')) {
            $where[] = [['appointments.updated_by' => $filters['updated_by']]];
            $this->saveFilter('updated_by', $filters['updated_by']);
        }

        // Appointment type filter
        if ($this->hasFilter($filters, 'appointment_type_id')) {
            $where[] = [['appointments.appointment_type_id' => $filters['appointment_type_id']]];
            $this->saveFilter('appointment_type_id', $filters['appointment_type_id']);
        }

        // Created at date range filter
        if ($this->hasFilter($filters, 'created_from') && $this->hasFilter($filters, 'created_to')) {
            $where[] = ['appointments.created_at', '>=', $filters['created_from'] . ' 00:00:00'];
            $where[] = ['appointments.created_at', '<=', $filters['created_to'] . ' 23:59:59'];
            $this->saveFilter('created_from', $filters['created_from']);
            $this->saveFilter('created_to', $filters['created_to']);
        }

        return $where;
    }

    /**
     * Apply date filters directly to query
     */
    private function applyDateFilters($query, array $filters): void
    {
        if ($this->hasFilter($filters, 'date_from')) {
            $query->whereRaw('DATE(appointments.scheduled_date) >= ?', [$filters['date_from']]);
            $this->saveFilter('date_from', $filters['date_from']);
        }

        if ($this->hasFilter($filters, 'date_to')) {
            $query->whereRaw('DATE(appointments.scheduled_date) <= ?', [$filters['date_to']]);
            $this->saveFilter('date_to', $filters['date_to']);
        }
    }

    /**
     * Get status IDs to filter including arrived/converted logic
     */
    private function getStatusIdsToFilter(array $filters): array
    {
        if (!$this->hasFilter($filters, 'appointment_status_id')) {
            return [];
        }

        $selectedStatus = AppointmentStatuses::find($filters['appointment_status_id']);
        
        // If status is "arrived", include both arrived and converted statuses
        if ($selectedStatus && $selectedStatus->is_arrived == 1) {
            $convertedStatus = AppointmentStatuses::where([
                'account_id' => $this->accountId,
                'is_converted' => 1
            ])->first();

            $statusIds = $convertedStatus 
                ? [$filters['appointment_status_id'], $convertedStatus->id]
                : [$filters['appointment_status_id']];
        } else {
            $statusIds = [$filters['appointment_status_id']];
        }

        $this->saveFilter('appointment_status_id', $filters['appointment_status_id']);
        
        return $statusIds;
    }

    /**
     * Apply location filter
     */
    private function applyLocationFilter($query, array $filters): void
    {
        if (!$this->hasFilter($filters, 'location_id')) {
            return;
        }

        $ids = explode(',', $filters['location_id']);
        
        if (count($ids) > 1) {
            $query->whereIn('location_id', $ids);
        } else {
            $query->where('location_id', $ids);
        }

        $this->saveFilter('location_id', $filters['location_id']);
    }

    /**
     * Apply name search filter
     */
    private function applyNameFilter($query, array $filters): void
    {
        if (!$this->hasFilter($filters, 'name')) {
            return;
        }

        $query->where(function ($q) use ($filters) {
            $q->where('users.name', 'like', '%' . $filters['name'] . '%')
              ->orWhere('appointments.name', 'like', '%' . $filters['name'] . '%');
        });

        $this->saveFilter('name', $filters['name']);
    }

    /**
     * Build base query with ACL and permissions
     */
    public function buildBaseQuery()
    {
        // Cache appointment types
        static $consultancyType, $treatmentType;
        if (!$consultancyType) {
            $consultancyType = AppointmentTypes::where('slug', 'consultancy')->first();
            $treatmentType = AppointmentTypes::where('slug', 'treatment')->first();
        }

        // Cache permissions
        $canViewConsultancy = Gate::allows('appointments_consultancy');
        $canViewTreatments = Gate::allows('treatments_services');

        // Build base query with LEFT JOIN to include consultations without patient records
        $query = \App\Models\Appointments::leftJoin('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id');
        })
        //->whereIn('appointments.city_id', ACL::getUserCities())
        ->whereIn('appointments.location_id', ACL::getUserCentres())
        ->where('appointments.appointment_type_id', $consultancyType->id); // Always filter for consultancy only

        return $query;
    }

    /**
     * Check if filter exists and has value
     */
    private function hasFilter(array $filters, string $key): bool
    {
        return hasFilter($filters, $key);
    }

    /**
     * Save filter to session
     */
    private function saveFilter(string $key, $value): void
    {
        Filters::put($this->userId, $this->filename, $key, $value);
    }
}
