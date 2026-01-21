<?php

namespace App\Services\Appointment;

use App\Exceptions\AppointmentException;
use App\Helpers\AppointmentHelper;
use App\Helpers\ActivityLogger;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentsDailyStats;
use App\Models\Activity;
use App\Models\AuditTrails;
use App\Models\Patients;
use App\Models\Locations;
use App\Models\Services;
use App\Models\Leads;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AppointmentService
{
    protected $account_id;
    protected $user_id;

    public function __construct()
    {
        $this->account_id = Auth::user()->account_id ?? null;
        $this->user_id = Auth::id();
    }

    public function getAppointmentsList($filters, $appointmentTypeId = null)
    {
        $query = Appointments::with([
            'appointment_type',
            'appointment_status',
            'service',
            'location.city',
            'doctor',
            'patient',
            'lead',
            'user',
            'user_converted_by',
            'user_updated_by'
        ])->where('account_id', $this->account_id);

        if ($appointmentTypeId) {
            $query->where('appointment_type_id', $appointmentTypeId);
        }

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->account_id);
        if ($cancelledStatus) {
            $query->where('base_appointment_status_id', '!=', $cancelledStatus->id);
        }

        $query = $this->applyFilters($query, $filters);

        return $query;
    }

    protected function applyFilters($query, $filters)
    {
        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (!empty($filters['phone'])) {
            $phone = GeneralFunctions::cleanNumber($filters['phone']);
            $query->whereHas('patient', function ($q) use ($phone) {
                $q->where('phone', 'like', "%{$phone}%");
            });
        }

        if (!empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (!empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (!empty($filters['appointment_status_id'])) {
            $query->where('appointment_status_id', $filters['appointment_status_id']);
        }

        if (!empty($filters['scheduled_date_from'])) {
            $query->where('scheduled_date', '>=', $filters['scheduled_date_from']);
        }

        if (!empty($filters['scheduled_date_to'])) {
            $query->where('scheduled_date', '<=', $filters['scheduled_date_to']);
        }

        if (!empty($filters['created_date_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_date_from']);
        }

        if (!empty($filters['created_date_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_date_to']);
        }

        if (isset($filters['scheduled']) && $filters['scheduled'] === true) {
            $query->whereNotNull('scheduled_date')
                  ->whereNotNull('scheduled_time');
        } elseif (isset($filters['scheduled']) && $filters['scheduled'] === false) {
            $query->whereNull('scheduled_date')
                  ->whereNull('scheduled_time');
        }

        return $query;
    }

    public function createAppointment(array $data)
    {
        DB::beginTransaction();
        try {
            if (!isset($data['appointment_type_id'])) {
                throw AppointmentException::invalidData('Appointment type is required.');
            }
            
            if (!isset($data['appointment_status_id'])) {
                throw AppointmentException::invalidData('Appointment status is required.');
            }
            
            if (!isset($data['location_id'])) {
                throw AppointmentException::invalidData('Location is required.');
            }
            
            $this->validateAppointmentData($data);

            // If creating new patient, create patient/user first, then lead
            if (isset($data['new_patient']) && $data['new_patient'] == 1 && !isset($data['lead_id'])) {
                // Step 1: Create patient/user record
                $patientData = [
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'gender' => $data['gender'] ?? 0,
                    'referred_by' => $data['referred_by'] ?? null,
                    'account_id' => $this->account_id,
                    'user_type_id' => 3, // Patient user type
                    'password' => \Hash::make('12345678'),
                    'active' => 1,
                ];
                
                $patient = User::create($patientData);
                if (!$patient) {
                    throw AppointmentException::invalidData('Failed to create patient.');
                }
                
                // Step 2: Create lead with patient_id
                $accountId = Auth::user()->account_id ?? 1;
                $userId = Auth::id();
                
                $leadData = [
                    'patient_id' => $patient->id,
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'referred_by' => $data['referred_by'] ?? null,
                    'account_id' => $accountId,
                    'created_by' => $userId,
                    'location_id' => $data['location_id'] ?? null,
                    'region_id' => null,
                    'city_id' => null,
                    'lead_status_id' => null,
                    'lead_source_id' => null,
                ];
                
                // Get location details for region and city
                if (isset($data['location_id'])) {
                    $location = \App\Models\Locations::find($data['location_id']);
                    if ($location) {
                        $leadData['region_id'] = $location->region_id;
                        $leadData['city_id'] = $location->city_id;
                    }
                }
                
                // Get 'Booked' lead status
                $bookedStatus = \App\Models\LeadStatuses::where('account_id', $accountId)
                    ->where('name', 'Booked')
                    ->first();
                
                if (!$bookedStatus) {
                    // Fallback to default status if 'Booked' not found
                    $bookedStatus = \App\Models\LeadStatuses::where('account_id', $accountId)
                        ->where('is_default', 1)
                        ->first();
                }
                
                if ($bookedStatus) {
                    $leadData['lead_status_id'] = $bookedStatus->id;
                }
                
                // Create lead record
                \Log::info('Creating lead with data:', $leadData);
                $lead = Leads::create($leadData);
                if (!$lead) {
                    throw AppointmentException::invalidData('Failed to create lead for new patient.');
                }
                \Log::info('Lead created successfully:', ['lead_id' => $lead->id, 'patient_id' => $lead->patient_id, 'account_id' => $lead->account_id, 'created_by' => $lead->created_by, 'lead_status_id' => $lead->lead_status_id]);
                
                // Create lead service entry if service_id is provided
                if (isset($data['service_id'])) {
                    \App\Models\LeadsServices::create([
                        'lead_id' => $lead->id,
                        'service_id' => $data['service_id'],
                        'account_id' => $accountId,
                        'status' => 1,
                    ]);
                }
                
                // Step 3: Set lead_id and patient_id for appointment
                $data['lead_id'] = $lead->id;
                $data['patient_id'] = $patient->id;
            }

            $appointmentData = AppointmentHelper::prepareAppointmentData($data, $this->account_id, $this->user_id, false);

            if (isset($data['lead_id'])) {
                $lead = Leads::find($data['lead_id']);
                if (!$lead) {
                    throw AppointmentException::leadNotFound();
                }
                $appointmentData['patient_id'] = $appointmentData['patient_id'] ?? $lead->patient_id;
                $appointmentData['name'] = $appointmentData['name'] ?? $lead->name;
            }

            if (isset($data['patient_id'])) {
                $patient = User::find($data['patient_id']);
                if (!$patient) {
                    throw AppointmentException::patientNotFound();
                }
            }

            // Schedule conflict check disabled to allow multiple bookings on the same slot
            // if (isset($appointmentData['scheduled_date']) && isset($appointmentData['scheduled_time'])) {
            //     $hasConflict = AppointmentHelper::validateScheduleConflict(
            //         $appointmentData['location_id'],
            //         $appointmentData['doctor_id'] ?? null,
            //         $appointmentData['resource_id'] ?? null,
            //         $appointmentData['scheduled_date'],
            //         $appointmentData['scheduled_time']
            //     );

            //     if ($hasConflict) {
            //         throw AppointmentException::scheduleConflict();
            //     }
            // }

            $appointment = Appointments::create($appointmentData);

            if (!$appointment) {
                throw AppointmentException::creationFailed();
            }

            AuditTrails::addEventLogger(
                Appointments::$_table,
                'create',
                $appointmentData,
                Appointments::$_fillable,
                $appointment
            );

            AppointmentHelper::clearAppointmentCache($this->account_id);

            DB::commit();
            
            $appointment->load([
                'appointment_type',
                'appointment_status',
                'service',
                'location',
                'doctor',
                'patient'
            ]);
            
            // Add start_time and end_time for calendar
            if ($appointment->scheduled_date && $appointment->scheduled_time) {
                // Format date properly (handle both string and Carbon object)
                $dateStr = $appointment->scheduled_date instanceof Carbon 
                    ? $appointment->scheduled_date->format('Y-m-d')
                    : (is_string($appointment->scheduled_date) ? $appointment->scheduled_date : Carbon::parse($appointment->scheduled_date)->format('Y-m-d'));
                
                // Get time string (handle if it's already a datetime)
                $timeStr = is_string($appointment->scheduled_time) ? $appointment->scheduled_time : $appointment->scheduled_time;
                if (strpos($timeStr, ' ') !== false) {
                    // Extract just the time part if it contains a space
                    $parts = explode(' ', $timeStr);
                    $timeStr = end($parts);
                }
                
                // Combine date and time
                $appointment->start_time = $dateStr . ' ' . $timeStr;
                
                // Calculate end time based on service duration
                if (isset($appointmentData['service_id'])) {
                    $service = \App\Models\Services::find($appointmentData['service_id']);
                    $duration = ($service && $service->duration) ? $service->duration : 30;
                } else {
                    $duration = 30;
                }
                
                $appointment->end_time = Carbon::parse($appointment->start_time)
                    ->addMinutes($duration)
                    ->format('Y-m-d H:i:s');
            } else {
                $appointment->start_time = null;
                $appointment->end_time = null;
            }
            
            return $appointment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateAppointment($id, array $data)
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->account_id
            ])->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }

            $appointmentData = AppointmentHelper::prepareAppointmentData($data, $this->account_id, $this->user_id, true);

            if (isset($data['reschedule']) && $data['reschedule'] == 1) {
                $appointmentData['converted_by'] = $this->user_id;
            }

            // Schedule conflict check disabled to allow multiple bookings on the same slot
            // if (isset($appointmentData['scheduled_date']) && isset($appointmentData['scheduled_time'])) {
            //     $hasConflict = AppointmentHelper::validateScheduleConflict(
            //         $appointmentData['location_id'] ?? $appointment->location_id,
            //         $appointmentData['doctor_id'] ?? $appointment->doctor_id,
            //         $appointmentData['resource_id'] ?? $appointment->resource_id,
            //         $appointmentData['scheduled_date'],
            //         $appointmentData['scheduled_time'],
            //         $id
            //     );

            //     if ($hasConflict) {
            //         throw AppointmentException::scheduleConflict();
            //     }
            // }

            $oldData = $appointment->toArray();
            $appointment->update($appointmentData);

            AuditTrails::editEventLogger(
                Appointments::$_table,
                'update',
                $appointmentData,
                Appointments::$_fillable,
                $oldData,
                $id
            );

            AppointmentHelper::clearAppointmentCache($this->account_id);

            DB::commit();
            return $appointment->fresh([
                'appointment_type',
                'appointment_status',
                'service',
                'location',
                'doctor',
                'patient'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteAppointment($id)
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->account_id
            ])->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }

            if (AppointmentHelper::isChildExists($id, $this->account_id)) {
                throw AppointmentException::cannotDelete();
            }

            $patient = Patients::find($appointment->patient_id);
            $location = Locations::with('city')->find($appointment->location_id);
            $service = Services::find($appointment->service_id);

            ActivityLogger::logAppointmentDeleted($appointment, $patient, $location, $service);

            AppointmentsDailyStats::where('appointment_id', $id)->delete();

            $appointment->update([
                'deleted_by' => $this->user_id,
                'arrived_at' => null,
                'converted_at' => null
            ]);

            $appointment->delete();

            Activity::where('appointment_id', $id)->update([
                'deleted_by' => $this->user_id,
                'action' => 'deleted',
                'deleted_date' => Carbon::now()->format('Y-m-d'),
                'updated_at' => Carbon::now()
            ]);

            AuditTrails::deleteEventLogger(
                Appointments::$_table,
                'delete',
                Appointments::$_fillable,
                $id,
                '0'
            );

            AppointmentHelper::clearAppointmentCache($this->account_id);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateAppointmentStatus($id, array $data)
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->account_id
            ])->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }

            $status = AppointmentStatuses::find($data['appointment_status_id']);
            if (!$status) {
                throw AppointmentException::invalidStatus();
            }

            $updateData = [
                'appointment_status_id' => $data['appointment_status_id'],
                'base_appointment_status_id' => $status->base_appointment_status_id ?? $data['appointment_status_id'],
                'updated_by' => $this->user_id,
                'updated_at' => Carbon::now()
            ];

            if (isset($data['reason'])) {
                $updateData['reason'] = $data['reason'];
            }

            if (isset($data['cancellation_reason_id'])) {
                $updateData['cancellation_reason_id'] = $data['cancellation_reason_id'];
            }

            if ($status->is_converted ?? false) {
                $updateData['converted_at'] = Carbon::now();
                $updateData['converted_by'] = $this->user_id;
            }

            $oldData = $appointment->toArray();
            $appointment->update($updateData);

            AuditTrails::editEventLogger(
                Appointments::$_table,
                'status_update',
                $updateData,
                Appointments::$_fillable,
                $oldData,
                $id
            );

            AppointmentHelper::clearAppointmentCache($this->account_id);

            DB::commit();
            return $appointment->fresh(['appointment_status', 'appointment_status_base']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getScheduledAppointments($filters)
    {
        $query = Appointments::with([
            'appointment_type',
            'appointment_status',
            'service',
            'location',
            'doctor',
            'patient',
            'resource'
        ])->whereNotNull('scheduled_date')
          ->whereNotNull('scheduled_time');

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->account_id);
        if ($cancelledStatus) {
            $query->where(function($q) use ($cancelledStatus) {
                $q->where('appointment_status_id', '!=', $cancelledStatus->id)
                  ->orWhereNull('appointment_status_id');
            });
        }

        $query = $this->applyFilters($query, $filters);

        return $query->get();
    }

    public function getNonScheduledAppointments($filters)
    {
        $query = Appointments::with([
            'appointment_type',
            'appointment_status',
            'service',
            'location',
            'doctor',
            'patient'
        ])->where('account_id', $this->account_id)
          ->whereNull('scheduled_date')
          ->whereNull('scheduled_time');

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->account_id);
        if ($cancelledStatus) {
            $query->where(function($q) use ($cancelledStatus) {
                $q->where('appointment_status_id', '!=', $cancelledStatus->id)
                  ->orWhereNull('appointment_status_id');
            });
        }

        $query = $this->applyFilters($query, $filters);

        return $query->get();
    }

    public function scheduleAppointment($id, array $data)
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->account_id
            ])->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }

            $scheduleData = AppointmentHelper::formatScheduleData(
                $data['start'],
                $appointment->first_scheduled_count,
                $appointment->scheduled_at_count
            );

            // Schedule conflict check disabled to allow multiple bookings on the same slot
            // $hasConflict = AppointmentHelper::validateScheduleConflict(
            //     $data['location_id'] ?? $appointment->location_id,
            //     $data['doctor_id'] ?? $appointment->doctor_id,
            //     $data['resource_id'] ?? $appointment->resource_id,
            //     $scheduleData['scheduled_date'],
            //     $scheduleData['scheduled_time'],
            //     $id
            // );

            // if ($hasConflict) {
            //     throw AppointmentException::scheduleConflict();
            // }

            $updateData = array_merge($scheduleData, [
                'updated_by' => $this->user_id,
                'updated_at' => Carbon::now()
            ]);

            if (isset($data['doctor_id'])) {
                $updateData['doctor_id'] = $data['doctor_id'];
            }

            if (isset($data['resource_id'])) {
                $updateData['resource_id'] = $data['resource_id'];
            }

            if (isset($data['reschedule']) && $data['reschedule']) {
                $updateData['converted_by'] = $this->user_id;
            }

            $appointment->update($updateData);

            AppointmentHelper::clearAppointmentCache($this->account_id);

            DB::commit();
            return $appointment->fresh(['doctor', 'resource', 'location']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAppointmentById($id)
    {
        $appointment = Appointments::with([
            'appointment_type',
            'appointment_status',
            'appointment_status_base',
            'service',
            'location.city',
            'doctor',
            'patient',
            'lead',
            'user',
            'user_converted_by',
            'user_updated_by',
            'cancellation_reason',
            'appointment_comments',
            'sms_logs',
            'packageadvance',
            'packages',
            'hasInvoices'
        ])->where([
            'id' => $id,
            'account_id' => $this->account_id
        ])->first();

        if (!$appointment) {
            throw AppointmentException::notFound();
        }

        return $appointment;
    }

    protected function validateAppointmentData(array $data)
    {
        if (isset($data['location_id'])) {
            $location = Locations::find($data['location_id']);
            if (!$location) {
                throw AppointmentException::invalidLocation();
            }
        }

        if (isset($data['doctor_id'])) {
            $doctor = User::find($data['doctor_id']);
            if (!$doctor) {
                throw AppointmentException::invalidDoctor();
            }
        }

        if (isset($data['service_id'])) {
            $service = Services::find($data['service_id']);
            if (!$service) {
                throw AppointmentException::invalidService();
            }
        }

        return true;
    }

    public function getAppointmentStatistics($filters = [])
    {
        $cacheKey = "appointment_stats_{$this->account_id}_" . md5(json_encode($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $query = Appointments::where('account_id', $this->account_id);
            $query = $this->applyFilters($query, $filters);

            return [
                'total' => $query->count(),
                'scheduled' => (clone $query)->whereNotNull('scheduled_date')->count(),
                'non_scheduled' => (clone $query)->whereNull('scheduled_date')->count(),
                'today' => (clone $query)->whereDate('scheduled_date', Carbon::today())->count(),
                'this_week' => (clone $query)->whereBetween('scheduled_date', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ])->count(),
                'this_month' => (clone $query)->whereMonth('scheduled_date', Carbon::now()->month)
                    ->whereYear('scheduled_date', Carbon::now()->year)->count(),
            ];
        });
    }
}
