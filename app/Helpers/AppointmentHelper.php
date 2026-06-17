<?php

declare(strict_types=1);
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

    public static function prepareSMSContent(int|null $appointment_id, string $smsContent): string
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
            $scheduledDate = $appointment->scheduled_date instanceof \DateTimeInterface
                ? $appointment->scheduled_date->format('l, F d, Y')
                : Carbon::parse($appointment->scheduled_date)->format('l, F d, Y');
            $scheduledTime = $appointment->scheduled_time instanceof \DateTimeInterface
                ? $appointment->scheduled_time->format('h:i A')
                : Carbon::parse($appointment->scheduled_time)->format('h:i A');
            $smsContent = str_replace('##appointment_date##', $scheduledDate, $smsContent);
            $smsContent = str_replace('##appointment_time##', $scheduledTime, $smsContent);
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

    public static function getNodeServices(int|null $serviceId, int $account_id, bool $drop_down = false, bool $remove_spaces = false): array
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

    public static function getCancelledStatus(int $account_id): mixed
    {
        $cacheKey = "appointment_cancelled_status_{$account_id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn() => AppointmentStatuses::getCancelledStatusOnly($account_id));
    }

    public static function formatScheduleData(?string $start, int $first_scheduled_count, int $scheduled_at_count): array
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

    public static function isChildExists(int $appointment_id, int $account_id): bool
    {
        return \App\Models\PackageAdvances::where(['appointment_id' => $appointment_id, 'account_id' => $account_id])->exists()
            || \App\Models\Invoices::where(['appointment_id' => $appointment_id, 'account_id' => $account_id])
                ->whereNull('deleted_at')
                ->where('invoice_status_id', '!=', 4)
                ->exists()
            || \App\Models\Measurement::where(['appointment_id' => $appointment_id])->exists()
            || \App\Models\Appointmentimage::where(['appointment_id' => $appointment_id])->exists();
    }

    /**
     * Batched mirror of {@see isChildExists()} for a whole page of
     * appointments. Returns a set keyed by appointment_id whose presence
     * means "has at least one child" — same four tables + filters as the
     * per-row check, but four `whereIn` queries for the page instead of
     * four EXISTS queries per row.
     *
     * Used by the list resources' preload step to kill the N+1 that
     * made the consultations / treatments list pages slow. The per-row
     * helper stays the canonical single-row contract.
     *
     * @param  array<int>  $appointment_ids
     * @return array<int, true>
     */
    public static function childExistenceSet(array $appointment_ids, int $account_id): array
    {
        $ids = array_values(array_unique(array_map('intval', $appointment_ids)));
        if (empty($ids)) {
            return [];
        }

        $set = [];
        $mark = static function ($pluck) use (&$set): void {
            foreach ($pluck as $id) {
                $set[(int) $id] = true;
            }
        };

        $mark(\App\Models\PackageAdvances::whereIn('appointment_id', $ids)
            ->where('account_id', $account_id)
            ->distinct()
            ->pluck('appointment_id'));
        $mark(\App\Models\Invoices::whereIn('appointment_id', $ids)
            ->where('account_id', $account_id)
            ->whereNull('deleted_at')
            ->where('invoice_status_id', '!=', 4)
            ->distinct()
            ->pluck('appointment_id'));
        $mark(\App\Models\Measurement::whereIn('appointment_id', $ids)
            ->distinct()
            ->pluck('appointment_id'));
        $mark(\App\Models\Appointmentimage::whereIn('appointment_id', $ids)
            ->distinct()
            ->pluck('appointment_id'));

        return $set;
    }

    /**
     * Batched paid-invoice lookup for a page of appointments. Returns a
     * set keyed by appointment_id that has at least one invoice in the
     * account's `paid` status. One query for the page (plus one cached
     * status-id lookup) instead of one EXISTS per row.
     *
     * @param  array<int>  $appointment_ids
     * @return array<int, true>
     */
    public static function paidInvoiceSet(array $appointment_ids, int $account_id): array
    {
        $ids = array_values(array_unique(array_map('intval', $appointment_ids)));
        if (empty($ids) || $account_id <= 0) {
            return [];
        }

        $paidId = (int) \App\Models\InvoiceStatuses::where('slug', '=', 'paid')
            ->where('account_id', $account_id)
            ->value('id');
        if ($paidId <= 0) {
            return [];
        }

        $set = [];
        foreach (
            \App\Models\Invoices::whereIn('appointment_id', $ids)
                ->where('invoice_status_id', $paidId)
                ->distinct()
                ->pluck('appointment_id') as $id
        ) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    public static function clearAppointmentCache(int $account_id): void
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

    public static function getAppointmentTypes(int $account_id): mixed
    {
        $cacheKey = "appointment_types_{$account_id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn() => \App\Models\AppointmentTypes::where('account_id', $account_id)
                ->orderBy('name')
                ->get());
    }

    public static function getAppointmentStatuses(int $account_id, ?int $appointment_type_id = null): mixed
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

    public static function validateScheduleConflict(int $location_id, int $doctor_id, ?int $resource_id, string $scheduled_date, string $scheduled_time, ?int $appointment_id = null): bool
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

    public static function prepareAppointmentData(array $data, int $account_id, int $user_id, bool $isUpdate = false): array
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

        // Mirror the status's `allow_message` onto the appointment so the
        // booking + reminder SMS crons (which gate on
        // appointment_status_allow_message = 1) can pick it up. The column
        // defaults to 0, so without this every API-created consultation was
        // silently un-sendable. crm2's create controllers set this the same
        // way. Only derived when the caller hasn't set it explicitly.
        if (isset($mergedData['appointment_status_id']) && !isset($mergedData['appointment_status_allow_message'])) {
            $mergedData['appointment_status_allow_message'] = (int) (
                AppointmentStatuses::where('id', $mergedData['appointment_status_id'])
                    ->where('account_id', $account_id)
                    ->value('allow_message') ?? 0
            );
        }

        return $mergedData;
    }
}
