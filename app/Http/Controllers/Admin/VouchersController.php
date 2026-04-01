<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
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
    private readonly int $success;
    private readonly int $error;
    private readonly int $unauthorized;

    public function __construct(
        private readonly VoucherTypeService $voucherTypeService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index()
    {
        if (!Gate::allows('voucher_types_manage')) {
            return abort(401);
        }

        return view('admin.voucherTypes.index');
    }

    public function create(): JsonResponse
    {
        if (!Gate::allows('voucher_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $data = $this->voucherTypeService->getCreateData();

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreVoucherTypeRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $this->voucherTypeService->store($request->validated());

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
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

            return ApiHelper::apiDataTable([
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
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $editData = $this->voucherTypeService->getEditData($id);

            if (!$editData) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $editData);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(UpdateVoucherTypeRequest $request, int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->voucherTypeService->update($request->validated(), $id);

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'])
                : ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(VoucherTypeStatusRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->voucherTypeService->toggleStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'])
                : ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->voucherTypeService->delete($id);

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'])
                : ApiHelper::apiResponse($this->error, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function displayDlocation(int $id): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $data = $this->voucherTypeService->getLocationAllocations($id);

            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getDservices(Request $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $data = $this->voucherTypeService->getServicesForLocation($request);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getDiscountServices(Request $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $data = $this->voucherTypeService->getDiscountServices($request);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function saveDservices(SaveVoucherTypeServiceRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $result = $this->voucherTypeService->saveServiceAllocation(
                (int) $request->validated('voucher_id'),
                $request->locationId(),
                $request->serviceId(),
            );

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'], true, $result['data'])
                : ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function deleteDservice(Request $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $this->voucherTypeService->deleteServiceAllocation((int) $request->input('id'));

            return ApiHelper::apiResponse($this->success, 'Row deleted', true, [
                'id' => $request->input('id'),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getListing(): JsonResponse
    {
        try {
            $vouchers = $this->voucherTypeService->getListing();

            return response()->json(['data' => $vouchers]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function assignToPatient(AssignVoucherToPatientRequest $request): JsonResponse
    {
        if (!Gate::allows('voucher_types_assign')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $validated = $request->validated();
            $result = $this->voucherTypeService->assignToPatient(
                (int) $validated['voucher_id'],
                (int) $validated['patient_id'],
                (float) $validated['amount'],
            );

            return $result['success']
                ? ApiHelper::apiResponse($this->success, $result['message'])
                : ApiHelper::apiResponse($this->error, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
