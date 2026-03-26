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
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(
        private readonly UserTypeService $userTypeService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('user_types_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $filters = getFilters($request->all());

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

            $params = [
                'name' => $filters['name'] ?? null,
                'type' => $filters['type'] ?? null,
                'order_by' => $orderBy,
                'order' => $order,
            ];

            // Get total for pagination
            $totalResult = $this->userTypeService->getDatatableData([
                ...$params,
                'offset' => 0,
                'limit' => PHP_INT_MAX,
            ]);
            $iTotalRecords = $totalResult['total'];

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $result = $this->userTypeService->getDatatableData([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

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

    public function create(): JsonResponse
    {
        if (!Gate::allows('user_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'types' => $this->userTypeService->getAllForDropdown(),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

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

    public function show(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $userType = $this->userTypeService->find($id);

            return $userType
                ? ApiHelper::apiResponse($this->success, 'Record found.', true, ['usertype' => $userType])
                : ApiHelper::apiResponse($this->success, 'Record not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

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

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'usertype' => $userType,
                'types' => $this->userTypeService->getTypeOptions(),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(UserTypeRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('user_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $userType = $this->userTypeService->update($id, $request->validated());

            return $userType
                ? ApiHelper::apiResponse($this->success, 'Record has been updated successfully.')
                : ApiHelper::apiResponse($this->success, 'Record not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->userTypeService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['success']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function activate(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->userTypeService->activate($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['success']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function inactivate(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_inactive')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->userTypeService->inactivate($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['success']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function dropdown(): JsonResponse
    {
        try {
            return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                'types' => $this->userTypeService->getAllForDropdown(),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function forDoctor(): JsonResponse
    {
        try {
            return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                'types' => $this->userTypeService->getForDoctor(),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
