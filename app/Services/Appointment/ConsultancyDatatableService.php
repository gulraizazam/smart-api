<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Enums\ConsultancyType;
use App\Helpers\ACL;
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
use Illuminate\Support\Facades\Gate;

class ConsultancyDatatableService
{
    private int|null $userId = null;
    private int|null $accountId = null;
    private string $filename = 'appointments';

    public function __construct(
        private readonly AppointmentFilterService $filterService,
    ) {
    }

    private function userId(): int
    {
        return $this->userId ??= (int) Auth::id();
    }

    private function accountId(): int
    {
        return $this->accountId ??= (int) Auth::user()->account_id;
    }

    /**
     * Get consultancy datatable data with optimized queries and filters.
     *
     * @return array<string, mixed>
     */
    public function getDatatableData(Request $request, ?int $patientId = null): array
    {
        $filters = getFilters($request->all());

        if ($patientId) {
            $filters['patient_id'] = $patientId;
        }

        [$orderBy, $order] = $this->handleSorting($request);

        // On a patient profile, show full consultation history at accessible centres
        // regardless of which doctor owns the consultation.
        $baseQuery = $this->filterService->buildBaseQuery(scopeByDoctor: $patientId === null);

        $countQuery = clone $baseQuery;
        $this->filterService->buildFilters($countQuery, $filters);
        $totalRecords = $countQuery->distinct()->count('appointments.id');

        [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

        $resultQuery = clone $baseQuery;
        $this->filterService->buildFilters($resultQuery, $filters);

        $appointments = $this->fetchAppointments($resultQuery, $displayLength, $displayStart, $orderBy, $order);
        $records = $this->buildRecordsArray($appointments, $orderBy, $order, $page, $pages, $displayLength, $totalRecords);

        if (hasFilter($filters, 'delete')) {
            $this->handleDelete($filters['delete'], $records);
        }

        $records['permissions'] = $this->getPermissions();

        return $records;
    }

    /**
     * @return array{string, string}
     */
    private function handleSorting(Request $request): array
    {
        if ($request->has('sort')) {
            [$orderBy, $order] = getSortBy($request, 'appointments.created_at', 'DESC', 'appointments');
        } else {
            $orderBy = 'appointments.created_at';
            $order = 'desc';
        }

        Filters::put($this->userId(), 'appointments', 'order_by', $orderBy);
        Filters::put($this->userId(), 'appointments', 'order', $order);

        return [$orderBy, $order];
    }

    private function fetchAppointments(
        mixed $query,
        int $limit,
        int $offset,
        string $orderBy,
        string $order,
    ): mixed {
        $orderBy = $orderBy === 'name' ? 'appointments.name' : $orderBy;

        return $query
            ->select(
                'appointments.*',
                'users.phone',
                'appointments.name as patient_name',
                'appointments.id as app_id',
                'appointments.created_by as app_created_by',
                'appointments.updated_by as app_updated_by',
                'appointments.created_at as app_created_at',
            )
            ->with([
                'invoice:id,appointment_id,invoice_status_id',
                'doctor:id,name',
                'city:id,name',
                'location:id,name',
                'service:id,name,parent_id',
                'service.parent:id,name',
                'appointment_type:id,name',
                'appointment_status:id,name,parent_id',
                'patient:id,phone',
                'region:id,name',
            ])
            ->limit($limit)
            ->offset($offset)
            ->orderBy($orderBy, $order)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecordsArray(
        mixed $appointments,
        string $orderBy,
        string $order,
        int $page,
        int $pages,
        int $perpage,
        int $total,
    ): array {
        $records = ['data' => []];
        $records = $this->getFiltersData($records);

        $meta = [
            'field' => $orderBy,
            'page' => $page,
            'pages' => $pages,
            'perpage' => $perpage,
            'total' => $total,
            'sort' => $order,
        ];

        if ($appointments->isEmpty()) {
            $records['meta'] = $meta;
            return $records;
        }

        $referenceData = $this->loadReferenceData($appointments);
        $canViewContact = Gate::allows('consultations.list.view_contact');

        foreach ($appointments as $index => $appointment) {
            $records['data'][$index] = $this->buildAppointmentRow($appointment, $referenceData, $canViewContact);
        }

        $records['meta'] = $meta;

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReferenceData(mixed $appointments): array
    {
        $invoiceStatus = InvoiceStatuses::where('slug', 'paid')->first();
        $unscheduledStatus = AppointmentStatuses::getUnScheduledStatusOnly($this->accountId(), ['id']);
        $cancelledStatus = AppointmentStatuses::getCancelledStatusOnly($this->accountId());

        $userIds = $appointments->pluck('app_created_by')
            ->merge($appointments->pluck('converted_by'))
            ->merge($appointments->pluck('app_updated_by'))
            ->unique()
            ->filter();
        $users = User::whereIn('id', $userIds)->pluck('name', 'id')->toArray();

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
     * @return array<string, mixed>
     */
    private function buildAppointmentRow(mixed $appointment, array $referenceData, bool $canViewContact): array
    {
        $invoiceId = 0;
        $invoice = null;
        if ($appointment->invoice?->invoice_status_id === $referenceData['invoice_status']?->id) {
            $invoice = $appointment->invoice;
            $invoiceId = $invoice->id;
        }

        $consultancyTypeLabel = ConsultancyType::tryFrom($appointment->consultancy_type)?->label() ?? '';
        $phoneNumber = $canViewContact ? $appointment->phone : '***********';

        $scheduledDate = $appointment->scheduled_date
            ? Carbon::parse($appointment->scheduled_date)->format('M j, Y')
                . ' at ' . Carbon::parse($appointment->scheduled_time)->format('h:i A')
            : '-';

        $statusName = $this->resolveStatusName($appointment, $referenceData);

        return [
            'id' => $appointment->app_id,
            'patient_id' => $appointment->patient_id,
            'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
            'name' => $appointment->patient_name ?: $appointment->name,
            'phone' => $phoneNumber,
            'scheduled_date' => $scheduledDate,
            'doctor_id' => $appointment->doctor?->name ?? 'N/A',
            'doctorId' => $appointment->doctor?->id ?? 0,
            'region_id' => $appointment->region?->name ?? 'N/A',
            'city_id' => $appointment->city?->name ?? 'N/A',
            'cityId' => $appointment->city_id ?? 0,
            'location_id' => $appointment->location?->name ?? 'N/A',
            'locationId' => $appointment->location_id ?? 'N/A',
            'service_id' => $appointment->service?->parent?->name
                ?? $appointment->service?->name
                ?? 'N/A',
            'resource_id' => $appointment->resource_id ?? 0,
            'appointment_type_id' => $appointment->appointment_type?->name ?? 'N/A',
            'appointment_type' => $appointment->appointment_type?->id ?? 0,
            'consultancy_type' => $consultancyTypeLabel,
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

    private function resolveStatusName(mixed $appointment, array $referenceData): string
    {
        if (!$appointment->appointment_status_id) {
            return '';
        }

        if ($appointment->appointment_status?->parent_id) {
            return $referenceData['appointment_statuses'][$appointment->appointment_status->parent_id]
                ?? $appointment->appointment_status->name;
        }

        return $appointment->appointment_status?->name ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getFiltersData(array $records): array
    {
        $records['active_filters'] = Filters::all($this->userId(), $this->filename);

        $regions = \App\Models\Regions::getActiveSorted(ACL::getUserRegions());
        $cities = \App\Models\Cities::getActiveSortedFeatured(ACL::getUserCities());
        $doctors = \App\Models\Doctors::getActiveOnly(ACL::getUserCentres(), $this->accountId());
        $locations = \App\Models\Locations::getActiveSorted(ACL::getUserCentres());
        $services = GeneralFunctions::ServicesTreeList();

        $appointmentStatuses = AppointmentStatuses::getAllParentRecords($this->accountId());
        $appointmentStatuses = $appointmentStatuses?->pluck('name', 'id');

        $appointmentTypes = match (true) {
            Gate::allows('consultations.list.view') && Gate::allows('treatments.list.view')
                => AppointmentTypes::pluck('name', 'id'),
            Gate::allows('consultations.list.view')
                => AppointmentTypes::where('slug', 'consultancy')->pluck('name', 'id'),
            Gate::allows('treatments.list.view')
                => AppointmentTypes::where('slug', 'treatment')->pluck('name', 'id'),
            default => collect(),
        };

        $users = User::getAllRecords($this->accountId())->where('active', 1)->pluck('name', 'id');

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

    private function handleDelete(string $deleteIds, array &$records): void
    {
        if (!Gate::allows('consultations.delete')) {
            $records['status'] = false;
            $records['message'] = 'You are not authorized to delete records.';
            return;
        }

        $ids = array_filter(explode(',', $deleteIds), fn ($id) => is_numeric($id));
        Appointments::where('account_id', $this->accountId())
            ->whereIn('id', $ids)
            ->delete();

        $records['status'] = true;
        $records['message'] = 'Records have been deleted successfully!';
    }

    /**
     * @return array<string, bool>
     */
    /**
     * Datatable permission map — consumed by the legacy admin-v1 KTDatatable
     * (the SPA reads gates directly from `usePermissions().has()` instead of
     * this response). Consultation-owned flags use the new dotted catalog;
     * attachment / cross-module flags (image / measurement / medical form /
     * plans / patient_card) stay on the legacy `appointments_*` perms until
     * those modules are audited.
     */
    private function getPermissions(): array
    {
        $canEdit = Gate::allows('consultations.edit');

        return [
            'edit' => $canEdit,
            'consultancy' => Gate::allows('consultations.list.view'),
            'treatment' => Gate::allows('treatments.list.view'),
            'delete' => Gate::allows('consultations.delete'),
            // active / inactive / create-row toggles aren't surfaced in the
            // SPA — flags retained for the admin-v1 datatable but always
            // resolved via the dotted catalog now.
            'active' => false,
            'inactive' => false,
            'create' => Gate::allows('consultations.create'),
            'log' => Gate::allows('appointments_log'),
            'status' => Gate::allows('consultations.update_status'),
            'invoice' => Gate::allows('consultations.invoice.create'),
            'invoice_display' => Gate::allows('consultations.invoice.view'),
            'image_manage' => Gate::allows('appointments_image_manage'),
            'measurement_manage' => Gate::allows('appointments_measurement_manage'),
            'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
            'plans_create' => $canEdit && Gate::allows('appointments_plans_create'),
            'patient_card' => Gate::allows('appointments_patient_card'),
            'contact' => Gate::allows('consultations.list.view_contact'),
            // These flags advertise the per-field after-arrival override
            // capability. The SPA reads them only when the row is in an
            // arrived/converted state; pre-arrival edits are gated by
            // `consultations.edit` alone, so the flag stays true for the
            // legacy admin's interpretation but the per-row arrival check
            // belongs on the consumer.
            'update_consultation_service' => $canEdit && Gate::allows('consultations.edit.service.after_arrived'),
            'update_consultation_doctor' => $canEdit && Gate::allows('consultations.edit.doctor.after_arrived'),
            'update_consultation_schedule' => $canEdit && Gate::allows('consultations.edit.schedule.after_arrived'),
        ];
    }
}
