<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\ResourceHasRota;
use App\Models\ResourceTimeOff;
use App\Models\BusinessClosure;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use App\Models\WorkingDayException;
use App\Models\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

class ConsultancyUpdateService
{
    /**
     * Update consultation with permission-based field handling.
     */
    public function updateConsultation(int $appointmentId, array $requestData): Appointments
    {
        $appointment = Appointments::findOrFail($appointmentId);

        $oldValues = $this->captureOldValues($appointment);

        $permissions = [
            'service' => Gate::allows('consultations.edit.service.after_arrived'),
            'doctor' => Gate::allows('consultations.edit.doctor.after_arrived'),
            'schedule' => Gate::allows('consultations.edit.schedule.after_arrived'),
        ];

        $isArrivedOrConverted = $this->isArrivedOrConverted($appointment);

        if ($isArrivedOrConverted) {
            $this->validateArrivedConsultationUpdate($appointment, $requestData, $permissions);
        } else {
            $this->validateNormalConsultationUpdate($appointment, $requestData);
        }

        $updateData = $this->prepareUpdateData($appointment, $requestData);
        $appointment->update($updateData);
        $this->updateRelatedRecords($appointment, $requestData);
        $this->logActivity($appointment->fresh(), $requestData, $oldValues);

        return $appointment->fresh(['doctor', 'service', 'location', 'patient', 'lead']);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    protected function validateArrivedConsultationUpdate(
        Appointments $appointment,
        array $requestData,
        array $permissions,
    ): void {
        $locationId = (int) ($requestData['location_id'] ?? $appointment->location_id);
        $newServiceId = $requestData['treatment_id'] ?? $requestData['service_id'] ?? null;

        if ($newServiceId && $appointment->service_id != $newServiceId) {
            if (!$permissions['service']) {
                throw AppointmentException::unauthorized('You do not have permission to change the service.');
            }
            $this->validateDoctorHasServiceForConsultancy((int) $appointment->doctor_id, (int) $newServiceId, $locationId);
        }

        $newDoctorId = $requestData['doctor_id'] ?? null;
        if ($newDoctorId && $appointment->doctor_id != $newDoctorId) {
            if (!$permissions['doctor']) {
                throw AppointmentException::unauthorized('You do not have permission to change the doctor.');
            }
            $this->validateDoctorHasServiceForConsultancy((int) $newDoctorId, (int) $appointment->service_id, $locationId);

            $scheduledDate = $requestData['scheduled_date'] ?? (string) $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? (string) $appointment->scheduled_time;
            $this->validateDoctorRota((int) $newDoctorId, $scheduledDate, $scheduledTime, $locationId);
        }

        $scheduleChanging = $this->isScheduleChanging($appointment, $requestData);

        if ($scheduleChanging) {
            if (!$permissions['schedule']) {
                throw AppointmentException::unauthorized('You do not have permission to change the schedule.');
            }

            $doctorId = (int) ($requestData['doctor_id'] ?? $appointment->doctor_id);
            $scheduledDate = $requestData['scheduled_date'] ?? (string) $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? (string) $appointment->scheduled_time;
            $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId);
        }
    }

