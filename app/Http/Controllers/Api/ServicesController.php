<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ServiceException;
use App\Helpers\Filters;
use App\Helpers\ServiceHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\SaveSortOrderRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Requests\Service\UpdateServiceStatusRequest;
use App\Http\Resources\Service\ServiceDatatableResource;
use App\Http\Resources\Service\ServiceFormDataResource;
use App\Services\Service\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ServicesController extends Controller
{
    public function __construct(
        private readonly ServiceService $serviceService,
    ) {}

    /**
     * Get services datatable data.
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $accountId = $user->account_id;
            $filters = getFilters($request->all());
            $records = ['data' => []];

            // Handle bulk delete
            if (hasFilter($filters, 'delete')) {
                $ids = array_filter(array_map('intval', explode(',', $filters['delete'])));
                foreach ($ids as $id) {
                    try {
                        $this->serviceService->deleteService($id, $accountId);
                    } catch (ServiceException) {
                        continue;
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records have been deleted successfully!';
            }

            $this->applyFilters($filters, $user->id);

            [$orderBy, $order] = getSortBy($request);
            $canViewInactive = ServiceHelper::canViewInactive();

            $totalRecords = $this->serviceService->getTotalRecords($filters, $accountId, $canViewInactive);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

            $services = $this->serviceService->getServicesList($filters, $accountId, $canViewInactive);

            $records = $this->appendFilterData($records, $accountId, $user->id);

            if (! empty($services)) {
                $records['data'] = ServiceDatatableResource::collection($services)->resolve();
                $records['permissions'] = ServiceHelper::getPermissions();
                $records['meta'] = [
                    'field'   => $orderBy,
                    'page'    => $page,
                    'pages'   => $pages,
                    'perpage' => 100,
                    'total'   => $totalRecords,
                    'sort'    => $order,
                ];
            }

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get form data for creating a new service.
     */
    public function create(): JsonResponse
    {
        if (! Gate::allows('services_create')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->serviceService->getFormData(Auth::user()->account_id);

            return $this->successResponse('Record found', new ServiceFormDataResource($data));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Store a new service.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $service = $this->serviceService->createService(
                $request->validated(),
                $user->account_id,
                $user->id,
            );

            return $service
                ? $this->successResponse('Record has been created successfully.')
                : $this->failResponse('Something went wrong, please try again later.');
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get service for editing.
     */
    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('services_edit')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->serviceService->getFormData(Auth::user()->account_id, $id);

            return $this->successResponse('Record found', new ServiceFormDataResource($data));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get service details (description/instructions modal).
     */
    public function show(int $id): JsonResponse
    {
        if (! Gate::allows('services_manage')) {
            return $this->unauthorizedResponse();
        }

        try {
            $description = $this->serviceService->getServiceDescription($id);

            return $this->successResponse('Record found', ['description' => $description]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Update a service.
     */
    public function update(UpdateServiceRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $service = $this->serviceService->updateService(
                $id,
                $request->validated(),
                $user->account_id,
                $user->id,
            );

            return $service
                ? $this->successResponse('Record has been updated successfully.')
                : $this->failResponse('Something went wrong, please try again later.');
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Delete a service.
     */
    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('services_destroy')) {
            return $this->unauthorizedResponse();
        }

        try {
            $result = $this->serviceService->deleteService($id, Auth::user()->account_id);

            return $this->successResponse($result['message'], null, $result['status']);
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Update service status (active/inactive).
     */
    public function status(UpdateServiceStatusRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $serviceId = (int) $request->validated('id');
            $status = (int) $request->validated('status');

            match ($status) {
                1 => $this->serviceService->activateService($serviceId, $user->account_id),
                0 => $this->serviceService->deactivateService($serviceId, $user->account_id),
            };

            return $this->successResponse('Status has been changed successfully.');
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get service data for duplication.
     */
    public function duplicate(int $id): JsonResponse
    {
        if (! Gate::allows('services_duplicate')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->serviceService->getFormData(Auth::user()->account_id, $id);

            return $this->successResponse('Record found', new ServiceFormDataResource($data));
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Store duplicated service.
     */
    public function storeDuplicate(StoreServiceRequest $request): JsonResponse
    {
        if (! Gate::allows('services_duplicate')) {
            return $this->unauthorizedResponse();
        }

        try {
            $user = Auth::user();
            $service = $this->serviceService->createService(
                $request->validated(),
                $user->account_id,
                $user->id,
            );

            return $service
                ? $this->successResponse('Service has been duplicated successfully.')
                : $this->failResponse('Something went wrong, please try again later.');
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get services for sorting.
     */
    public function sortOrderGet(): JsonResponse
    {
        if (! Gate::allows('services_sort')) {
            return $this->unauthorizedResponse();
        }

        try {
            $services = $this->serviceService->getServicesForSort(Auth::user()->account_id);

            return $this->successResponse('Success', $services);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Save sort order.
     */
    public function sortOrderSave(SaveSortOrderRequest $request): JsonResponse
    {
        try {
            $saved = $this->serviceService->saveSortOrder(
                $request->validated('item_ids'),
                Auth::user()->account_id,
            );

            return $saved
                ? $this->successResponse('Records are sorted successfully!')
                : $this->failResponse('Something went wrong! Records are not sorted.');
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get service color.
     *
     * Note: Flat response format preserved for frontend compatibility.
     * The JS reads `data.color` directly from $.get() callback.
     */
    public function getColor(Request $request): JsonResponse
    {
        $serviceId = (int) $request->input('service', 0);

        return response()->json([
            'color' => $this->serviceService->getServiceColor($serviceId),
        ]);
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    private function applyFilters(array $filters, int $userId): void
    {
        $filename = 'services';
        $applyFilter = checkFilters($filters, $filename);

        foreach (['name', 'status'] as $key) {
            if (hasFilter($filters, $key)) {
                Filters::put($userId, $filename, $key, $filters[$key]);
            } elseif ($applyFilter) {
                Filters::forget($userId, $filename, $key);
            }
        }
    }

    private function appendFilterData(array $records, int $accountId, int $userId): array
    {
        $records['filter_values'] = [
            'services' => ServiceHelper::getParentServices($accountId),
            'status'   => config('constants.status'),
        ];
        $records['active_filters'] = Filters::all($userId, 'services');

        return $records;
    }

    // ──────────────────────────────────────────────
    // Standardized response helpers
    // ──────────────────────────────────────────────

    private function successResponse(string $message, mixed $data = null, bool $success = true): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'status'  => $success,   // backward compat — JS reads response.status
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ], 200);
    }

    private function failResponse(string $message, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], 200);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status'  => false,
            'message' => 'You are not authorized to access this resource.',
            'data'    => null,
            'errors'  => [],
        ], 403);
    }

    private function errorResponse(\Exception $e): JsonResponse
    {
        $message = config('app.debug')
            ? $e->getMessage() . ' Line ' . $e->getLine() . ' File ' . $e->getFile()
            : 'Something went wrong, please try again later.';

        return response()->json([
            'success' => false,
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => [],
        ], 500);
    }
}
