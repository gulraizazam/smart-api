<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Schedule\BusinessClosureService;
use App\Http\Requests\Schedule\StoreBusinessClosureRequest;
use App\Http\Requests\Schedule\UpdateBusinessClosureRequest;
use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Models\Locations;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class BusinessClosureController extends Controller
{
    protected BusinessClosureService $service;
    protected string $success;
    protected string $error;
    protected string $unauthorized;

    public function __construct(BusinessClosureService $service)
    {
        $this->service = $service;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Get business closures datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        if (!Gate::allows('business_closures_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $filters = getFilters($request->all());

            // Handle bulk delete
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $deleted = $this->service->bulkDelete($ids);
                return ApiHelper::apiResponse($this->success, "{$deleted} records deleted successfully.", true);
            }

            $datatableData = $this->service->getDatatableData($filters);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $closures = $datatableData['query']
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($datatableData['orderBy'], $datatableData['order'])
                ->get();

            $records = [
                'data' => [],
            ];

            foreach ($closures as $closure) {
                $locationNames = $closure->locations->isEmpty() 
                    ? 'All Locations' 
                    : $closure->locations->pluck('name')->implode(', ');

                $records['data'][] = [
                    'id' => $closure->id,
                    'locations' => $locationNames,
                    'start_date' => Carbon::parse($closure->start_date)->format('D M j, Y'),
                    'end_date' => Carbon::parse($closure->end_date)->format('D M j, Y'),
                    'title' => $closure->title ?? '-',
                    'created_by' => $closure->creator->name ?? 'N/A',
                    'created_at' => Carbon::parse($closure->created_at)->format('M j, Y h:i A'),
                ];
            }

            $records['meta'] = [
                'page' => $page,
                'pages' => $pages,
                'perpage' => $displayLength,
                'total' => $datatableData['total'],
                'sort' => $datatableData['order'],
                'field' => $datatableData['orderBy'],
            ];

            // Add filter values
            $filterData = $this->service->getFilterValues();
            $records['filter_values'] = $filterData['filter_values'];
            $records['active_filters'] = $filterData['active_filters'];

            $records['permissions'] = [
                'create' => Gate::allows('business_closures_create'),
                'edit' => Gate::allows('business_closures_edit'),
                'delete' => Gate::allows('business_closures_delete'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get form data for creating a new closure
     */
    public function create(): JsonResponse
    {
        if (!Gate::allows('business_closures_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $userCentres = ACL::getUserCentres();
            $locationsQuery = Locations::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
            ]);
            
            if ($userCentres && is_array($userCentres) && count($userCentres) > 0) {
                $locationsQuery->whereIn('id', $userCentres);
            }
            
            $locations = $locationsQuery->orderBy('name', 'asc')->get(['id', 'name']);

            return ApiHelper::apiResponse($this->success, 'Data loaded successfully.', true, [
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a new business closure
     */
    public function store(StoreBusinessClosureRequest $request): JsonResponse
    {
        if (!Gate::allows('business_closures_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $closure = $this->service->create($request->validated());

            return ApiHelper::apiResponse($this->success, 'Business closure created successfully.', true, [
                'closure' => $closure,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get a single business closure for editing
     */
    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('business_closures_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $closure = $this->service->getById($id);

            if (!$closure) {
                return ApiHelper::apiResponse($this->error, 'Business closure not found.', false);
            }

            $userCentres = ACL::getUserCentres();
            $locationsQuery = Locations::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
            ]);
            
            if ($userCentres && is_array($userCentres) && count($userCentres) > 0) {
                $locationsQuery->whereIn('id', $userCentres);
            }
            
            $locations = $locationsQuery->orderBy('name', 'asc')->get(['id', 'name']);

            return ApiHelper::apiResponse($this->success, 'Data loaded successfully.', true, [
                'closure' => $closure,
                'location_ids' => $closure->locations->pluck('id')->toArray(),
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update a business closure
     */
    public function update(UpdateBusinessClosureRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('business_closures_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $closure = $this->service->update($id, $request->validated());

            return ApiHelper::apiResponse($this->success, 'Business closure updated successfully.', true, [
                'closure' => $closure,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete a business closure
     */
    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('business_closures_delete')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $this->service->delete($id);

            return ApiHelper::apiResponse($this->success, 'Business closure deleted successfully.', true);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
