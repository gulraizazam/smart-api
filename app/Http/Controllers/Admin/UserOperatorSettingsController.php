<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserOperatorSettingsUpdateRequest;
use App\Http\Resources\User\UserOperatorSettingsResource;
use App\Services\UserManagement\UserOperatorSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserOperatorSettingsController extends Controller
{


    public function __construct(
        private readonly UserOperatorSettingsService $operatorSettingsService,
    ) {


    }

    public function index(): mixed
    {
        if (!Gate::allows('user_operator_settings_manage')) {
            return abort(401);
        }

        $filters = $this->operatorSettingsService->getActiveFilters();

        return view('admin.user_operator_settings.index', compact('filters'));
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $filters = getFilters($request->all());
            $applyFilter = checkFilters($filters, 'operators');

            [$orderBy, $order] = getSortBy($request, 'operator_name');

            $params = [
                'operator_name' => $filters['operator_name'] ?? null,
                'apply_filter' => $applyFilter,
                'order_by' => $orderBy,
                'order' => $order,
            ];

            $iTotalRecords = $this->operatorSettingsService->getDatatableCount($params);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $result = $this->operatorSettingsService->getDatatableData([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Records retrieved successfully.',
                'data' => UserOperatorSettingsResource::collection($result['data']),
                'permissions' => $this->operatorSettingsService->getPermissions(),
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
            return $this->handleException($e, 'UserOperatorSettingsController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('user_operator_settings_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $operatorSetting = $this->operatorSettingsService->find($id);

            if (!$operatorSetting) {
                return $this->errorResponse('No Data Found!', 404);
            }

            return $this->successResponse('Success', $operatorSetting);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserOperatorSettingsController');
        }
    }

    public function update(UserOperatorSettingsUpdateRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->operatorSettingsService->update($id, $request->validated());

            return $result
                ? $this->successResponse('Record has been updated successfully.')
                : $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserOperatorSettingsController');
        }
    }

    public function loadOperator(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $operatorId = (int) $request->input('operator_id');
            $globalOperator = $this->operatorSettingsService->getGlobalOperator($operatorId);

            if (!$globalOperator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong, please try again later.',
                    'data' => null,
                ]);
            }

            $data = $globalOperator->toArray();
            $data['password'] = '********';

            return response()->json([
                'success' => true,
                'message' => 'Operator loaded successfully.',
                'data' => ['operator_setting' => $data],
                // Keep legacy fields for backward compatibility
                'status' => 1,
                'operator_setting' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserOperatorSettingsController');
        }
    }
}
