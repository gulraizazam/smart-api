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
        // Properties will be set lazily when needed via getAccountId() and getUserId()
    }

    protected function getAccountId()
    {
        if (!$this->account_id) {
            if (!Auth::check()) {
                throw new \Exception('User must be authenticated to use AppointmentService');
            }
            $this->account_id = Auth::user()->account_id;
        }
        return $this->account_id;
    }

    protected function getUserId()
    {
        if (!$this->user_id) {
            if (!Auth::check()) {
                throw new \Exception('User must be authenticated to use AppointmentService');
            }
            $this->user_id = Auth::id();
        }
        return $this->user_id;
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
        ])->where('account_id', $this->getAccountId());

        if ($appointmentTypeId) {
            $query->where('appointment_type_id', $appointmentTypeId);
        }

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->getAccountId());
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

            // Clean up empty lead_id and patient_id (sent as empty strings from form)
            if (isset($data['lead_id']) && (empty($data['lead_id']) || $data['lead_id'] === '' || $data['lead_id'] === '0')) {
                unset($data['lead_id']);
            }
            if (isset($data['patient_id']) && (empty($data['patient_id']) || $data['patient_id'] === '' || $data['patient_id'] === '0')) {
                unset($data['patient_id']);
            }

            // CRITICAL: Validate that lead_id/patient_id matches the phone number being submitted
            // This prevents linking a consultation to the wrong patient when user enters a new phone
            if (isset($data['lead_id']) && isset($data['phone'])) {
                $lead = Leads::find($data['lead_id']);
                if ($lead) {
                    $submittedPhone = \App\Helpers\GeneralFunctions::cleanNumber($data['phone']);
                    $leadPhone = \App\Helpers\GeneralFunctions::cleanNumber($lead->phone ?? '');
                    
                    // If phone numbers don't match, this is a new patient - clear lead_id and patient_id
                    if ($submittedPhone !== $leadPhone) {
                        \Log::info('Phone mismatch detected - treating as new patient', [
                            'submitted_phone' => $submittedPhone,
                            'lead_phone' => $leadPhone,
                            'lead_id' => $data['lead_id'],
                        ]);
                        unset($data['lead_id']);
                        unset($data['patient_id']);
                    }
                }
            }

            // If creating new patient (either explicitly via checkbox OR when no lead_id exists but phone is provided)
            // This handles the case where user enters a new phone number without selecting an existing lead
            $shouldCreateNewPatient = (isset($data['new_patient']) && $data['new_patient'] == 1 && !isset($data['lead_id'])) 
                || (!isset($data['lead_id']) && isset($data['phone']) && !empty($data['phone']));
            
            if ($shouldCreateNewPatient) {
                // Step 1: Create patient/user record
                $patientData = [
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'gender' => $data['gender'] ?? 0,
                    'referred_by' => $data['referred_by'] ?? null,
                    'account_id' => $this->getAccountId(),
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

            $appointmentData = AppointmentHelper::prepareAppointmentData($data, $this->getAccountId(), $this->getUserId(), false);

            if (isset($data['lead_id'])) {
                $lead = Leads::find($data['lead_id']);
                if (!$lead) {
                    throw AppointmentException::leadNotFound();
                }
                
                // Set patient_id from lead if not already set
                if (!isset($appointmentData['patient_id']) || !$appointmentData['patient_id']) {
                    $appointmentData['patient_id'] = $lead->patient_id;
                }
                
                // Set name from lead if not already set
                if (!isset($appointmentData['name']) || !$appointmentData['name']) {
                    $appointmentData['name'] = $lead->name;
                }
                
                // If lead doesn't have patient_id, we need to create a patient
                if (!$lead->patient_id) {
                    $patientData = [
                        'name' => $lead->name ?? $data['name'] ?? null,
                        'phone' => $lead->phone ?? $data['phone'] ?? null,
                        'email' => $lead->email ?? $data['email'] ?? null,
                        'gender' => $lead->gender ?? $data['gender'] ?? 0,
                        'referred_by' => $lead->referred_by ?? $data['referred_by'] ?? null,
                        'account_id' => $this->getAccountId(),
                        'user_type_id' => 3, // Patient user type
                        'password' => \Hash::make('12345678'),
                        'active' => 1,
                    ];
                    
                    $patient = User::create($patientData);
                    if (!$patient) {
                        throw AppointmentException::invalidData('Failed to create patient for lead.');
                    }
                    
                    // Update lead with patient_id
                    $lead->update(['patient_id' => $patient->id]);
                    
                    // Set patient_id in appointment data
                    $appointmentData['patient_id'] = $patient->id;
                }
            }

            if (isset($data['patient_id'])) {
                $patient = User::find($data['patient_id']);
                if (!$patient) {
                    throw AppointmentException::patientNotFound();
                }
            }

            // Validate doctor has service allocated at location
            if (isset($appointmentData['doctor_id']) && isset($appointmentData['service_id']) && isset($appointmentData['location_id'])) {
                // Check if doctor has "all services" assigned at this location
                $hasAllServices = \DB::table('doctor_has_locations')
                    ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
                    ->where('doctor_has_locations.user_id', $appointmentData['doctor_id'])
                    ->where('doctor_has_locations.location_id', $appointmentData['location_id'])
                    ->where('services.slug', 'all')
                    ->where('doctor_has_locations.is_allocated', 1)
                    ->exists();

                if (!$hasAllServices) {
                    // If not all services, check for specific service
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $appointmentData['doctor_id'])
                        ->where('location_id', $appointmentData['location_id'])
                        ->where('service_id', $appointmentData['service_id'])
                        ->where('is_allocated', 1)
                        ->exists();

                    if (!$hasService) {
                        // Check if the service is a child and its parent is assigned to the doctor
                        $service = \App\Models\Services::find($appointmentData['service_id']);
                        
                        if ($service && $service->parent_id) {
                            // Service has a parent, check if parent is assigned to doctor
                            $hasParentService = \DB::table('doctor_has_locations')
                                ->where('user_id', $appointmentData['doctor_id'])
                                ->where('location_id', $appointmentData['location_id'])
                                ->where('service_id', $service->parent_id)
                                ->where('is_allocated', 1)
                                ->exists();
                            
                            if (!$hasParentService) {
                                throw AppointmentException::invalidData('This doctor does not have the required service or its parent service allocated for this location.');
                            }
                        } else {
                            throw AppointmentException::invalidData('This doctor does not have the required service allocated for this location.');
                        }
                    }
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

            // Always set send_message to 1 for new appointments to trigger SMS via cron job
            $appointment->update(['send_message' => 1]);

            AuditTrails::addEventLogger(
                Appointments::$_table,
                'create',
                $appointmentData,
                Appointments::$_fillable,
                $appointment
            );

            // Handle lead status update and activity logging if lead_id is present
            if (isset($data['lead_id'])) {
                $lead = Leads::find($data['lead_id']);
                
                if ($lead) {
                    // Check if consultation service is different from lead service
                    if (isset($appointment->service_id) && $lead->service_id != $appointment->service_id) {
                        // Update lead's service_id
                        $lead->update([
                            'service_id' => $appointment->service_id,
                            'updated_by' => $this->getUserId(),
                            'updated_at' => Carbon::now()
                        ]);
                        
                        // Create new lead_services record for the new service
                        $existingLeadService = \App\Models\LeadsServices::where([
                            'lead_id' => $lead->id,
                            'service_id' => $appointment->service_id
                        ])->first();
                        
                        if (!$existingLeadService) {
                            // Set all previous lead_services records to inactive before creating new one
                            \App\Models\LeadsServices::where('lead_id', $lead->id)
                                ->update([
                                    'status' => 0,
                                    'updated_at' => Carbon::now()
                                ]);
                            
                            // Get 'Booked' status to set in lead_services
                            $bookedStatus = \App\Models\LeadStatuses::where('account_id', $this->getAccountId())
                                ->where('name', 'Booked')
                                ->first();
                            
                            // Create new active lead_services record
                            \App\Models\LeadsServices::create([
                                'lead_id' => $lead->id,
                                'service_id' => $appointment->service_id,
                                'account_id' => $this->getAccountId(),
                                'lead_status_id' => $bookedStatus ? $bookedStatus->id : null,
                                'status' => 1,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                        }
                    }
                    
                    // Get 'Booked' lead status
                    $bookedStatus = \App\Models\LeadStatuses::where('account_id', $this->getAccountId())
                        ->where('name', 'Booked')
                        ->first();
                    
                    if ($bookedStatus) {
                        if ($lead->lead_status_id != $bookedStatus->id) {
                            // Update lead status to Booked
                            $lead->update([
                                'lead_status_id' => $bookedStatus->id,
                                'updated_by' => $this->getUserId(),
                                'updated_at' => Carbon::now()
                            ]);
                        }
                        
                        // Update lead_status_id in lead_services for this service
                        if (isset($appointment->service_id)) {
                            \App\Models\LeadsServices::where([
                                'lead_id' => $lead->id,
                                'service_id' => $appointment->service_id
                            ])->update([
                                'lead_status_id' => $bookedStatus->id,
                                'updated_at' => Carbon::now()
                            ]);
                        }
                    }
                    
                    // Send Meta CAPI event for booked status
                    \Log::info('Sending Meta CAPI booked event', [
                        'lead_id' => $lead->id,
                        'phone' => $lead->phone,
                        'meta_lead_id' => $lead->meta_lead_id,
                        'email' => $lead->email,
                    ]);
                    try {
                        $metaService = new \App\Services\MetaConversionApiService();
                        $metaService->sendLeadStatus(
                            $lead->phone,
                            'booked',
                            $lead->meta_lead_id,
                            $lead->email
                        );
                        \Log::info('Meta CAPI booked event sent successfully', [
                            'lead_id' => $lead->id,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Meta CAPI booked event failed: ' . $e->getMessage(), [
                            'lead_id' => $lead->id,
                            'exception' => $e->getTraceAsString(),
                        ]);
                    }
                    
                    // Get related data for activity logging
                    $location = \App\Models\Locations::with('city')->find($appointment->location_id);
                    $service = \App\Models\Services::find($appointment->service_id);
                    
                    // Log lead booked activity
                    ActivityLogger::logLeadBooked($lead, $appointment, $location, $service);
                }
            }

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

            DB::commit();
            
            $appointment->load([
                'appointment_type',
                'appointment_status',
                'service',
                'location',
                'doctor',
                'patient'
            ]);

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
                'account_id' => $this->getAccountId()
            ])->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }

            $appointmentData = AppointmentHelper::prepareAppointmentData($data, $this->getAccountId(), $this->getUserId(), true);

            if (isset($data['reschedule']) && $data['reschedule'] == 1) {
                $appointmentData['converted_by'] = $this->getUserId();
            }

            // Validate doctor has service allocated at location (when doctor or service is being changed)
            $doctorId = $appointmentData['doctor_id'] ?? $appointment->doctor_id;
            $serviceId = $appointmentData['service_id'] ?? $appointment->service_id;
            $locationId = $appointmentData['location_id'] ?? $appointment->location_id;

            if ($doctorId && $serviceId && $locationId) {
                // Check if doctor has "all services" assigned at this location
                $hasAllServices = \DB::table('doctor_has_locations')
                    ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
                    ->where('doctor_has_locations.user_id', $doctorId)
                    ->where('doctor_has_locations.location_id', $locationId)
                    ->where('services.slug', 'all')
                    ->where('doctor_has_locations.is_allocated', 1)
                    ->exists();

                if (!$hasAllServices) {
                    // If not all services, check for specific service
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $doctorId)
                        ->where('location_id', $locationId)
                        ->where('service_id', $serviceId)
                        ->where('is_allocated', 1)
                        ->exists();

                    if (!$hasService) {
                        // Check if the service is a child and its parent is assigned to the doctor
                        $service = \App\Models\Services::find($serviceId);
                        
                        if ($service && $service->parent_id) {
                            // Service has a parent, check if parent is assigned to doctor
                            $hasParentService = \DB::table('doctor_has_locations')
                                ->where('user_id', $doctorId)
                                ->where('location_id', $locationId)
                                ->where('service_id', $service->parent_id)
                                ->where('is_allocated', 1)
                                ->exists();
                            
                            if (!$hasParentService) {
                                throw AppointmentException::invalidData('This doctor does not have the required service or its parent service allocated for this location.');
                            }
                        } else {
                            throw AppointmentException::invalidData('This doctor does not have the required service allocated for this location.');
                        }
                    }
                }
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

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

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
                'account_id' => $this->getAccountId()
            ])->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }

            if (AppointmentHelper::isChildExists($id, $this->getAccountId())) {
                throw AppointmentException::cannotDelete();
            }

            $patient = Patients::find($appointment->patient_id);
            $location = Locations::with('city')->find($appointment->location_id);
            $service = Services::find($appointment->service_id);

            ActivityLogger::logAppointmentDeleted($appointment, $patient, $location, $service);

            AppointmentsDailyStats::where('appointment_id', $id)->delete();

            $appointment->update([
                'deleted_by' => $this->getUserId(),
                'arrived_at' => null,
                'converted_at' => null
            ]);

            $appointment->delete();

            Activity::where('appointment_id', $id)->update([
                'deleted_by' => $this->getUserId(),
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

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

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
                'account_id' => $this->getAccountId()
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
                'updated_by' => $this->getUserId(),
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
                $updateData['converted_by'] = $this->getUserId();
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

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

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
        ->where('appointment_type_id',1)
          ->whereNotNull('scheduled_time');

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->getAccountId());
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
        ])->where('account_id', $this->getAccountId())
          ->whereNull('scheduled_date')
          ->whereNull('scheduled_time');

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->getAccountId());
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
            $accountId = $this->getAccountId();
            
            // Find appointment by ID, allowing for NULL account_id or matching account_id
            $appointment = Appointments::where('id', $id)
                ->where(function($query) use ($accountId) {
                    $query->where('account_id', $accountId)
                          ->orWhereNull('account_id');
                })
                ->first();

            if (!$appointment) {
                throw AppointmentException::notFound();
            }
            
            // If appointment has NULL account_id, set it to current user's account
            if (is_null($appointment->account_id)) {
                $appointment->account_id = $accountId;
            }

            $scheduleData = AppointmentHelper::formatScheduleData(
                $data['start'],
                $appointment->first_scheduled_count,
                $appointment->scheduled_at_count
            );

            // Validate doctor has service allocated at location
            $doctorId = $data['doctor_id'] ?? $appointment->doctor_id;
            $locationId = $data['location_id'] ?? $appointment->location_id;
            
            // Check if doctor has "all services" assigned at this location
            $hasAllServices = \DB::table('doctor_has_locations')
                ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
                ->where('doctor_has_locations.user_id', $doctorId)
                ->where('doctor_has_locations.location_id', $locationId)
                ->where('services.slug', 'all')
                ->where('doctor_has_locations.is_allocated', 1)
                ->exists();

            if (!$hasAllServices) {
                // If not all services, check for specific service
                $hasService = \DB::table('doctor_has_locations')
                    ->where('user_id', $doctorId)
                    ->where('location_id', $locationId)
                    ->where('service_id', $appointment->service_id)
                    ->where('is_allocated', 1)
                    ->exists();

                if (!$hasService) {
                    throw AppointmentException::invalidData('This doctor does not have the required service allocated for this location.');
                }
            }

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
                'updated_by' => $this->getUserId(),
                'updated_at' => Carbon::now()
            ]);

            if (isset($data['doctor_id'])) {
                $updateData['doctor_id'] = $data['doctor_id'];
            }

            if (isset($data['resource_id'])) {
                $updateData['resource_id'] = $data['resource_id'];
            }

            if (isset($data['reschedule']) && $data['reschedule']) {
                $updateData['converted_by'] = $this->getUserId();
            }

            $appointment->update($updateData);

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

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
            'account_id' => $this->getAccountId()
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

        // Validate rota for consultancy appointments
        if (isset($data['appointment_type_id']) && $data['appointment_type_id'] == 1) {
            $this->validateRotaAvailability($data);
        }

        return true;
    }

    protected function validateRotaAvailability(array $data)
    {
        \Log::info('validateRotaAvailability called', [
            'has_scheduled_date' => isset($data['scheduled_date']),
            'has_scheduled_time' => isset($data['scheduled_time']),
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'start' => $data['start'] ?? null,
        ]);
        
        $object = new \stdClass();
        
        // If we have scheduled_time but no scheduled_date, extract date from start
        if (!isset($data['scheduled_date']) && isset($data['start'])) {
            $data['scheduled_date'] = \Carbon\Carbon::parse($data['start'])->format('Y-m-d');
            \Log::info('Extracted scheduled_date from start', ['scheduled_date' => $data['scheduled_date']]);
        }
        
        // Build start datetime from scheduled_date and scheduled_time
        if (isset($data['scheduled_date']) && isset($data['scheduled_time'])) {
            $object->start = $data['scheduled_date'].'T'.\Carbon\Carbon::parse($data['scheduled_time'])->format('H:i:s');
            \Log::info('Using scheduled_date and scheduled_time', ['object_start' => $object->start]);
        } elseif (isset($data['start'])) {
            $object->start = $data['start'];
            \Log::info('Using start parameter', ['object_start' => $object->start]);
        } else {
            \Log::info('No time to validate, returning');
            return; // No time to validate
        }
        
        $object->city_id = $data['city_id'] ?? '';
        $object->doctor_id = $data['doctor_id'] ?? null;
        $object->location_id = $data['location_id'] ?? null;
        $object->appointment_type = 'consulting';
        
        $rota = \App\Helpers\Widgets\AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        
        if (!$rota['status']) {
            throw AppointmentException::invalidData($rota['message'] ?? 'Doctor rota is not available for the selected time.');
        }
    }

    public function getAppointmentStatistics($filters = [])
    {
        $cacheKey = "appointment_stats_{$this->getAccountId()}_" . md5(json_encode($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $query = Appointments::where('account_id', $this->getAccountId());
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
