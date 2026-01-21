<?php

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\DoctorHasLocations;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\ResourceHasRotaDays;
use App\Models\Services;
use App\Models\User as Patients;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

class ConsultancyUpdateService
{
    /**
     * Update consultation with permission-based field handling
     */
    public function updateConsultation(int $appointmentId, array $requestData)
    {
        // Find appointment
        $appointment = Appointments::find($appointmentId);
        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        // Store old values for activity logging
        $oldValues = [
            'service_id' => $appointment->service_id,
            'doctor_id' => $appointment->doctor_id,
            'scheduled_date' => $appointment->scheduled_date,
            'scheduled_time' => $appointment->scheduled_time,
            'location_id' => $appointment->location_id,
            'city_id' => $appointment->city_id,
            'consultancy_type' => $appointment->consultancy_type,
        ];

        // Check if arrived/converted
        $isArrivedOrConverted = in_array($appointment->appointment_status_id, [2, 16]);
        
        // Get permissions
        $permissions = [
            'service' => Gate::allows('update_consultation_service'),
            'doctor' => Gate::allows('update_consultation_doctor'),
            'schedule' => Gate::allows('update_consultation_schedule'),
        ];

        // Validate permissions for arrived/converted
        if ($isArrivedOrConverted) {
            $this->validateArrivedConsultationUpdate($appointment, $requestData, $permissions);
        } else {
            $this->validateNormalConsultationUpdate($appointment, $requestData);
        }

        // Prepare update data
        $updateData = $this->prepareUpdateData($appointment, $requestData);

        // Update appointment
        $appointment->update($updateData);

        // Update related records
        $this->updateRelatedRecords($appointment, $requestData);

        // Log activity with detailed changes
        $this->logActivity($appointment->fresh(), $requestData, $oldValues);

        return $appointment->fresh();
    }

