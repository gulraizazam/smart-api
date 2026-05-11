<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ServiceException;
use App\Helpers\Filters;
use App\Helpers\ServiceHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\BundleImpactRequest;
use App\Http\Requests\Service\SaveSortOrderRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Requests\Service\UpdateServiceStatusRequest;
use App\Http\Resources\Service\ServiceDatatableResource;
use App\Http\Resources\Service\ServiceFormDataResource;
use App\Models\Services;
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Preview which ServiceBundles would be re-priced if this service's price changes.
     * Returns an empty list when the service has no bundles or the price is unchanged.
     */
    public function bundleImpact(BundleImpactRequest $request, int $id): JsonResponse
    {
        try {
            $affected = $this->serviceService->previewServiceBundleImpact(
                $id,
                (float) $request->validated('new_price'),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found', ['affected' => $affected]);
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
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

            return $result['status']
                ? $this->successResponse($result['message'])
                : $this->failResponse($result['message']);
        } catch (ServiceException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
            return $this->exceptionToResponse($e);
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
                (int) $request->validated('parent_id'),
            );

            return $saved
                ? $this->successResponse('Records are sorted successfully!')
                : $this->failResponse('Something went wrong! Records are not sorted.');
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Save category (parent) sort order.
     */
    public function categorySortOrderSave(Request $request): JsonResponse
    {
        if (! Gate::allows('services_sort')) {
            return $this->unauthorizedResponse();
        }

        try {
            $categoryIds = $request->input('category_ids', []);

            if (empty($categoryIds) || ! is_array($categoryIds)) {
                return $this->failResponse('No categories to sort.');
            }

            $ids = array_map('intval', $categoryIds);
            $cases = [];
            $bindings = [];
            foreach ($ids as $sortNo => $id) {
                $cases[] = 'WHEN id = ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $sortNo;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $bindings = array_merge($bindings, $ids);

            \DB::update(
                'UPDATE services SET sort_no = CASE ' . implode(' ', $cases) . ' END WHERE id IN (' . $placeholders . ') AND (parent_id IS NULL OR parent_id = 0)',
                $bindings
            );

            return $this->successResponse('Category order saved!');
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
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

    protected function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status'  => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ], $code);
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

    private function exceptionToResponse(\Exception $e): JsonResponse
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
