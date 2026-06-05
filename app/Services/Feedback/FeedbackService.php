<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Http\Resources\Feedback\FeedbackDatatableResource;
use App\Models\Appointments;
use App\Models\DoctorHasLocations;
use App\Models\Feedback;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FeedbackService
{
    use ParsesDateRange;

    private const FILTER_KEY = 'feedbacks';

    private const TREATMENT_APPOINTMENT_TYPE = 2;

    private const COMPLETED_STATUS = 2;

    private const DOCTOR_USER_TYPE_ID = 5;

    private const ROOT_PARENT_ID = 0;

    // =========================================================================
    // Datatable
    // =========================================================================

    public function getDatatableData(array $requestData): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;

        $filters = getFilters($requestData);
        $applyFilter = checkFilters($filters, self::FILTER_KEY);

        $whereConditions = $this->buildFilterConditions($filters, $applyFilter, $userId);

        // Count WITH the active filters so the total + pagination reflect the
        // filtered set (previously this counted every feedback, breaking the
        // page count whenever a patient/doctor/date filter was applied).
        $baseQuery = $this->buildDatatableQuery($whereConditions);
        $totalRecords = (clone $baseQuery)->count();

        [$displayLength, $displayStart, $pages, $page] = getPaginationElement(
            request(),
            $totalRecords
        );

        $feedbacks = $baseQuery
            ->limit($displayLength)
            ->offset($displayStart)
            ->orderByDesc('id')
            ->get();

        $usersLookup = User::getAllRecords($accountId)->getDictionary();
        FeedbackDatatableResource::$usersLookup = $usersLookup;

        $records = [];
        $records['data'] = FeedbackDatatableResource::collection($feedbacks)->resolve();
        $records['meta'] = [
            'field' => 'created_at',
            'page' => $page,
            'pages' => $pages,
            'perpage' => $displayLength,
            'total' => $totalRecords,
        ];

        $records = $this->attachFiltersData($records);

        $records['permissions'] = [
            'edit' => Gate::allows('feedbacks_edit'),
            'delete' => Gate::allows('feedbacks_delete'),
            'create' => Gate::allows('feedbacks_create'),
        ];

        return $records;
    }

    // =========================================================================
    // CRUD
    // =========================================================================

    public function store(int $appointmentId, int $rating, ?string $comment = null): Feedback
    {
        $appointment = Appointments::findOrFail($appointmentId);

        $this->ensureAppointmentIsArrivedTreatment($appointment);
        $this->ensureNoDuplicateFeedback($appointmentId);

        $patient = Patients::findOrFail($appointment->patient_id);
        $treatmentService = Services::findOrFail($appointment->service_id);

        $feedback = Feedback::create([
            'patient_id' => $appointment->patient_id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'service_id' => $treatmentService->parent_id,
            'treatment_id' => $appointment->service_id,
            'appointment_id' => $appointmentId,
            'created_by' => Auth::id(),
            'location_id' => $appointment->location_id,
            'doctor_id' => $appointment->doctor_id,
            'rating' => $rating,
            'comment' => $comment,
        ]);

        $this->logFeedbackActivity($feedback, $appointment, $patient, $treatmentService);

        return $feedback;
    }

    public function update(Feedback $feedback, int $rating, ?string $comment = null): Feedback
    {
        $feedback->update([
            'rating' => $rating,
            'comment' => $comment,
        ]);

        return $feedback;
    }

    public function destroy(Feedback $feedback): void
    {
        $feedback->delete();
    }

    // =========================================================================
    // Treatment Lookup
    // =========================================================================

    public function getAvailableTreatments(int $patientId): Collection
    {
        return Appointments::with('service')
            ->where('patient_id', $patientId)
            ->where('appointment_type_id', self::TREATMENT_APPOINTMENT_TYPE)
            ->where('appointment_status_id', self::COMPLETED_STATUS)
            ->doesntHave('feedback')
            ->orderByDesc('scheduled_date')
            ->get();
    }

    public function getTreatmentInfo(int $treatmentId): ?Appointments
    {
        return Appointments::with(['doctor', 'location'])
            ->where('id', $treatmentId)
            ->where('appointment_type_id', self::TREATMENT_APPOINTMENT_TYPE)
            ->where('appointment_status_id', self::COMPLETED_STATUS)
            ->first();
    }

    // =========================================================================
    // Report
    // =========================================================================

    /**
     * @param  int[]|null  $locationIds
     */
    public function getReportData(
        ?array $locationIds,
        ?int $doctorId,
        ?int $serviceId,
        string $dateRange,
    ): Collection|array {
        [$startDate, $endDate] = $this->parseReportDateRange($dateRange);

        $hasLocation = ! empty($locationIds);

        // Mirror the admin-home Doctor Feedback widget's scope: feedback is
        // counted only for doctors currently allocated (is_allocated=1) to
        // the selected centres AND active in users. Without this clamp the
        // report inflates ratings/counts with rows from doctors who have
        // since been transferred or deactivated, producing different numbers
        // than the dashboard for the same date range.
        $allocatedDoctorIds = $hasLocation
            ? $this->getAllocatedActiveDoctorIds($locationIds)
            : null;

        if ($hasLocation && empty($allocatedDoctorIds)) {
            return new Collection();
        }

        $baseQuery = Feedback::query()
            ->when($hasLocation, fn (Builder $q) => $q->whereIn('location_id', $locationIds))
            ->when($allocatedDoctorIds !== null, fn (Builder $q) => $q->whereIn('doctor_id', $allocatedDoctorIds))
            ->when($serviceId, fn (Builder $q) => $q->where('service_id', $serviceId))
            ->when($doctorId, fn (Builder $q) => $q->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$startDate, $endDate]);

        return match (true) {
            // CASE 7: All three filters
            $hasLocation && $doctorId !== null && $serviceId !== null => $this->reportAllFilters($locationIds, $doctorId, $serviceId, $startDate, $endDate, $allocatedDoctorIds),

            // CASE 6: service + doctor (no location)
            $serviceId !== null && $doctorId !== null && ! $hasLocation => $this->reportSingleResult($baseQuery, ['doctor_id', 'service_id'], ['doctor', 'service']),

            // CASE 4: location + doctor (no service)
            $hasLocation && $doctorId !== null && $serviceId === null => $this->reportGroupedBy($baseQuery, 'service_id', ['service', 'doctor']),

            // CASE 5: location + service (no doctor)
            $hasLocation && $serviceId !== null && $doctorId === null => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),

            // CASE 1: Only centre
            $hasLocation && $serviceId === null && $doctorId === null => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),

            // CASE 2: Only doctor
            $doctorId !== null && $serviceId === null && ! $hasLocation => $this->reportGroupedBy($baseQuery, 'service_id', ['service']),

            // CASE 3: Only service
            $serviceId !== null && $doctorId === null && ! $hasLocation => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),

            // Default: group by doctor
            default => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),
        };
    }

    public function getReportPageData(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'doctors' => User::getAllRecords($accountId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->where('active', 1)
                ->getDictionary(),
            'locations' => Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId),
            'services' => Services::where('parent_id', self::ROOT_PARENT_ID)->where('active', 1)->get(),
            'feedbacks' => Feedback::select('doctor_id')
                ->selectRaw('AVG(CAST(rating AS DECIMAL(4,2))) as avg_rating, COUNT(*) as total_feedbacks')
                ->groupBy('doctor_id')
                ->with('doctor')
                ->get(),
        ];
    }

    // =========================================================================
    // Private: Filter Building
    // =========================================================================

    private function buildFilterConditions(array $filters, bool $applyFilter, int $userId): array
    {
        $where = [];
        $filterableFields = ['patient_id', 'location_id', 'doctor_id'];

        foreach ($filterableFields as $field) {
            if (hasFilter($filters, $field)) {
                $where[] = [$field, '=', $filters[$field]];
                Filters::put($userId, self::FILTER_KEY, $field, $filters[$field]);
            } elseif ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, $field);
            } else {
                $savedValue = Filters::get($userId, self::FILTER_KEY, $field);
                if ($savedValue) {
                    $where[] = [$field, '=', $savedValue];
                }
            }
        }

        // `created_at` is a date RANGE ("YYYY-MM-DD - YYYY-MM-DD"), handled
        // separately from the equality fields above.
        $createdRange = null;
        if (hasFilter($filters, 'created_at')) {
            $createdRange = (string) $filters['created_at'];
            Filters::put($userId, self::FILTER_KEY, 'created_at', $createdRange);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        } else {
            $createdRange = Filters::get($userId, self::FILTER_KEY, 'created_at') ?: null;
        }

        if ($createdRange) {
            $parts = array_map('trim', explode(' - ', $createdRange));
            $start = $parts[0] ?? '';
            $end = ($parts[1] ?? '') !== '' ? $parts[1] : $start;
            if ($start !== '') {
                $where[] = ['created_at', '>=', $start.' 00:00:00'];
                $where[] = ['created_at', '<=', $end.' 23:59:59'];
            }
        }

        return $where;
    }

    private function buildDatatableQuery(array $whereConditions): Builder
    {
        $query = Feedback::with(['location', 'patient', 'doctor', 'service', 'treatment', 'creator'])
            ->whereIn('feedback.location_id', ACL::getUserCentres());

        if (! empty($whereConditions)) {
            $query->where($whereConditions);
        }

        return $query;
    }

    private function attachFiltersData(array $records): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;

        $storedFilters = Filters::all($userId, self::FILTER_KEY);

        if (isset($storedFilters['created_from'])) {
            $storedFilters['created_from'] = date('Y-m-d', strtotime($storedFilters['created_from']));
        }
        if (isset($storedFilters['created_to'])) {
            $storedFilters['created_to'] = date('Y-m-d', strtotime($storedFilters['created_to']));
        }

        $records['filter_values'] = [
            'users' => User::getAllActiveRecords($accountId)->pluck('name', 'id'),
        ];
        $records['active_filters'] = $storedFilters;

        return $records;
    }

    // =========================================================================
    // Private: Report Helpers
    // =========================================================================

    /**
     * Produce the [start, end] datetime pair for a feedback report window.
     * Dashboard reports always exclude the current day (and anything later),
     * so an end date at or after today is clamped to yesterday 23:59:59.
     */
    private function parseReportDateRange(string $dateRange): array
    {
        [$startDate, $endDate] = self::excludeTodayFromRange(self::parseDateRange($dateRange));

        return [
            $startDate.' 00:00:00',
            $endDate.' 23:59:59',
        ];
    }

    private function reportGroupedBy(Builder $query, string $groupField, array $relations): Collection
    {
        return $query->select($groupField)
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy($groupField)
            ->with($relations)
            ->get();
    }

    private function reportSingleResult(Builder $query, array $selectFields, array $relations): array
    {
        $record = $query->select($selectFields)
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy($selectFields)
            ->with($relations)
            ->first();

        return $record ? [$record] : [];
    }

    /**
     * @param  int[]       $locationIds
     * @param  int[]|null  $allocatedDoctorIds  Restricts the rating set to
     *                                          doctors currently allocated
     *                                          to $locationIds (and active),
     *                                          matching the dashboard widget.
     */
    private function reportAllFilters(
        array $locationIds,
        int $doctorId,
        int $serviceId,
        string $startDate,
        string $endDate,
        ?array $allocatedDoctorIds = null,
    ): array {
        if ($allocatedDoctorIds !== null && ! in_array($doctorId, $allocatedDoctorIds, true)) {
            return [];
        }

        $feedback = Feedback::whereIn('location_id', $locationIds)
            ->where('doctor_id', $doctorId)
            ->where('service_id', $serviceId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('doctor_id', 'service_id')
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('doctor_id', 'service_id')
            ->with(['doctor', 'service'])
            ->first();

        return $feedback?->total_feedbacks > 0 ? [$feedback] : [];
    }

    /**
     * Doctor IDs that are (a) marked is_allocated=1 in doctor_has_locations
     * for the given centres and (b) active in users. Mirrors the scoping in
     * {@see \App\Services\Dashboard\DashboardChartService::getDoctorWiseFeedback}
     * so the Doctor Feedback report matches the admin-home widget exactly.
     *
     * @param  int[]  $locationIds
     * @return int[]
     */
    private function getAllocatedActiveDoctorIds(array $locationIds): array
    {
        $allocated = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locationIds)
            ->distinct()
            ->pluck('user_id')
            ->all();

        if (empty($allocated)) {
            return [];
        }

        return User::whereIn('id', $allocated)
            ->where('active', 1)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    // =========================================================================
    // Private: Activity & Validation
    // =========================================================================

    private function ensureNoDuplicateFeedback(int $appointmentId): void
    {
        $exists = Feedback::where('appointment_id', $appointmentId)->exists();

        if ($exists) {
            abort(422, 'Feedback already added for this appointment.');
        }
    }

    /**
     * Feedback is only valid on an arrived treatment (i.e. a procedure that
     * actually took place). Rejects any appointment that is not a treatment
     * or has not reached the "arrived" status — mirrors the eligibility
     * shown on the management-dashboard Branch Feedback panel.
     */
    private function ensureAppointmentIsArrivedTreatment(Appointments $appointment): void
    {
        $isTreatment = (int) $appointment->appointment_type_id === 2;
        $isArrived = (int) $appointment->appointment_status_id === 2;

        if (! $isTreatment) {
            abort(422, 'Feedback can only be recorded for treatment appointments.');
        }

        if (! $isArrived) {
            abort(422, 'Feedback can only be recorded once the treatment has arrived.');
        }
    }

    private function logFeedbackActivity(
        Feedback $feedback,
        Appointments $appointment,
        Patients $patient,
        Services $service,
    ): void {
        $location = $appointment->location_id ? Locations::find($appointment->location_id) : null;

        ActivityLogger::logFeedbackAdded($feedback, $appointment, $patient, $service, $location);
    }

    public function getFutureTreatmentsPageData(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'locations' => Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId),
            'services' => Services::where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
                ->where('active', 1)
                ->where('slug', '!=', 'all')
                ->where('name', 'NOT LIKE', '%refund%')
                ->where('name', 'NOT LIKE', '%settlement%')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @param  int[]|null  $centreIds
     */
    public function getFutureTreatmentsData(?array $centreIds, ?string $serviceId): array
    {
        $startDate = Carbon::today()->startOfDay();
        $endDate = Carbon::today()->addDays(6)->endOfDay();

        $serviceIds = [];
        if ($serviceId) {
            $serviceIds[] = $serviceId;

            $childServices = DB::table('services')
                ->where('parent_id', $serviceId)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();

            $serviceIds = array_merge($serviceIds, $childServices);
        }

        $hasCentre = ! empty($centreIds);

        $appointments = DB::table('appointments')
            ->join('users', 'appointments.patient_id', '=', 'users.id')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->join('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->where('appointments.appointment_type_id', 2)
            ->where('appointments.appointment_status_id', 1)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate])
            ->when($hasCentre, fn ($query) => $query->whereIn('appointments.location_id', $centreIds))
            ->when(! empty($serviceIds), fn ($query) => $query->whereIn('appointments.service_id', $serviceIds))
            ->select(
                'users.name as patient_name',
                'services.name as service_name',
                'appointments.scheduled_date',
                'appointment_statuses.name as appointment_status'
            )
            ->orderBy('appointments.scheduled_date', 'asc')
            ->get();

        return [
            'appointments' => $appointments,
            'filters' => [
                'start_date' => $startDate->format('d M Y'),
                'end_date' => $endDate->format('d M Y'),
                'centre_id' => $centreIds,
                'service_id' => $serviceId,
            ],
        ];
    }
}
