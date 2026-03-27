<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Lead\LeadService;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Http\Requests\Lead\ImportLeadsRequest;
use App\Http\Requests\Admin\StoreUpdateLeadCommentsRequest;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Lead\LeadDetailResource;
use App\Http\Resources\Lead\LeadCommentResource;
use App\Exceptions\LeadException;
use App\HelperModule\ApiHelper;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Exports\ExportLead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadsController extends Controller
{
    protected int $success;
    protected int $error;
    protected int $unauthorized;

    public function __construct(
        protected readonly LeadService $leadService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    // =========================================================================
    // Datatable
    // =========================================================================

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = getFilters($request->all());
            $leadType = $request->get('type');
            $filename = $leadType ? 'junk_leads' : 'leads';

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $this->leadService->bulkDelete($ids);
                return ApiHelper::apiResponse($this->success, 'Records deleted successfully.', true);
            }

            $datatableData = $this->leadService->getDatatableData($filters, $leadType);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $query = $datatableData['query'];

            if (!Gate::allows('view_inactive_leads')) {
                $query->where('leads.active', 1);
            }

            $leads = $query->select([
                    'leads.id', 'leads.name', 'leads.phone', 'leads.gender',
                    'leads.active', 'leads.city_id', 'leads.region_id',
                    'leads.location_id', 'leads.lead_status_id',
                    'leads.created_by', 'leads.created_at',
                ])
                ->with('user:id,name')
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($datatableData['orderBy'], $datatableData['order'])
                ->get();

            LeadResource::$statusLookup = $this->leadService->batchLoadStatusLookup(
                $leads->pluck('lead_status_id')->unique()->filter()->toArray()
            );

            $filterData = $this->leadService->getFilterData($filename);

            return ApiHelper::apiDataTable([
                'data' => LeadResource::collection($leads),
                'permissions' => $this->getPermissions(),
                'active_filters' => $filterData['active_filters'],
                'filter_values' => $filterData['filter_values'],
                'meta' => [
                    'field' => $datatableData['orderBy'],
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $displayLength,
                    'total' => $datatableData['total'],
                    'sort' => $datatableData['order'],
                ],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // CRUD
    // =========================================================================

    public function create(): JsonResponse
    {
        if (!Gate::allows('leads_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            return ApiHelper::apiResponse($this->success, 'Record found.', true, $this->leadService->getCreateFormData());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        try {
            $this->leadService->createLead($request->validated());
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (LeadException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function detail(int $id): JsonResponse
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $lead = $this->leadService->getLeadDetail($id);

            if (!$lead) {
                return ApiHelper::apiResponse($this->error, 'Lead not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'lead' => new LeadDetailResource($lead),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('leads_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $editData = $this->leadService->getEditFormData($id);

            if (!$editData) {
                return ApiHelper::apiResponse($this->success, 'Resource not found', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, $editData);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        try {
            $this->leadService->updateLead($id, $request->validated());
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (LeadException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('leads_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $this->leadService->deleteLead($id);
            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $lead = $this->leadService->toggleStatus($request->id, $request->status);
            return ApiHelper::apiResponse($this->success, 'Status Changed Successfully', true, [
                'lead' => new LeadResource($lead),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Lead Status Management
    // =========================================================================

    public function showLeadStatuses(Request $request): JsonResponse
    {
        if (!Gate::allows('leads_lead_status')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $data = $this->leadService->getLeadStatusesWithChildren((int) $request->get('id'));
            return ApiHelper::apiResponse($this->success, 'Record Found', true, $data);
        } catch (LeadException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function storeLeadStatuses(UpdateLeadStatusRequest $request): JsonResponse
    {
        try {
            $this->leadService->updateLeadStatus($request->id, $request->validated());
            return ApiHelper::apiResponse($this->success, 'Status updated successfully!');
        } catch (LeadException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function leadStatusesPopCheck(Request $request): JsonResponse
    {
        try {
            $data = $this->leadService->getStatusWithChildren((int) $request->id);

            if (!$data) {
                return ApiHelper::apiResponse($this->error, 'Status not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function leadStatusChildPopCheck(Request $request): JsonResponse
    {
        try {
            $data = $this->leadService->getChildStatusWithParent((int) $request->id);
            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Services
    // =========================================================================

    public function loadChildServices(Request $request): JsonResponse
    {
        try {
            $data = $this->leadService->getChildServicesWithLead($request->serviceId, $request->leadId);
            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function editService(int $leadId, int $serviceId): JsonResponse
    {
        try {
            $data = $this->leadService->getEditServiceData($leadId, $serviceId);
            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Import / Export
    // =========================================================================

    public function uploadLeads(ImportLeadsRequest $request): JsonResponse
    {
        set_time_limit(300);
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        try {
            $file = $request->file('leads_file');
            $collections = (new FastExcel)->import($file);

            $rows = $collections->map(function ($collection): array {
                $data = [];
                foreach ($collection as $key => $value) {
                    $data[strtolower(str_replace(' ', '_', trim($key)))] = $value;
                }
                return $data;
            })->toArray();

            $stats = $this->leadService->importLeads($rows, [
                'update_records' => $request->update_records == '1',
                'skip_lead_statuses' => $request->skip_lead_statuses == '1',
            ]);

            $message = "Leads imported. Created: {$stats['created']}, Updated: {$stats['updated']}";

            if (!empty($stats['invalid_phones'])) {
                $message .= '. Invalid phones: ' . count($stats['invalid_phones']);
            }
            if (!empty($stats['invalid_services'])) {
                $message .= '. Invalid services: ' . implode(', ', $stats['invalid_services']);
            }

            return ApiHelper::apiResponse($this->success, $message);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        }
    }

    public function exportPdf(Request $request): BinaryFileResponse|JsonResponse
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        try {
            $leads = $this->leadService->getExportData($request->all());

            $customPaper = [0, 0, 720, 1440];
            $pdf = PDF::loadView('admin.leads.lead-pdf', compact('leads'))->setPaper($customPaper, 'portrait');

            return $pdf->download('leads.pdf');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function exportDocs(Request $request): BinaryFileResponse
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        return Excel::download(new ExportLead($request), 'leads.' . $request->ext);
    }

    // =========================================================================
    // Search
    // =========================================================================

    public function getLeadId(Request $request): JsonResponse
    {
        try {
            $leads = $this->leadService->searchLeadsById($request->search, Auth::user()->account_id);
            return ApiHelper::apiResponse($this->success, 'Record found.', true, ['leads' => $leads]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getLeadNumber(Request $request): JsonResponse
    {
        try {
            $lead = $this->leadService->findLead((int) $request->lead_id);
            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'lead' => $lead ? new LeadResource($lead) : null,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function phoneSearch(Request $request): JsonResponse
    {
        try {
            $leads = $this->leadService->searchByPhone($request->search, Auth::user()->account_id);
            return ApiHelper::apiResponse($this->success, 'Record found.', true, ['leads' => $leads]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Comments
    // =========================================================================

    public function storeComment(StoreUpdateLeadCommentsRequest $request): JsonResponse
    {
        try {
            $comment = $this->leadService->addComment((int) $request->lead_id, $request->comment);
            $comment->load('user');

            return ApiHelper::apiResponse($this->success, 'Comment added.', true, [
                'username' => Auth::user()->name,
                'lead' => new LeadCommentResource($comment),
                'leadCommentDate' => $comment->created_at->format('D M, j Y h:i A'),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Conversion
    // =========================================================================

    public function convert(int $id): JsonResponse
    {
        if (!Gate::allows('appointments_manage') || !Gate::allows('leads_convert')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $data = $this->leadService->getConversionData($id);

            if (!$data) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Loaders (Dropdown Data)
    // =========================================================================

    public function loadLeadStatuses(): JsonResponse
    {
        return response()->json(
            LeadStatuses::getActiveOnly()
                ->map(fn($status): array => ['value' => $status->id, 'text' => $status->name])
                ->toArray()
        );
    }

    public function loadTreatments(): JsonResponse
    {
        return response()->json(
            \App\Models\Services::getActiveOnly()
                ->map(fn($service): array => ['value' => $service->id, 'text' => $service->name])
                ->toArray()
        );
    }

    public function loadLeadSources(): JsonResponse
    {
        return response()->json(
            LeadSources::getActiveOnly()
                ->map(fn($source): array => ['value' => $source->id, 'text' => $source->name])
                ->toArray()
        );
    }

    public function loadCities(): JsonResponse
    {
        if (!Gate::allows('leads_city')) {
            return response()->json([]);
        }

        return response()->json(
            \App\Models\Cities::getActiveOnly(\App\Helpers\ACL::getUserCities(), Auth::user()->account_id)
                ->map(fn($city): array => ['value' => $city->id, 'text' => $city->name])
                ->toArray()
        );
    }

    // =========================================================================
    // City / Lead Data / SMS / Junk
    // =========================================================================

    public function saveCity(Request $request): JsonResponse
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $result = $this->leadService->updateLeadCity((int) $request->get('pk'), (int) $request->get('value'));

            if (!$result) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'City updated successfully.', true, [
                'city' => $result['city_name'],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function loadLeadData(Request $request): JsonResponse
    {
        try {
            return response()->json(
                $this->leadService->resolveLeadData($request->all(), Gate::allows('leads_manage'))
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sendSms(int $id): JsonResponse
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $result = $this->leadService->sendSmsToLead($id);

            return $result['status']
                ? ApiHelper::apiResponse($this->success, 'SMS has been sent successfully.')
                : ApiHelper::apiResponse($this->error, 'SMS sending failed.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function removeFromJunk(int $id): JsonResponse
    {
        if (!Gate::allows('leads_convert')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $result = $this->leadService->removeLeadFromJunk($id);

            return $result['status']
                ? ApiHelper::apiResponse($this->success, $result['message'])
                : ApiHelper::apiResponse($this->error, $result['message']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    protected function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('leads_edit'),
            'delete' => Gate::allows('leads_destroy'),
            'active' => Gate::allows('leads_active'),
            'inactive' => Gate::allows('leads_inactive'),
            'create' => Gate::allows('leads_create'),
            'convert' => Gate::allows('leads_convert'),
            'contact' => Gate::allows('contact'),
            'update_status' => Gate::allows('leads_lead_status'),
        ];
    }
}
