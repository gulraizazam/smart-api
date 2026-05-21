<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Exceptions\TreatmentException;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\BusinessClosure;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\ResourceTimeOff;
use App\Models\Services;
use App\Models\Patients;
use App\Models\User;
use App\Models\WorkingDayException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

final class TreatmentUpdateService
{
    // ──────────────────────────────────────────────────
    //  Public entry point
    // ──────────────────────────────────────────────────

    /**
     * Update treatment with permission-based field handling.
     *
     * @return Appointments The fresh appointment with relationships.
     */
    public function updateTreatment(int $appointmentId, array $requestData): Appointments
    {
        $appointment = Appointments::find($appointmentId)
            ?? throw TreatmentException::notFound();

        $oldValues = $this->captureOldValues($appointment);

        $isArrivedOrConverted = $this->isArrivedOrConverted($appointment);

        $permissions = [
            'service'  => Gate::allows('treatments.edit.service.after_arrived'),
            'doctor'   => Gate::allows('treatments.edit.doctor.after_arrived'),
            'schedule' => Gate::allows('treatments.edit.schedule.after_arrived'),
        ];

        if ($isArrivedOrConverted) {
            $this->validateArrivedTreatmentUpdate($appointment, $requestData, $permissions);
        } else {
            $this->validateNormalTreatmentUpdate($appointment, $requestData);
        }

        $updateData = $this->prepareUpdateData($appointment, $requestData);

        $appointment->update($updateData);

        $this->updateRelatedRecords($appointment, $requestData);

        $this->logActivity($appointment->fresh(), $requestData, $oldValues);

        return $appointment->fresh(['doctor', 'service', 'location', 'patient', 'lead']);
    }

    // ──────────────────────────────────────────────────
    //  Validation — arrived/converted treatment
    // ──────────────────────────────────────────────────

    private function validateArrivedTreatmentUpdate(
        Appointments $appointment,
        array $requestData,
        array $permissions,
    ): void {
        $locationId = (int) ($requestData['location_id'] ?? $appointment->location_id);
        $serviceIdForValidation = isset($requestData['service_id'])
            ? (int) $requestData['service_id']
            : (int) $appointment->service_id;

        // Service change
        $newServiceId = $requestData['treatment_service_id'] ?? null;
        if ($newServiceId && $appointment->service_id != $newServiceId) {
            if (!$permissions['service']) {
                throw TreatmentException::permissionDenied('service');
            }
            $this->validateDoctorHasService((int) $appointment->doctor_id, $locationId, $serviceIdForValidation);
        }

        // Doctor change
        $newDoctorId = $requestData['doctor_id'] ?? null;
        if ($newDoctorId && $appointment->doctor_id != $newDoctorId) {
            if (!$permissions['doctor']) {
                throw TreatmentException::permissionDenied('doctor');
            }

            $this->validateDoctorHasService((int) $newDoctorId, $locationId, $serviceIdForValidation);

            $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
            $this->validateDoctorRota((int) $newDoctorId, $scheduledDate, $scheduledTime, $locationId);
        }

        // Schedule change
        $scheduleChanging = $this->isScheduleChanging($appointment, $requestData);
        if ($scheduleChanging) {
            if (!$permissions['schedule']) {
                throw TreatmentException::permissionDenied('schedule');
            }

            $doctorId      = (int) ($requestData['doctor_id'] ?? $appointment->doctor_id);
            $scheduledDate = $requestData['scheduled_date'] ?? $appointment->scheduled_date;
            $scheduledTime = $requestData['scheduled_time'] ?? $appointment->scheduled_time;
            $this->validateDoctorRota($doctorId, $scheduledDate, $scheduledTime, $locationId);
        }
    }

    // ──────────────────────────────────────────────────
    //  Validation — normal treatment
    // ──────────────────────────────────────────────────

