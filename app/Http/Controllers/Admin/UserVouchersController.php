<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserVoucherStoreRequest;
use App\Http\Requests\Admin\UserVoucherUpdateRequest;
use App\Services\UserManagement\UserVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserVouchersController extends Controller
{


    public function __construct(
        private readonly UserVoucherService $voucherService,
    ) {


    }

    public function index(): \Illuminate\View\View
    {
        if (!Gate::allows('vouchers.list.view')) {
            return abort(403);
        }

        return view('admin.vouchers.index');
    }

    public function datatable(Request $request, ?int $patientId = null): JsonResponse
    {
        // Patient-card embed (patientId != null) is gated by the parent
        // route's `patients.vouchers.view` perm via api.php middleware;
        // the global module datatable requires the catalogue perm.
        if ($patientId === null && !Gate::allows('vouchers.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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

            return response()->json([
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
            return $this->handleException($e, 'UserVouchersController');
        }
    }

    public function store(UserVoucherStoreRequest $request): JsonResponse
    {
        if (!Gate::allows('vouchers.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->voucherService->store($request->validated());

            return $this->successResponse('Voucher assigned successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserVouchersController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('vouchers.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $editData = $this->voucherService->getEditData($id);

            if (!$editData) {
                return $this->errorResponse('Record not found.', 500);
            }

            if (!empty($editData['restricted'])) {
                return $this->errorResponse('This voucher cannot be edited as it is already applied to services.', 500);
            }

            return $this->successResponse('Record found', $editData);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserVouchersController');
        }
    }

    public function update(UserVoucherUpdateRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('vouchers.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->voucherService->update($id, $request->validated());

            return $result['success']
                ? $this->successResponse($result['message'], $result['voucher'] ?? null)
                : $this->errorResponse($result['message'], 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserVouchersController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('vouchers.destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->voucherService->delete($id);

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserVouchersController');
        }
    }

    public function show(Request $request, int $id): \Illuminate\View\View|JsonResponse
    {
        // Single-voucher view gets its own perm (`vouchers.detail.view`);
        // legacy umbrella `vouchers_manage` covered both list and detail.
        if (!Gate::allows('vouchers.detail.view')) {
            if ($request->ajax()) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            return abort(403);
        }

        try {
            $userVoucher = $this->voucherService->find($id);

            if (!$userVoucher) {
                if ($request->ajax()) {
                    return $this->errorResponse('Voucher not found.', 500);
                }
                abort(404);
            }

            $voucher = $userVoucher->voucher;
            $user = $userVoucher->user;
            $voucherUsageData = $this->voucherService->getVoucherUsageData($id);

            if ($request->ajax()) {
                $html = view('admin.vouchers.view_content', compact('voucher', 'user', 'voucherUsageData', 'userVoucher'))->render();

                return $this->successResponse('Voucher usage details retrieved successfully.', ['html' => $html]);
            }

            return view('admin.vouchers.show', compact('voucher', 'user', 'voucherUsageData', 'userVoucher'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'UserVouchersController');
        }
    }
}