    protected function validateNormalConsultationUpdate(Appointments $appointment, array $requestData): void
    {
        // Pre-arrival edits are unrestricted at the field level — anyone
        // with `consultations.edit` can change service/doctor/schedule.
        // The per-field `consultations.edit.{field}.after_arrived` perms
        // only kick in once the consultation reaches the arrived/converted
        // states (see validateArrivedConsultationUpdate above).
        $doctorId = (int) ($requestData['doctor_id'] ?? $appointment->doctor_id);
        $locationId = (int) ($requestData['location_id'] ?? $appointment->location_id);
        $serviceId = (int) ($requestData['treatment_id'] ?? $requestData['service_id'] ?? $appointment->service_id);

        $this->validateDoctorHasServiceForConsultancy($doctorId, $serviceId, $locationId);

        $scheduledDate = $requestData['scheduled_date'] ?? (string) $appointment->scheduled_date;
        $scheduledTime = $requestData['scheduled_time'] ?? (string) $appointment->scheduled_time;
        $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId);
    }

    protected function validateDoctorRota(
        int $doctorId,
        ?string $scheduledDate,
        ?string $scheduledTime,
        int $locationId,
    ): void {
        $resourceDoctorTypeId = (int) Config::get('constants.resource_doctor_type_id');
        $accountId = Auth::user()->account_id;

        $resource = Resources::where([
            'external_id' => $doctorId,
            'resource_type_id' => $resourceDoctorTypeId,
            'account_id' => $accountId,
        ])->first();

        if (!$resource) {
            throw AppointmentException::invalidData('Doctor resource not found.');
        }

        $date = Carbon::parse($scheduledDate)->format('Y-m-d');

        $this->validateBusinessClosure($accountId, $locationId, $date);
        $this->validateWorkingDay($accountId, $date);

        $rotaDays = ResourceHasRota::join('resource_has_rota_days', 'resource_has_rota_days.resource_has_rota_id', '=', 'resource_has_rota.id')
            ->whereDate('resource_has_rota_days.date', $date)
            ->where('resource_has_rota.resource_id', $resource->id)
            ->where('resource_has_rota_days.active', 1)
            ->select('resource_has_rota_days.*')
            ->get();

        if ($rotaDays->isEmpty()) {
            throw AppointmentException::invalidData('Doctor does not have rota availability for the selected date.');
        }

        $this->validateTimeWithinShifts($scheduledTime, $rotaDays);
        $this->validateNoTimeOffConflict($resource->id, $accountId, $locationId, $date, $scheduledTime);
    }

    protected function validateBusinessClosure(int $accountId, int $locationId, string $date): void
    {
        $allCentresLocationId = (int) config('constants.all_centres_location_id');

        $closure = BusinessClosure::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId, $allCentresLocationId) {
                $query->whereHas('locations', fn ($q) => $q->where('locations.id', $locationId))
                    ->orWhereHas('locations', fn ($q) => $q->where('locations.id', $allCentresLocationId))
                    ->orWhereDoesntHave('locations');
            })
            ->first();

        if ($closure) {
            $formattedDate = Carbon::parse($date)->format('d M, Y');
            throw AppointmentException::invalidData(
                "Cannot schedule appointment on {$formattedDate}. Business is closed: " . ($closure->title ?? 'Business Closed')
            );
        }
    }

    protected function validateWorkingDay(int $accountId, string $date): void
    {
        $workingDays = $this->getBusinessWorkingDays($accountId);
        $isWorkingDay = WorkingDayException::isWorkingDay($accountId, $date, $workingDays);

        if (!$isWorkingDay) {
            $formattedDate = Carbon::parse($date)->format('l, d M Y');
            throw AppointmentException::invalidData(
                "Cannot schedule appointment on {$formattedDate}. Business is closed on this day."
            );
        }
    }

    private function getBusinessWorkingDays(int $accountId): array
    {
        $setting = Settings::where('account_id', $accountId)
            ->where('slug', 'business_working_days')
            ->first();

        if ($setting?->data) {
            return json_decode($setting->data, true);
        }

        return [
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
            'sunday' => false,
        ];
    }

    protected function validateDoctorHasServiceForConsultancy(int $doctorId, int $serviceId, int $locationId): void
    {
        $service = Services::find($serviceId);
        if (!$service) {
            throw AppointmentException::invalidData('Service not found.');
        }

        // Check "all services" allocation
        $hasAllServices = \App\Models\DoctorHasLocations::whereHas('service', fn ($q) => $q->where('slug', 'all'))
            ->where('user_id', $doctorId)
            ->where('location_id', $locationId)
            ->where('is_allocated', 1)
            ->exists();

        if ($hasAllServices) {
            return;
        }

        // Check exact service allocation
        $hasService = \App\Models\DoctorHasLocations::where([
            'user_id' => $doctorId,
            'location_id' => $locationId,
            'service_id' => $serviceId,
            'is_allocated' => 1,
        ])->exists();

        if ($hasService) {
            return;
        }

        // Check parent service allocation
        if ($service->parent_id && $service->parent_id !== 0) {
            $hasParentService = \App\Models\DoctorHasLocations::where([
                'user_id' => $doctorId,
                'location_id' => $locationId,
                'service_id' => $service->parent_id,
                'is_allocated' => 1,
            ])->exists();

            if ($hasParentService) {
                return;
            }
        }

        throw AppointmentException::invalidData('This doctor does not have the selected service allocated at this location.');
    }

    // =========================================================================
    // Data preparation
    // =========================================================================

    protected function prepareUpdateData(Appointments $appointment, array $requestData): array
    {
        $data = ['updated_at' => Filters::getCurrentTimeStamp()];

        // Schedule fields
        $data['scheduled_date'] = isset($requestData['scheduled_date']) && $requestData['scheduled_date']
            ? Carbon::parse($requestData['scheduled_date'])->format('Y-m-d')
            : $appointment->scheduled_date;

        $data['scheduled_time'] = isset($requestData['scheduled_time']) && $requestData['scheduled_time']
            ? Carbon::parse($requestData['scheduled_time'])->format('H:i:s')
            : $appointment->scheduled_time;

        $data['location_id'] = $requestData['location_id'] ?? $appointment->location_id;
        $data['doctor_id'] = $requestData['doctor_id'] ?? $appointment->doctor_id;

        // Service (treatment_id takes priority)
        if (!empty($requestData['treatment_id'])) {
            $data['service_id'] = $requestData['treatment_id'];
        } elseif (!empty($requestData['service_id'])) {
            $data['service_id'] = $requestData['service_id'];
        }

        // Consultancy type
        if (isset($requestData['consultancy_type'])) {
            $data['consultancy_type'] = $requestData['consultancy_type'];
        }

        // Derive city/region from location
        $location = Locations::find($data['location_id']);
        if ($location) {
            $data['city_id'] = $location->city_id;
            $data['region_id'] = $location->region_id;
        }

        // Resource and rota day
        $this->attachResourceData($data, $appointment);

        // Track changes for messaging/auditing
        $this->applyChangeTracking($data, $appointment, $requestData);

        // Appointment status sync
        $this->syncAppointmentStatus($data, $appointment, $requestData);

        return $data;
    }

    protected function updateRelatedRecords(Appointments $appointment, array $requestData): void
    {
        $fieldsToSync = ['name', 'phone', 'gender'];
        $updateData = array_intersect_key($requestData, array_flip($fieldsToSync));

        if (empty($updateData)) {
            return;
        }

        // Update lead
        if (isset($requestData['lead_id'])) {
            Leads::where('id', $requestData['lead_id'])->update($updateData);
        }

        // Update patient
        if ($appointment->patient_id) {
            Patients::where('id', $appointment->patient_id)->update($updateData);
        }

        // Sync name across all patient appointments
        if (isset($requestData['name'])) {
            Appointments::where('patient_id', $appointment->patient_id)
                ->update(['name' => $requestData['name']]);
        }
    }

    // =========================================================================
    // Activity logging
    // =========================================================================

    protected function logActivity(Appointments $appointment, array $requestData, array $oldValues): void
    {
        $patient = Patients::find($appointment->patient_id);
        $location = Locations::with('city')->find($appointment->location_id);
        $service = Services::find($appointment->service_id);

        $fieldChanges = $this->detectFieldChanges($appointment, $requestData, $oldValues, $patient);

        if (!empty($fieldChanges)) {
            ActivityLogger::logAppointmentUpdated($appointment, $patient, $fieldChanges, $location, $service);
        }

        if (isset($fieldChanges['Scheduled Date']) || isset($fieldChanges['Scheduled Time'])) {
            ActivityLogger::logAppointmentRescheduled(
                $appointment,
                $patient,
                $oldValues['scheduled_date'],
                $oldValues['scheduled_time'],
                $appointment->scheduled_date,
                $appointment->scheduled_time,
                $location,
                $service,
            );
        }

        ActivityLogger::saveAppointmentLogs('updated', 'Consultancy', $appointment);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function captureOldValues(Appointments $appointment): array
    {
        return [
            'service_id' => $appointment->service_id,
            'doctor_id' => $appointment->doctor_id,
            'scheduled_date' => $appointment->scheduled_date,
            'scheduled_time' => $appointment->scheduled_time,
            'location_id' => $appointment->location_id,
            'city_id' => $appointment->city_id,
            'consultancy_type' => $appointment->consultancy_type,
        ];
    }

    private function isArrivedOrConverted(Appointments $appointment): bool
    {
        $arrivedStatus = AppointmentStatuses::where([
            'account_id' => Auth::user()->account_id,
            'is_arrived' => 1,
        ])->first();

        $convertedStatus = AppointmentStatuses::where([
            'account_id' => Auth::user()->account_id,
            'is_converted' => 1,
        ])->first();

        $statusIds = array_filter([
            $arrivedStatus?->id,
            $convertedStatus?->id,
        ]);

        return in_array($appointment->base_appointment_status_id, $statusIds, true)
            || in_array($appointment->appointment_status_id, $statusIds, true);
    }

    private function isScheduleChanging(Appointments $appointment, array $requestData): bool
    {
        $dateChanging = isset($requestData['scheduled_date'])
            && $requestData['scheduled_date'] != $appointment->scheduled_date;
        $timeChanging = isset($requestData['scheduled_time'])
            && $requestData['scheduled_time'] != $appointment->scheduled_time;

        return $dateChanging || $timeChanging;
    }

    private function validateTimeWithinShifts(string $scheduledTime, $rotaDays): void
    {
        $scheduledMinutes = Carbon::parse($scheduledTime)->hour * 60 + Carbon::parse($scheduledTime)->minute;
        $allShiftRanges = [];

        foreach ($rotaDays as $rotaDay) {
            $start = Carbon::parse($rotaDay->start_time);
            $end = Carbon::parse($rotaDay->end_time);
            $allShiftRanges[] = $start->format('h:i A') . ' - ' . $end->format('h:i A');

            $startMinutes = $start->hour * 60 + $start->minute;
            $endMinutes = $end->hour * 60 + $end->minute;

            // Handle overnight shifts (e.g., 8PM to 12AM)
            $isWithinShift = $endMinutes <= $startMinutes
                ? ($scheduledMinutes >= $startMinutes || $scheduledMinutes <= $endMinutes)
                : ($scheduledMinutes >= $startMinutes && $scheduledMinutes <= $endMinutes);

            if ($isWithinShift) {
                return;
            }
        }

        throw AppointmentException::invalidData(
            'Scheduled time is outside doctor\'s rota hours (' . implode(', ', $allShiftRanges) . ').'
        );
    }

    private function validateNoTimeOffConflict(
        int $resourceId,
        int $accountId,
        int $locationId,
        string $date,
        string $scheduledTime,
    ): void {
        $timeOffs = ResourceTimeOff::where('resource_id', $resourceId)
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->where(function ($query) use ($date) {
                $query->whereDate('start_date', $date)
                    ->orWhere(function ($q) use ($date) {
                        $q->where('is_repeat', 1)
                            ->whereDate('start_date', '<=', $date)
                            ->where(fn ($rq) => $rq->whereNull('repeat_until')->orWhereDate('repeat_until', '>=', $date));
                    });
            })
            ->get();

        $scheduledFormatted = Carbon::parse($scheduledTime)->format('H:i:s');

        foreach ($timeOffs as $timeOff) {
            $offStart = Carbon::parse($timeOff->start_time)->format('H:i:s');
            $offEnd = Carbon::parse($timeOff->end_time)->format('H:i:s');

            if ($scheduledFormatted >= $offStart && $scheduledFormatted < $offEnd) {
                throw AppointmentException::invalidData(
                    'Doctor has time off during this time slot ('
                    . Carbon::parse($timeOff->start_time)->format('h:i A') . ' - '
                    . Carbon::parse($timeOff->end_time)->format('h:i A') . ').'
                );
            }
        }
    }

    private function attachResourceData(array &$data, Appointments $appointment): void
    {
        $resourceDoctorTypeId = (int) Config::get('constants.resource_doctor_type_id');

        $resource = Resources::where([
            'external_id' => $data['doctor_id'],
            'resource_type_id' => $resourceDoctorTypeId,
            'account_id' => Auth::user()->account_id,
        ])->first();

        if (!$resource) {
            return;
        }

        $rotaDay = ResourceHasRotaDays::getSingleDayRotaWithResourceID(
            $resource->id,
            $data['scheduled_date'],
            Auth::user()->account_id,
            $data['location_id'],
        );

        if (count($rotaDay)) {
            $data['resource_id'] = $resource->id;
            $data['resource_has_rota_day_id'] = $rotaDay['id'];
        }
    }

    private function applyChangeTracking(array &$data, Appointments $appointment, array $requestData): void
    {
        $dateChanged = isset($requestData['scheduled_date'])
            && Carbon::parse($appointment->scheduled_date)->format('Y-m-d') !== Carbon::parse($data['scheduled_date'])->format('Y-m-d');

        $timeChanged = isset($requestData['scheduled_time'])
            && Carbon::parse($appointment->scheduled_time)->format('H:i:s') !== Carbon::parse($data['scheduled_time'])->format('H:i:s');

        if ($dateChanged) {
            $data['converted_by'] = Auth::id();
        }

        if (($dateChanged || $timeChanged)) {
            $pendingStatusId = (int) config('constants.appointment_status_pending', 1);
            if ($appointment->base_appointment_status_id == $pendingStatusId) {
                $data['send_message'] = 1;
            }
        }

        if (isset($requestData['location_id']) || isset($requestData['doctor_id'])) {
            $data['updated_by'] = Auth::id();
        }
    }

    private function syncAppointmentStatus(array &$data, Appointments $appointment, array $requestData): void
    {
        if (isset($requestData['appointment_status_id'])) {
            $data['appointment_status_id'] = $requestData['appointment_status_id'];
            $data['base_appointment_status_id'] = $requestData['base_appointment_status_id']
                ?? $requestData['appointment_status_id'];
        } elseif (!$appointment->base_appointment_status_id && $appointment->appointment_status_id) {
            $data['base_appointment_status_id'] = $appointment->appointment_status_id;
        }
    }

    private function detectFieldChanges(
        Appointments $appointment,
        array $requestData,
        array $oldValues,
        ?Patients $patient,
    ): array {
        $changes = [];

        if ($oldValues['service_id'] != $appointment->service_id) {
            $oldService = Services::find($oldValues['service_id']);
            $newService = Services::find($appointment->service_id);
            $changes['Service'] = [
                'old' => $oldService?->name ?? 'Unknown',
                'new' => $newService?->name ?? 'Unknown',
            ];
        }

        if ($oldValues['doctor_id'] != $appointment->doctor_id) {
            // Doctor lookups bypass the Patients global scope (user_type_id = 3).
            $oldDoctor = User::find($oldValues['doctor_id']);
            $newDoctor = User::find($appointment->doctor_id);
            $changes['Doctor'] = [
                'old' => $oldDoctor?->name ?? 'Unknown',
                'new' => $newDoctor?->name ?? 'Unknown',
            ];
        }

        if ($oldValues['scheduled_date'] != $appointment->scheduled_date) {
            $changes['Scheduled Date'] = [
                'old' => Carbon::parse($oldValues['scheduled_date'])->format('d M Y'),
                'new' => Carbon::parse($appointment->scheduled_date)->format('d M Y'),
            ];
        }

        if ($oldValues['scheduled_time'] != $appointment->scheduled_time) {
            $changes['Scheduled Time'] = [
                'old' => Carbon::parse($oldValues['scheduled_time'])->format('h:i A'),
                'new' => Carbon::parse($appointment->scheduled_time)->format('h:i A'),
            ];
        }

        if ($oldValues['location_id'] != $appointment->location_id) {
            $oldLocation = Locations::with('city')->find($oldValues['location_id']);
            $newLocation = Locations::with('city')->find($appointment->location_id);
            $changes['Location'] = [
                'old' => ($oldLocation?->city?->name ?? '') . ' - ' . ($oldLocation?->name ?? ''),
                'new' => ($newLocation?->city?->name ?? '') . ' - ' . ($newLocation?->name ?? ''),
            ];
        }

        if (isset($requestData['consultancy_type']) && $oldValues['consultancy_type'] != $appointment->consultancy_type) {
            $changes['Consultancy Type'] = [
                'old' => ucfirst(str_replace('_', ' ', $oldValues['consultancy_type'] ?? '')),
                'new' => ucfirst(str_replace('_', ' ', $appointment->consultancy_type ?? '')),
            ];
        }

        $genderMap = ['0' => 'Male', '1' => 'Female', '2' => 'Other'];

        if (isset($requestData['name'])) {
            $changes['Patient Name'] = ['old' => $patient?->name ?? 'Unknown', 'new' => $requestData['name']];
        }
        if (isset($requestData['phone'])) {
            $changes['Patient Phone'] = ['old' => $patient?->phone ?? 'Unknown', 'new' => $requestData['phone']];
        }
        if (isset($requestData['gender'])) {
            $changes['Patient Gender'] = [
                'old' => $genderMap[$patient?->gender ?? '0'] ?? 'Unknown',
                'new' => $genderMap[$requestData['gender']] ?? 'Unknown',
            ];
        }

        return $changes;
    }
}
