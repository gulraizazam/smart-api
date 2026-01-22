<?php

namespace App\Services\Treatment;

use App\Exceptions\TreatmentException;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Appointments;
use App\Models\Cities;
use App\Models\Doctors;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class TreatmentService
{
    const CACHE_TTL = 3600; // 1 hour
    const FILTER_KEY = 'treatments';

    /**
     * Get treatment datatable data with optimized queries
     */
    public function getDatatableData(Request $request): array
    {
        $filters = $this->processFilters($request);
        $orderBy = $filters['order_by'];
        $order = $filters['order'];

        // Get treatment type ID (cached)
        $treatmentTypeId = $this->getTreatmentTypeId();

        // Build base query conditions
        $baseConditions = $this->buildBaseConditions($filters);

        // Get total count using optimized query
        $totalRecords = $this->getRecordsCount($baseConditions, $filters, $treatmentTypeId);

        // Get pagination parameters
        [$perPage, $offset, $pages, $page] = getPaginationElement($request, $totalRecords);

        // Get appointments with eager loading to prevent N+1
        $appointments = $this->getAppointments(
            $baseConditions,
            $filters,
            $treatmentTypeId,
            $orderBy,
            $order,
            $perPage,
            $offset
        );

        // Get lookup data (cached)
        $lookupData = $this->getLookupData();

        // Transform data for response
        $data = $this->transformAppointments($appointments, $lookupData);

        // Build response
        return [
            'data' => $data,
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $perPage,
                'total' => $totalRecords,
                'sort' => $order,
            ],
            'active_filters' => Filters::all(Auth::user()->id, self::FILTER_KEY),
            'filter_values' => $this->getFilterValues(),
            'permissions' => $this->getPermissions(),
        ];
    }

    /**
     * Process and store filters from request
     */
    protected function processFilters(Request $request): array
    {
        $userId = Auth::user()->id;
        $filters = getFilters($request->all());

        // Handle sorting
        if ($request->has('sort')) {
            [$orderBy, $order] = getSortBy($request, 'appointments.created_at', 'DESC', 'appointments');
        } else {
            $orderBy = 'appointments.created_at';
            $order = 'desc';
        }

        Filters::put($userId, self::FILTER_KEY, 'order_by', $orderBy);
        Filters::put($userId, self::FILTER_KEY, 'order', $order);

        // Process date range filter
        $startDateTime = null;
        $endDateTime = null;
        if (hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            $startDateTime = date('Y-m-d H:i:s', strtotime($dateRange[0]));
            $endDateString = new \DateTime($dateRange[1]);
            $endDateString->setTime(23, 59, 0);
            $endDateTime = $endDateString->format('Y-m-d H:i:s');
            Filters::put($userId, self::FILTER_KEY, 'created_at', $filters['created_at']);
        }

        // Store individual filters
        $filterMappings = [
            'patient_id' => fn($v) => GeneralFunctions::patientSearch($v),
            'phone' => fn($v) => $v,
            'date_from' => fn($v) => $v . ' 00:00:00',
            'date_to' => fn($v) => $v . ' 23:59:59',
            'doctor_id' => fn($v) => $v,
            'region_id' => fn($v) => $v,
            'city_id' => fn($v) => $v,
            'location_id' => fn($v) => $v,
            'service_id' => fn($v) => $v,
            'created_by' => fn($v) => $v,
            'converted_by' => fn($v) => $v,
            'updated_by' => fn($v) => $v,
            'appointment_status_id' => fn($v) => $v,
            'appointment_type_id' => fn($v) => $v,
            'consultancy_type' => fn($v) => $v,
            'name' => fn($v) => $v,
        ];

        foreach ($filterMappings as $key => $transform) {
            if (hasFilter($filters, $key)) {
                Filters::put($userId, self::FILTER_KEY, $key, $transform($filters[$key]));
            }
        }

        return array_merge($filters, [
            'order_by' => $orderBy,
            'order' => $order,
            'start_date_time' => $startDateTime,
            'end_date_time' => $endDateTime,
        ]);
    }

    /**
     * Build base query conditions
     */
    protected function buildBaseConditions(array $filters): array
    {
        $where = [];

        if (hasFilter($filters, 'patient_id')) {
            $where[] = ['patient_id' => GeneralFunctions::patientSearch($filters['patient_id'])];
        }

        if (hasFilter($filters, 'phone')) {
            $where[] = ['users.phone', 'like', '%' . $filters['phone'] . '%'];
        }

        if (hasFilter($filters, 'date_from')) {
            $where[] = ['appointments.scheduled_date', '>=', $filters['date_from'] . ' 00:00:00'];
        }

        if (hasFilter($filters, 'date_to')) {
            $where[] = ['appointments.scheduled_date', '<=', $filters['date_to'] . ' 23:59:59'];
        }

        if (hasFilter($filters, 'doctor_id')) {
            $where[] = ['doctor_id' => $filters['doctor_id']];
        }

        if (hasFilter($filters, 'region_id')) {
            $where[] = ['region_id' => $filters['region_id']];
        }

        if (hasFilter($filters, 'city_id')) {
            $where[] = ['city_id' => $filters['city_id']];
        }

        if (hasFilter($filters, 'created_by')) {
            $where[] = ['appointments.created_by' => $filters['created_by']];
        }

        if (hasFilter($filters, 'converted_by')) {
            $where[] = ['appointments.converted_by' => $filters['converted_by']];
        }

        if (hasFilter($filters, 'updated_by')) {
            $where[] = ['appointments.updated_by' => $filters['updated_by']];
        }

        if (hasFilter($filters, 'appointment_type_id')) {
            $where[] = ['appointments.appointment_type_id' => $filters['appointment_type_id']];
        }

        if (hasFilter($filters, 'consultancy_type')) {
            $where[] = ['appointments.consultancy_type' => $filters['consultancy_type']];
        }

        if (isset($filters['start_date_time']) && $filters['start_date_time']) {
            $where[] = ['appointments.created_at', '>=', $filters['start_date_time']];
        }

        if (isset($filters['end_date_time']) && $filters['end_date_time']) {
            $where[] = ['appointments.created_at', '<=', $filters['end_date_time']];
        }

        return $where;
    }

    /**
     * Get status IDs for filtering (handles arrived + converted logic)
     */
    protected function getStatusIdsForFilter(array $filters): array
    {
        if (!hasFilter($filters, 'appointment_status_id')) {
            return [];
        }

        $accountId = Auth::user()->account_id;
        $selectedStatus = AppointmentStatuses::find($filters['appointment_status_id']);

        if ($selectedStatus && $selectedStatus->is_arrived == 1) {
            $convertedStatus = AppointmentStatuses::where([
                'account_id' => $accountId,
                'is_converted' => 1
            ])->first();

            if ($convertedStatus) {
                return [$filters['appointment_status_id'], $convertedStatus->id];
            }
        }

        return [$filters['appointment_status_id']];
    }

    /**
     * Get service IDs for filtering
     */
    protected function getServiceIdsForFilter(array $filters): array
    {
        if (!hasFilter($filters, 'service_id')) {
            return [];
        }

        $serviceId = GeneralFunctions::getServiceId($filters['service_id']);
        $service = Services::find($serviceId);

        if (!$service) {
            return [];
        }

        if ($service->parent_id == 0) {
            return ($service->id == 13)
                ? Services::pluck('id')->toArray()
                : Services::where('parent_id', $service->id)->pluck('id')->toArray();
        }

        return [$service->id];
    }

    /**
     * Get total records count
     */
    protected function getRecordsCount(array $where, array $filters, int $treatmentTypeId): int
    {
        if (!Gate::allows('appointments_services')) {
            return 0;
        }

        $query = Appointments::query()
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })
            ->where('appointments.appointment_type_id', '=', $treatmentTypeId)
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->where('appointment_type_id', config('constants.appointment_type_service'));

        $this->applyFiltersToQuery($query, $where, $filters);

        return $query->count();
    }

    /**
     * Get appointments with eager loading
     */
    protected function getAppointments(
        array $where,
        array $filters,
        int $treatmentTypeId,
        string $orderBy,
        string $order,
        int $limit,
        int $offset
    ): \Illuminate\Database\Eloquent\Collection {
        if (!Gate::allows('appointments_services')) {
            return collect();
        }

        $invoiceStatus = $this->getPaidInvoiceStatus();

        $query = Appointments::query()
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })
            ->with([
                'patient:id,name,phone',
                'doctor:id,name',
                'city:id,name',
                'location:id,name',
                'service:id,name',
                'appointment_type:id,name',
                'appointment_status:id,name,parent_id',
                'invoice' => function ($q) use ($invoiceStatus) {
                    $q->where('invoice_status_id', $invoiceStatus->id ?? 0);
                }
            ])
            ->where('appointments.appointment_type_id', '=', $treatmentTypeId)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->where('appointment_type_id', config('constants.appointment_type_service'));

        $this->applyFiltersToQuery($query, $where, $filters);

        // Handle name filter with OR condition
        if (hasFilter($filters, 'name')) {
            $query->where(function ($q) use ($filters) {
                $q->where('users.name', 'like', '%' . $filters['name'] . '%')
                    ->orWhere('appointments.name', 'like', '%' . $filters['name'] . '%');
            });
        }

        // Fix order by for name column
        if ($orderBy == 'name') {
            $orderBy = 'appointments.name';
        }

        return $query->select([
                'appointments.*',
                'users.phone',
                'appointments.name as patient_name',
                'appointments.id as app_id',
                'appointments.created_by as app_created_by',
                'appointments.updated_by as app_updated_by',
                'appointments.created_at as app_created_at'
            ])
            ->limit($limit)
            ->offset($offset)
            ->orderBy('appointments.created_at', 'DESC')
            ->get();
    }

    /**
     * Apply filters to query builder
     */
    protected function applyFiltersToQuery($query, array $where, array $filters): void
    {
        if (count($where)) {
            $query->where($where);
        }

        $statusIds = $this->getStatusIdsForFilter($filters);
        if (count($statusIds)) {
            $query->whereIn('appointments.base_appointment_status_id', $statusIds);
        }

        $serviceIds = $this->getServiceIdsForFilter($filters);
        if (count($serviceIds)) {
            $query->whereIn('service_id', $serviceIds);
        }

        if (hasFilter($filters, 'location_id')) {
            $ids = explode(',', $filters['location_id']);
            if (count($ids) > 1) {
                $query->whereIn('location_id', $ids);
            } else {
                $query->where('location_id', $ids);
            }
        }
    }

    /**
     * Transform appointments to response format
     */
    protected function transformAppointments($appointments, array $lookupData): array
    {
        $data = [];
        $canViewContact = Gate::allows('contact');
        $regions = $lookupData['regions'];
        $users = $lookupData['users'];
        $appointmentStatuses = $lookupData['appointment_statuses'];
        $unscheduledStatus = $lookupData['unscheduled_status'];
        $cancelledStatus = $lookupData['cancelled_status'];

        foreach ($appointments as $appointment) {
            $consultancyType = match ($appointment->consultancy_type) {
                'in_person' => 'In Person',
                'virtual' => 'Virtual',
                default => '',
            };

            $phoneNumber = $canViewContact ? ($appointment->patient->phone ?? '') : '***********';

            $scheduledDate = $appointment->scheduled_date
                ? Carbon::parse($appointment->scheduled_date)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time)->format('h:i A')
                : '-';

            $appointmentStatusName = '';
            if ($appointment->appointment_status_id && $appointment->appointment_status) {
                $appointmentStatusName = $appointment->appointment_status->parent_id
                    ? ($appointmentStatuses[$appointment->appointment_status->parent_id]->name ?? $appointment->appointment_status->name)
                    : $appointment->appointment_status->name;
            }

            $data[] = [
                'id' => $appointment->app_id,
                'patient_id' => $appointment->patient_id,
                'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
                'name' => $appointment->patient_name ?: ($appointment->patient->name ?? ''),
                'phone' => $phoneNumber,
                'scheduled_date' => $scheduledDate,
                'apt_scheduled_date' => $appointment->scheduled_date,
                'doctor_id' => $appointment->doctor->name ?? 'N/A',
                'doctorId' => $appointment->doctor->id ?? 0,
                'region_id' => isset($regions[$appointment->region_id]) ? $regions[$appointment->region_id]->name : 'N/A',
                'city_id' => $appointment->city->name ?? 'N/A',
                'cityId' => $appointment->city_id ?? 0,
                'location_id' => $appointment->location->name ?? 'N/A',
                'locationId' => $appointment->location_id ?? 'N/A',
                'service_id' => $appointment->service->name ?? 'N/A',
                'resource_id' => $appointment->resource_id ?? 0,
                'appointment_type_id' => $appointment->appointment_type->name ?? '',
                'appointment_type' => $appointment->appointment_type->id ?? 0,
                'consultancy_type' => $consultancyType,
                'created_at' => Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'),
                'created_by' => isset($users[$appointment->app_created_by]) ? $users[$appointment->app_created_by]->name : 'N/A',
                'converted_by' => isset($users[$appointment->converted_by]) ? $users[$appointment->converted_by]->name : 'N/A',
                'updated_by' => isset($users[$appointment->app_updated_by]) ? $users[$appointment->app_updated_by]->name : 'N/A',
                'unscheduled_appointment_status' => $unscheduledStatus,
                'cancelled_appointment_status' => $cancelledStatus,
                'appointment_status_id' => $appointmentStatusName,
                'appointment_status' => $appointment->appointment_status_id,
                'invoice_id' => $appointment->invoice->id ?? 0,
                'invoice' => $appointment->invoice ?? null,
            ];
        }

        return $data;
    }

    /**
     * Get lookup data with caching
     */
    protected function getLookupData(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "treatment_lookup_data_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return [
                'regions' => Regions::getAllRecordsDictionary($accountId),
                'users' => User::getAllRecords($accountId)->getDictionary(),
                'appointment_statuses' => AppointmentStatuses::getAllRecordsDictionary($accountId),
                'unscheduled_status' => AppointmentStatuses::getUnScheduledStatusOnly($accountId, ['id']),
                'cancelled_status' => AppointmentStatuses::getCancelledStatusOnly($accountId),
            ];
        });
    }

    /**
     * Get filter dropdown values with caching
     */
    protected function getFilterValues(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "treatment_filter_values_{$accountId}_" . md5(json_encode(ACL::getUserCentres()));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            $regions = Regions::getActiveSorted(ACL::getUserRegions());
            $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
            $doctors = Doctors::getActiveOnly(ACL::getUserCentres());
            $locations = Locations::getActiveSorted(ACL::getUserCentres());
            $services = GeneralFunctions::ServicesTreeList();

            $appointmentStatuses = AppointmentStatuses::getAllParentRecords($accountId);
            if ($appointmentStatuses) {
                $appointmentStatuses = $appointmentStatuses->pluck('name', 'id');
            }

            $appointmentTypes = $this->getAppointmentTypes();
            $users = User::getAllRecords($accountId)->pluck('name', 'id');

            return [
                'cities' => $cities,
                'regions' => $regions,
                'users' => $users,
                'doctors' => $doctors,
                'locations' => $locations,
                'services' => $services,
                'appointment_statuses' => $appointmentStatuses,
                'appointment_types' => $appointmentTypes,
                'consultancy_types' => config('constants.consultancy_type_array'),
            ];
        });
    }

    /**
     * Get appointment types based on permissions
     */
    protected function getAppointmentTypes()
    {
        $canConsultancy = Gate::allows('appointments_consultancy');
        $canServices = Gate::allows('appointments_services');

        if ($canConsultancy && $canServices) {
            return AppointmentTypes::get()->pluck('name', 'id');
        }

        if ($canConsultancy) {
            return AppointmentTypes::where('slug', 'consultancy')->get()->pluck('name', 'id');
        }

        if ($canServices) {
            return AppointmentTypes::where('slug', 'treatment')->get()->pluck('name', 'id');
        }

        return [];
    }

    /**
     * Get permissions for the current user
     */
    protected function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('appointments_edit'),
            'consultancy' => Gate::allows('appointments_consultancy'),
            'treatment' => Gate::allows('appointments_services'),
            'delete' => Gate::allows('appointments_destroy'),
            'active' => Gate::allows('appointments_active'),
            'inactive' => Gate::allows('appointments_inactive'),
            'create' => Gate::allows('appointments_create'),
            'log' => Gate::allows('appointments_log'),
            'status' => Gate::allows('appointments_appointment_status'),
            'invoice' => Gate::allows('appointments_invoice'),
            'invoice_display' => Gate::allows('appointments_invoice_display'),
            'image_manage' => Gate::allows('appointments_image_manage'),
            'measurement_manage' => Gate::allows('appointments_measurement_manage'),
            'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
            'plans_create' => Gate::allows('appointments_plans_create'),
            'patient_card' => Gate::allows('appointments_patient_card'),
            'contact' => Gate::allows('contact'),
            'add_feedback' => Gate::allows('feedbacks_create'),
        ];
    }

    /**
     * Get treatment type ID (cached)
     */
    protected function getTreatmentTypeId(): int
    {
        return Cache::remember('treatment_type_id', self::CACHE_TTL, function () {
            $treatmentType = AppointmentTypes::where('slug', 'treatment')->first();
            return $treatmentType ? $treatmentType->id : 0;
        });
    }

    /**
     * Get paid invoice status (cached)
     */
    protected function getPaidInvoiceStatus()
    {
        return Cache::remember('paid_invoice_status', self::CACHE_TTL, function () {
            return InvoiceStatuses::where('slug', 'paid')->first();
        });
    }

    /**
     * Clear treatment-related caches
     */
    public function clearCache(): void
    {
        $accountId = Auth::user()->account_id;
        Cache::forget("treatment_lookup_data_{$accountId}");
        Cache::forget("treatment_filter_values_{$accountId}_" . md5(json_encode(ACL::getUserCentres())));
        Cache::forget('treatment_type_id');
        Cache::forget('paid_invoice_status');
    }
}
