<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignVoucherToPatientRequest;
use App\Http\Requests\Admin\SaveVoucherTypeServiceRequest;
use App\Http\Requests\Admin\StoreVoucherTypeRequest;
use App\Http\Requests\Admin\UpdateVoucherTypeRequest;
use App\Http\Requests\Admin\VoucherTypeStatusRequest;
use App\Http\Resources\VoucherTypeResource;
use App\Services\Voucher\VoucherTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VouchersController extends Controller
{


    public function __construct(
        private readonly VoucherTypeService $voucherTypeService,
    ) {


    }

    public function index(): mixed
    {
        if (!Gate::allows('voucher_types_manage')) {
            return abort(401);
        }

        return view('admin.voucherTypes.index');
    }

    public function create(): JsonResponse
    {
        if (!Gate::allows('voucher_types_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $data = $this->voucherTypeService->getCreateData();

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function store(StoreVoucherTypeRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $this->voucherTypeService->store($request->validated());

            return $this->successResponse('Record has been created successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = getFilters($request->all());
            $applyFilter = checkFilters($filters, 'vouchers');

            [$orderBy, $order] = getSortBy($request);

            $result = $this->voucherTypeService->getDatatable([
                'filters' => $filters,
                'apply_filter' => $applyFilter,
                'request' => $request,
            ]);

            return response()->json([
                'data' => VoucherTypeResource::collection($result['data']),
                'meta' => [
                    'field' => $orderBy,
                    'page' => $result['page'],
                    'pages' => $result['pages'],
                    'perpage' => $result['perpage'],
                    'total' => $result['total'],
                    'sort' => $order,
                ],
                'permissions' => $this->voucherTypeService->getPermissions(),
                'filter_values' => $this->voucherTypeService->getFilterValues(),
                'active_filters' => $this->voucherTypeService->getActiveFilters(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $editData = $this->voucherTypeService->getEditData($id);

            if (!$editData) {
                return $this->errorResponse('Resource not found.', 404);
            }

            return $this->successResponse('Record found', $editData);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function update(UpdateVoucherTypeRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $result = $this->voucherTypeService->update($request->validated(), $id);

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function status(VoucherTypeStatusRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_active')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $result = $this->voucherTypeService->toggleStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $result = $this->voucherTypeService->delete($id);

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function displayDlocation(int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $data = $this->voucherTypeService->getLocationAllocations($id);

            return $this->successResponse('Service Allocated', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function getDservices(Request $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $data = $this->voucherTypeService->getServicesForLocation($request);

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function getDiscountServices(Request $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $data = $this->voucherTypeService->getDiscountServices($request);

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function saveDservices(SaveVoucherTypeServiceRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $result = $this->voucherTypeService->saveServiceAllocation(
                (int) $request->validated('voucher_id'),
                $request->locationId(),
                $request->serviceId(),
            );

            return $result['success']
                ? $this->successResponse($result['message'], $result['data'])
                : $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function deleteDservice(Request $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $this->voucherTypeService->deleteServiceAllocation((int) $request->input('id'));

            return $this->successResponse('Row deleted', [
                'id' => $request->input('id'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function getListing(): JsonResponse
    {
        try {
            $vouchers = $this->voucherTypeService->getListing();

            return response()->json(['data' => $vouchers]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }

    public function assignToPatient(AssignVoucherToPatientRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_assign')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $validated = $request->validated();
            $result = $this->voucherTypeService->assignToPatient(
                (int) $validated['voucher_id'],
                (int) $validated['patient_id'],
                (float) $validated['amount'],
            );

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'VouchersController');
        }
    }
}
