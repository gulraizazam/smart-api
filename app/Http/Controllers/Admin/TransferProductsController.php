<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransferProduct\StoreTransferProductRequest;
use App\Services\TransferProduct\TransferProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransferProductsController extends Controller
{


    public function __construct(
        private readonly TransferProductService $transferService,
    ) {


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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'TransferProductsController');
        }
    }

    public function create(): JsonResponse
    {
        try {
            $data = $this->transferService->getCreateFormData();

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'TransferProductsController');
        }
    }

    public function store(StoreTransferProductRequest $request): JsonResponse
    {
        try {
            $data = $request->all();

            $validation = $this->transferService->validateTransfer($data);
            if ($validation) {
                return $this->errorResponse($validation['message'], 200);
            }

            $result = $this->transferService->store($data);

            return $this->successResponse($result['message'], $result['success']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'TransferProductsController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            $data = $this->transferService->getEditFormData($id);

            if (! $data) {
                return $this->errorResponse('No Record Found!', 404);
            }

            return $this->successResponse('Success', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'TransferProductsController');
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $data = $request->all();

            $validation = $this->transferService->validateTransfer($data, true);
            if ($validation) {
                return $this->errorResponse($validation['message'], 400);
            }

            $result = $this->transferService->update($id, $data);

            return $this->successResponse($result['message'], $result['success']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'TransferProductsController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->transferService->delete($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'TransferProductsController');
        }
    }

    public function getProducts(Request $request): JsonResponse
    {
        $data = $this->transferService->getProductsWithDoctors($request->all());

        return $this->successResponse('Record found.', $data);
    }

    public function getTransferProducts(Request $request): JsonResponse
    {
        $data = $this->transferService->getTransferProducts($request->all());

        return $this->successResponse('Record found.', $data);
    }
}
