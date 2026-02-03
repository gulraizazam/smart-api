<?php

namespace App\Services\Appointment;

use App\Helpers\ACL;
use App\Helpers\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\InvoiceStatuses;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConsultancyDatatableService
{
    private $filterService;
    private $userId;
    private $accountId;
    private $filename = 'appointments';

    public function __construct(AppointmentFilterService $filterService)
    {
        $this->filterService = $filterService;
        $this->userId = Auth::id();
        $this->accountId = Auth::user()->account_id;
    }

    /**
     * Get consultancy datatable data with optimized queries and filters
     * Supports optional patient_id parameter for patient-specific filtering
     */
    public function getDatatableData(Request $request, $patientId = null): array
    {
        $filters = getFilters($request->all());
        
        // If patient_id is provided (patient card context), add it to filters
        if ($patientId) {
            $filters['patient_id'] = $patientId;
        }
        
        // Handle sorting
        [$orderBy, $order] = $this->handleSorting($request);
        
        // Build base query with filters
        $baseQuery = $this->buildBaseQuery();
        
        // Apply filters
        $countQuery = clone $baseQuery;
        $this->applyFilters($countQuery, $filters);
        
        // Get total count - use distinct count on appointments.id to handle JOIN correctly
        $totalRecords = $countQuery->distinct()->count('appointments.id');
        [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);
        
        // Build result query
        $resultQuery = clone $baseQuery;
        $this->applyFilters($resultQuery, $filters);
        
        // Fetch appointments with eager loading
        $appointments = $this->fetchAppointments($resultQuery, $displayLength, $displayStart, $orderBy, $order);
        
        // Build response
        $records = $this->buildRecordsArray($appointments, $orderBy, $order, $page, $pages, $displayLength, $totalRecords);
        
        // Handle delete operation
        if (hasFilter($filters, 'delete')) {
            $this->handleDelete($filters['delete'], $records);
        }
        
        // Add permissions
        $records['permissions'] = $this->getPermissions();
        
        return $records;
    }

    /**
     * Handle sorting logic
     */
    private function handleSorting(Request $request): array
    {
        if ($request->has('sort')) {
            [$orderBy, $order] = getSortBy($request, 'appointments.created_at', 'DESC', 'appointments');
        } else {
            $orderBy = 'appointments.created_at';
            $order = 'desc';
        }
        
        Filters::put($this->userId, 'appointments', 'order_by', $orderBy);
        Filters::put($this->userId, 'appointments', 'order', $order);
        
        return [$orderBy, $order];
    }

    /**
     * Build base query with ACL and permissions
     */
    private function buildBaseQuery()
    {
        return $this->filterService->buildBaseQuery();
    }

    /**
     * Apply all filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        $this->filterService->buildFilters($query, $filters);
    }

    /**
     * Fetch appointments with eager loading to prevent N+1 queries
     */
    private function fetchAppointments($query, int $limit, int $offset, string $orderBy, string $order)
    {
        // Adjust order by column if needed
        if ($orderBy == 'name') {
            $orderBy = 'appointments.name';
        }

        return $query
            ->select(
                'appointments.*',
                'users.phone',
                'appointments.name as patient_name',
                'appointments.id as app_id',
                'appointments.created_by as app_created_by',
                'appointments.updated_by as app_updated_by',
                'appointments.created_at as app_created_at'
            )
            ->with([
                'invoice:id,appointment_id,invoice_status_id',
                'doctor:id,name',
                'city:id,name',
                'location:id,name',
                'service:id,name',
                'appointment_type:id,name',
                'appointment_status:id,name,parent_id',
                'patient:id,phone',
                'region:id,name'
            ])
            ->limit($limit)
            ->offset($offset)
            ->orderBy($orderBy, $order)
            ->get();
    }

    /**
     * Build records array from appointments
     */
    private function buildRecordsArray($appointments, string $orderBy, string $order, int $page, int $pages, int $perpage, int $total): array
    {
        $records = [];
        $records['data'] = [];
        $records = $this->getFiltersData($records);
        
        if ($appointments->isEmpty()) {
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $perpage,
                'total' => $total,
                'sort' => $order,
            ];
            return $records;
        }
        
        // Load reference data once
        $referenceData = $this->loadReferenceData($appointments);
        
        // Cache permission check
        $canViewContact = Gate::allows('contact');
        
        // Build data rows
        foreach ($appointments as $index => $appointment) {
            $records['data'][$index] = $this->buildAppointmentRow(
                $appointment,
                $referenceData,
                $canViewContact
            );
        }
        
        // Add metadata
        $records['meta'] = [
            'field' => $orderBy,
            'page' => $page,
            'pages' => $pages,
            'perpage' => $perpage,
            'total' => $total,
            'sort' => $order,
        ];
        
        return $records;
    }

    /**
     * Load reference data for appointments (users, statuses, etc.)
     */
    private function loadReferenceData($appointments): array
    {
        // Load invoice status
        $invoiceStatus = InvoiceStatuses::where('slug', 'paid')->first();
        
        // Load appointment statuses
        $unscheduledStatus = AppointmentStatuses::getUnScheduledStatusOnly($this->accountId, ['id']);
        $cancelledStatus = AppointmentStatuses::getCancelledStatusOnly($this->accountId);
        
        // Load users referenced in appointments
        $userIds = $appointments->pluck('app_created_by')
            ->merge($appointments->pluck('converted_by'))
            ->merge($appointments->pluck('app_updated_by'))
            ->unique()
            ->filter();
        $users = User::whereIn('id', $userIds)->pluck('name', 'id')->toArray();
        
        // Load parent appointment statuses
        $statusIds = $appointments->pluck('appointment_status.parent_id')->filter()->unique();
        $appointmentStatuses = AppointmentStatuses::whereIn('id', $statusIds)->pluck('name', 'id')->toArray();
        
        return [
            'invoice_status' => $invoiceStatus,
            'unscheduled_status' => $unscheduledStatus,
            'cancelled_status' => $cancelledStatus,
            'users' => $users,
            'appointment_statuses' => $appointmentStatuses,
        ];
    }

    /**
     * Build single appointment row
     */
    private function buildAppointmentRow($appointment, array $referenceData, bool $canViewContact): array
    {
        // Get invoice info
        $invoiceId = 0;
        $invoice = null;
        if ($appointment->invoice && $appointment->invoice->invoice_status_id == $referenceData['invoice_status']->id) {
            $invoice = $appointment->invoice;
            $invoiceId = $invoice->id;
        }
        
        // Map consultancy type
        $consultancyType = match($appointment->consultancy_type) {
            'in_person' => 'In Person',
            'virtual' => 'Virtual',
            default => ''
        };
        
        // Format phone number based on permission
        $phoneNumber = $canViewContact ? $appointment->phone : '***********';
        
        // Format scheduled date
        $scheduledDate = $appointment->scheduled_date 
            ? Carbon::parse($appointment->scheduled_date)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time)->format('h:i A')
            : '-';
        
        // Get appointment status name
        $statusName = '';
        if ($appointment->appointment_status_id) {
            if ($appointment->appointment_status->parent_id) {
                $statusName = $referenceData['appointment_statuses'][$appointment->appointment_status->parent_id] 
                    ?? $appointment->appointment_status->name;
            } else {
                $statusName = $appointment->appointment_status->name;
            }
        }
        
        return [
            'id' => $appointment->app_id,
            'patient_id' => $appointment->patient_id,
            'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
            'name' => $appointment->patient_name ?: $appointment->name,
            'phone' => $phoneNumber,
            'scheduled_date' => $scheduledDate,
            'doctor_id' => $appointment->doctor->name ?? 'N/A',
            'doctorId' => $appointment->doctor->id ?? 0,
            'region_id' => $appointment->region->name ?? 'N/A',
            'city_id' => $appointment->city->name ?? 'N/A',
            'cityId' => $appointment->city_id ?? 0,
            'location_id' => $appointment->location->name ?? 'N/A',
            'locationId' => $appointment->location_id ?? 'N/A',
            'service_id' => $appointment->service->name ?? 'N/A',
            'resource_id' => $appointment->resource_id ?? 0,
            'appointment_type_id' => $appointment->appointment_type->name ?? 'N/A',
            'appointment_type' => $appointment->appointment_type->id ?? 0,
            'consultancy_type' => $consultancyType,
            'created_at' => Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'),
            'created_by' => $referenceData['users'][$appointment->app_created_by] ?? 'N/A',
            'converted_by' => $referenceData['users'][$appointment->converted_by] ?? 'N/A',
            'updated_by' => $referenceData['users'][$appointment->app_updated_by] ?? 'N/A',
            'unscheduled_appointment_status' => $referenceData['unscheduled_status'],
            'cancelled_appointment_status' => $referenceData['cancelled_status'],
            'appointment_status_id' => $statusName,
            'appointment_status' => $appointment->appointment_status_id,
            'invoice_id' => $invoiceId,
            'invoice' => $invoice,
        ];
    }

    /**
     * Get filters data including dropdown values for frontend
     */
    private function getFiltersData(array $records): array
    {
        // Get active filters
        $records['active_filters'] = Filters::all($this->userId, $this->filename);
        
        // Get filter dropdown values
        $regions = \App\Models\Regions::getActiveSorted(ACL::getUserRegions());
        $cities = \App\Models\Cities::getActiveSortedFeatured(ACL::getUserCities());
        $doctors = \App\Models\Doctors::getActiveOnly(ACL::getUserCentres(), $this->accountId);
        $locations = \App\Models\Locations::getActiveSorted(ACL::getUserCentres());
        $services = GeneralFunctions::ServicesTreeList();
        
        // Get appointment statuses
        $appointmentStatuses = AppointmentStatuses::getAllParentRecords($this->accountId);
        if ($appointmentStatuses) {
            $appointmentStatuses = $appointmentStatuses->pluck('name', 'id');
        }
        
        // Get appointment types based on permissions
        if (Gate::allows('appointments_consultancy') && Gate::allows('treatments_services')) {
            $appointmentTypes = AppointmentTypes::get()->pluck('name', 'id');
        } elseif (Gate::allows('appointments_consultancy')) {
            $appointmentTypes = AppointmentTypes::where('slug', 'consultancy')->get()->pluck('name', 'id');
        } elseif (Gate::allows('treatments_services')) {
            $appointmentTypes = AppointmentTypes::where('slug', 'treatment')->get()->pluck('name', 'id');
        } else {
            $appointmentTypes = [];
        }
        
        $users = User::getAllRecords($this->accountId)->where('active', 1)->pluck('name', 'id');
        
        $records['filter_values'] = [
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
        
        return $records;
    }

    /**
     * Handle delete operation
     */
    private function handleDelete(string $deleteIds, array &$records): void
    {
        $ids = explode(',', $deleteIds);
        $appointments = Appointments::whereIn('id', $ids);
        
        if ($appointments) {
            $appointments->delete();
        }
        
        $records['status'] = true;
        $records['message'] = 'Records has been deleted successfully!';
    }

    /**
     * Get user permissions
     */
    private function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('appointments_edit'),
            'consultancy' => Gate::allows('appointments_consultancy'),
            'treatment' => Gate::allows('treatments_services'),
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
            'update_consultation_service' => Gate::allows('update_consultation_service'),
            'update_consultation_doctor' => Gate::allows('update_consultation_doctor'),
            'update_consultation_schedule' => Gate::allows('update_consultation_schedule'),
        ];
    }
}