    private function validateNormalTreatmentUpdate(Appointments $appointment, array $requestData): void
    {
        // Pre-arrival edits are unrestricted at the field level — anyone
        // with `treatments.edit` can change service/doctor/schedule. The
        // per-field `treatments.edit.{field}.after_arrived` perms only
        // kick in once the treatment reaches the arrived/converted
        // states (see validateArrivedTreatmentUpdate above).
        $doctorId   = (int) ($requestData['doctor_id'] ?? $appointment->doctor_id);
        $locationId = (int) ($requestData['location_id'] ?? $appointment->location_id);
        $serviceId  = isset($requestData['service_id'])
            ? (int) $requestData['service_id']
            : (int) $appointment->service_id;

        $this->validateDoctorHasService($doctorId, $locationId, $serviceId);

        $doctorChanging   = isset($requestData['doctor_id']) && $requestData['doctor_id'] != $appointment->doctor_id;
        $scheduleChanging = $this->isScheduleChanging($appointment, $requestData);

        if ($doctorChanging || $scheduleChanging) {
            $newDate = isset($requestData['scheduled_date'])
                ? Carbon::parse($requestData['scheduled_date'])->format('Y-m-d')
                : Carbon::parse($appointment->scheduled_date)->format('Y-m-d');

            $newTime = isset($requestData['scheduled_time'])
                ? Carbon::parse($requestData['scheduled_time'])->format('H:i')
                : Carbon::parse($appointment->scheduled_time)->format('H:i');

            $this->validateDoctorRota($doctorId, $newDate, $newTime, $locationId);
        }
    }

    // ──────────────────────────────────────────────────
    //  Rota / Business validation
    // ──────────────────────────────────────────────────

    private function validateDoctorRota(
        int $doctorId,
        mixed $scheduledDate,
        mixed $scheduledTime,
        int $locationId,
    ): void {
        $accountId = (int) Auth::user()->account_id;

        $resource = Resources::where([
            'external_id'      => $doctorId,
            'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
            'account_id'       => $accountId,
        ])->first()
            ?? throw TreatmentException::invalidData('Doctor resource not found.');

        $date = Carbon::parse($scheduledDate)->format('Y-m-d');

        $this->validateBusinessClosure($accountId, $locationId, $date);
        $this->validateWorkingDay($accountId, $date);

        $rotaDays = ResourceHasRota::join(
            'resource_has_rota_days',
            'resource_has_rota_days.resource_has_rota_id',
            '=',
            'resource_has_rota.id',
        )
            ->whereDate('resource_has_rota_days.date', $date)
            ->where('resource_has_rota.resource_id', $resource->id)
            ->where('resource_has_rota_days.active', 1)
            ->select('resource_has_rota_days.*')
            ->get();

        if ($rotaDays->isEmpty()) {
            throw TreatmentException::doctorUnavailable('Doctor does not have rota availability for the selected date.');
        }

        $this->validateTimeWithinShifts($scheduledTime, $rotaDays);
        $this->validateNoTimeOffConflict($resource->id, $accountId, $locationId, $date, $scheduledTime);
    }

    private function validateTimeWithinShifts(mixed $scheduledTime, mixed $rotaDays): void
    {
        $scheduledMinutes = Carbon::parse($scheduledTime)->hour * 60 + Carbon::parse($scheduledTime)->minute;
        $allShiftRanges   = [];

        foreach ($rotaDays as $rotaDay) {
            $rotaStart  = Carbon::parse($rotaDay->start_time);
            $rotaEnd    = Carbon::parse($rotaDay->end_time);
            $allShiftRanges[] = $rotaStart->format('h:i A') . ' - ' . $rotaEnd->format('h:i A');

            $startMinutes = $rotaStart->hour * 60 + $rotaStart->minute;
            $endMinutes   = $rotaEnd->hour * 60 + $rotaEnd->minute;

            // Handle overnight shifts
            if ($endMinutes <= $startMinutes) {
                if ($scheduledMinutes >= $startMinutes || $scheduledMinutes <= $endMinutes) {
                    return;
                }
            } elseif ($scheduledMinutes >= $startMinutes && $scheduledMinutes <= $endMinutes) {
                return;
            }
        }

        throw TreatmentException::invalidData(
            "Scheduled time is outside doctor's rota hours (" . implode(', ', $allShiftRanges) . ').',
        );
    }

