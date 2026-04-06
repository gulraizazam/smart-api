<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransferProduct\StoreTransferProductRequest;
use App\Services\TransferProduct\TransferProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransferProductsController extends Controller
{
    private readonly int $success;

    private readonly int $error;

    private readonly int $unauthorized;

    public function __construct(
        private readonly TransferProductService $transferService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index(): View
    {
        return view('admin.transfer_product.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'transfer_product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            $params = [
                'apply_filter' => $apply_filter,
                'filters' => $filters,
            ];

            $iTotalRecords = $this->transferService->getDatatableCount($params);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $transfers = $this->transferService->getDatatableRecords([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            $records['data'] = $this->transferService->transformForDatatable($transfers);
            $records['permissions'] = $this->transferService->getPermissions();
            $records['active_filters'] = $filters;
            $records['filter_values'] = $this->transferService->getFilterValues();
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function create(): JsonResponse
    {
        try {
            $data = $this->transferService->getCreateFormData();

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreTransferProductRequest $request): JsonResponse
    {
        try {
            $data = $request->all();

            $validation = $this->transferService->validateTransfer($data);
            if ($validation) {
                return ApiHelper::apiResponse(
                    $validation['message'] === 'Please add different location.' ? $this->error : $this->success,
                    $validation['message'],
                    false,
                );
            }

            $result = $this->transferService->store($data);

            return ApiHelper::apiResponse(
                $this->success,
                $result['message'],
                $result['success'],
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            $data = $this->transferService->getEditFormData($id);

            if (! $data) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $data = $request->all();

            $validation = $this->transferService->validateTransfer($data, true);
            if ($validation) {
                return ApiHelper::apiResponse($this->success, $validation['message'], false);
            }

            $result = $this->transferService->update($id, $data);

            return ApiHelper::apiResponse(
                $this->success,
                $result['message'],
                $result['success'],
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->transferService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getProducts(Request $request): JsonResponse
    {
        $data = $this->transferService->getProductsWithDoctors($request->all());

        return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
    }

    public function getTransferProducts(Request $request): JsonResponse
    {
        $data = $this->transferService->getTransferProducts($request->all());

        return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
    }
}
