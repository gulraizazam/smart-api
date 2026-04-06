<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\StoreLeadSourceRequest;
use App\Http\Requests\Lead\UpdateLeadSourceRequest;
use App\Http\Resources\Lead\LeadSourceResource;
use App\Services\Lead\LeadSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadSourcesController extends Controller
{
    protected int $success;
    protected int $unauthorized;

    public function __construct(
        protected readonly LeadSourceService $service,
    ) {
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index(): \Illuminate\View\View
    {
        if (!Gate::allows('lead_sources_manage')) {
            abort(401);
        }

        return view('admin.lead_sources.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_manage')) {
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

            return ApiHelper::apiDataTable([
                'data' => LeadSourceResource::collection($records),
                'permissions' => [
                    'edit' => Gate::allows('lead_sources_edit'),
                    'delete' => Gate::allows('lead_sources_destroy'),
                    'active' => Gate::allows('lead_sources_active'),
                    'inactive' => Gate::allows('lead_sources_inactive'),
                ],
                'active_filters' => $datatableData['active_filters'],
                'filter_values' => ['status' => config('constants.status')],
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

    public function sortOrder(): \Illuminate\View\View
    {
        if (!Gate::allows('lead_sources_sort')) {
            abort(401);
        }

        return view('admin.lead_sources.sort');
    }

    public function sortOrderGet(): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $records = $this->service->getSortableRecords(Auth::user()->account_id);

            return ApiHelper::apiResponse($this->success, 'Success', true, LeadSourceResource::collection($records));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sortOrderSave(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_sort')) {
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

    public function store(StoreLeadSourceRequest $request): JsonResponse
    {
        try {
            $record = $this->service->create($request->validated(), Auth::user()->account_id);

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.', true, new LeadSourceResource($record));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $record = $this->service->find($id);

            if (!$record) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, new LeadSourceResource($record));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(UpdateLeadSourceRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->service->update($id, $request->validated(), Auth::user()->account_id);

            if (!$record) {
                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.', true, new LeadSourceResource($record));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_destroy')) {
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
            $gate = $request->status == 0 ? 'lead_sources_inactive' : 'lead_sources_active';

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