    private function validateNoTimeOffConflict(
        int $resourceId,
        int $accountId,
        int $locationId,
        string $date,
        mixed $scheduledTime,
    ): void {
        $timeOffs = ResourceTimeOff::where('resource_id', $resourceId)
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->where(function ($query) use ($date): void {
                $query->whereDate('start_date', $date)
                    ->orWhere(function ($q) use ($date): void {
                        $q->where('is_repeat', 1)
                            ->whereDate('start_date', '<=', $date)
                            ->where(fn ($rq) => $rq->whereNull('repeat_until')
                                ->orWhereDate('repeat_until', '>=', $date));
                    });
            })
            ->get();

        $scheduledFormatted = Carbon::parse($scheduledTime)->format('H:i:s');

        foreach ($timeOffs as $timeOff) {
            $offStart = Carbon::parse($timeOff->start_time)->format('H:i:s');
            $offEnd   = Carbon::parse($timeOff->end_time)->format('H:i:s');

            if ($scheduledFormatted >= $offStart && $scheduledFormatted < $offEnd) {
                $rangeDisplay = Carbon::parse($timeOff->start_time)->format('h:i A')
                    . ' - '
                    . Carbon::parse($timeOff->end_time)->format('h:i A');

                throw TreatmentException::doctorUnavailable("Doctor has time off during this slot ({$rangeDisplay}).");
            }
        }
    }

