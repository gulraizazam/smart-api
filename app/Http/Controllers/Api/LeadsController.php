<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Lead\LeadService;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Http\Requests\Lead\ImportLeadsRequest;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Lead\LeadDetailResource;
use App\Http\Resources\Lead\LeadCommentResource;
use App\Exceptions\LeadException;
use App\HelperModule\ApiHelper;
use App\Models\Leads;
use App\Models\User;
use App\Models\LeadStatuses;
use App\Models\Services;
use App\Models\Locations;
use App\Exports\ExportLead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadsController extends Controller
{
    protected string $success;
    protected string $error;
    protected string $unauthorized;

    public function __construct(
        protected readonly LeadService $leadService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

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
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($datatableData['orderBy'], $datatableData['order'])
                ->get();

            // Batch-load lookup data for LeadResource status resolution
            $statusIds = $leads->pluck('lead_status_id')->unique()->filter()->toArray();
            $leadStatuses = LeadStatuses::whereIn('id', $statusIds)
                ->select('id', 'name', 'parent_id')
                ->get()
                ->keyBy('id')
                ->toArray();

            // Also load parent statuses that aren't in the current set
            $parentIds = collect($leadStatuses)->pluck('parent_id')->filter()->diff(array_keys($leadStatuses))->unique()->toArray();
            if (!empty($parentIds)) {
                $parentStatuses = LeadStatuses::whereIn('id', $parentIds)->select('id', 'name', 'parent_id')->get()->keyBy('id')->toArray();
                $leadStatuses = array_replace($leadStatuses, $parentStatuses);
            }

            LeadResource::$statusLookup = $leadStatuses;

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

    public function create(): JsonResponse
    {
        if (!Gate::allows('leads_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $formData = $this->leadService->getFormLookupData();
            $employees = User::getAllActiveRecords(Auth::user()->account_id)?->pluck('full_name', 'id') ?? [];

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'Services' => $formData['services'],
                'cities' => $formData['cities'],
                'lead_sources' => $formData['lead_sources'],
                'lead_statuses' => $formData['lead_statuses'],
                'lead' => [
                    'id' => null,
                    'name' => null,
                    'email' => null,
                    'phone' => null,
                    'gender' => null,
                ],
                'leadServices' => null,
                'employees' => $employees,
                'edit_status' => 0,
                'gender' => $formData['gender'],
            ]);
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
            $lead = $this->leadService->getLeadForEdit($id);

            if (!$lead) {
                return ApiHelper::apiResponse($this->success, 'Resource not found', false);
            }

            $formData = $this->leadService->getFormLookupData();
            $locations = Locations::where(['active' => 1, 'city_id' => $lead->city_id])->pluck('name', 'id');
            $employees = User::getAllActiveRecords(Auth::user()->account_id)?->pluck('full_name', 'id') ?? [];

            $childServiceIds = $lead->lead_service->pluck('child_service_id')->toArray();
            $childServices = Services::whereIn('id', $childServiceIds)
                ->where(['slug' => 'custom', 'active' => 1])
                ->pluck('name', 'id');

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'Services' => $formData['services'],
                'child_services' => $childServices,
                'lead' => new LeadDetailResource($lead),
                'locations' => $locations,
                'cities' => $formData['cities'],
                'lead_sources' => $formData['lead_sources'],
                'lead_statuses' => $formData['lead_statuses'],
                'employees' => $employees,
                'edit_status' => 1,
                'gender' => $formData['gender'],
            ]);
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

    public function showLeadStatuses(Request $request): JsonResponse
    {
        if (!Gate::allows('leads_lead_status')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $data = $this->leadService->getLeadStatusesWithChildren($request->get('id'));
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

    public function loadChildServices(Request $request): JsonResponse
    {
        try {
            $childServices = $this->leadService->getChildServices($request->serviceId);
            $lead = $request->leadId ? Leads::with('lead_service')->find($request->leadId) : null;

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' => $childServices,
                'lead_child_service' => $lead?->lead_service ?? '',
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function uploadLeads(ImportLeadsRequest $request): JsonResponse
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        try {
            $file = $request->file('leads_file');
            $collections = (new FastExcel)->import($file);

            $rows = $collections->map(function ($collection) {
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
                $message .= ". Invalid phones: " . count($stats['invalid_phones']);
            }
            if (!empty($stats['invalid_services'])) {
                $message .= ". Invalid services: " . implode(', ', $stats['invalid_services']);
            }

            return ApiHelper::apiResponse($this->success, $message);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        }
    }

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
            $lead = Leads::find($request->lead_id);
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

    public function editService(int $leadId, int $serviceId): JsonResponse
    {
        try {
            $leadService = \App\Models\LeadsServices::with('service', 'childservice')
                ->where(['lead_id' => $leadId, 'service_id' => $serviceId])
                ->get();

            $services = Services::where(['slug' => 'custom', 'parent_id' => 0, 'active' => 1])->pluck('name', 'id');
            $childServices = Services::where(['slug' => 'custom', 'parent_id' => $serviceId, 'active' => 1])->pluck('name', 'id');

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'lead_service' => $leadService,
                'Services' => $services,
                'Child_service' => $childServices,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function storeComment(Request $request): JsonResponse
    {
        try {
            $comment = $this->leadService->addComment($request->lead_id, $request->comment);
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

    public function convert(int $id): JsonResponse
    {
        if (!Gate::allows('appointments_manage') || !Gate::allows('leads_convert')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $lead = Leads::with(['lead_service', 'patient'])->where([
                'id' => $id,
                'account_id' => Auth::user()->account_id,
            ])->first();

            if (!$lead) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }

            $accountId = Auth::user()->account_id;
            $userInfo = User::where(['id' => $lead->patient_id, 'active' => 1, 'account_id' => $accountId])->first();
            $employees = User::getAllActiveRecords($accountId)?->pluck('full_name', 'id') ?? [];
            $cities = \App\Models\Cities::getActiveFeaturedOnly(\App\Helpers\ACL::getUserCities(), $accountId)
                ->get()?->pluck('full_name', 'id') ?? collect();
            $leadSources = \App\Models\LeadSources::getActiveSorted();
            $services = Services::getGroupsActiveOnly()->pluck('name', 'id');
            $setting = \App\Models\Settings::where('slug', 'sys-virtual-consultancy')->first();

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'services' => $services,
                'lead' => new LeadDetailResource($lead),
                'employees' => $employees,
                'cities' => $cities,
                'lead_sources' => $leadSources,
                'user_info' => $userInfo,
                'setting' => $setting,
                'consultancy_types' => Config::get('constants.consultancy_type_array'),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function exportPdf(Request $request): BinaryFileResponse|JsonResponse
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        try {
            $leads = $this->buildExportQuery($request)
                ->with([
                    'lead_service' => fn($q) => $q->where('status', 1)->with(['service:id,name', 'childservice:id,name']),
                    'city:id,name',
                    'towns:id,name',
                    'region:id,name',
                    'lead_status:id,name',
                    'user:id,name',
                ])
                ->get();

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

    public function leadStatusesPopCheck(Request $request): JsonResponse
    {
        try {
            $leadStatus = LeadStatuses::find($request->id);

            if (!$leadStatus) {
                return ApiHelper::apiResponse($this->error, 'Status not found.', false);
            }

            $childStatuses = LeadStatuses::where('parent_id', $leadStatus->id)->get();

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'd' => $childStatuses,
                'lead_status' => $leadStatus,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function leadStatusChildPopCheck(Request $request): JsonResponse
    {
        try {
            $childStatus = LeadStatuses::find($request->id);
            $parentStatus = $childStatus ? LeadStatuses::find($childStatus->parent_id) : null;

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'd' => $childStatus,
                'lead_status2' => $parentStatus,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function loadLeadStatuses(): JsonResponse
    {
        $data = LeadStatuses::getActiveOnly()
            ->map(fn($status) => ['value' => $status->id, 'text' => $status->name])
            ->toArray();

        return response()->json($data);
    }

    public function loadTreatments(): JsonResponse
    {
        $data = Services::getActiveOnly()
            ->map(fn($service) => ['value' => $service->id, 'text' => $service->name])
            ->toArray();

        return response()->json($data);
    }

    public function loadLeadSources(): JsonResponse
    {
        $data = \App\Models\LeadSources::getActiveOnly()
            ->map(fn($source) => ['value' => $source->id, 'text' => $source->name])
            ->toArray();

        return response()->json($data);
    }

    public function loadCities(): JsonResponse
    {
        if (!Gate::allows('leads_city')) {
            return response()->json([]);
        }

        $data = \App\Models\Cities::getActiveOnly(\App\Helpers\ACL::getUserCities(), Auth::user()->account_id)
            ->map(fn($city) => ['value' => $city->id, 'text' => $city->name])
            ->toArray();

        return response()->json($data);
    }

    public function saveCity(Request $request): JsonResponse
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $city = \App\Models\Cities::find($request->get('value'));
            $lead = Leads::find($request->get('pk'));

            if (!$lead || !$city) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }

            $lead->update([
                'city_id' => $city->id,
                'region_id' => $city->region_id,
            ]);

            return ApiHelper::apiResponse($this->success, 'City updated successfully.', true, [
                'city' => $city->name,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function loadLeadData(Request $request): JsonResponse
    {
        $data = $request->all();
        $data['status'] = 0;
        $data['patient_id'] = 0;

        if (!Gate::allows('leads_manage') || !$request->get('phone') || $request->get('lead_id')) {
            return response()->json($data);
        }

        $phone = $request->input('phone') === '***********'
            ? $request->input('old_phone')
            : $request->input('phone');

        $phone = \App\Helpers\GeneralFunctions::cleanNumber($phone);
        $patient = \App\Models\Patients::getByPhone($phone, Auth::user()->account_id, $request->patient_id);

        if (!$patient) {
            $data['status'] = 1;
            $data['service_id'] = $request->get('service_id');
            $data['phone'] = $request->get('phone');
            $data['cnic'] = $request->get('cnic');
            $data['dob'] = $request->get('dob');
            $data['address'] = $request->get('address');
            $data['referred_by'] = $request->get('referred_by');
        } else {
            $lead = Leads::where([
                'patient_id' => $patient->id,
                'service_id' => $request->get('service_id'),
            ])->first();

            if ($lead) {
                $data['id'] = $lead->id;
                $data['city_id'] = $lead->city_id;
                $data['town_id'] = $lead->town_id;
                $data['service_id'] = $lead->service_id;
                $data['lead_source_id'] = $lead->lead_source_id;
                $data['lead_status_id'] = $lead->lead_status_id;
            } else {
                $data['service_id'] = $request->get('service_id');
            }

            $data['patient_id'] = $patient->id;
            $data['gender'] = $patient->gender;
            $data['phone'] = $patient->phone;
            $data['cnic'] = $patient->cnic;
            $data['dob'] = $patient->dob;
            $data['address'] = $patient->address;
            $data['name'] = $patient->name;
            $data['email'] = $patient->email;
            $data['referred_by'] = $patient->referred_by;
        }

        return response()->json($data);
    }

    public function sendSms(int $id): JsonResponse
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $lead = Leads::findOrFail($id);
            $response = $this->leadService->sendSMS($lead->id, $lead->phone);

            return $response['status']
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
            $lead = Leads::where([
                'id' => $id,
                'account_id' => Auth::user()->account_id,
            ])->first();

            if (!$lead) {
                return ApiHelper::apiResponse($this->error, 'Lead not found.');
            }

            $openStatus = \App\Helpers\LeadHelper::getDefaultStatus(Auth::user()->account_id);

            if (!$openStatus) {
                return ApiHelper::apiResponse($this->error, 'Open status not found.');
            }

            $lead->update([
                'lead_status_id' => $openStatus->id,
                'updated_by' => Auth::id(),
            ]);

            \App\Models\LeadsServices::where('lead_id', $lead->id)
                ->where('status', 1)
                ->update(['lead_status_id' => $openStatus->id]);

            return ApiHelper::apiResponse($this->success, 'Lead has been removed from junk.');
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

    protected function buildExportQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $where = [];

        if ($request->created_at) {
            $dateRange = explode(' - ', $request->created_at);
            $startDate = date('Y-m-d H:i:s', strtotime($dateRange[0]));
            $endDate = (new \DateTime($dateRange[1]))->setTime(23, 59, 0)->format('Y-m-d H:i:s');
            $where[] = ['created_at', '>=', $startDate];
            $where[] = ['created_at', '<=', $endDate];
        }

        $simpleFilters = [
            'id' => 'id',
            'lead_status_id' => 'lead_status_id',
            'city_id' => 'city_id',
            'location_id' => 'location_id',
            'region_id' => 'region_id',
            'created_by' => 'created_by',
            'phone' => 'phone',
            'gender_id' => 'gender',
        ];

        foreach ($simpleFilters as $requestKey => $column) {
            $value = $request->$requestKey;
            if ($value && $value !== 'undefined' && $value !== 'null') {
                $where[] = [$column, '=', $value];
            }
        }

        if ($request->name) {
            $where[] = ['name', 'like', '%' . $request->name . '%'];
        }

        $userCities = \App\Helpers\ACL::getUserCities();
        $query = Leads::forAccount()
            ->forCities($userCities);

        if (!empty($where)) {
            $query->where($where);
        }

        if ($request->service_id) {
            $serviceId = $request->service_id;
            $query->with(['lead_service' => fn($q) => $q->where(['service_id' => $serviceId, 'status' => 1])->whereNotNull('service_id')]);
        } else {
            $query->with(['lead_service' => fn($q) => $q->where('status', 1)->whereNotNull('service_id')]);
        }

        return $query->select([
            '*',
            'leads.created_by as lead_created_by',
            'leads.id as lead_id',
            'leads.created_at as lead_created_at',
        ])->orderByDesc('id')->latest();
    }
}
