<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserTypeRequest;
use App\Services\UserManagement\UserTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserTypeController extends Controller
{
    private UserTypeService $userTypeService;
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(UserTypeService $userTypeService)
    {
        $this->userTypeService = $userTypeService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of the resource (datatable).
     */
    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('user_types_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $filters = getFilters($request->all());
            
            // Handle bulk delete if requested
            if (!empty($filters['delete'])) {
                $ids = array_filter(explode(',', $filters['delete']));
                if (!empty($ids)) {
                    $result = $this->userTypeService->bulkDelete($ids);
                    return response()->json([
                        'status' => true,
                        'message' => "Deleted {$result['deleted']} records. Skipped {$result['skipped']} records with child dependencies.",
                    ]);
                }
            }

            [$orderBy, $order] = getSortBy($request);
            
            // Get params for service
            $params = [
                'name' => $filters['name'] ?? null,
                'type' => $filters['type'] ?? null,
                'order_by' => $orderBy,
                'order' => $order,
            ];

            // Get total for pagination
            $totalParams = $params;
            $totalParams['offset'] = 0;
            $totalParams['limit'] = PHP_INT_MAX;
            $totalResult = $this->userTypeService->getDatatableData($totalParams);
            $iTotalRecords = $totalResult['total'];

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $params['offset'] = $iDisplayStart;
            $params['limit'] = $iDisplayLength;

            $result = $this->userTypeService->getDatatableData($params);

            return response()->json([
                'data' => $result['data'],
                'permissions' => [
                    'edit' => Gate::allows('user_types_edit'),
                ],
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get data for creating a new resource.
     */
    public function create(): JsonResponse
    {
        if (!Gate::allows('user_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $types = $this->userTypeService->getAllForDropdown();

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'types' => $types,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserTypeRequest $request): JsonResponse
    {
        if (!Gate::allows('user_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $this->userTypeService->create($request->validated());

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $userType = $this->userTypeService->find($id);

            if (!$userType) {
                return ApiHelper::apiResponse($this->success, 'Record not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'usertype' => $userType,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get data for editing the specified resource.
     */
    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $userType = $this->userTypeService->find($id);

            if (!$userType) {
                return ApiHelper::apiResponse($this->success, 'Record not found.', false);
            }

            $types = $this->userTypeService->getTypeOptions();

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'usertype' => $userType,
                'types' => $types,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserTypeRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('user_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $userType = $this->userTypeService->update($id, $request->validated());

            if (!$userType) {
                return ApiHelper::apiResponse($this->success, 'Record not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->userTypeService->delete($id);

            return ApiHelper::apiResponse(
                $this->success,
                $result['message'],
                $result['success']
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Activate the specified resource.
     */
    public function activate(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->userTypeService->activate($id);

            return ApiHelper::apiResponse(
                $this->success,
                $result['message'],
                $result['success']
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Inactivate the specified resource.
     */
    public function inactivate(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_inactive')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->userTypeService->inactivate($id);

            return ApiHelper::apiResponse(
                $this->success,
                $result['message'],
                $result['success']
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get user types for dropdown (cached).
     */
    public function dropdown(): JsonResponse
    {
        try {
            $types = $this->userTypeService->getAllForDropdown();

            return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                'types' => $types,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get user types for doctors (consultant type).
     */
    public function forDoctor(): JsonResponse
    {
        try {
            $types = $this->userTypeService->getForDoctor();

            return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                'types' => $types,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