    private function validateBusinessClosure(int $accountId, int $locationId, string $date): void
    {
        $allCentresId = (int) config('constants.all_centres_id', 30);

        $closure = BusinessClosure::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId, $allCentresId): void {
                $query->whereHas('locations', fn ($q) => $q->where('locations.id', $locationId))
                    ->orWhereHas('locations', fn ($q) => $q->where('locations.id', $allCentresId))
                    ->orWhereDoesntHave('locations');
            })
            ->first();

        if ($closure) {
            $dateFormatted = Carbon::parse($date)->format('d M, Y');
            throw TreatmentException::invalidData(
                "Cannot schedule appointment on {$dateFormatted}. Business is closed: " . ($closure->title ?? 'Business Closed'),
            );
        }
    }

    private function validateWorkingDay(int $accountId, string $date): void
    {
        $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($accountId);

        $isWorkingDay = WorkingDayException::isWorkingDay($accountId, $date, $workingDays);

        if (!$isWorkingDay) {
            $dateFormatted = Carbon::parse($date)->format('l, d M Y');
            throw TreatmentException::invalidData(
                "Cannot schedule appointment on {$dateFormatted}. Business is closed on this day.",
            );
        }
    }

    // ──────────────────────────────────────────────────
    //  Doctor-service validation
    // ──────────────────────────────────────────────────

    private function validateDoctorHasService(int $doctorId, int $locationId, int $serviceId): void
    {
        $service = Services::find($serviceId)
            ?? throw TreatmentException::resourceNotFound('Service');

        $parentServiceId = ($service->parent_id && $service->parent_id != 0)
            ? $service->parent_id
            : $serviceId;

        // Scenario 1: "all services" assigned
        $hasAll = \DB::table('doctor_has_locations')
            ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
            ->where('doctor_has_locations.user_id', $doctorId)
            ->where('doctor_has_locations.location_id', $locationId)
            ->where('services.slug', 'all')
            ->where('doctor_has_locations.is_allocated', 1)
            ->exists();

        if ($hasAll) {
            return;
        }

        // Scenario 2: parent category assigned
        $hasParent = \DB::table('doctor_has_locations')
            ->where('user_id', $doctorId)
            ->where('location_id', $locationId)
            ->where('service_id', $parentServiceId)
            ->where('is_allocated', 1)
            ->exists();

        if ($hasParent) {
            return;
        }

        // Scenario 3: specific service assigned
        $hasSpecific = \DB::table('doctor_has_locations')
            ->where('user_id', $doctorId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('is_allocated', 1)
            ->exists();

        if ($hasSpecific) {
            return;
        }

        throw TreatmentException::invalidData(
            'This doctor does not have the selected service allocated at this location.',
        );
    }

    // ──────────────────────────────────────────────────
    //  Data preparation
    // ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function prepareUpdateData(Appointments $appointment, array $requestData): array
    {
        $data = ['updated_at' => Filters::getCurrentTimeStamp()];

        // Schedule
        $data['scheduled_date'] = isset($requestData['scheduled_date']) && $requestData['scheduled_date']
            ? Carbon::parse($requestData['scheduled_date'])->format('Y-m-d')
            : $appointment->scheduled_date;

        $data['scheduled_time'] = isset($requestData['scheduled_time']) && $requestData['scheduled_time']
            ? Carbon::parse($requestData['scheduled_time'])->format('H:i:s')
            : $appointment->scheduled_time;

        // Location
        $data['location_id'] = $requestData['location_id'] ?? $appointment->location_id;

        // Doctor
        $data['doctor_id'] = isset($requestData['doctor_id']) && $requestData['doctor_id']
            ? $requestData['doctor_id']
            : $appointment->doctor_id;

        // Service
        if (isset($requestData['service_id']) && $requestData['service_id']) {
            $data['service_id'] = $requestData['service_id'];
        }

        // City and region from location
        $location = Locations::find($data['location_id']);
        if ($location) {
            $data['city_id']   = $location->city_id;
            $data['region_id'] = $location->region_id;
        }

        // Resource and rota day
        $accountId = (int) Auth::user()->account_id;
        $resource  = Resources::where([
            'external_id'      => $data['doctor_id'],
            'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
            'account_id'       => $accountId,
        ])->first();

        if ($resource) {
            $rotaDay = ResourceHasRotaDays::getSingleDayRotaWithResourceID(
                $resource->id,
                $data['scheduled_date'],
                $accountId,
                $data['location_id'],
            );

            if (is_countable($rotaDay) && count($rotaDay)) {
                $data['resource_id']              = $resource->id;
                $data['resource_has_rota_day_id'] = $rotaDay['id'];
            }
        }

        // Track date/time changes
        $dateChanged = $this->hasFieldChanged($appointment, $requestData, $data, 'scheduled_date', 'Y-m-d');
        $timeChanged = $this->hasFieldChanged($appointment, $requestData, $data, 'scheduled_time', 'H:i:s');

        if ($dateChanged) {
            $data['converted_by'] = Auth::id();
        }

        if (($dateChanged || $timeChanged) && $appointment->base_appointment_status_id == config('constants.appointment_status_pending', 1)) {
            $data['send_message'] = 1;
        }

        if (isset($requestData['location_id']) || isset($requestData['doctor_id'])) {
            $data['updated_by'] = Auth::id();
        }

        // Status sync
        if (isset($requestData['appointment_status_id'])) {
            $data['appointment_status_id'] = $requestData['appointment_status_id'];
            $data['base_appointment_status_id'] = $requestData['base_appointment_status_id']
                ?? $requestData['appointment_status_id'];
        } elseif (!$appointment->base_appointment_status_id && $appointment->appointment_status_id) {
            $data['base_appointment_status_id'] = $appointment->appointment_status_id;
        }

        return $data;
    }

    // ──────────────────────────────────────────────────
    //  Related records
    // ──────────────────────────────────────────────────

    private function updateRelatedRecords(Appointments $appointment, array $requestData): void
    {
        // Update lead
        if (isset($requestData['lead_id'])) {
            $lead = Leads::find($requestData['lead_id']);
            if ($lead) {
                $leadData = array_filter([
                    'name'   => $requestData['name'] ?? null,
                    'phone'  => $requestData['phone'] ?? null,
                    'gender' => $requestData['gender'] ?? null,
                ], fn ($v) => $v !== null);

                if ($leadData) {
                    $lead->update($leadData);
                }
            }
        }

        // Update patient
        if ($appointment->patient_id) {
            $patient = User::find($appointment->patient_id);
            if ($patient) {
                $patientData = array_filter([
                    'name'   => $requestData['name'] ?? null,
                    'phone'  => $requestData['phone'] ?? null,
                    'gender' => $requestData['gender'] ?? null,
                ], fn ($v) => $v !== null);

                if ($patientData) {
                    $patient->update($patientData);
                }
            }
        }

        // Sync patient name across all appointments
        if (isset($requestData['name'])) {
            Appointments::where('patient_id', $appointment->patient_id)
                ->update(['name' => $requestData['name']]);
        }
    }

    // ──────────────────────────────────────────────────
    //  Activity logging
    // ──────────────────────────────────────────────────

    private function logActivity(Appointments $appointment, array $requestData, array $oldValues): void
    {
        $patient  = Patients::find($appointment->patient_id);
        $location = Locations::with('city')->find($appointment->location_id);
        $service  = Services::find($appointment->service_id);

        $fieldChanges = $this->buildFieldChanges($appointment, $requestData, $oldValues, $patient);

        if ($fieldChanges) {
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

        ActivityLogger::saveAppointmentLogs('updated', 'Treatment', $appointment);
    }

    /**
     * @return array<string, array{old: string, new: string}>
     */
    private function buildFieldChanges(
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
                'old' => $oldService->name ?? 'Unknown',
                'new' => $newService->name ?? 'Unknown',
            ];
        }

        if ($oldValues['doctor_id'] != $appointment->doctor_id) {
            $oldDoctor = User::find($oldValues['doctor_id']);
            $newDoctor = User::find($appointment->doctor_id);
            $changes['Doctor'] = [
                'old' => $oldDoctor->name ?? 'Unknown',
                'new' => $newDoctor->name ?? 'Unknown',
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

        // Patient info changes
        $genderMap = ['0' => 'Male', '1' => 'Female', '2' => 'Other'];

        if (isset($requestData['name'])) {
            $changes['Patient Name'] = [
                'old' => $patient->name ?? 'Unknown',
                'new' => $requestData['name'],
            ];
        }
        if (isset($requestData['phone'])) {
            $changes['Patient Phone'] = [
                'old' => $patient->phone ?? 'Unknown',
                'new' => $requestData['phone'],
            ];
        }
        if (isset($requestData['gender'])) {
            $changes['Patient Gender'] = [
                'old' => $genderMap[$patient->gender] ?? 'Unknown',
                'new' => $genderMap[$requestData['gender']] ?? 'Unknown',
            ];
        }

        return $changes;
    }

    // ──────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function captureOldValues(Appointments $appointment): array
    {
        return [
            'service_id'     => $appointment->service_id,
            'doctor_id'      => $appointment->doctor_id,
            'scheduled_date' => $appointment->scheduled_date,
            'scheduled_time' => $appointment->scheduled_time,
            'location_id'    => $appointment->location_id,
            'city_id'        => $appointment->city_id,
        ];
    }

    private function isArrivedOrConverted(Appointments $appointment): bool
    {
        $accountId = (int) Auth::user()->account_id;

        $statusIds = array_filter([
            AppointmentStatuses::where(['account_id' => $accountId, 'is_arrived' => 1])->value('id'),
            AppointmentStatuses::where(['account_id' => $accountId, 'is_converted' => 1])->value('id'),
        ]);

        return in_array($appointment->appointment_status_id, $statusIds, false);
    }

    private function isScheduleChanging(Appointments $appointment, array $requestData): bool
    {
        $oldDate = Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
        $newDate = isset($requestData['scheduled_date'])
            ? Carbon::parse($requestData['scheduled_date'])->format('Y-m-d')
            : $oldDate;

        $oldTime = Carbon::parse($appointment->scheduled_time)->format('H:i');
        $newTime = isset($requestData['scheduled_time'])
            ? Carbon::parse($requestData['scheduled_time'])->format('H:i')
            : $oldTime;

        return ($newDate !== $oldDate) || ($newTime !== $oldTime);
    }

    private function hasFieldChanged(
        Appointments $appointment,
        array $requestData,
        array $data,
        string $field,
        string $format,
    ): bool {
        if (!isset($requestData[$field])) {
            return false;
        }

        $oldFormatted = Carbon::parse($appointment->{$field})->format($format);
        $newFormatted = Carbon::parse($data[$field])->format($format);

        return $oldFormatted !== $newFormatted;
    }
}
