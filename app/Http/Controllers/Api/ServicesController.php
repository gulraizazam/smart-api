<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ServiceException;
use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\ServiceHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Requests\Service\UpdateServiceStatusRequest;
use App\Models\Services;
use App\Services\Service\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ServicesController extends Controller
{
    protected string $success;
    protected string $error;
    protected string $unauthorized;
    protected ServiceService $serviceService;

    public function __construct(ServiceService $serviceService)
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
        $this->serviceService = $serviceService;
    }

    /**
     * Get services datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $filters = getFilters($request->all());
            $records = ['data' => []];

            // Handle bulk delete
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                foreach ($ids as $id) {
                    try {
                        $this->serviceService->deleteService((int)$id, $accountId);
                    } catch (ServiceException $e) {
                        // Skip services that can't be deleted
                        continue;
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records have been deleted successfully!';
            }

            // Get sorting
            [$orderBy, $order] = getSortBy($request);

            // Get total records
            $totalRecords = $this->serviceService->getTotalRecords($request, $accountId);

            // Get pagination elements
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

            // Get services list
            $services = $this->serviceService->getServicesList($request, $accountId);

            // Get filter values
            $records = $this->getExtraData($records);

            if (!empty($services)) {
                $records['data'] = $services;
                $records['permissions'] = ServiceHelper::getPermissions();
                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => 100,
                    'total' => $totalRecords,
                    'sort' => $order,
                ];
            }

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get extra data for filters
     */
    private function getExtraData(array $records = []): array
    {
        $accountId = Auth::user()->account_id;
        $filters = Filters::all(Auth::user()->id, 'services');

        $records['filter_values'] = [
            'services' => ServiceHelper::getParentServices($accountId),
            'status' => config('constants.status'),
        ];

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Get form data for creating a new service
     */
    public function create(): JsonResponse
    {
        if (!Gate::allows('services_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $accountId = Auth::user()->account_id;
            $data = $this->serviceService->getFormData($accountId);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a new service
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $service = $this->serviceService->createService($request->validated(), $accountId);

            if ($service) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (ServiceException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get service for editing
     */
    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('services_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $accountId = Auth::user()->account_id;
            $data = $this->serviceService->getFormData($accountId, $id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get service details (for instructions modal)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (!Gate::allows('services_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $description = $this->serviceService->getServiceDescription($id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update a service
     */
    public function update(UpdateServiceRequest $request, int $id): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $service = $this->serviceService->updateService($id, $request->validated(), $accountId);

            if ($service) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (ServiceException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete a service
     */
    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('services_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $accountId = Auth::user()->account_id;
            $result = $this->serviceService->deleteService($id, $accountId);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (ServiceException $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update service status (active/inactive)
     */
    public function status(UpdateServiceStatusRequest $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $serviceId = $request->input('id');
            $status = $request->input('status');

            if ($status == 1) {
                $this->serviceService->activateService($serviceId, $accountId);
            } else {
                $this->serviceService->deactivateService($serviceId, $accountId);
            }

            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        } catch (ServiceException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get service data for duplication
     */
    public function duplicate(int $id): JsonResponse
    {
        if (!Gate::allows('services_duplicate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $accountId = Auth::user()->account_id;
            $data = $this->serviceService->getFormData($accountId, $id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store duplicated service
     */
    public function storeDuplicate(StoreServiceRequest $request): JsonResponse
    {
        if (!Gate::allows('services_duplicate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $accountId = Auth::user()->account_id;
            $service = $this->serviceService->createService($request->validated(), $accountId);

            if ($service) {
                return ApiHelper::apiResponse($this->success, 'Service has been duplicated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (ServiceException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get services for sorting
     */
    public function sortOrderGet(): JsonResponse
    {
        if (!Gate::allows('services_sort')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $services = $this->serviceService->getServicesForSort();

            return ApiHelper::apiResponse($this->success, 'Success', true, $services);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Save sort order
     */
    public function sortOrderSave(Request $request): JsonResponse
    {
        if (!Gate::allows('services_sort')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $itemIds = $request->input('item_ids', []);
            $accountId = Auth::user()->account_id;

            if ($this->serviceService->saveSortOrder($itemIds, $accountId)) {
                return ApiHelper::apiResponse($this->success, 'Records are sorted successfully!');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong! Records are not sorted', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get service color
     */
    public function getColor(Request $request): JsonResponse
    {
        $serviceId = (int)$request->input('service', 0);
        $color = $this->serviceService->getServiceColor($serviceId);

        return response()->json(['color' => $color]);
    }
}
