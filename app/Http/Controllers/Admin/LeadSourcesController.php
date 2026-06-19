<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
    public function __construct(
        protected readonly LeadSourceService $service,
    ) {

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
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $accountId = Auth::user()->account_id;
            $filters = getFilters($request->all());

            if (hasFilter($filters, 'delete')) {
                if (! Gate::allows('lead_sources_destroy')) {
                    return $this->errorResponse('You are not authorized to delete these records.', 403);
                }
                $ids = explode(',', $filters['delete']);
                $this->service->bulkDelete($ids, $accountId);
                return $this->successResponse('Records have been deleted successfully!');
            }

            [$orderBy, $order] = getSortBy($request);
            $datatableData = $this->service->getDatatableData($request->all(), $accountId);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $records = $datatableData['query']
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy('sort_no')
                ->get();

            return response()->json([
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
            return $this->handleException($e, 'LeadSourcesController');
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
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $records = $this->service->getSortableRecords(Auth::user()->account_id);

            return $this->successResponse('Success', LeadSourceResource::collection($records));
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }

    public function sortOrderSave(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $itemIds = $request->item_ids;
            if (!$itemIds || !count($itemIds)) {
                return $this->errorResponse('No items to sort.', 404);
            }

            $this->service->saveSortOrder($itemIds);

            return $this->successResponse('Records are sorted successfully!');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }

    public function store(StoreLeadSourceRequest $request): JsonResponse
    {
        try {
            $record = $this->service->create($request->validated(), Auth::user()->account_id);

            return $this->successResponse('Record has been created successfully.', new LeadSourceResource($record));
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $record = $this->service->find($id);

            if (!$record) {
                return $this->errorResponse('No Record Found!', 404);
            }

            return $this->successResponse('Success', new LeadSourceResource($record));
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }

    public function update(UpdateLeadSourceRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->service->update($id, $request->validated(), Auth::user()->account_id);

            if (!$record) {
                return $this->errorResponse('Something went wrong, please try again later.', 404);
            }

            return $this->successResponse('Record has been updated successfully.', new LeadSourceResource($record));
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->service->delete($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $gate = $request->status == 0 ? 'lead_sources_inactive' : 'lead_sources_active';

            if (!Gate::allows($gate)) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->service->toggleStatus((int) $request->id, (int) $request->status);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadSourcesController');
        }
    }
}
