<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Schedule\BusinessClosureService;
use App\Http\Requests\Schedule\StoreBusinessClosureRequest;
use App\Http\Requests\Schedule\UpdateBusinessClosureRequest;
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


    }

    /**
     * Get business closures datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        if (!Gate::allows('business_closures_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $filters = getFilters($request->all());

            // Handle bulk delete
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $deleted = $this->service->bulkDelete($ids);
                return $this->successResponse("{$deleted} records deleted successfully.");
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

            return response()->json($records);

        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Get form data for creating a new closure
     */
    public function create(): JsonResponse
    {
        if (!Gate::allows('business_closures_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
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

            return $this->successResponse('Data loaded successfully.', [
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Store a new business closure
     */
    public function store(StoreBusinessClosureRequest $request): JsonResponse
    {
        if (!Gate::allows('business_closures_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $closure = $this->service->create($request->validated());

            return $this->successResponse('Business closure created successfully.', [
                'closure' => $closure,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Get a single business closure for editing
     */
    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('business_closures_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $closure = $this->service->getById($id);

            if (!$closure) {
                return $this->errorResponse('Business closure not found.', 500);
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

            return $this->successResponse('Data loaded successfully.', [
                'closure' => $closure,
                'location_ids' => $closure->locations->pluck('id')->toArray(),
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Update a business closure
     */
    public function update(UpdateBusinessClosureRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('business_closures_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $closure = $this->service->update($id, $request->validated());

            return $this->successResponse('Business closure updated successfully.', [
                'closure' => $closure,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Delete a business closure
     */
    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('business_closures_delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $this->service->delete($id);

            return $this->successResponse('Business closure deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }
}
