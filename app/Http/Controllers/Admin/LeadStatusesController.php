<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
    public function __construct(
        protected readonly LeadStatusService $service,
    ) {

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
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $accountId = Auth::user()->account_id;
            $filters = getFilters($request->all());

            if (hasFilter($filters, 'delete')) {
                if (! Gate::allows('lead_statuses_destroy')) {
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

            LeadStatusResource::$allStatuses = $datatableData['allStatuses'];

            return response()->json([
                'data' => LeadStatusResource::collection($records),
                'permissions' => [
                    'create' => Gate::allows('lead_statuses_create'),
                    'edit' => Gate::allows('lead_statuses_edit'),
                    'delete' => Gate::allows('lead_statuses_destroy'),
                    'active' => Gate::allows('lead_statuses_active'),
                    'inactive' => Gate::allows('lead_statuses_inactive'),
                    'sort' => Gate::allows('lead_statuses_sort'),
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
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function create(): JsonResponse
    {
        if (!Gate::allows('lead_statuses_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $parentStatuses = $this->service->getParentRecords(Auth::user()->account_id, [], 'Parent Group');

        // New rows start with every unique flag off; the SPA uses this
        // shape as the form's initial state. Matches LeadStatusService's
        // UNIQUE_FLAGS (is_default/is_booked/is_arrived/is_converted/
        // is_junk) plus is_comment so the TS contract is complete.
        return $this->successResponse('Success', [
            'parentLeadStatuses' => $parentStatuses,
            'lead_status' => [
                'is_default' => 0,
                'is_booked' => 0,
                'is_arrived' => 0,
                'is_converted' => 0,
                'is_junk' => 0,
                'is_comment' => 0,
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
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $records = $this->service->getSortableRecords(Auth::user()->account_id);

            return $this->successResponse('Success', $records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function sortOrderSave(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $itemIds = $request->item_ids;
            if (!$itemIds || !count($itemIds)) {
                return $this->errorResponse('No items to sort.', 404);
            }

            $this->service->saveSortOrder($itemIds, Auth::user()->account_id);

            return $this->successResponse('Records are sorted successfully!');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function store(SaveLeadStatusRecordRequest $request): JsonResponse
    {
        try {
            $record = $this->service->create($request->validated(), Auth::user()->account_id);

            return $this->successResponse('Record has been created successfully.', new LeadStatusResource($record));
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $record = $this->service->find($id);

            if (!$record) {
                return $this->errorResponse('No Record Found!', 404);
            }

            $parentStatuses = $this->service->getParentRecords(Auth::user()->account_id, $record->id);

            return $this->successResponse('Success', [
                'lead_statuse' => new LeadStatusResource($record),
                'parentLeadStatuses' => $parentStatuses,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function update(SaveLeadStatusRecordRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->service->update($id, $request->validated(), Auth::user()->account_id);

            if (!$record) {
                return $this->errorResponse('Something went wrong, please try again later.', 404);
            }

            return $this->successResponse('Record has been updated successfully.', new LeadStatusResource($record));
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->service->delete($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $gate = $request->status == 0 ? 'lead_statuses_inactive' : 'lead_statuses_active';

            if (!Gate::allows($gate)) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->service->toggleStatus((int) $request->id, (int) $request->status);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeadStatusesController');
        }
    }
}
