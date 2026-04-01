<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserVoucherStoreRequest;
use App\Http\Requests\Admin\UserVoucherUpdateRequest;
use App\Services\UserManagement\UserVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserVouchersController extends Controller
{
    private readonly int $success;
    private readonly int $error;
    private readonly int $unauthorized;

    public function __construct(
        private readonly UserVoucherService $voucherService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index()
    {
        if (!Gate::allows('vouchers_manage')) {
            return abort(401);
        }

        return view('admin.vouchers.index');
    }

    public function datatable(Request $request, ?int $patientId = null): JsonResponse
    {
        try {
            $filters = getFilters($request->all());

            if ($patientId) {
                $filters['patient_id'] = $patientId;
            }

            $applyFilter = checkFilters($filters, 'vouchers');

            [$orderBy, $order] = getSortBy($request);
            $params = array_merge($filters, ['apply_filter' => $applyFilter]);

            $totalRecords = $this->voucherService->getDatatableCount($params);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

            $result = $this->voucherService->getDatatableData([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            return ApiHelper::apiDataTable([
                'success' => true,
                'message' => 'Records retrieved successfully.',
                'data' => $result['data'],
                'permissions' => $this->voucherService->getPermissions(),
                'filter_values' => $this->voucherService->getFilterValues(),
                'active_filters' => $this->voucherService->getActiveFilters(),
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $totalRecords,
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(UserVoucherStoreRequest $request): JsonResponse
    {
        if (!Gate::allows('vouchers_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $this->voucherService->store($request->validated());

            return ApiHelper::apiResponse($this->success, 'Voucher assigned successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('vouchers_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $editData = $this->voucherService->getEditData($id);

            if (!$editData) {
                return ApiHelper::apiResponse($this->error, 'Record not found.', false);
            }

            if (!empty($editData['restricted'])) {
                return ApiHelper::apiResponse($this->error, 'This voucher cannot be edited as it is already applied to services.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $editData);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(UserVoucherUpdateRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('vouchers_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->voucherService->update($id, $request->validated());

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'], true, $result['voucher'] ?? null)
                : ApiHelper::apiResponse($this->error, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('vouchers_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->voucherService->delete($id);

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'])
                : ApiHelper::apiResponse($this->error, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function show(Request $request, int $id): mixed
    {
        if (!Gate::allows('vouchers_manage')) {
            if ($request->ajax()) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return abort(401);
        }

        try {
            $userVoucher = $this->voucherService->find($id);

            if (!$userVoucher) {
                if ($request->ajax()) {
                    return ApiHelper::apiResponse($this->error, 'Voucher not found.', false);
                }
                abort(404);
            }

            $voucher = $userVoucher->voucher;
            $user = $userVoucher->user;
            $voucherUsageData = $this->voucherService->getVoucherUsageData($id);

            if ($request->ajax()) {
                $html = view('admin.vouchers.view_content', compact('voucher', 'user', 'voucherUsageData', 'userVoucher'))->render();

                return ApiHelper::apiResponse($this->success, 'Voucher usage details retrieved successfully.', true, ['html' => $html]);
            }

            return view('admin.vouchers.show', compact('voucher', 'user', 'voucherUsageData', 'userVoucher'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
