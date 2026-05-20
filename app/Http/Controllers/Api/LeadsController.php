<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\LeadException;
use App\Exports\ExportLead;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateLeadCommentsRequest;
use App\Http\Requests\Lead\ImportLeadsRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Http\Resources\Lead\LeadCommentResource;
use App\Http\Resources\Lead\LeadDetailResource;
use App\Http\Resources\Lead\LeadResource;
use App\Models\Cities;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\Services;
use App\Services\Lead\LeadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadsController extends Controller
{
    public function __construct(
        protected readonly LeadService $leadService,
    ) {}

    // =========================================================================
    // Datatable
    // =========================================================================

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = getFilters($request->all());
            $leadType = $request->get('type');
            $filename = $leadType ? 'junk_leads' : 'leads';

            // Gate the list itself. The junk tab + junk-datatable both route
            // here; if `?type=junk` is in play, require the junk view perm
            // too. Without these gates, a direct API hit returned every
            // lead in the account to any authenticated user.
            if (! Gate::allows('leads.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }
            if ($leadType === 'junk' && ! Gate::allows('leads.list.view_junk')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            // Bulk-delete is reached via the same datatable endpoint with
            // a `delete=...` filter — separate gate so a `leads.list.view`
            // user can't elevate to deletion by URL-crafting.
            if (hasFilter($filters, 'delete')) {
                if (! Gate::allows('leads.delete')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 403);
                }
                $ids = explode(',', $filters['delete']);
                $this->leadService->bulkDelete($ids);

                return $this->successResponse('Records deleted successfully.');
            }

            $datatableData = $this->leadService->getDatatableData($filters, $leadType);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $query = $datatableData['query'];

            if (! Gate::allows('leads.list.view_inactive')) {
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

            return response()->json([
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
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // CRUD
    // =========================================================================

    public function create(): JsonResponse
    {
        if (! Gate::allows('leads.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            return $this->successResponse('Record found.', $this->leadService->getCreateFormData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        // Authoritative gate for the create flow — `create()` (the GET form
        // initialiser) gates `leads.create` too, but a direct POST without
        // visiting the form first would otherwise bypass that check.
        if (! Gate::allows('leads.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->leadService->createLead($request->validated());

            return $this->successResponse('Record has been created successfully.');
        } catch (LeadException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function detail(int $id): JsonResponse
    {
        if (! Gate::allows('leads.detail.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $lead = $this->leadService->getLeadDetail($id);

            if (! $lead) {
                return $this->errorResponse('Lead not found.', 500);
            }

            return $this->successResponse('Record found.', [
                'lead' => new LeadDetailResource($lead),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('leads.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $editData = $this->leadService->getEditFormData($id);

            if (! $editData) {
                return $this->errorResponse('Resource not found', 404);
            }

            return $this->successResponse('Record found.', $editData);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('leads.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->leadService->updateLead($id, $request->validated());

            return $this->successResponse('Record has been updated successfully.');
        } catch (LeadException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('leads.delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->leadService->deleteLead($id);

            return $this->successResponse('Record has been deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            // Toggles lead.active. Not currently surfaced in the SPA but the
            // endpoint is alive; gate on `leads.edit` so any direct API
            // caller still needs write privilege.
            if (! Gate::allows('leads.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $lead = $this->leadService->toggleStatus((int) $request->id, (int) $request->status);

            return $this->successResponse('Status Changed Successfully', [
                'lead' => new LeadResource($lead),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Lead Status Management
    // =========================================================================

    public function showLeadStatuses(Request $request): JsonResponse
    {
        if (! Gate::allows('leads.update_status')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $data = $this->leadService->getLeadStatusesWithChildren((int) $request->get('id'));

            return $this->successResponse('Record Found', $data);
        } catch (LeadException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function storeLeadStatuses(UpdateLeadStatusRequest $request): JsonResponse
    {
        if (! Gate::allows('leads.update_status')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->leadService->updateLeadStatus((int) $request->id, $request->validated());

            return $this->successResponse('Status updated successfully!');
        } catch (LeadException $e) {
            // LeadException here is a business-rule violation —
            // statusChangeNotAllowed (lead at Arrived/Converted) or
            // targetStatusNotAllowed (operator picked a system-managed
            // target). 422 is the right shape: the SPA's `api.ts` masks
            // 5xx as a generic "Something went wrong on our end" toast,
            // which hides the actual reason. With 422 the SPA surfaces
            // the message verbatim and can wire `setError` against the
            // field key. Defense-in-depth — the SPA's Option A locked
            // dropdown already prevents reaching this path under normal
            // use; this is for races, direct API hits, and future
            // refactors that drop the locked-state check.
            return $this->errorResponse($e->getMessage(), 422, [
                'lead_status_parent_id' => [$e->getMessage()],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function leadStatusesPopCheck(Request $request): JsonResponse
    {
        try {
            // Helper for the status-change UI — surfaces the parent status
            // record + children. Gate on update_status because that's the
            // workflow that consumes it.
            if (! Gate::allows('leads.update_status')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->leadService->getStatusWithChildren((int) $request->id);

            if (! $data) {
                return $this->errorResponse('Status not found.', 500);
            }

            return $this->successResponse('Record found.', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function leadStatusChildPopCheck(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('leads.update_status')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->leadService->getChildStatusWithParent((int) $request->id);

            return $this->successResponse('Record found.', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Services
    // =========================================================================

    public function loadChildServices(Request $request): JsonResponse
    {
        try {
            // Dropdown loader for the lead-edit / create flow. Gate on
            // list view so non-lead users can't enumerate service trees
            // via this endpoint.
            if (! Gate::allows('leads.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->leadService->getChildServicesWithLead((int) $request->serviceId, (int) $request->leadId);

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function editService(int $leadId, int $serviceId): JsonResponse
    {
        try {
            // Returns the form data for editing a lead's inquired service.
            // This is part of the lead-edit flow; gate on `leads.edit`.
            if (! Gate::allows('leads.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->leadService->getEditServiceData($leadId, $serviceId);

            return $this->successResponse('Record found.', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Import / Export
    // =========================================================================

    public function uploadLeads(ImportLeadsRequest $request): JsonResponse
    {
        if (! Gate::allows('leads.import')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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

            if (! empty($stats['invalid_phones'])) {
                $message .= '. Invalid phones: '.count($stats['invalid_phones']);
            }
            if (! empty($stats['invalid_services'])) {
                $message .= '. Invalid services: '.implode(', ', $stats['invalid_services']);
            }

            return $this->successResponse($message);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function exportPdf(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! Gate::allows('leads.export')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        ini_set('memory_limit', '-1');
        set_time_limit(0);

        try {
            $leads = $this->leadService->getExportData($request->all());

            $customPaper = [0, 0, 720, 1440];
            $pdf = Pdf::loadView('admin.leads.lead-pdf', compact('leads'))->setPaper($customPaper, 'portrait');

            return $pdf->download('leads.pdf');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function exportDocs(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! Gate::allows('leads.export')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            $filters = [];
            foreach (['date_from', 'date_to', 'location_id', 'service_id', 'lead_status_id', 'user_id'] as $k) {
                $v = $request->input($k);
                if ($v !== null && $v !== '') {
                    $filters[$k] = is_array($v) ? implode(',', $v) : (string) $v;
                }
            }
            ActivityLogger::logDataExport(
                exportType: 'leads',
                rowCount: 0,
                filters: $filters,
            );
        } catch (\Throwable $e) {
            Log::warning('activities.data_export.audit_write_failed', [
                'event' => 'activities.data_export.audit_write_failed',
                'export_type' => 'leads',
                'error' => $e->getMessage(),
            ]);
        }

        return Excel::download(new ExportLead($request, $this->leadService), 'leads.'.$request->ext);
    }

    // =========================================================================
    // Search
    // =========================================================================

    public function getLeadId(Request $request): JsonResponse
    {
        try {
            // Search endpoints expose lead PII; gate on the list-view perm
            // so non-lead users can't enumerate by id. Phone-prefix search
            // is additionally guarded inside LeadService::searchByPhone.
            if (! Gate::allows('leads.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $leads = $this->leadService->searchLeadsById($request->search, Auth::user()->account_id);

            return $this->successResponse('Record found.', ['leads' => $leads]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function getLeadNumber(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('leads.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $lead = $this->leadService->findLead((int) $request->lead_id);

            return $this->successResponse('Record found.', [
                'lead' => $lead ? new LeadResource($lead) : null,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function phoneSearch(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('leads.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $leads = $this->leadService->searchByPhone($request->search, Auth::user()->account_id);

            return $this->successResponse('Record found.', ['leads' => $leads]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Comments
    // =========================================================================

    public function storeComment(StoreUpdateLeadCommentsRequest $request): JsonResponse
    {
        if (! Gate::allows('leads.comment.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $comment = $this->leadService->addComment((int) $request->lead_id, $request->comment);
            $comment->load('user');

            return $this->successResponse('Comment added.', [
                'username' => Auth::user()->name,
                'lead' => new LeadCommentResource($comment),
                'leadCommentDate' => $comment->created_at->format('D M, j Y h:i A'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Conversion
    // =========================================================================

    public function convert(int $id): JsonResponse
    {
        // Convert routes the lead into the appointment booking flow, so it
        // needs both the patient-facing convert perm AND the cross-module
        // appointments-manage perm. The appointments perm gate will be
        // re-pointed when the appointments module audit ships.
        if (! Gate::allows('appointments_manage') || ! Gate::allows('leads.convert')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $data = $this->leadService->getConversionData($id);

            if (! $data) {
                return $this->errorResponse('Resource not found.', 404);
            }

            return $this->successResponse('Record found.', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Loaders (Dropdown Data)
    // =========================================================================

    public function loadLeadStatuses(): JsonResponse
    {
        // `is_*` flags are surfaced so the SPA's manual-status dropdown
        // can hide automation-only statuses (Booked → set on appointment
        // create, Arrived → set on consultation invoice paid, Converted
        // → set on first package payment, Junk → owned by the dedicated
        // Move-to-junk action). Without these flags the SPA filter
        // `!s.is_booked && !s.is_arrived && …` evaluates `!undefined`
        // = true for every row and hides nothing.
        //
        // Gate on list view — the filter shelf + the inline status-change
        // dropdown both consume this; anyone who can see the list needs
        // it. Without a gate, dropdown data leaked to anyone authed.
        if (! Gate::allows('leads.list.view')) {
            return response()->json([]);
        }

        return response()->json(
            LeadStatuses::getActiveOnly()
                ->map(fn ($status): array => [
                    'value'        => $status->id,
                    'text'         => $status->name,
                    'is_booked'    => (bool) $status->is_booked,
                    'is_arrived'   => (bool) $status->is_arrived,
                    'is_converted' => (bool) $status->is_converted,
                    'is_junk'      => (bool) $status->is_junk,
                ])
                ->toArray()
        );
    }

    public function loadTreatments(): JsonResponse
    {
        if (! Gate::allows('leads.list.view')) {
            return response()->json([]);
        }

        return response()->json(
            Services::getActiveOnly()
                ->map(fn ($service): array => ['value' => $service->id, 'text' => $service->name])
                ->toArray()
        );
    }

    public function loadLeadSources(): JsonResponse
    {
        if (! Gate::allows('leads.list.view')) {
            return response()->json([]);
        }

        return response()->json(
            LeadSources::getActiveOnly()
                ->map(fn ($source): array => ['value' => $source->id, 'text' => $source->name])
                ->toArray()
        );
    }

    public function loadCities(): JsonResponse
    {
        // Cities dropdown is consumed by the inline City-change action, so
        // we gate it on the same `leads.update_city` perm. Returns [] for
        // users without the perm to keep the SPA dropdown from rendering
        // stale data on a 403.
        if (! Gate::allows('leads.update_city')) {
            return response()->json([]);
        }

        return response()->json(
            Cities::getActiveOnly(ACL::getUserCities(), Auth::user()->account_id)
                ->map(fn ($city): array => ['value' => $city->id, 'text' => $city->name])
                ->toArray()
        );
    }

    // =========================================================================
    // City / Lead Data / SMS / Junk
    // =========================================================================

    public function saveCity(Request $request): JsonResponse
    {
        if (! Gate::allows('leads.update_city')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->leadService->updateLeadCity((int) $request->get('pk'), (int) $request->get('value'));

            if (! $result) {
                return $this->errorResponse('Resource not found.', 404);
            }

            return $this->successResponse('City updated successfully.', [
                'city' => $result['city_name'],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function loadLeadData(Request $request): JsonResponse
    {
        try {
            return response()->json(
                $this->leadService->resolveLeadData($request->all(), Gate::allows('leads.list.view'))
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function sendSms(int $id): JsonResponse
    {
        if (! Gate::allows('leads.send_sms')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->leadService->sendSmsToLead($id);

            return $result['status']
                ? $this->successResponse('SMS has been sent successfully.')
                : $this->errorResponse('SMS sending failed.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    public function removeFromJunk(int $id): JsonResponse
    {
        // Restore-from-junk is logically a status revert, not a conversion.
        // The legacy gate was `leads_convert` — likely a copy/paste mismap
        // (the convert flow lives in convert()). Use the dedicated
        // update-status perm so revoking it disables both move-to-junk and
        // restore-from-junk, matching the SPA's canUpdateStatus pattern.
        if (! Gate::allows('leads.update_status')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->leadService->removeLeadFromJunk($id);

            return $result['status']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadsController');
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    protected function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('leads.edit'),
            'delete' => Gate::allows('leads.delete'),
            'create' => Gate::allows('leads.create'),
            'convert' => Gate::allows('leads.convert'),
            // Patient-style contact gate — replaces the cross-module
            // `contact` perm for the leads list/drawer so the role-editor
            // toggle `leads.list.view_contact` is the authority here.
            'contact' => Gate::allows('leads.list.view_contact'),
            'update_status' => Gate::allows('leads.update_status'),
            // Previously missing — the SPA's `canImport` / `canExport`
            // gated on these keys, but the response shape didn't include
            // them, so the buttons were hidden for everyone. Surfaced
            // now so role-editor changes are reflected.
            'import' => Gate::allows('leads.import'),
            'export' => Gate::allows('leads.export'),
            'update_city' => Gate::allows('leads.update_city'),
            'comment' => Gate::allows('leads.comment.create'),
            'send_sms' => Gate::allows('leads.send_sms'),
            // Tab visibility — the SPA shows / hides the Junk tab on
            // this flag. view_inactive is consumed by the legacy inactive
            // filter (Admin-v1) but kept here for parity.
            'view_junk' => Gate::allows('leads.list.view_junk'),
            'view_inactive' => Gate::allows('leads.list.view_inactive'),
        ];
    }
}
