<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\BusinessClosure;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\ResourceTimeOff;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * Get locations for the schedule calendar filter
     */
    public function getLocations(): JsonResponse
    {
        // Locations dropdown for the scheduling-shifts calendar — gate
        // on list view so non-scheduling users can't enumerate the
        // location list via this helper.
        if (! Gate::allows('scheduling_shifts.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        // Use same method and format as consultancy calendar for consistent ordering
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

        return $this->successResponse('Locations retrieved successfully', [
            'dropdown' => $locations->pluck('name', 'id'),
        ]);
    }

    /**
     * Get business working days configuration
     *
     * Legacy endpoint kept live for older callers (the SPA hits
     * Api/BusinessWorkingDaysController::show via /api/business-working-days
     * instead). Gated on the same business-working-days view perm so
     * direct callers face the same auth as the new endpoint.
     */
    public function getBusinessWorkingDays(): JsonResponse
    {
        if (! Gate::allows('business_working_days.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $accountId = Auth::user()->account_id;
        
        // Try to get existing setting
        $setting = Settings::where('account_id', $accountId)
            ->where('slug', 'business_working_days')
            ->first();
        
        if ($setting && $setting->data) {
            $workingDays = json_decode($setting->data, true);
        } else {
            // Default: Monday to Saturday are working days, Sunday is closed
            $workingDays = [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => true,
                'sunday' => false,
            ];
        }
        
        // Get exceptions
        $exceptions = \App\Models\WorkingDayException::where('account_id', $accountId)
            ->orderBy('exception_date', 'asc')
            ->get()
            ->map(fn($exc) => [
                    'id' => $exc->id,
                    'exception_date' => $exc->exception_date->format('Y-m-d'),
                    'exception_date_formatted' => $exc->exception_date->format('l, d M Y'),
                    'is_working' => $exc->is_working,
                ]);

        return $this->successResponse('Business working days retrieved', [
            'working_days' => $workingDays,
            'exceptions' => $exceptions,
        ]);
    }

    /**
     * Save business working days configuration
     */
    public function saveBusinessWorkingDays(Request $request): JsonResponse
    {
        // Legacy endpoint — see comment on getBusinessWorkingDays above.
        if (! Gate::allows('business_working_days.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $accountId = Auth::user()->account_id;
        $workingDays = $request->input('working_days', []);
        
        // Validate that we have all days
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $validatedDays = [];
        foreach ($days as $day) {
            // Handle string 'true'/'false' values from form data
            $value = $workingDays[$day] ?? false;
            if (is_string($value)) {
                $validatedDays[$day] = $value === 'true' || $value === '1';
            } else {
                $validatedDays[$day] = (bool) $value;
            }
        }
        
        // Update or create setting
        $setting = Settings::updateOrCreate(
            [
                'account_id' => $accountId,
                'slug' => 'business_working_days',
            ],
            [
                'name' => 'Business Working Days',
                'data' => json_encode($validatedDays),
                'active' => 1,
            ]
        );

        // Handle working day exceptions
        $exceptions = $request->input('exceptions', []);
        $userId = Auth::user()->id;

        // Delete existing exceptions and recreate
        \App\Models\WorkingDayException::where('account_id', $accountId)->delete();

        foreach ($exceptions as $exception) {
            if (!empty($exception['exception_date'])) {
                \App\Models\WorkingDayException::create([
                    'account_id' => $accountId,
                    'exception_date' => $exception['exception_date'],
                    'is_working' => filter_var($exception['is_working'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'created_by' => $userId,
                ]);
            }
        }
        
        return $this->successResponse('Business working days saved successfully', [
            'working_days' => $validatedDays,
        ]);
    }

    /**
     * Get shifts for resources in a given week
     */
    public function getShifts(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $locationId = (int) $request->input('location_id');
        $resourceTypeId = (int) $request->input('resource_type_id', 2); // Default to Doctor (2)
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$locationId || !$startDate || !$endDate) {
            return $this->errorResponse('Missing required parameters', 400);
        }

        // Get active resources for the location and type
        $resources = $this->getResourcesForLocation($locationId, $resourceTypeId);

        if ($resources->isEmpty()) {
            return $this->errorResponse('No resources found', 400);
        }

        // Get shifts for these resources in the date range
        $resourceIds = $resources->pluck('id')->toArray();
        $shifts = $this->getShiftsForResources($resourceIds, $locationId, $startDate, $endDate);

        // Get business closures for this location and date range
        $closures = $this->getBusinessClosures($locationId, $startDate, $endDate);
        $timeOffs = $this->getTimeOffsForResources($resourceIds, $locationId, $startDate, $endDate);

        return $this->successResponse('Shifts retrieved successfully', [
            'resources' => $resources,
            'shifts' => $shifts,
            'closures' => $closures,
            'time_offs' => $timeOffs,
        ]);
    }

    /**
     * Get resources (doctors or machines) for a location
     */
    private function getResourcesForLocation(int $locationId, int $resourceTypeId): \Illuminate\Database\Eloquent\Collection
    {
        $accountId = Auth::user()->account_id;

        // Resource type 2 = Doctor, 1 = Machine
        if ($resourceTypeId == 2) {
            // For doctors: Get all active doctors allocated to this location
            // Get doctor user IDs from doctor_has_locations where user is also active
            $doctorUserIds = \App\Models\DoctorHasLocations::where('location_id', $locationId)
                ->where('is_allocated', 1)
                ->whereHas('user', function ($query) {
                    $query->where('active', 1);
                })
                ->pluck('user_id')
                ->toArray();

            // Get resources for these doctors (no rota check - allows creating schedules for new doctors)
            return Resources::where('account_id', $accountId)
                ->where('resource_type_id', $resourceTypeId)
                ->where('active', 1)
                ->whereIn('external_id', $doctorUserIds)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'external_id']);
        } else {
            // For machines: Get machines that have active rotas for this location
            return Resources::where('account_id', $accountId)
                ->where('resource_type_id', $resourceTypeId)
                ->where('active', 1)
                ->whereHas('resourceRota', function ($query) use ($locationId) {
                    $query->where('location_id', $locationId)
                        ->where('active', 1);
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name']);
        }
    }

    /**
     * Get shifts for resources in a date range
     */
    private function getShiftsForResources(array $resourceIds, int $locationId, string $startDate, string $endDate): array
    {
        $shifts = [];

        // Get all active rotas for these resources at this location
        $rotas = ResourceHasRota::whereIn('resource_id', $resourceIds)
            ->where('location_id', $locationId)
            ->where('active', 1)
            ->get();

        if ($rotas->isEmpty()) {
            return $shifts;
        }

        $rotaIds = $rotas->pluck('id')->toArray();

        // Map rota to resource
        $rotaToResource = [];
        foreach ($rotas as $rota) {
            $rotaToResource[$rota->id] = $rota->resource_id;
        }

        // Get rota days for the date range — filter pushed into SQL using
        // idx_rota_days_date instead of loading every rota day for the
        // resource and filtering in PHP. (The "debug" comment that was here
        // suggests the filter was temporarily removed during debugging and
        // never re-added.)
        $rotaDays = ResourceHasRotaDays::whereIn('resource_has_rota_id', $rotaIds)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get();

        // Build shifts array
        foreach ($rotaDays as $rotaDay) {
            $resourceId = $rotaToResource[$rotaDay->resource_has_rota_id] ?? null;
            if ($resourceId) {
                $shifts[] = [
                    'id' => $rotaDay->id,
                    'resource_id' => $resourceId,
                    'date' => $rotaDay->date,
                    'start_time' => $rotaDay->start_time,
                    'end_time' => $rotaDay->end_time,
                    'start_off' => $rotaDay->start_off,
                    'end_off' => $rotaDay->end_off,
                ];
            }
        }

        return $shifts;
    }

    /**
     * Store or update shifts for a resource on a specific date
     */
    public function storeShifts(Request $request): JsonResponse
    {
        // storeShifts handles both new-shift create AND edit-existing
        // writes (the controller doesn't differentiate — the SPA dialog
        // routes both intents here). Allow either create or edit.
        if (! Gate::allows('scheduling_shifts.create') && ! Gate::allows('scheduling_shifts.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $resourceId = $request->input('resource_id');
        $locationId = $request->input('location_id');
        $date = $request->input('date');
        $shifts = $request->input('shifts', []);
        $accountId = Auth::user()->account_id;

        if (!$resourceId || !$locationId || !$date) {
            return $this->errorResponse('Missing required parameters', 400);
        }

        // Validate shifts - check for overlapping times
        $overlapError = $this->validateShiftOverlaps($shifts);
        if ($overlapError) {
            return $this->errorResponse($overlapError, 400);
        }

        // Find the active rota for this resource at this location that covers this date
        $rota = ResourceHasRota::where('resource_id', $resourceId)
            ->where('location_id', $locationId)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->whereDate('start', '<=', $date)
            ->whereDate('end', '>=', $date)
            ->first();

        // If no rota found with date range, try to find any active rota for this resource/location
        if (!$rota) {
            $rota = ResourceHasRota::where('resource_id', $resourceId)
                ->where('location_id', $locationId)
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->first();
        }

        // If still no rota found, create a new one for this resource at this location
        if (!$rota) {
            $rota = ResourceHasRota::create([
                'resource_id' => $resourceId,
                'location_id' => $locationId,
                'account_id' => $accountId,
                'start' => $date,
                'end' => Carbon::parse($date)->addYear()->format('Y-m-d'),
                'is_treatment' => 1,
                'is_consultancy' => 0,
                'active' => 1,
            ]);
        }

        // Get existing shift IDs for this date
        $existingShiftIds = ResourceHasRotaDays::where('resource_has_rota_id', $rota->id)
            ->whereDate('date', $date)
            ->pluck('id')
            ->toArray();

        // Unlink any appointments from these shifts (don't delete appointments, just remove the link)
        if (!empty($existingShiftIds)) {
            \DB::table('appointments')
                ->whereIn('resource_has_rota_day_id', $existingShiftIds)
                ->update(['resource_has_rota_day_id' => null]);
        }

        // Delete all existing rota day records for this date
        ResourceHasRotaDays::where('resource_has_rota_id', $rota->id)
            ->whereDate('date', $date)
            ->forceDelete();

        // Create new rota day records for each shift
        $createdShifts = [];
        foreach ($shifts as $shift) {
            $startTime = $this->convertTo24Hour($shift['start_time']);
            $endTime = $this->convertTo24Hour($shift['end_time']);

            // Handle midnight end time: 00:00 means end of day, so end_timestamp should be next day
            $endTimestamp = $endTime === '00:00'
                ? Carbon::parse($date)->addDay()->startOfDay()->format('Y-m-d H:i:s')
                : Carbon::parse($date . ' ' . $endTime)->format('Y-m-d H:i:s');

            $rotaDay = ResourceHasRotaDays::create([
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_off' => null,
                'end_off' => null,
                'start_timestamp' => Carbon::parse($date . ' ' . $startTime)->format('Y-m-d H:i:s'),
                'end_timestamp' => $endTimestamp,
                'resource_has_rota_id' => $rota->id,
                'active' => 1,
            ]);

            $createdShifts[] = $rotaDay;
        }

        return $this->successResponse('Shifts saved successfully', [
            'shifts' => $createdShifts,
        ]);
    }

    /**
     * Store repeating shifts for a resource based on weekly schedule.
     *
     * Accepts an optional `additional_resource_ids` array so an operator
     * can copy the same weekly pattern to other doctors at the same location
     * in one submit. The primary `resource_id` plus the additional ids are
     * processed in a single DB transaction.
     */
    public function storeRepeatingShifts(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $resourceId = (int) $request->input('resource_id');
        $locationId = (int) $request->input('location_id');
        $scheduleType = $request->input('schedule_type', 'every_week');
        $startDateStr = $request->input('start_date');
        $endDateStr = $request->input('end_date');
        $days = $request->input('days', []);
        $additionalIds = array_values(array_filter(array_map('intval', (array) $request->input('additional_resource_ids', []))));
        $accountId = Auth::user()->account_id;

        if (!$resourceId || !$locationId || !$startDateStr || !$endDateStr) {
            return $this->errorResponse('Missing required parameters', 400);
        }

        // Parse dates (format: "February 13, 2026")
        $startDate = Carbon::parse($startDateStr);
        $endDate = Carbon::parse($endDateStr);

        if ($endDate->lt($startDate)) {
            return $this->errorResponse('End date must be after start date', 400);
        }

        // Map day names to day of week numbers (0 = Sunday, 1 = Monday, etc.)
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        // Determine week interval based on schedule type
        $weekInterval = match ($scheduleType) {
            'every_2_weeks' => 2,
            'every_3_weeks' => 3,
            'every_4_weeks' => 4,
            default => 1,
        };

        // Build a map of day -> shifts and validate for overlaps once up front,
        // so a bad input fails before we touch any rota.
        $dayShifts = [];
        foreach ($days as $day) {
            $dayName = strtolower($day['day']);
            if (isset($dayMap[$dayName]) && $day['enabled'] && !empty($day['shifts'])) {
                $overlapError = $this->validateShiftOverlaps($day['shifts']);
                if ($overlapError) {
                    return $this->errorResponse(ucfirst($dayName) . ': ' . $overlapError, 400);
                }
                $dayShifts[$dayMap[$dayName]] = $day['shifts'];
            }
        }

        // Restrict additional ids to active doctor resources actually allocated
        // to this location — never trust the client to enforce "same branch".
        if (!empty($additionalIds)) {
            $allowedIds = $this->getResourcesForLocation($locationId, 2)->pluck('id')->all();
            $additionalIds = array_values(array_intersect($additionalIds, $allowedIds));
        }

        // Primary first, then any additional, deduped (the primary is allowed
        // to be in either list).
        $resourceIds = array_values(array_unique(array_merge([$resourceId], $additionalIds)));

        $totalCreated = 0;
        try {
            DB::transaction(function () use ($resourceIds, $locationId, $accountId, $startDate, $endDate, $dayShifts, $weekInterval, &$totalCreated) {
                foreach ($resourceIds as $rid) {
                    $totalCreated += $this->applyRepeatingShiftsForResource(
                        $rid, $locationId, $accountId, $startDate, $endDate, $dayShifts, $weekInterval
                    );
                }
            });
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save repeating shifts: ' . $e->getMessage(), 500);
        }

        return $this->successResponse('Repeating shifts saved successfully', [
            'shifts_created' => $totalCreated,
            'resources_processed' => count($resourceIds),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
    }

    /**
     * Apply a parsed weekly pattern to a single resource. Returns the number
     * of rota-day rows created. Caller is responsible for the surrounding
     * transaction.
     */
    private function applyRepeatingShiftsForResource(
        int $resourceId,
        int $locationId,
        int $accountId,
        Carbon $startDate,
        Carbon $endDate,
        array $dayShifts,
        int $weekInterval
    ): int {
        // Find the active rota for this resource at this location
        $rota = ResourceHasRota::where('resource_id', $resourceId)
            ->where('location_id', $locationId)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->first();

        // If no rota found, create a new one for this resource at this location
        if (!$rota) {
            $rota = ResourceHasRota::create([
                'resource_id' => $resourceId,
                'location_id' => $locationId,
                'account_id' => $accountId,
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'is_treatment' => 1,
                'is_consultancy' => 0,
                'active' => 1,
            ]);
        }

        // Wipe every rota day in the date range so the new pattern fully
        // replaces the old one. Rota days with linked appointments would
        // otherwise survive the delete and the new pattern would stack on
        // top of them, leaving the operator with two shifts on the same day.
        // Mirror storeShifts' behavior: unlink the appointments first
        // (don't delete the appointments themselves), then force-delete.
        $existingShiftIds = ResourceHasRotaDays::where('resource_has_rota_id', $rota->id)
            ->whereDate('date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('date', '<=', $endDate->format('Y-m-d'))
            ->pluck('id')
            ->all();

        if (!empty($existingShiftIds)) {
            DB::table('appointments')
                ->whereIn('resource_has_rota_day_id', $existingShiftIds)
                ->update(['resource_has_rota_day_id' => null]);

            ResourceHasRotaDays::whereIn('id', $existingShiftIds)->forceDelete();
        }

        $createdCount = 0;
        $currentDate = $startDate->copy();
        $startOfFirstWeek = $startDate->copy()->startOfWeek(Carbon::MONDAY);

        while ($currentDate->lte($endDate)) {
            $currentWeekStart = $currentDate->copy()->startOfWeek(Carbon::MONDAY);
            $weekNumber = $startOfFirstWeek->diffInWeeks($currentWeekStart);

            if ($weekNumber % $weekInterval === 0) {
                $dayOfWeek = $currentDate->dayOfWeek;

                if (isset($dayShifts[$dayOfWeek])) {
                    foreach ($dayShifts[$dayOfWeek] as $shift) {
                        $startTime = $this->convertTo24Hour($shift['start_time']);
                        $endTime = $this->convertTo24Hour($shift['end_time']);

                        $dateStr = $currentDate->format('Y-m-d');
                        $endTimestamp = $endTime === '00:00'
                            ? Carbon::parse($dateStr)->addDay()->startOfDay()->format('Y-m-d H:i:s')
                            : Carbon::parse($dateStr . ' ' . $endTime)->format('Y-m-d H:i:s');

                        ResourceHasRotaDays::create([
                            'date' => $dateStr,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'start_off' => null,
                            'end_off' => null,
                            'start_timestamp' => Carbon::parse($dateStr . ' ' . $startTime)->format('Y-m-d H:i:s'),
                            'end_timestamp' => $endTimestamp,
                            'resource_has_rota_id' => $rota->id,
                            'active' => 1,
                        ]);

                        $createdCount++;
                    }
                }
            }

            $currentDate->addDay();
        }

        return $createdCount;
    }

    /**
     * Delete all shifts for a resource on a specific date
     */
    public function deleteShifts(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $resourceId = $request->input('resource_id');
        $locationId = $request->input('location_id');
        $date = $request->input('date');
        $accountId = Auth::user()->account_id;

        if (!$resourceId || !$locationId || !$date) {
            return $this->errorResponse('Missing required parameters', 400);
        }

        // Find the active rota for this resource at this location that covers this date
        $rota = ResourceHasRota::where('resource_id', $resourceId)
            ->where('location_id', $locationId)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->whereDate('start', '<=', $date)
            ->whereDate('end', '>=', $date)
            ->first();

        // If no rota found with date range, try to find any active rota for this resource/location
        if (!$rota) {
            $rota = ResourceHasRota::where('resource_id', $resourceId)
                ->where('location_id', $locationId)
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->first();
        }

        // If no rota found, nothing to delete
        if (!$rota) {
            return $this->successResponse('No shifts to delete');
        }

        // Delete rota day records for this date that don't have appointments
        $deleted = ResourceHasRotaDays::where('resource_has_rota_id', $rota->id)
            ->whereDate('date', $date)
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.resource_has_rota_day_id', 'resource_has_rota_days.id');
            })
            ->forceDelete();

        return $this->successResponse('Shifts deleted successfully', [
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Delete a single shift by ID
     */
    public function deleteSingleShift(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $shiftId = $request->input('shift_id');

        if (!$shiftId) {
            return $this->errorResponse('Shift ID is required', 400);
        }

        $shift = ResourceHasRotaDays::find($shiftId);

        if (!$shift) {
            return $this->errorResponse('Shift not found', 404);
        }

        // Use transaction to ensure data integrity
        \DB::transaction(function () use ($shift, $shiftId) {
            // Unlink any appointments from this shift (don't delete them, just remove the link)
            \DB::table('appointments')
                ->where('resource_has_rota_day_id', $shiftId)
                ->update(['resource_has_rota_day_id' => null]);

            $shift->forceDelete();
        });

        return $this->successResponse('Shift deleted successfully');
    }

    /**
     * Store time off for a resource
     */
    public function storeTimeOff(Request $request): JsonResponse
    {
        // Time-offs are managed inside the scheduling-shifts page; they
        // share the shift `create` perm by design (single-perm rotas UX).
        if (! Gate::allows('scheduling_shifts.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $resourceId = $request->input('resource_id');
        $locationId = $request->input('location_id');
        $type = $request->input('type', 'time_off');
        $startDate = $request->input('start_date');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $isRepeat = $request->input('is_repeat', false);
        $repeatUntil = $request->input('repeat_until');
        $description = $request->input('description');
        $accountId = Auth::user()->account_id;

        if (!$resourceId || !$startDate) {
            return $this->errorResponse('Resource and start date are required', 400);
        }

        $resourceId = (int) $resourceId;
        $locationId = $locationId !== null && $locationId !== '' ? (int) $locationId : null;
        $accountId = (int) $accountId;

        // Parse dates to Y-m-d format (they may come in display format like "Mon, 16 Feb 2026")
        $startDateParsed = Carbon::parse($startDate)->format('Y-m-d');
        $repeatUntilParsed = $repeatUntil ? Carbon::parse($repeatUntil)->format('Y-m-d') : null;

        // Convert times to 24-hour format
        $startTime24 = $startTime ? Carbon::parse($startTime)->format('H:i:s') : null;
        $endTime24 = $endTime ? Carbon::parse($endTime)->format('H:i:s') : null;

        // Check for overlapping time offs
        $overlapCheck = $this->checkTimeOffOverlap(
            $resourceId,
            $accountId,
            $startDateParsed,
            $isRepeat ? $repeatUntilParsed : $startDateParsed,
            $startTime24,
            $endTime24,
            null,
            $locationId
        );

        if ($overlapCheck) {
            return $this->errorResponse('This time off overlaps with an existing time off. Please choose a different time.', 400);
        }

        // Create time off record
        $timeOff = ResourceTimeOff::create([
            'resource_id' => $resourceId,
            'location_id' => $locationId,
            'account_id' => $accountId,
            'type' => $type,
            'start_date' => $startDateParsed,
            'start_time' => $startTime24,
            'end_time' => $endTime24,
            'is_full_day' => !$startTime && !$endTime,
            'is_repeat' => $isRepeat ? true : false,
            'repeat_until' => $isRepeat ? $repeatUntilParsed : null,
            'description' => $description,
        ]);

        return $this->successResponse('Time off saved successfully', [
            'time_off' => $timeOff,
        ]);
    }

    /**
     * Get time offs for resources in a date range
     */
    public function getTimeOffs(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $locationId = $request->input('location_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountId = Auth::user()->account_id;

        $timeOffs = ResourceTimeOff::where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('is_repeat', true)
                            ->where('start_date', '<=', $endDate)
                            ->where(function ($q2) use ($startDate) {
                                $q2->whereNull('repeat_until')
                                    ->orWhere('repeat_until', '>=', $startDate);
                            });
                    });
            })
            ->get();

        $result = [];
        foreach ($timeOffs as $timeOff) {
            $result[] = [
                'id' => $timeOff->id,
                'resource_id' => $timeOff->resource_id,
                'type' => $timeOff->type,
                'type_label' => $timeOff->type_label,
                'start_date' => $timeOff->start_date->format('Y-m-d'),
                'start_time' => $timeOff->start_time,
                'end_time' => $timeOff->end_time,
                'is_full_day' => $timeOff->is_full_day,
                'is_repeat' => $timeOff->is_repeat,
                'repeat_until' => $timeOff->repeat_until ? $timeOff->repeat_until->format('Y-m-d') : null,
                'description' => $timeOff->description,
            ];
        }

        return $this->successResponse('Time offs retrieved successfully', [
            'time_offs' => $result,
        ]);
    }

    /**
     * Get a single time off record
     */
    public function getTimeOff(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $timeOffId = $request->input('time_off_id');

        if (!$timeOffId) {
            return $this->errorResponse('Time off ID is required', 400);
        }

        $timeOff = ResourceTimeOff::find($timeOffId);

        if (!$timeOff) {
            return $this->errorResponse('Time off not found', 404);
        }

        return $this->successResponse('Time off retrieved successfully', [
            'time_off' => [
                'id' => $timeOff->id,
                'resource_id' => $timeOff->resource_id,
                'location_id' => $timeOff->location_id,
                'type' => $timeOff->type,
                'type_label' => $timeOff->type_label,
                'start_date' => $timeOff->start_date->format('Y-m-d'),
                'start_time' => $timeOff->start_time,
                'end_time' => $timeOff->end_time,
                'is_full_day' => $timeOff->is_full_day,
                'is_repeat' => $timeOff->is_repeat,
                'repeat_until' => $timeOff->repeat_until ? $timeOff->repeat_until->format('Y-m-d') : null,
                'description' => $timeOff->description,
            ],
        ]);
    }

    /**
     * Update a time off record
     */
    public function updateTimeOff(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $timeOffId = $request->input('time_off_id');

        if (!$timeOffId) {
            return $this->errorResponse('Time off ID is required', 400);
        }

        $timeOff = ResourceTimeOff::find($timeOffId);

        if (!$timeOff) {
            return $this->errorResponse('Time off not found', 404);
        }

        $type = $request->input('type');
        $startDate = $request->input('start_date');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $isRepeat = $request->input('is_repeat', false);
        $repeatUntil = $request->input('repeat_until');
        $description = $request->input('description');

        // Parse dates to Y-m-d format (they may come in display format like "Mon, 16 Feb 2026")
        $startDateParsed = Carbon::parse($startDate)->format('Y-m-d');
        $repeatUntilParsed = $repeatUntil ? Carbon::parse($repeatUntil)->format('Y-m-d') : null;

        // Convert times to 24-hour format
        $startTime24 = $startTime ? Carbon::parse($startTime)->format('H:i:s') : null;
        $endTime24 = $endTime ? Carbon::parse($endTime)->format('H:i:s') : null;

        // Check for overlapping time offs (exclude current time off)
        $overlapCheck = $this->checkTimeOffOverlap(
            $timeOff->resource_id,
            $timeOff->account_id,
            $startDateParsed,
            $isRepeat ? $repeatUntilParsed : $startDateParsed,
            $startTime24,
            $endTime24,
            $timeOffId,
            $timeOff->location_id
        );

        if ($overlapCheck) {
            return $this->errorResponse('This time off overlaps with an existing time off. Please choose a different time.', 400);
        }

        $timeOff->update([
            'type' => $type,
            'start_date' => $startDateParsed,
            'start_time' => $startTime24,
            'end_time' => $endTime24,
            'is_repeat' => $isRepeat ? 1 : 0,
            'repeat_until' => $isRepeat && $repeatUntilParsed ? $repeatUntilParsed : null,
            'description' => $description,
        ]);

        return $this->successResponse('Time off updated successfully', [
            'time_off' => $timeOff,
        ]);
    }

    /**
     * Delete a time off record
     */
    public function deleteTimeOff(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $timeOffId = $request->input('time_off_id');

        if (!$timeOffId) {
            return $this->errorResponse('Time off ID is required', 400);
        }

        $timeOff = ResourceTimeOff::find($timeOffId);

        if (!$timeOff) {
            return $this->errorResponse('Time off not found', 404);
        }

        $timeOff->delete();

        return $this->successResponse('Time off deleted successfully');
    }

    /**
     * Get time offs for resources in a date range (private helper)
     */
    private function getTimeOffsForResources(array $resourceIds, int $locationId, string $startDate, string $endDate): array
    {
        $accountId = Auth::user()->account_id;

        $timeOffs = ResourceTimeOff::where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->whereIn('resource_id', $resourceIds)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('is_repeat', true)
                            ->where('start_date', '<=', $endDate)
                            ->where(function ($q2) use ($startDate) {
                                $q2->whereNull('repeat_until')
                                    ->orWhere('repeat_until', '>=', $startDate);
                            });
                    });
            })
            ->get();

        $result = [];
        foreach ($timeOffs as $timeOff) {
            $result[] = [
                'id' => $timeOff->id,
                'resource_id' => $timeOff->resource_id,
                'type' => $timeOff->type,
                'type_label' => $timeOff->type_label,
                'start_date' => $timeOff->start_date->format('Y-m-d'),
                'start_time' => $timeOff->start_time,
                'end_time' => $timeOff->end_time,
                'is_full_day' => $timeOff->is_full_day,
                'is_repeat' => $timeOff->is_repeat,
                'repeat_until' => $timeOff->repeat_until ? $timeOff->repeat_until->format('Y-m-d') : null,
                'description' => $timeOff->description,
            ];
        }

        return $result;
    }

    /**
     * Validate that shifts don't overlap with each other
     */
    private function validateShiftOverlaps(array $shifts): ?string
    {
        if (count($shifts) < 2) {
            return null;
        }

        // Convert all times to minutes for easier comparison
        $timeRanges = [];
        foreach ($shifts as $index => $shift) {
            $startMinutes = $this->timeToMinutes($shift['start_time']);
            $endMinutes = $this->timeToMinutes($shift['end_time']);

            // Treat 12:00am (midnight) end time as end of day (1440) instead of start of day (0)
            if ($endMinutes === 0) {
                $endMinutes = 1440;
            }

            // Validate start is before end
            if ($startMinutes >= $endMinutes) {
                return 'Shift ' . ($index + 1) . ': Start time must be before end time';
            }

            $timeRanges[] = [
                'start' => $startMinutes,
                'end' => $endMinutes,
                'index' => $index + 1,
            ];
        }

        // Sort by start time
        usort($timeRanges, fn($a, $b) => $a['start'] - $b['start']);

        // Check for overlaps
        for ($i = 0; $i < count($timeRanges) - 1; $i++) {
            if ($timeRanges[$i]['end'] > $timeRanges[$i + 1]['start']) {
                return 'Shift ' . $timeRanges[$i]['index'] . ' overlaps with Shift ' . $timeRanges[$i + 1]['index'];
            }
        }

        return null;
    }

    /**
     * Convert time string (e.g., "10:00 AM") to minutes since midnight
     */
    private function timeToMinutes(string $time): int
    {
        $parsed = Carbon::parse($time);
        return $parsed->hour * 60 + $parsed->minute;
    }

    /**
     * Convert 12-hour time to 24-hour format
     */
    private function convertTo24Hour(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }

    /**
     * Get business closures for a location in a date range
     */
    private function getBusinessClosures(int $locationId, string $startDate, string $endDate): array
    {
        $accountId = Auth::user()->account_id;
        $allCentresId = 30; // "All Centres" location ID

        $closures = BusinessClosure::where('account_id', $accountId)
            ->where(function ($query) use ($startDate, $endDate) {
                // Closures that overlap with the date range
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereDate('start_date', '<=', $endDate)
                      ->whereDate('end_date', '>=', $startDate);
                });
            })
            ->where(function ($query) use ($locationId, $allCentresId) {
                // Closures for this specific location OR "All Centres" OR no locations assigned
                $query->whereHas('locations', function ($subQ) use ($locationId) {
                    $subQ->where('locations.id', $locationId);
                })
                ->orWhereHas('locations', function ($subQ) use ($allCentresId) {
                    $subQ->where('locations.id', $allCentresId);
                })
                ->orWhereDoesntHave('locations');
            })
            ->get();

        $result = [];
        foreach ($closures as $closure) {
            // Generate dates for each day in the closure period that falls within the requested range
            $closureStart = Carbon::parse($closure->start_date);
            $closureEnd = Carbon::parse($closure->end_date);
            $rangeStart = Carbon::parse($startDate);
            $rangeEnd = Carbon::parse($endDate);

            // Adjust to the overlapping period
            $effectiveStart = $closureStart->greaterThan($rangeStart) ? $closureStart : $rangeStart;
            $effectiveEnd = $closureEnd->lessThan($rangeEnd) ? $closureEnd : $rangeEnd;

            $currentDate = $effectiveStart->copy();
            while ($currentDate->lessThanOrEqualTo($effectiveEnd)) {
                $result[] = [
                    'id' => $closure->id,
                    'title' => $closure->title,
                    'date' => $currentDate->format('Y-m-d'),
                ];
                $currentDate->addDay();
            }
        }

        return $result;
    }

    /**
     * Check if a time off overlaps with existing time offs for the same resource
     */
    private function checkTimeOffOverlap(
        int $resourceId,
        int $accountId,
        string $startDate,
        ?string $endDate,
        ?string $startTime,
        ?string $endTime,
        ?int $excludeTimeOffId = null,
        ?int $locationId = null
    ): bool {
        $endDate = $endDate ?: $startDate;

        // Get all time offs for this resource that could potentially overlap
        $query = ResourceTimeOff::where('resource_id', $resourceId)
            ->where('account_id', $accountId);

        // Filter by location if provided
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Exclude the current time off if updating
        if ($excludeTimeOffId) {
            $query->where('id', '!=', $excludeTimeOffId);
        }

        $existingTimeOffs = $query->get();

        foreach ($existingTimeOffs as $existing) {
            $existingStartDate = $existing->start_date->format('Y-m-d');
            $existingEndDate = $existing->is_repeat && $existing->repeat_until
                ? $existing->repeat_until->format('Y-m-d')
                : $existingStartDate;

            // Check if date ranges overlap
            if ($startDate <= $existingEndDate && $endDate >= $existingStartDate) {
                // Date ranges overlap, now check time overlap
                if ($startTime && $endTime && $existing->start_time && $existing->end_time) {
                    // Both have times - check if times overlap
                    if ($startTime < $existing->end_time && $endTime > $existing->start_time) {
                        return true; // Overlap found
                    }
                } else {
                    // One or both are full day - any date overlap is a conflict
                    return true;
                }
            }
        }

        return false; // No overlap
    }

    /**
     * Bulk delete shifts for a resource within a date range
     */
    public function bulkDeleteShifts(Request $request): JsonResponse
    {
        if (! Gate::allows('scheduling_shifts.delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $resourceId = $request->input('resource_id');
        $locationId = $request->input('location_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountId = Auth::user()->account_id;

        if (!$resourceId || !$locationId || !$startDate || !$endDate) {
            return $this->errorResponse('Missing required parameters', 400);
        }

        // Parse dates
        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        $endDate = Carbon::parse($endDate)->format('Y-m-d');

        if ($endDate < $startDate) {
            return $this->errorResponse('End date must be after start date', 400);
        }

        // Find the active rota for this resource at this location
        $rota = ResourceHasRota::where('resource_id', $resourceId)
            ->where('location_id', $locationId)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->first();

        if (!$rota) {
            return $this->errorResponse('No active schedule found for this resource', 404);
        }

        // Get shift IDs to delete
        $shiftIds = ResourceHasRotaDays::where('resource_has_rota_id', $rota->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->pluck('id')
            ->toArray();

        if (empty($shiftIds)) {
            return $this->errorResponse('No shifts found in the selected date range', 404);
        }

        // Count appointments linked to these shifts
        $appointmentCount = \DB::table('appointments')
            ->whereIn('resource_has_rota_day_id', $shiftIds)
            ->count();

        // Unlink appointments from these shifts
        if ($appointmentCount > 0) {
            \DB::table('appointments')
                ->whereIn('resource_has_rota_day_id', $shiftIds)
                ->update(['resource_has_rota_day_id' => null]);
        }

        // Delete the shifts
        $deletedCount = ResourceHasRotaDays::whereIn('id', $shiftIds)->forceDelete();

        $message = "Successfully deleted {$deletedCount} shifts";
        if ($appointmentCount > 0) {
            $message .= " ({$appointmentCount} appointments were unlinked)";
        }

        return $this->successResponse($message, [
            'deleted_count' => $deletedCount,
            'unlinked_appointments' => $appointmentCount,
        ]);
    }
}
