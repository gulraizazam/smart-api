<?php

namespace App\Helpers;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Services;
use App\Models\Locations;
use App\Models\Settings;
use App\Models\Patients;
use App\Models\Doctors;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AppointmentHelper
{
    const CACHE_TTL = 3600;

    public static function prepareSMSContent($appointment_id, $smsContent)
    {
        if (!$appointment_id) {
            return $smsContent;
        }

        $appointment = Appointments::with(['patient', 'service', 'location', 'doctor'])
            ->find($appointment_id);

        if (!$appointment) {
            return $smsContent;
        }

        $patient = $appointment->patient;
        $service = $appointment->service;
        $location = $appointment->location;
        $doctor = $appointment->doctor;

        $setting = Settings::getBySlug('sys-headoffice', $appointment->account_id);
        $smsContent = str_replace('##head_office_phone##', $setting->data ?? '', $smsContent);

        if ($patient) {
            $smsContent = str_replace('##patient_name##', $appointment->name ?? $patient->name, $smsContent);
            $smsContent = str_replace('##patient_phone##', $patient->phone, $smsContent);
        }

        if ($appointment->scheduled_date && $appointment->scheduled_time) {
            $smsContent = str_replace('##appointment_date##', Carbon::parse($appointment->scheduled_date)->format('l, F d, Y'), $smsContent);
            $smsContent = str_replace('##appointment_time##', Carbon::parse($appointment->scheduled_time)->format('h:i A'), $smsContent);
        }

        if ($service) {
            $smsContent = str_replace('##appointment_service##', $service->name, $smsContent);
        }

        if ($location) {
            $smsContent = str_replace('##fdo_name##', $location->fdo_name ?? '', $smsContent);
            $smsContent = str_replace('##fdo_phone##', GeneralFunctions::prepareNumber4CallSMS($location->fdo_phone ?? ''), $smsContent);
            $smsContent = str_replace('##centre_name##', $location->name, $smsContent);
            $smsContent = str_replace('##centre_address##', $location->address ?? '', $smsContent);
            $smsContent = str_replace('##centre_google_map##', $location->google_map ?? '', $smsContent);
        }

        if ($doctor) {
            $smsContent = str_replace('##doctor_name##', $doctor->name, $smsContent);
            $smsContent = str_replace('##doctor_profile_link##', $doctor->profile_url ?? '', $smsContent);
        }

        return $smsContent;
    }

    public static function getNodeServices($serviceId, $account_id, $drop_down = false, $remove_spaces = false)
    {
        $cacheKey = "appointment_node_services_{$account_id}_{$serviceId}_{$drop_down}_{$remove_spaces}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serviceId, $account_id, $drop_down, $remove_spaces) {
            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build(($serviceId) ? $serviceId : 0, $account_id, true, true);
            $parentGroups->toList($parentGroups, -1);
            $services = $parentGroups->nodeList;

            $nodeList = [];

            if (count($services)) {
                foreach ($services as $key => $service) {
                    if ($key < 0) {
                        continue;
                    }

                    if ($drop_down) {
                        if ($remove_spaces) {
                            $nodeList[$key] = str_replace('&nbsp;', '', trim($service['name']));
                        } else {
                            $nodeList[$key] = trim($service['name']);
                        }
                    } else {
                        if ($remove_spaces) {
                            $service['name'] = str_replace('&nbsp;', '', trim($service['name']));
                        }
                        $nodeList[$key] = $service;
                    }
                }
            }

            return $nodeList;
        });
    }

    public static function getCancelledStatus($account_id)
    {
        $cacheKey = "appointment_cancelled_status_{$account_id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($account_id) {
            return AppointmentStatuses::getCancelledStatusOnly($account_id);
        });
    }

    public static function formatScheduleData($start, $first_scheduled_count, $scheduled_at_count)
    {
        $data = [];

        if ($start) {
            $parsedDate = Carbon::parse($start);
            $data['scheduled_date'] = $parsedDate->format('Y-m-d');
            $data['scheduled_time'] = $parsedDate->format('H:i:s');

            if ($first_scheduled_count == 0) {
                $data['first_scheduled_date'] = $data['scheduled_date'];
                $data['first_scheduled_time'] = $data['scheduled_time'];
                $data['first_scheduled_count'] = 1;
            } else {
                $data['scheduled_at_count'] = $scheduled_at_count + 1;
            }
        } else {
            $data['scheduled_date'] = null;
            $data['scheduled_time'] = null;
        }

        return $data;
    }

    public static function isChildExists($appointment_id, $account_id)
    {
        return \App\Models\PackageAdvances::where(['appointment_id' => $appointment_id, 'account_id' => $account_id])->exists()
            || \App\Models\Invoices::where(['appointment_id' => $appointment_id, 'account_id' => $account_id])
                ->whereNull('deleted_at')
                ->where('invoice_status_id', '!=', 4)
                ->exists()
            || \App\Models\Measurement::where(['appointment_id' => $appointment_id])->exists()
            || \App\Models\Appointmentimage::where(['appointment_id' => $appointment_id])->exists();
    }

    public static function clearAppointmentCache($account_id)
    {
        Cache::forget("appointment_cancelled_status_{$account_id}");
        Cache::forget("appointment_types_{$account_id}");
        
        // Clear all appointment statuses caches (we can't use wildcard, so clear common ones)
        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("appointment_statuses_{$account_id}_{$i}");
        }
        Cache::forget("appointment_statuses_{$account_id}_");
        
        // Clear node services caches (limited clearing since we can't use wildcards)
        Cache::forget("appointment_node_services_{$account_id}");
    }

    public static function getAppointmentTypes($account_id)
    {
        $cacheKey = "appointment_types_{$account_id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($account_id) {
            return \App\Models\AppointmentTypes::where('account_id', $account_id)
                ->orderBy('name')
                ->get();
        });
    }

    public static function getAppointmentStatuses($account_id, $appointment_type_id = null)
    {
        $cacheKey = "appointment_statuses_{$account_id}_{$appointment_type_id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($account_id, $appointment_type_id) {
            $query = AppointmentStatuses::where('account_id', $account_id);

            if ($appointment_type_id) {
                $query->where('appointment_type_id', $appointment_type_id);
            }

            return $query->orderBy('name')->get();
        });
    }

    public static function validateScheduleConflict($location_id, $doctor_id, $resource_id, $scheduled_date, $scheduled_time, $appointment_id = null)
    {
        $query = Appointments::where('location_id', $location_id)
            ->where('scheduled_date', $scheduled_date)
            ->where('scheduled_time', $scheduled_time)
            ->where(function ($q) use ($doctor_id, $resource_id) {
                $q->where('doctor_id', $doctor_id)
                    ->orWhere('resource_id', $resource_id);
            });

        if ($appointment_id) {
            $query->where('id', '!=', $appointment_id);
        }

        return $query->exists();
    }

    public static function prepareAppointmentData(array $data, $account_id, $user_id, $isUpdate = false)
    {
        $appointmentData = [
            'account_id' => $account_id,
            'updated_at' => Carbon::now(),
        ];

        if ($isUpdate) {
            $appointmentData['updated_by'] = $user_id;
        } else {
            $appointmentData['created_by'] = $user_id;
            $appointmentData['created_at'] = Carbon::now();
        }

        // Fetch location details if location_id is provided
        if (isset($data['location_id'])) {
            $location = \App\Models\Locations::find($data['location_id']);
            if ($location) {
                $appointmentData['region_id'] = $location->region_id;
                $appointmentData['city_id'] = $location->city_id;
            }
        }

        // Fetch resource_id from resources table where external_id matches doctor_id
        if (isset($data['doctor_id']) && !isset($data['resource_id'])) {
            $resource = \App\Models\Resources::where('external_id', $data['doctor_id'])
                ->where('account_id', $account_id)
                ->first();
            if ($resource) {
                $appointmentData['resource_id'] = $resource->id;
                
                // Fetch resource_has_rota_day_id if scheduled_date and location_id are available
                if (isset($data['start']) || (isset($data['scheduled_date']) && isset($data['location_id']))) {
                    $scheduleDate = isset($data['start']) 
                        ? Carbon::parse($data['start'])->format('Y-m-d')
                        : $data['scheduled_date'];
                    
                    $locationId = $data['location_id'];
                    
                    // Find the rota day for this doctor at this location on this date
                    $rotaDay = \App\Models\ResourceHasRotaDays::whereHas('resource_rota', function($query) use ($resource, $locationId) {
                            $query->where('resource_id', $resource->id)
                                  ->where('location_id', $locationId)
                                  ->where('is_consultancy', 1)
                                  ->where('active', 1);
                        })
                        ->where('date', $scheduleDate)
                        ->where('active', 1)
                        ->first();
                    
                    if ($rotaDay) {
                        $appointmentData['resource_has_rota_day_id'] = $rotaDay->id;
                    }
                }
            }
        }

        if (isset($data['start'])) {
            $scheduleData = self::formatScheduleData(
                $data['start'],
                $data['first_scheduled_count'] ?? 0,
                $data['scheduled_at_count'] ?? 0
            );
            $appointmentData = array_merge($appointmentData, $scheduleData);
        }

        if (isset($data['resourceId'])) {
            $appointmentData['resource_id'] = $data['resourceId'];
        }

        $mergedData = array_merge($appointmentData, $data);
        
        // Ensure base_appointment_status_id is set when appointment_status_id exists
        if (isset($mergedData['appointment_status_id']) && !isset($mergedData['base_appointment_status_id'])) {
            $mergedData['base_appointment_status_id'] = $mergedData['appointment_status_id'];
        }

        return $mergedData;
    }
}
