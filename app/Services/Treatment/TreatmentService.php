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
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use App\Models\Leads;
use App\Models\Patients;
use App\Models\Resources;
use App\Models\LeadStatuses;
use App\Models\LeadsServices;
use App\Models\MachineTypeHasServices;
use App\Models\DoctorHasServices;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Helpers\ActivityLogger;
use App\Jobs\IndexSingleAppointmentJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TreatmentService
{
    const CACHE_TTL = 3600; // 1 hour
    const FILTER_KEY = 'treatments';

    /**
     * Get treatment datatable data with optimized queries
     * Supports optional patient_id parameter for patient-specific filtering
     */
    public function getDatatableData(Request $request, $patientId = null): array
    {
        $filters = $this->processFilters($request);
        $orderBy = $filters['order_by'];
        $order = $filters['order'];

        // Get treatment type ID (cached)
        $treatmentTypeId = $this->getTreatmentTypeId();

        // Build base query conditions
        $baseConditions = $this->buildBaseConditions($filters, $patientId);

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
     * Supports optional patient_id parameter for patient card context
     */
    protected function buildBaseConditions(array $filters, $patientId = null): array
    {
        $where = [];

        // If patient_id is provided directly (patient card context), use it
        if ($patientId) {
            $where[] = ['patient_id', '=', $patientId];
        } elseif (hasFilter($filters, 'patient_id')) {
            $where[] = ['patient_id', '=', GeneralFunctions::patientSearch($filters['patient_id'])];
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
            $where[] = ['doctor_id', '=', $filters['doctor_id']];
        }

        if (hasFilter($filters, 'region_id')) {
            $where[] = ['region_id', '=', $filters['region_id']];
        }

        if (hasFilter($filters, 'city_id')) {
            $where[] = ['city_id', '=', $filters['city_id']];
        }

        if (hasFilter($filters, 'created_by')) {
            $where[] = ['appointments.created_by', '=', $filters['created_by']];
        }

        if (hasFilter($filters, 'converted_by')) {
            $where[] = ['appointments.converted_by', '=', $filters['converted_by']];
        }

        if (hasFilter($filters, 'updated_by')) {
            $where[] = ['appointments.updated_by', '=', $filters['updated_by']];
        }

        if (hasFilter($filters, 'appointment_type_id')) {
            $where[] = ['appointments.appointment_type_id', '=', $filters['appointment_type_id']];
        }

        if (hasFilter($filters, 'consultancy_type')) {
            $where[] = ['appointments.consultancy_type', '=', $filters['consultancy_type']];
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
            'status' => Gate::allows('treatments_appointment_status'),
            'schedule_edit' => Gate::allows('update_treatment_schedule'),
            'invoice' => Gate::allows('appointments_invoice'),
            'invoice_display' => Gate::allows('appointments_invoice_display'),
            'image_manage' => Gate::allows('appointments_image_manage'),
            'measurement_manage' => Gate::allows('appointments_measurement_manage'),
            'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
            'plans_create' => Gate::allows('appointments_plans_create'),
            'patient_card' => Gate::allows('appointments_patient_card'),
            'contact' => Gate::allows('contact'),
            'add_feedback' => Gate::allows('feedbacks_create'),
            'can_edit_doctor' => Gate::allows('can_edit_doctor'),
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

    /**
     * Store a new treatment appointment
     * 
     * @param Request $request
     * @return array
     * @throws TreatmentException
     */
    public function store(Request $request): array
    {
        // Validate request
        $validation = $this->validateStoreRequest($request);
        if ($validation['fails']) {
            throw new TreatmentException($validation['message'], 422);
        }

        $user = Auth::user();
        $accountId = $user->account_id;

        // Get service with parent info (single query)
        $service = Services::find($request->service_id);
        if (!$service) {
            throw new TreatmentException('Service not found.', 422);
        }

        // Auto-determine base_service_id from service's parent
        $baseServiceId = $request->base_service_id;
        if (!$baseServiceId && $service->parent_id != 0) {
            $baseServiceId = $service->parent_id;
        }

        // Find machine (resource) for this service
        $resourceId = $request->resource_id;
        if (!$resourceId && $request->location_id) {
            $resourceId = $this->findMachineForService($request->service_id, $baseServiceId, $request->location_id);
        }

        if (!$resourceId) {
            throw new TreatmentException('Machine not found. Please select a valid machine or ensure the location has an available machine for this service.', 422);
        }

        // Get location info (single query)
        $location = Locations::find($request->location_id);
        if (!$location) {
            throw new TreatmentException('Location not found.', 422);
        }

        // Validate doctor is allocated to this location for this service
        if (!$this->validateDoctorService($request->doctor_id, $request->service_id, $baseServiceId, $request->location_id)) {
            Log::warning('Doctor service validation skipped - no allocation found', [
                'doctor_id' => $request->doctor_id,
                'service_id' => $request->service_id,
                'base_service_id' => $baseServiceId,
                'location_id' => $request->location_id
            ]);
            // Note: Not throwing exception as this may be optional validation
        }

        // Prepare appointment data
        $appointmentData = $this->prepareAppointmentData($request, $service, $baseServiceId, $resourceId, $location, $accountId);

        // Validate rota and availability
        $rotaValidation = $this->validateRotaAndAvailability($request, $resourceId, $service, $appointmentData);
        if (!$rotaValidation['valid']) {
            throw new TreatmentException($rotaValidation['message'], 422);
        }
        $appointmentData = array_merge($appointmentData, $rotaValidation['data']);

        // Use database transaction for data integrity
        return DB::transaction(function () use ($request, $appointmentData, $accountId, $user, $service, $location) {
            // Handle lead creation/update
            $lead = $this->handleLead($request, $appointmentData, $accountId);
            $appointmentData['lead_id'] = $lead->id ?? null;

            // Update patient record
            Patients::updateRecord($appointmentData['patient_id'], false, $appointmentData, $appointmentData);

            // Create appointment
            $appointment = Appointments::create($appointmentData);

            // Handle lead services
            if ($lead) {
                $this->handleLeadServices($lead, $request, $appointment);
            }

            // Update all patient appointments with new name
            if (!empty($appointmentData['name'])) {
                Appointments::where('patient_id', $appointmentData['patient_id'])
                    ->update([
                        'name' => $appointmentData['name'],
                        'updated_at' => $appointmentData['updated_at']
                    ]);
            }

            // Handle message sending flag
            //if ($appointment->appointment_status_allow_message && $appointment->scheduled_date) {
                $appointment->update(['send_message' => 1]);
            //}

            // Handle unscheduled status
            $this->handleUnscheduledStatus($appointment, $accountId);

            // Log activity
            GeneralFunctions::saveAppointmentLogs('booked', 'Treatment', $appointment);

            // Log treatment booked activity
            $patient = Patients::find($appointment->patient_id);
            if ($patient) {
                ActivityLogger::logTreatmentBooked($appointment, $patient, $location, $service);
            }

            // Dispatch indexing job
            dispatch(new IndexSingleAppointmentJob([
                'account_id' => $accountId,
                'appointment_id' => $appointment->id,
            ]));

            return [
                'success' => true,
                'message' => 'Treatment has been created successfully.',
                'id' => $appointment->id
            ];
        });
    }

    /**
     * Validate store request
     */
    protected function validateStoreRequest(Request $request): array
    {
        $phone = $request->phone;
        if ($phone == '***********') {
            $phone = $request->old_phone;
        }
        $data = $request->all();
        $data['phone'] = GeneralFunctions::cleanNumber($phone);

        $validator = Validator::make($data, [
            'name' => 'required',
            'phone' => 'required',
            'service_id' => 'required',
            'location_id' => 'required',
            'doctor_id' => 'required',
            'patient_id' => 'required',
        ]);

        if ($validator->fails()) {
            return ['fails' => true, 'message' => $validator->messages()->first()];
        }

        return ['fails' => false];
    }

    /**
     * Find machine for service - checks child service first, then parent
     */
    protected function findMachineForService(int $serviceId, ?int $baseServiceId, int $locationId): ?int
    {
        // First, try to find machine type using child service (service_id)
        $machineTypeService = MachineTypeHasServices::where('service_id', $serviceId)->first();

        if ($machineTypeService) {
            // Check if machine exists at this location
            $resource = Resources::where('location_id', $locationId)
                ->where('machine_type_id', $machineTypeService->machine_type_id)
                ->where('active', 1)
                ->first();

            if ($resource) {
                return $resource->id;
            }
        }

        // If no machine found with child service, try with parent service (base_service_id)
        if ($baseServiceId) {
            $machineTypeService = MachineTypeHasServices::where('service_id', $baseServiceId)->first();

            if ($machineTypeService) {
                $resource = Resources::where('location_id', $locationId)
                    ->where('machine_type_id', $machineTypeService->machine_type_id)
                    ->where('active', 1)
                    ->first();

                if ($resource) {
                    return $resource->id;
                }
            }
        }

        return null;
    }

    /**
     * Validate doctor can perform this service at the given location
     */
    protected function validateDoctorService(int $doctorId, int $serviceId, ?int $baseServiceId, int $locationId): bool
    {
        $service = Services::find($serviceId);
        
        // Check if doctor is allocated to this location for:
        // 1. The exact service
        // 2. The parent service (if this is a child service)
        // 3. "All Services" (slug = 'all')
        return DB::table('doctor_has_locations as dhl')
            ->join('services as s', 's.id', '=', 'dhl.service_id')
            ->where('dhl.location_id', $locationId)
            ->where('dhl.user_id', $doctorId)
            ->where('dhl.is_allocated', 1)
            ->where(function ($query) use ($serviceId, $baseServiceId) {
                $query->where('dhl.service_id', $serviceId);
                
                if ($baseServiceId) {
                    $query->orWhere('dhl.service_id', $baseServiceId);
                }
                
                $query->orWhere('s.slug', 'all');
            })
            ->exists();
    }

    /**
     * Prepare appointment data array
     */
    protected function prepareAppointmentData(Request $request, Services $service, ?int $baseServiceId, int $resourceId, Locations $location, int $accountId): array
    {
        $user = Auth::user();
        $phone = $request->phone;
        if ($phone == '***********') {
            $phone = $request->old_phone;
        }

        $data = $request->all();
        $data['phone'] = GeneralFunctions::cleanNumber($phone);
        $data['account_id'] = $accountId;
        $data['created_by'] = $user->id;
        $data['consultancy_type'] = 'treatment';
        $data['appointment_type_id'] = config('constants.appointment_type_service');
        $data['city_id'] = $location->city_id;
        $data['region_id'] = $location->region_id;
        $data['base_service_id'] = $baseServiceId;
        $data['resource_id'] = $resourceId;
        $data['user_type_id'] = 3;
        $data['created_at'] = Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();

        // Set appointment status
        $appointmentStatus = AppointmentStatuses::getADefaultStatusOnly($accountId);
        if ($appointmentStatus) {
            $data['appointment_status_id'] = $appointmentStatus->id;
            $data['base_appointment_status_id'] = $appointmentStatus->id;
            $data['appointment_status_allow_message'] = $appointmentStatus->allow_message;
        } else {
            $data['appointment_status_id'] = null;
            $data['base_appointment_status_id'] = null;
            $data['appointment_status_allow_message'] = 0;
        }

        return $data;
    }

    /**
     * Validate rota and availability for doctor only (machine rota check removed)
     */
    protected function validateRotaAndAvailability(Request $request, int $resourceId, Services $service, array $appointmentData): array
    {
        if (!$request->start) {
            return ['valid' => true, 'data' => [], 'message' => ''];
        }

        $start = $request->start;
        $serviceDuration = $service->duration ?? '00:30';
        $durationArray = explode(':', $serviceDuration);

        if (count($durationArray) >= 2) {
            $end = Carbon::parse($start)->addHours((int)$durationArray[0])->addMinutes((int)$durationArray[1]);
            $startFormatted = Carbon::parse($start)->format('Y-m-d H:i:s');
        } else {
            $end = Carbon::parse($start)->addMinutes(30);
            $startFormatted = Carbon::parse($start)->format('Y-m-d H:i:s');
        }

        // Check doctor availability only (machine rota check removed)
        $doctorAvailable = Resources::checkingDoctorAvailbility($request->doctor_id, $startFormatted, $end);

        if (!$doctorAvailable) {
            return [
                'valid' => false,
                'data' => [],
                'message' => 'Doctor is not available. Appointment cannot be scheduled.'
            ];
        }

        // Get rota IDs
        $data = [];

        // Doctor rota only (machine rota check removed)
        $resourceDoctor = Resources::where('external_id', $request->doctor_id)->first();
        if ($resourceDoctor) {
            $doctorRota = Resources::getResourceRotaHasDay($request->start, $resourceDoctor->id);
            if (isset($doctorRota['resource_has_rota_day_id']) && $doctorRota['resource_has_rota_day_id']) {
                $data['resource_has_rota_day_id'] = $doctorRota['resource_has_rota_day_id'];
            }
        }

        // Set scheduled date/time
        $data['scheduled_date'] = Carbon::parse($request->start)->format('Y-m-d');
        $data['first_scheduled_date'] = Carbon::parse($request->start)->format('Y-m-d');
        $data['first_scheduled_count'] = 1;

        // Use scheduled_time from request if provided, otherwise extract from start
        if ($request->scheduled_time) {
            $data['scheduled_time'] = Carbon::parse($request->scheduled_time)->format('H:i:s');
            $data['first_scheduled_time'] = Carbon::parse($request->scheduled_time)->format('H:i:s');
        } else {
            $data['scheduled_time'] = Carbon::parse($request->start)->format('H:i:s');
            $data['first_scheduled_time'] = Carbon::parse($request->start)->format('H:i:s');
        }

        return ['valid' => true, 'data' => $data, 'message' => ''];
    }

    /**
     * Handle lead creation or retrieval
     */
    protected function handleLead(Request $request, array $appointmentData, int $accountId): ?Leads
    {
        $phone = $appointmentData['phone'];
        $lead = Leads::where(['phone' => $phone])->orderBy('id', 'desc')->first();

        if ($lead) {
            return $lead;
        }

        // Create new lead
        $patient = Patients::find($appointmentData['patient_id']);
        
        $defaultBookedLeadStatus = LeadStatuses::where([
            'account_id' => $accountId,
            'is_booked' => 1,
        ])->first();

        $leadStatusId = $defaultBookedLeadStatus 
            ? $defaultBookedLeadStatus->id 
            : config('constants.lead_status_booked');

        $leadData = $appointmentData;
        $leadData['lead_status_id'] = $leadStatusId;
        $leadData['created_at'] = Filters::getCurrentTimeStamp();
        $leadData['updated_at'] = Filters::getCurrentTimeStamp();
        $leadData['location_id'] = $request->location_id;
        $leadData['gender'] = $patient->gender ?? null;

        return Leads::updateOrCreate(
            ['phone' => $phone, 'account_id' => $accountId],
            $leadData
        );
    }

    /**
     * Handle lead services
     */
    protected function handleLeadServices(Leads $lead, Request $request, Appointments $appointment): void
    {
        $serviceId = $request->service_id;
        $baseServiceId = $request->base_service_id ?? $appointment->base_service_id;

        // Reset all lead services status
        LeadsServices::where(['lead_id' => $lead->id])->update(['status' => 0]);

        // Update or create lead service
        LeadsServices::updateOrCreate(
            [
                'lead_id' => $lead->id,
                'service_id' => $baseServiceId ?? $serviceId,
            ],
            [
                'child_service_id' => $serviceId,
                'treatment_id' => $appointment->id,
                'status' => 1,
            ]
        );
    }

    /**
     * Handle unscheduled appointment status
     */
    protected function handleUnscheduledStatus(Appointments $appointment, int $accountId): void
    {
        if ($appointment->scheduled_date || $appointment->scheduled_time) {
            return;
        }

        $unscheduledStatus = AppointmentStatuses::getUnScheduledStatusOnly($accountId);
        
        if ($unscheduledStatus) {
            $appointment->update([
                'appointment_status_id' => $unscheduledStatus->id,
                'base_appointment_status_id' => $unscheduledStatus->id,
                'appointment_status_allow_message' => 0,
            ]);
            return;
        }

        $defaultStatus = AppointmentStatuses::getADefaultStatusOnly($accountId);
        if ($defaultStatus) {
            $appointment->update([
                'appointment_status_id' => $defaultStatus->id,
                'base_appointment_status_id' => $defaultStatus->id,
                'appointment_status_allow_message' => 0,
            ]);
        } else {
            $appointment->update([
                'appointment_status_id' => null,
                'base_appointment_status_id' => null,
                'appointment_status_allow_message' => 0,
            ]);
        }
    }

    /**
     * Check patient's last treatment for continuity of care
     */
    public function checkPatientLastTreatment(Request $request): array
    {
        $patientId = $request->input('patient_id');
        $serviceId = $request->input('service_id');
        $locationId = $request->input('location_id');
        $excludeAppointmentId = $request->input('exclude_appointment_id');
        $startDateTime = $request->input('start');

        // Find last arrived treatment for this patient with same service
        $query = Appointments::where('appointments.patient_id', $patientId)
            ->where('appointments.appointment_type_id', 2) // Treatment type
            ->where('appointments.appointment_status_id', 2) // Arrived status
            ->where('appointments.service_id', $serviceId)
            ->join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->select('appointments.*', 'invoices.created_at as invoice_created_at')
            ->orderBy('invoices.created_at', 'DESC')
            ->with(['doctor:id,name', 'service:id,name']);

        if ($excludeAppointmentId) {
            $query->where('appointments.id', '!=', $excludeAppointmentId);
        }

        $lastTreatment = $query->first();

        if (!$lastTreatment) {
            return ['last_treatment' => null];
        }

        // Check if doctor is active
        $isDoctorActive = false;
        if ($lastTreatment->doctor_id) {
            $doctor = User::find($lastTreatment->doctor_id);
            $isDoctorActive = $doctor && $doctor->active == 1;
        }

        if (!$isDoctorActive) {
            return ['last_treatment' => null];
        }

        // Check if doctor is still allocated to location for this service
        $isDoctorAllocated = $this->checkDoctorAllocation(
            $lastTreatment->doctor_id,
            $locationId,
            $serviceId
        );

        if (!$isDoctorAllocated) {
            return ['last_treatment' => null];
        }

        // Check if doctor has rota for selected date/time
        $hasDoctorRota = $this->checkDoctorRotaForDateTime(
            $lastTreatment->doctor_id,
            $locationId,
            $startDateTime
        );

        return [
            'last_treatment' => [
                'id' => $lastTreatment->id,
                'doctor_id' => $lastTreatment->doctor_id,
                'doctor_name' => $lastTreatment->doctor->name ?? 'Unknown',
                'service_id' => $lastTreatment->service_id,
                'service_name' => $lastTreatment->service->name ?? 'Unknown',
                'scheduled_date' => $lastTreatment->scheduled_date,
                'scheduled_time' => $lastTreatment->scheduled_time,
                'has_doctor_rota' => $hasDoctorRota,
            ],
            'can_edit_doctor' => Gate::allows('can_edit_doctor'),
        ];
    }

    /**
     * Check if doctor is allocated to location for service
     */
    protected function checkDoctorAllocation(int $doctorId, int $locationId, int $serviceId): bool
    {
        $requestedService = Services::find($serviceId);

        return DB::table('doctor_has_locations as dhl')
            ->join('services as s', 's.id', '=', 'dhl.service_id')
            ->where('dhl.location_id', $locationId)
            ->where('dhl.user_id', $doctorId)
            ->where('dhl.is_allocated', 1)
            ->where(function ($query) use ($serviceId, $requestedService) {
                $query->where('dhl.service_id', $serviceId);
                
                if ($requestedService && $requestedService->parent_id) {
                    $query->orWhere('dhl.service_id', $requestedService->parent_id);
                }
                
                $query->orWhere('s.slug', 'all');
            })
            ->exists();
    }

    /**
     * Check if doctor has rota for specific date/time
     */
    protected function checkDoctorRotaForDateTime(int $doctorId, int $locationId, ?string $startDateTime): bool
    {
        if (!$startDateTime) {
            return false;
        }

        $start = Carbon::parse($startDateTime)->format('Y-m-d');
        $startedTime = Carbon::parse($startDateTime)->format('Y-m-d H:i:s');

        $resourceDoctor = Resources::where('external_id', $doctorId)->first();
        if (!$resourceDoctor) {
            return false;
        }

        $resourceRotas = ResourceHasRota::where([
            ['resource_id', '=', $resourceDoctor->id],
            ['location_id', '=', $locationId]
        ])->get();

        $activeRota = null;
        foreach ($resourceRotas as $rota) {
            $rotaStart = Carbon::parse($rota->created_at)->format('Y-m-d');
            if ($start >= $rotaStart && $start <= $rota->end) {
                $activeRota = $rota;
                break;
            }
        }

        if (!$activeRota) {
            return false;
        }

        return ResourceHasRotaDays::where([
            ['resource_has_rota_id', '=', $activeRota->id],
            ['date', '=', $start],
            ['active', '=', '1'],
            ['start_timestamp', '<=', $startedTime],
            ['end_timestamp', '>', $startedTime],
        ])->exists();
    }

    /**
     * Drag and drop reschedule treatment
     * 
     * @param Request $request
     * @return array
     * @throws TreatmentException
     */
    public function dragDropReschedule(Request $request): array
    {
        // Validate required parameters
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'start' => 'required',
            'end' => 'required',
            'doctor_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            throw new TreatmentException($validator->messages()->first(), 422);
        }

        $user = Auth::user();
        $accountId = $user->account_id;

        // Get appointment
        $appointment = Appointments::find($request->id);
        if (!$appointment) {
            throw new TreatmentException('Appointment not found.', 404);
        }

        // Check if appointment has paid invoice (optimized with exists())
        $paidStatusId = InvoiceStatuses::where('slug', 'paid')->value('id');
        $hasPaidInvoice = $paidStatusId && Invoices::where('appointment_id', $appointment->id)
            ->where('invoice_status_id', $paidStatusId)
            ->exists();

        if ($hasPaidInvoice) {
            throw new TreatmentException('Appointment has invoice and cannot be rescheduled.', 422);
        }

        // Check doctor availability
        $doctorAvailable = Resources::checkDoctorAvailbility($request);
        if (!$doctorAvailable) {
            throw new TreatmentException('Doctor is not available for this time slot.', 422);
        }

        // Get doctor resource
        $resourceDoctor = Resources::where('external_id', $request->doctor_id)->first();
        if (!$resourceDoctor) {
            throw new TreatmentException('Doctor not found.', 404);
        }

        // Get doctor rota
        $doctorRota = Resources::getResourceRotaHasDay($request->start, $resourceDoctor->id);
        if (!isset($doctorRota['resource_has_rota_day_id']) || !$doctorRota['resource_has_rota_day_id']) {
            throw new TreatmentException('Doctor rota is not available for this time slot.', 422);
        }

        // Prepare appointment data
        $data = $request->all();
        $data['first_scheduled_count'] = $appointment->first_scheduled_count;
        $data['scheduled_at_count'] = $appointment->scheduled_at_count;
        $data['reschedule'] = 1;
        $data['resource_has_rota_day_id'] = $doctorRota['resource_has_rota_day_id'];

        // Handle resource/machine (machine rota check removed - only store resource_id)
        if ($request->resourceId && !empty($request->resourceId)) {
            $data['resource_id'] = $request->resourceId;
        } else {
            // Keep existing resource_id if not provided
            $data['resource_id'] = $appointment->resource_id;
        }

        // Use database transaction for data integrity
        return DB::transaction(function () use ($request, $data, $appointment, $accountId) {
            // Update appointment
            $record = Appointments::updateServiceRecord($request->id, $data, $accountId);
            
            if (!$record) {
                throw new TreatmentException('Failed to update appointment.', 500);
            }

            // Set Appointment Status 'pending' and set send message flag
            $appointmentStatus = AppointmentStatuses::getADefaultStatusOnly($accountId);
            if ($appointmentStatus) {
                $record->update([
                    'appointment_status_id' => $appointmentStatus->id,
                    'base_appointment_status_id' => $appointmentStatus->id,
                    'appointment_status_allow_message' => $appointmentStatus->allow_message,
                    'send_message' => 1,
                ]);
            }

            // Log activity
            GeneralFunctions::saveAppointmentLogs('rescheduled', 'Treatment', $record);

            // Dispatch Elastic Search Index
            dispatch(new IndexSingleAppointmentJob([
                'account_id' => $accountId,
                'appointment_id' => $appointment->id,
            ]));

            return [
                'success' => true,
                'message' => 'Treatment rescheduled successfully.',
                'id' => $appointment->id
            ];
        });
    }

    /**
     * Get treatment data for edit modal (optimized)
     * 
     * @param int $id
     * @return array
     * @throws TreatmentException
     */
    public function getEditData(int $id): array
    {
        $accountId = Auth::user()->account_id;

        // Single query with eager loading
        $appointment = Appointments::with(['patient', 'doctor', 'service', 'location'])
            ->where('id', $id)
            ->where(function($query) use ($accountId) {
                $query->where('account_id', $accountId)
                      ->orWhereNull('account_id');
            })
            ->first();

        if (!$appointment) {
            throw TreatmentException::notFound();
        }

        // Get rota days in single queries
        $resourceRotaDay = $appointment->resource_has_rota_day_id 
            ? ResourceHasRotaDays::find($appointment->resource_has_rota_day_id) 
            : null;
        
        $machineRotaDay = $appointment->resource_has_rota_day_id_for_machine 
            ? ResourceHasRotaDays::find($appointment->resource_has_rota_day_id_for_machine) 
            : null;

        // Calculate time bounds with null safety
        $biggerTime = null;
        $smallerTime = null;
        
        if ($resourceRotaDay && $machineRotaDay) {
            $biggerTime = ResourceHasRota::getBiggerTime($resourceRotaDay->start_time, $machineRotaDay->start_time);
            $smallerTime = ResourceHasRota::getSmallerTime($resourceRotaDay->end_time, $machineRotaDay->end_time);
        } elseif ($resourceRotaDay) {
            $biggerTime = $resourceRotaDay->start_time;
            $smallerTime = $resourceRotaDay->end_time;
        }

        // Get doctors with rota in optimized way - single query with join
        $doctors = Doctors::getActiveOnly($appointment->location_id, $accountId);
        
        if ($doctors && count($doctors) > 0) {
            // Get all doctor external IDs
            $doctorExternalIds = array_keys($doctors->toArray());
            
            // Single query to get resources with treatment rotas
            $resourcesWithRota = Resources::whereIn('external_id', $doctorExternalIds)
                ->whereHas('rotas', function($query) {
                    $query->where('is_treatment', 1);
                })
                ->pluck('external_id')
                ->toArray();
            
            // Filter doctors to only those with rotas
            $doctors = $doctors->filter(function($doctor, $key) use ($resourcesWithRota) {
                return in_array($key, $resourcesWithRota);
            });
        }

        // Format dates
        $appointmentData = $appointment->toArray();
        if (isset($appointmentData['scheduled_date'])) {
            $appointmentData['scheduled_date'] = \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
        }
        if (isset($appointmentData['first_scheduled_date'])) {
            $appointmentData['first_scheduled_date'] = \Carbon\Carbon::parse($appointment->first_scheduled_date)->format('Y-m-d');
        }

        // Get all active child services (parent_id not null and not 0)
        $services = Services::whereNotNull('parent_id')
            ->where('parent_id', '!=', 0)
            ->whereNull('deleted_at')
            ->where('active', 1)
            ->orderBy('name')
            ->pluck('name', 'id');

        // Check if appointment is arrived (status_id = 2) or converted (status_id = 16)
        $isArrivedOrConverted = in_array($appointment->appointment_status_id, [2, 16]);

        // Get permissions for editing after arrived using Spatie's hasPermissionTo
        $user = Auth::user();
        $permissions = [
            'can_edit_doctor' => !$isArrivedOrConverted || ($user && $user->can('can_edit_doctor')),
            'can_edit_service' => !$isArrivedOrConverted || ($user && $user->can('can_edit_service')),
            'can_edit_schedule' => !$isArrivedOrConverted || ($user && $user->can('can_edit_schedule')),
        ];

        return [
            'appointment' => $appointmentData,
            'services' => $services,
            'doctors' => $doctors,
            'resourceRotaDay' => $resourceRotaDay,
            'machineRotaDay' => $machineRotaDay,
            'biggerTime' => $biggerTime,
            'smallerTime' => $smallerTime,
            'permissions' => $permissions,
            'is_arrived_or_converted' => $isArrivedOrConverted,
        ];
    }
}
