<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserTypeRequest;
use App\Http\Resources\User\UserTypeResource;
use App\Services\UserManagement\UserTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserTypeController extends Controller
{


    public function __construct(
        private readonly UserTypeService $userTypeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('user_types_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $filters = getFilters($request->all());

            if (!empty($filters['delete'])) {
                $ids = array_filter(explode(',', $filters['delete']));
                if (!empty($ids)) {
                    $result = $this->userTypeService->bulkDelete($ids);
                    return response()->json([
                        'success' => true,
                        'message' => "Deleted {$result['deleted']} records. Skipped {$result['skipped']} records with child dependencies.",
                        'data' => $result,
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

            $countResult = $this->userTypeService->getDatatableCount($params);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $countResult);

            $result = $this->userTypeService->getDatatableData([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Records retrieved successfully.',
                'data' => UserTypeResource::collection($result['data']),
                'permissions' => [
                    'edit' => Gate::allows('user_types_edit'),
                ],
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $countResult,
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function create(): JsonResponse
    {
        if (!Gate::allows('user_types_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            return $this->successResponse('Record found', [
                'types' => $this->userTypeService->getAllForDropdown(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function store(UserTypeRequest $request): JsonResponse
    {
        if (!Gate::allows('user_types_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->userTypeService->create($request->validated());

            return $this->successResponse('Record has been created successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function show(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $userType = $this->userTypeService->find($id);

            return $userType
                ? $this->successResponse('Record found.', ['usertype' => new UserTypeResource($userType)])
                : $this->errorResponse('Record not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $userType = $this->userTypeService->find($id);

            if (!$userType) {
                return $this->errorResponse('Record not found.', 404);
            }

            return $this->successResponse('Record found.', [
                'usertype' => new UserTypeResource($userType),
                'types' => $this->userTypeService->getTypeOptions(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function update(UserTypeRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('user_types_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $userType = $this->userTypeService->update($id, $request->validated());

            return $userType
                ? $this->successResponse('Record has been updated successfully.')
                : $this->errorResponse('Record not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->userTypeService->delete($id);

            return $result['success'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function activate(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_active')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->userTypeService->activate($id);

            return $result['success'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function inactivate(int $id): JsonResponse
    {
        if (!Gate::allows('user_types_inactive')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->userTypeService->inactivate($id);

            return $result['success'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function dropdown(): JsonResponse
    {
        try {
            return $this->successResponse('Records found.', [
                'types' => $this->userTypeService->getAllForDropdown(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }

    public function forDoctor(): JsonResponse
    {
        try {
            return $this->successResponse('Records found.', [
                'types' => $this->userTypeService->getForDoctor(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserTypeController');
        }
    }
}
