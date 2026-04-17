<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Http\Resources\Feedback\FeedbackDatatableResource;
use App\Models\Appointments;
use App\Models\Feedback;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FeedbackService
{
    private const FILTER_KEY = 'feedbacks';

    private const TREATMENT_APPOINTMENT_TYPE = 2;

    private const COMPLETED_STATUS = 2;

    private const TREATMENT_LOOKBACK_DAYS = 7;

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

        $totalRecords = Feedback::whereIn('location_id', ACL::getUserCentres())->count();

        [$displayLength, $displayStart, $pages, $page] = getPaginationElement(
            request(),
            $totalRecords
        );

        $feedbacks = $this->buildDatatableQuery($whereConditions)
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
        $this->ensureNoDuplicateFeedback($appointmentId);

        $appointment = Appointments::findOrFail($appointmentId);
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

    public function update(Feedback $feedback, int $rating): Feedback
    {
        $feedback->update(['rating' => $rating]);

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
            ->whereDate('scheduled_date', '>=', now()->subDays(self::TREATMENT_LOOKBACK_DAYS))
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

    public function getReportData(
        ?int $locationId,
        ?int $doctorId,
        ?int $serviceId,
        string $dateRange,
    ): Collection|array {
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        $baseQuery = Feedback::query()
            ->when($locationId, fn (Builder $q) => $q->where('location_id', $locationId))
            ->when($serviceId, fn (Builder $q) => $q->where('service_id', $serviceId))
            ->when($doctorId, fn (Builder $q) => $q->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$startDate, $endDate]);

        return match (true) {
            // CASE 7: All three filters
            $locationId !== null && $doctorId !== null && $serviceId !== null
                => $this->reportAllFilters($locationId, $doctorId, $serviceId, $startDate, $endDate),

            // CASE 6: service + doctor (no location)
            $serviceId !== null && $doctorId !== null && $locationId === null
                => $this->reportSingleResult($baseQuery, ['doctor_id', 'service_id'], ['doctor', 'service']),

            // CASE 4: location + doctor (no service)
            $locationId !== null && $doctorId !== null && $serviceId === null
                => $this->reportGroupedBy($baseQuery, 'service_id', ['service', 'doctor']),

            // CASE 5: location + service (no doctor)
            $locationId !== null && $serviceId !== null && $doctorId === null
                => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),

            // CASE 1: Only centre
            $locationId !== null && $serviceId === null && $doctorId === null
                => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),

            // CASE 2: Only doctor
            $doctorId !== null && $serviceId === null && $locationId === null
                => $this->reportGroupedBy($baseQuery, 'service_id', ['service']),

            // CASE 3: Only service
            $serviceId !== null && $doctorId === null && $locationId === null
                => $this->reportGroupedBy($baseQuery, 'doctor_id', ['doctor']),

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

        return $where;
    }

    private function buildDatatableQuery(array $whereConditions): Builder
    {
        $query = Feedback::with(['location', 'patient', 'doctor', 'service', 'treatment', 'creator'])
            ->whereIn('feedback.location_id', ACL::getUserCentres());

        if (!empty($whereConditions)) {
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

    private function parseDateRange(string $dateRange): array
    {
        $dates = explode(' - ', $dateRange);
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));

        $endDateParsed = date('Y-m-d', strtotime($dates[1]));
        $today = date('Y-m-d');

        // Always exclude today to match dashboard behavior
        $endDate = $endDateParsed >= $today
            ? date('Y-m-d 23:59:59', strtotime('yesterday'))
            : date('Y-m-d 23:59:59', strtotime($dates[1]));

        return [$startDate, $endDate];
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

    private function reportAllFilters(
        int $locationId,
        int $doctorId,
        int $serviceId,
        string $startDate,
        string $endDate,
    ): array {
        $feedback = Feedback::where('location_id', $locationId)
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
            'services' => Services::where('parent_id', 0)
                ->where('active', 1)
                ->where('slug', '!=', 'all')
                ->where('name', 'NOT LIKE', '%refund%')
                ->where('name', 'NOT LIKE', '%settlement%')
                ->get(),
        ];
    }

    public function getFutureTreatmentsData(?string $centreId, ?string $serviceId): array
    {
        $startDate = \Illuminate\Support\Carbon::today()->startOfDay();
        $endDate = \Illuminate\Support\Carbon::today()->addDays(6)->endOfDay();

        $serviceIds = [];
        if ($serviceId) {
            $serviceIds[] = $serviceId;

            $childServices = \Illuminate\Support\Facades\DB::table('services')
                ->where('parent_id', $serviceId)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();

            $serviceIds = array_merge($serviceIds, $childServices);
        }

        $appointments = \Illuminate\Support\Facades\DB::table('appointments')
            ->join('users', 'appointments.patient_id', '=', 'users.id')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->join('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->where('appointments.appointment_type_id', 2)
            ->where('appointments.appointment_status_id', 1)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate])
            ->when($centreId, fn ($query) => $query->where('appointments.location_id', $centreId))
            ->when(!empty($serviceIds), fn ($query) => $query->whereIn('appointments.service_id', $serviceIds))
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
                'centre_id' => $centreId,
                'service_id' => $serviceId,
            ],
        ];
    }
}
