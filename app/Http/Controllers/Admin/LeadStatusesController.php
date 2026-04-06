<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\SaveLeadStatusRecordRequest;
use App\Http\Resources\Lead\LeadStatusResource;
use App\Services\Lead\LeadStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadStatusesController extends Controller
{
    protected int $success;
    protected int $unauthorized;

    public function __construct(
        protected readonly LeadStatusService $service,
    ) {
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index(): \Illuminate\View\View
    {
        if (!Gate::allows('lead_statuses_manage')) {
            abort(401);
        }

        return view('admin.lead_statuses.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $accountId = Auth::user()->account_id;
            $filters = getFilters($request->all());

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $this->service->bulkDelete($ids, $accountId);
                return ApiHelper::apiResponse($this->success, 'Records have been deleted successfully!');
            }

            [$orderBy, $order] = getSortBy($request);
            $datatableData = $this->service->getDatatableData($request->all(), $accountId);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $records = $datatableData['query']
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy('sort_no')
                ->get();

            LeadStatusResource::$allStatuses = $datatableData['allStatuses'];

            return ApiHelper::apiDataTable([
                'data' => LeadStatusResource::collection($records),
                'permissions' => [
                    'edit' => Gate::allows('lead_statuses_edit'),
                    'delete' => Gate::allows('lead_statuses_destroy'),
                    'active' => Gate::allows('lead_statuses_active'),
                    'inactive' => Gate::allows('lead_statuses_inactive'),
                ],
                'active_filters' => $datatableData['active_filters'],
                'filter_values' => [
                    'parents' => $datatableData['parentStatuses'],
                    'status' => config('constants.status'),
                ],
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $displayLength,
                    'total' => $datatableData['total'],
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function create(): JsonResponse
    {
        if (!Gate::allows('lead_statuses_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $parentStatuses = $this->service->getParentRecords(Auth::user()->account_id, [], 'Parent Group');

        return ApiHelper::apiResponse($this->success, 'Success', true, [
            'parentLeadStatuses' => $parentStatuses,
            'lead_status' => [
                'is_default' => 0,
                'is_arrived' => 0,
                'is_converted' => 0,
                'is_junk' => 0,
            ],
        ]);
    }

    public function sortOrder(): \Illuminate\View\View
    {
        if (!Gate::allows('lead_statuses_sort')) {
            abort(401);
        }

        return view('admin.lead_statuses.sort');
    }

    public function sortOrderGet(): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $records = $this->service->getSortableRecords(Auth::user()->account_id);

            return ApiHelper::apiResponse($this->success, 'Success', true, $records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sortOrderSave(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $itemIds = $request->item_ids;
            if (!$itemIds || !count($itemIds)) {
                return ApiHelper::apiResponse($this->success, 'No items to sort.', false);
            }

            $this->service->saveSortOrder($itemIds);

            return ApiHelper::apiResponse($this->success, 'Records are sorted successfully!');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(SaveLeadStatusRecordRequest $request): JsonResponse
    {
        try {
            $record = $this->service->create($request->validated(), Auth::user()->account_id);

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.', true, new LeadStatusResource($record));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $record = $this->service->find($id);

            if (!$record) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            $parentStatuses = $this->service->getParentRecords(Auth::user()->account_id, $record->id);

            return ApiHelper::apiResponse($this->success, 'Success', true, [
                'lead_statuse' => new LeadStatusResource($record),
                'parentLeadStatuses' => $parentStatuses,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(SaveLeadStatusRecordRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->service->update($id, $request->validated(), Auth::user()->account_id);

            if (!$record) {
                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.', true, new LeadStatusResource($record));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $result = $this->service->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $gate = $request->status == 0 ? 'lead_statuses_inactive' : 'lead_statuses_active';

            if (!Gate::allows($gate)) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $result = $this->service->toggleStatus($request->id, (int) $request->status);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