    /**
     * Validate update for arrived/converted consultation
     */
    protected function validateArrivedConsultationUpdate($appointment, $requestData, $permissions)
    {
        // Check if service is changing
        $newServiceId = $requestData['treatment_service_id'] ?? $requestData['service_id'] ?? $requestData['treatment_id'] ?? null;
        if ($newServiceId && $appointment->service_id != $newServiceId) {
            if (!$permissions['service']) {
                throw AppointmentException::unauthorized('You do not have permission to change the service.');
            }
            
            // Validate current doctor has new service
            $this->validateDoctorHasService($appointment->doctor_id, $newServiceId, $requestData['location_id']);
        }

        // Check if doctor is changing
        $newDoctorId = $requestData['doctor_id'] ?? null;
        if ($newDoctorId && $appointment->doctor_id != $newDoctorId) {
            if (!$permissions['doctor']) {
                throw AppointmentException::unauthorized('You do not have permission to change the doctor.');
            }
            
            // Validate new doctor has current service
            $this->validateDoctorHasService($newDoctorId, $appointment->service_id, $requestData['location_id']);
            
            // Validate new doctor has rota availability
            $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
            $this->validateDoctorRota($newDoctorId, $scheduledDate, $scheduledTime, $requestData['location_id']);
        }

        // Check if schedule is changing
        $scheduleChanging = (isset($requestData['scheduled_date']) && $requestData['scheduled_date'] != $appointment->scheduled_date) ||
                           (isset($requestData['scheduled_time']) && $requestData['scheduled_time'] != $appointment->scheduled_time);
        
        if ($scheduleChanging) {
            if (!$permissions['schedule']) {
                throw AppointmentException::unauthorized('You do not have permission to change the schedule.');
            }
            
            // Validate doctor has rota for new schedule
            $doctorId = $requestData['doctor_id'] ?? $appointment->doctor_id;
            $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
            $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $requestData['location_id']);
        }
    }

    /**
     * Validate update for normal consultation
     */
    protected function validateNormalConsultationUpdate($appointment, $requestData)
    {
        // Check invoice
        if (!Gate::allows('edit_after_arrived')) {
            $invoice = Invoices::where('appointment_id', $appointment->id)->first();
            if ($invoice) {
                throw AppointmentException::invalidData('Invoice already generated. Appointment cannot be rescheduled.');
            }
        }

        // Validate doctor has service
        $doctorId = $requestData['doctor_id'] ?? $appointment->doctor_id;
        $serviceId = $requestData['treatment_service_id'] ?? $requestData['service_id'] ?? $requestData['treatment_id'] ?? $appointment->service_id;
        $locationId = $requestData['location_id'] ?? $appointment->location_id;
        
        $this->validateDoctorHasService($doctorId, $serviceId, $locationId);
        
        // Validate doctor has rota availability
        $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
        $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
        $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId);
    }

    /**
     * Validate doctor has rota availability
     */
    protected function validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId)
    {
        // Get resource (doctor) record
        $resource = Resources::where([
            'external_id' => $doctorId,
            'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
            'account_id' => Auth::user()->account_id,
        ])->first();

        if (!$resource) {
            throw AppointmentException::invalidData('Doctor resource not found.');
        }

        // Check if doctor has rota for the scheduled date
        $rotaDay = ResourceHasRotaDays::getSingleDayRotaWithResourceID(
            $resource->id,
            $scheduledDate,
            Auth::user()->account_id,
            $locationId
        );

        if (empty($rotaDay)) {
            throw AppointmentException::invalidData('Doctor does not have rota availability for the selected date and location.');
        }

        // Validate scheduled time is within rota hours
        $scheduledTimeCarbon = Carbon::parse($scheduledTime);
        $rotaStartTime = Carbon::parse($rotaDay['start_time']);
        $rotaEndTime = Carbon::parse($rotaDay['end_time']);

        if ($scheduledTimeCarbon->lt($rotaStartTime) || $scheduledTimeCarbon->gt($rotaEndTime)) {
            throw AppointmentException::invalidData('Scheduled time is outside doctor\'s rota hours.');
        }
    }

    /**
     * Validate doctor has service allocated
     */
    protected function validateDoctorHasService($doctorId, $serviceId, $locationId)
    {
        $service = Services::find($serviceId);
        if (!$service) {
            throw AppointmentException::invalidData('Service not found.');
        }

        $parentServiceId = $service->parent_id == 0 ? $service->id : $service->parent_id;

        // Check for "all services" (ID 13) or specific service
        $hasService = DoctorHasLocations::where('is_allocated', 1)
            ->where('user_id', $doctorId)
            ->where('location_id', $locationId)
            ->where(function($query) use ($parentServiceId) {
                $query->where('service_id', 13)
                      ->orWhere('service_id', $parentServiceId);
            })
            ->exists();

        if (!$hasService) {
            throw AppointmentException::invalidData('Doctor does not have the required service allocated for this location.');
        }
    }

    /**
     * Prepare update data from request
     */
    protected function prepareUpdateData($appointment, $requestData)
    {
        $data = [];

        // Updated timestamp
        $data['updated_at'] = Filters::getCurrentTimeStamp();

        // Scheduled date
        if (isset($requestData['scheduled_date']) && $requestData['scheduled_date']) {
            $data['scheduled_date'] = Carbon::parse($requestData['scheduled_date'])->format('Y-m-d');
        } else {
            $data['scheduled_date'] = $appointment->scheduled_date;
        }

        // Scheduled time
        if (isset($requestData['scheduled_time']) && $requestData['scheduled_time']) {
            $data['scheduled_time'] = Carbon::parse($requestData['scheduled_time'])->format('H:i:s');
        } else {
            $data['scheduled_time'] = $appointment->scheduled_time;
        }

        // Location
        $data['location_id'] = $requestData['location_id'] ?? $appointment->location_id;

        // Doctor
        if (isset($requestData['doctor_id']) && $requestData['doctor_id']) {
            $data['doctor_id'] = $requestData['doctor_id'];
        } else {
            $data['doctor_id'] = $appointment->doctor_id;
        }

        // Service
        if (isset($requestData['treatment_id']) && $requestData['treatment_id']) {
            $data['service_id'] = $requestData['treatment_id'];
        } elseif (isset($requestData['service_id']) && $requestData['service_id']) {
            $data['service_id'] = $requestData['service_id'];
        } elseif (isset($requestData['treatment_service_id']) && $requestData['treatment_service_id']) {
            $data['service_id'] = $requestData['treatment_service_id'];
        }

        // Consultancy type
        if (isset($requestData['consultancy_type'])) {
            $data['consultancy_type'] = $requestData['consultancy_type'];
        }

        // City and region from location
        $location = Locations::find($data['location_id']);
        if ($location) {
            $data['city_id'] = $location->city_id;
            $data['region_id'] = $location->region_id;
        }

        // Resource and rota day
        $resource = Resources::where([
            'external_id' => $data['doctor_id'],
            'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
            'account_id' => Auth::user()->account_id,
        ])->first();

        if ($resource) {
            $rotaDay = ResourceHasRotaDays::getSingleDayRotaWithResourceID(
                $resource->id,
                $data['scheduled_date'],
                Auth::user()->account_id,
                $data['location_id']
            );
            
            if (count($rotaDay)) {
                $data['resource_id'] = $resource->id;
                $data['resource_has_rota_day_id'] = $rotaDay['id'];
            }
        }

        // Track who updated
        if (isset($requestData['scheduled_date']) || isset($requestData['scheduled_time'])) {
            $data['converted_by'] = Auth::id();
        }
        
        if (isset($requestData['location_id']) || isset($requestData['doctor_id'])) {
            $data['updated_by'] = Auth::id();
        }

        return $data;
    }

    /**
     * Update related records (lead, patient)
     */
    protected function updateRelatedRecords($appointment, $requestData)
    {
        // Update lead
        if (isset($requestData['lead_id'])) {
            $lead = Leads::find($requestData['lead_id']);
            if ($lead) {
                $leadData = [];
                
                if (isset($requestData['name'])) $leadData['name'] = $requestData['name'];
                if (isset($requestData['phone'])) $leadData['phone'] = $requestData['phone'];
                if (isset($requestData['gender'])) $leadData['gender'] = $requestData['gender'];
                
                if (!empty($leadData)) {
                    $lead->update($leadData);
                }
            }
        }

        // Update patient
        if ($appointment->patient_id) {
            $patient = Patients::find($appointment->patient_id);
            if ($patient) {
                $patientData = [];
                
                if (isset($requestData['name'])) $patientData['name'] = $requestData['name'];
                if (isset($requestData['phone'])) $patientData['phone'] = $requestData['phone'];
                if (isset($requestData['gender'])) $patientData['gender'] = $requestData['gender'];
                
                if (!empty($patientData)) {
                    $patient->update($patientData);
                }
            }
        }

        // Update all appointments for this patient with new name
        if (isset($requestData['name'])) {
            Appointments::where('patient_id', $appointment->patient_id)
                ->update(['name' => $requestData['name']]);
        }
    }

    /**
     * Log activity for the update with detailed field changes
     */
    protected function logActivity($appointment, $requestData, $oldValues)
    {
        $patient = Patients::find($appointment->patient_id);
        $location = Locations::with('city')->find($appointment->location_id);
        $service = Services::find($appointment->service_id);
        
        $fieldChanges = [];

        // Track service change
        if ($oldValues['service_id'] != $appointment->service_id) {
            $oldService = Services::find($oldValues['service_id']);
            $fieldChanges['Service'] = [
                'old' => $oldService->name ?? 'Unknown',
                'new' => $service->name ?? 'Unknown'
            ];
        }

        // Track doctor change
        if ($oldValues['doctor_id'] != $appointment->doctor_id) {
            $oldDoctor = Patients::find($oldValues['doctor_id']);
            $newDoctor = Patients::find($appointment->doctor_id);
            $fieldChanges['Doctor'] = [
                'old' => $oldDoctor->name ?? 'Unknown',
                'new' => $newDoctor->name ?? 'Unknown'
            ];
        }

        // Track scheduled date change
        if ($oldValues['scheduled_date'] != $appointment->scheduled_date) {
            $fieldChanges['Scheduled Date'] = [
                'old' => Carbon::parse($oldValues['scheduled_date'])->format('d M Y'),
                'new' => Carbon::parse($appointment->scheduled_date)->format('d M Y')
            ];
        }

        // Track scheduled time change
        if ($oldValues['scheduled_time'] != $appointment->scheduled_time) {
            $fieldChanges['Scheduled Time'] = [
                'old' => Carbon::parse($oldValues['scheduled_time'])->format('h:i A'),
                'new' => Carbon::parse($appointment->scheduled_time)->format('h:i A')
            ];
        }

        // Track location change
        if ($oldValues['location_id'] != $appointment->location_id) {
            $oldLocation = Locations::with('city')->find($oldValues['location_id']);
            $oldLocationName = ($oldLocation->city->name ?? '') . ' - ' . ($oldLocation->name ?? '');
            $newLocationName = ($location->city->name ?? '') . ' - ' . ($location->name ?? '');
            
            $fieldChanges['Location'] = [
                'old' => $oldLocationName,
                'new' => $newLocationName
            ];
        }

        // Track consultancy type change
        if (isset($requestData['consultancy_type']) && $oldValues['consultancy_type'] != $appointment->consultancy_type) {
            $fieldChanges['Consultancy Type'] = [
                'old' => ucfirst(str_replace('_', ' ', $oldValues['consultancy_type'])),
                'new' => ucfirst(str_replace('_', ' ', $appointment->consultancy_type))
            ];
        }

        // Track patient info changes
        if (isset($requestData['name']) || isset($requestData['phone']) || isset($requestData['gender'])) {
            if (isset($requestData['name'])) {
                $fieldChanges['Patient Name'] = [
                    'old' => $patient->name ?? 'Unknown',
                    'new' => $requestData['name']
                ];
            }
            if (isset($requestData['phone'])) {
                $fieldChanges['Patient Phone'] = [
                    'old' => $patient->phone ?? 'Unknown',
                    'new' => $requestData['phone']
                ];
            }
            if (isset($requestData['gender'])) {
                $genderMap = ['0' => 'Male', '1' => 'Female', '2' => 'Other'];
                $fieldChanges['Patient Gender'] = [
                    'old' => $genderMap[$patient->gender] ?? 'Unknown',
                    'new' => $genderMap[$requestData['gender']] ?? 'Unknown'
                ];
            }
        }

        // Log the changes if any
        if (!empty($fieldChanges)) {
            ActivityLogger::logAppointmentUpdated($appointment, $patient, $fieldChanges, $location, $service);
        }

        // Also log rescheduling specifically if date/time changed
        if (isset($fieldChanges['Scheduled Date']) || isset($fieldChanges['Scheduled Time'])) {
            ActivityLogger::logAppointmentRescheduled(
                $appointment,
                $patient,
                $oldValues['scheduled_date'],
                $oldValues['scheduled_time'],
                $appointment->scheduled_date,
                $appointment->scheduled_time,
                $location,
                $service
            );
        }

        // General appointment log
        GeneralFunctions::saveAppointmentLogs('updated', 'Consultancy', $appointment);
    }
}
