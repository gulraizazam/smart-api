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
        
        // Get permissions for consultations
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

        // Return appointment with relationships loaded
        return $appointment->fresh(['doctor', 'service', 'location', 'patient', 'lead']);
    }

    /**
     * Validate update for arrived/converted consultation
     */
    protected function validateArrivedConsultationUpdate($appointment, $requestData, $permissions)
    {
        $locationId = $requestData['location_id'] ?? $appointment->location_id;
        
        // Determine service ID for consultation
        $newServiceId = $requestData['treatment_id'] ?? $requestData['service_id'] ?? null;
        
        // Check if service is changing
        if ($newServiceId && $appointment->service_id != $newServiceId) {
            if (!$permissions['service']) {
                throw AppointmentException::unauthorized('You do not have permission to change the service.');
            }
            
            // Validate current doctor has new service
            $this->validateDoctorHasServiceForConsultancy($appointment->doctor_id, $newServiceId, $locationId);
        }

        // Check if doctor is changing
        $newDoctorId = $requestData['doctor_id'] ?? null;
        if ($newDoctorId && $appointment->doctor_id != $newDoctorId) {
            if (!$permissions['doctor']) {
                throw AppointmentException::unauthorized('You do not have permission to change the doctor.');
            }
            
            // Validate new doctor has current service
            $this->validateDoctorHasServiceForConsultancy($newDoctorId, $appointment->service_id, $locationId);
            
            // Validate new doctor has rota availability
            $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
            $this->validateDoctorRota($newDoctorId, $scheduledDate, $scheduledTime, $locationId);
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
            $locationId = $requestData['location_id'] ?? $appointment->location_id;
            $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId);
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
        $locationId = $requestData['location_id'] ?? $appointment->location_id;
        
        // CONSULTANCY: check treatment_id in doctor_has_locations
        $serviceId = $requestData['treatment_id'] ?? $requestData['service_id'] ?? $appointment->service_id;
        $this->validateDoctorHasServiceForConsultancy($doctorId, $serviceId, $locationId);
        
        // Validate doctor has rota availability
        $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
        $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
        $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId);
    }

    /**
     * Validate doctor has rota availability
     * Uses same logic as Resources::getResourceRotaHasDay() which works for appointment creation
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

        $date = Carbon::parse($scheduledDate)->format('Y-m-d');
        $accountId = Auth::user()->account_id;

        // Check for business closures - prevent scheduling on closed days
        $this->validateBusinessClosure($accountId, $locationId, $date);

        // Check for non-working days - prevent scheduling on closed working days
        $this->validateWorkingDay($accountId, $date);

        // Use same approach as Resources::getResourceRotaHasDay() - directly check rota_days by date
        // This doesn't rely on start/end columns of resource_has_rota which may be inconsistent
        // Get ALL shifts for the doctor on this date (doctor may have multiple shifts)
        $rotaDays = \App\Models\ResourceHasRota::join('resource_has_rota_days', 'resource_has_rota_days.resource_has_rota_id', '=', 'resource_has_rota.id')
            ->whereDate('resource_has_rota_days.date', $date)
            ->where('resource_has_rota.resource_id', $resource->id)
            ->where('resource_has_rota_days.active', 1)
            ->select('resource_has_rota_days.*')
            ->get();

        if ($rotaDays->isEmpty()) {
            throw AppointmentException::invalidData('Doctor does not have rota availability for the selected date.');
        }

        // Validate scheduled time is within ANY of the rota shifts
        // Compare using time-only (H:i) to avoid date component issues with Carbon::parse on time strings
        $scheduledMinutes = Carbon::parse($scheduledTime)->hour * 60 + Carbon::parse($scheduledTime)->minute;
        $isWithinAnyShift = false;
        $allShiftRanges = [];

        foreach ($rotaDays as $rotaDay) {
            $rotaStartTime = Carbon::parse($rotaDay->start_time);
            $rotaEndTime = Carbon::parse($rotaDay->end_time);
            $allShiftRanges[] = $rotaStartTime->format('h:i A') . ' - ' . $rotaEndTime->format('h:i A');

            $startMinutes = $rotaStartTime->hour * 60 + $rotaStartTime->minute;
            $endMinutes = $rotaEndTime->hour * 60 + $rotaEndTime->minute;

            // Handle overnight shifts (e.g., 8PM to 12AM) where end <= start in minutes
            if ($endMinutes <= $startMinutes) {
                // Overnight shift: valid if time >= start OR time <= end
                if ($scheduledMinutes >= $startMinutes || $scheduledMinutes <= $endMinutes) {
                    $isWithinAnyShift = true;
                    break;
                }
            } else {
                // Normal shift: valid if time >= start AND time <= end
                if ($scheduledMinutes >= $startMinutes && $scheduledMinutes <= $endMinutes) {
                    $isWithinAnyShift = true;
                    break;
                }
            }
        }

        if (!$isWithinAnyShift) {
            throw AppointmentException::invalidData('Scheduled time is outside doctor\'s rota hours (' . implode(', ', $allShiftRanges) . ').');
        }

        // Check for time offs - block scheduling during doctor's time off
        $timeOffs = \App\Models\ResourceTimeOff::where('resource_id', $resource->id)
            ->where('account_id', Auth::user()->account_id)
            ->where('location_id', $locationId)
            ->where(function ($query) use ($date) {
                $query->whereDate('start_date', $date)
                    ->orWhere(function ($q) use ($date) {
                        // Check repeating time offs
                        $q->where('is_repeat', 1)
                            ->whereDate('start_date', '<=', $date)
                            ->where(function ($rq) use ($date) {
                                $rq->whereNull('repeat_until')
                                    ->orWhereDate('repeat_until', '>=', $date);
                            });
                    });
            })
            ->get();

        $scheduledTimeFormatted = Carbon::parse($scheduledTime)->format('H:i:s');

        foreach ($timeOffs as $timeOff) {
            $timeOffStart = Carbon::parse($timeOff->start_time)->format('H:i:s');
            $timeOffEnd = Carbon::parse($timeOff->end_time)->format('H:i:s');

            if ($scheduledTimeFormatted >= $timeOffStart && $scheduledTimeFormatted < $timeOffEnd) {
                throw AppointmentException::invalidData('Doctor has time off during this time slot (' . Carbon::parse($timeOff->start_time)->format('h:i A') . ' - ' . Carbon::parse($timeOff->end_time)->format('h:i A') . ').');
            }
        }
    }

    /**
     * Validate business is not closed on the scheduled date
     */
    protected function validateBusinessClosure($accountId, $locationId, $date)
    {
        // "All Centres" location ID - closures with this location apply to all locations
        $allCentresId = 30;

        $closure = \App\Models\BusinessClosure::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId, $allCentresId) {
                // Match closures that have this specific location
                $query->whereHas('locations', function ($subQ) use ($locationId) {
                    $subQ->where('locations.id', $locationId);
                })
                // OR closures that have "All Centres" assigned
                ->orWhereHas('locations', function ($subQ) use ($allCentresId) {
                    $subQ->where('locations.id', $allCentresId);
                })
                // OR closures that have no locations assigned (applies to all)
                ->orWhereDoesntHave('locations');
            })
            ->first();

        if ($closure) {
            throw AppointmentException::invalidData('Cannot schedule appointment on ' . Carbon::parse($date)->format('d M, Y') . '. Business is closed: ' . ($closure->title ?? 'Business Closed'));
        }
    }

    /**
     * Validate the scheduled date is a working day (considering exceptions)
     */
    protected function validateWorkingDay($accountId, $date)
    {
        $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($accountId);
        
        // Check if there's an exception for this specific date
        $isWorkingDay = \App\Models\WorkingDayException::isWorkingDay($accountId, $date, $workingDays);
        
        if (!$isWorkingDay) {
            $dayOfWeek = Carbon::parse($date)->dayOfWeek;
            $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $dayName = $dayNames[$dayOfWeek];
            throw AppointmentException::invalidData('Cannot schedule appointment on ' . Carbon::parse($date)->format('l, d M Y') . '. Business is closed on this day.');
        }
    }

    /**
     * Validate doctor has service allocated at location - FOR CONSULTANCY ONLY
     * @param int $doctorId
     * @param int $serviceId - The service being booked
     * @param int $locationId
     */
    protected function validateDoctorHasServiceForConsultancy($doctorId, $serviceId, $locationId)
    {
        $service = Services::find($serviceId);
        if (!$service) {
            throw AppointmentException::invalidData('Service not found.');
        }

        // Check if doctor has "all services" assigned at this location
        $hasAllServices = \DB::table('doctor_has_locations')
            ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
            ->where('doctor_has_locations.user_id', $doctorId)
            ->where('doctor_has_locations.location_id', $locationId)
            ->where('services.slug', 'all')
            ->where('doctor_has_locations.is_allocated', 1)
            ->exists();

        if ($hasAllServices) {
            return;
        }

        // Check if exact service is assigned
        $hasService = \DB::table('doctor_has_locations')
            ->where('user_id', $doctorId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('is_allocated', 1)
            ->exists();

        if ($hasService) {
            return;
        }

        // Check if service's parent is assigned (only if service has a parent)
        if ($service->parent_id && $service->parent_id != 0) {
            $hasParentService = \DB::table('doctor_has_locations')
                ->where('user_id', $doctorId)
                ->where('location_id', $locationId)
                ->where('service_id', $service->parent_id)
                ->where('is_allocated', 1)
                ->exists();
            
            if ($hasParentService) {
                return;
            }
        }

        throw AppointmentException::invalidData('This doctor does not have the selected service allocated at this location.');
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

        // Service - for consultations, use treatment_id or service_id
        if (isset($requestData['treatment_id']) && $requestData['treatment_id']) {
            $data['service_id'] = $requestData['treatment_id'];
        } elseif (isset($requestData['service_id']) && $requestData['service_id']) {
            $data['service_id'] = $requestData['service_id'];
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

        // Track who updated - compare dates and times in same format
        $dateChanged = false;
        $timeChanged = false;
        
        if (isset($requestData['scheduled_date'])) {
            $oldDateFormatted = \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
            $newDateFormatted = \Carbon\Carbon::parse($data['scheduled_date'])->format('Y-m-d');
            $dateChanged = ($oldDateFormatted != $newDateFormatted);
        }
        
        if (isset($requestData['scheduled_time'])) {
            $oldTimeFormatted = \Carbon\Carbon::parse($appointment->scheduled_time)->format('H:i:s');
            $newTimeFormatted = \Carbon\Carbon::parse($data['scheduled_time'])->format('H:i:s');
            $timeChanged = ($oldTimeFormatted != $newTimeFormatted);
        }
        
        // Only update converted_by if scheduled_date changed (not just time)
        if ($dateChanged) {
            $data['converted_by'] = Auth::id();
        }
        
        // If consultation is rescheduled (date or time changed) and status is pending, set send_message to 1 for SMS
        if ($dateChanged || $timeChanged) {
            $pendingStatusId = config('constants.appointment_status_pending', 1);
            
            if ($appointment->base_appointment_status_id == $pendingStatusId) {
                $data['send_message'] = 1;
            }
        }
        
        if (isset($requestData['location_id']) || isset($requestData['doctor_id'])) {
            $data['updated_by'] = Auth::id();
        }

        // Ensure base_appointment_status_id is set if appointment_status_id is provided
        if (isset($requestData['appointment_status_id'])) {
            $data['appointment_status_id'] = $requestData['appointment_status_id'];
            // Set base_appointment_status_id to match appointment_status_id if not explicitly provided
            if (!isset($requestData['base_appointment_status_id'])) {
                $data['base_appointment_status_id'] = $requestData['appointment_status_id'];
            } else {
                $data['base_appointment_status_id'] = $requestData['base_appointment_status_id'];
            }
        } elseif (!$appointment->base_appointment_status_id && $appointment->appointment_status_id) {
            // If base_appointment_status_id is NULL but appointment_status_id exists, set it
            $data['base_appointment_status_id'] = $appointment->appointment_status_id;
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
