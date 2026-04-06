<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\StoreProductRequest;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Stock;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProductsController extends Controller
{
    private readonly int $success;

    private readonly int $error;

    private readonly int $unauthorized;

    public function __construct(
        private readonly ProductService $productService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index(): View
    {
        if (! Gate::allows('product_manage')) {
            abort(401);
        }

        return view('admin.products.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (! empty($filters)) {
                if (($filters['filter'] ?? null) === 'filter_cancel') {
                    foreach (['centre_id', 'warehouse_id', 'brand_id', 'status', 'product_type'] as $key) {
                        if (($filters[$key] ?? null) === null) {
                            unset($filters[$key]);
                        }
                    }
                }
            }

            if (isset($filters['delete'])) {
                $ids = explode(',', $filters['delete']);
                $bulkResult = $this->productService->bulkDelete($ids);
                $records['status'] = $bulkResult['status'];
                $records['message'] = $bulkResult['message'];
            }

            $params = [
                'apply_filter' => $apply_filter,
                'filters' => $filters,
            ];

            $iTotalRecords = $this->productService->getDatatableCount($params);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $products = $this->productService->getDatatableRecords([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            $brands = Brand::getAllRecordsDictionary(Auth::user()->account_id);
            $products = $this->productService->transformForDatatable($products, $brands);

            $records['data'] = $products;
            $records['permissions'] = $this->productService->getPermissions();
            $records['active_filters'] = $filters;
            $records['filter_values'] = $this->productService->getFilterValues();
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
            if (! Gate::allows('product_create')) {
                abort(401);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $this->productService->getCreateFormData());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            if (! Gate::allows('product_create')) {
                abort(401);
            }

            if ($request->sale_price && $request->sale_price < 0) {
                return ApiHelper::apiResponse($this->error, 'Sale price must be greater than 0', false);
            }

            $product = $this->productService->create($request->validated());

            if ($product) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $data = $this->productService->getEditFormData($id);

            if (! $data) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(Request $request, int $id, int $detail): JsonResponse
    {
        try {
            if (! Gate::allows('product_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $data = $request->all();
            $data['type'] = 'product_update';
            $data['message'] = 'Product update';

            $product = $this->productService->update($id, $data);

            if ($product) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function updateSalePrice(Request $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_sale_price')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $product = $this->productService->find($id);

            if (! $product) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            if ($request->sale_price && $request->sale_price < 0) {
                return ApiHelper::apiResponse($this->error, 'Sale price must be greater than 0', false);
            }
            if ($product->product_type === 'in_house_use') {
                return ApiHelper::apiResponse($this->success, "Sale price can't be added against in-house product!", false);
            }
            if ($product->purchase_price > $request->sale_price) {
                return ApiHelper::apiResponse($this->success, 'Sale price must be equal or greater than purchase price!', false);
            }

            $data = $request->all();
            $data['type'] = 'product_sale_price_update';
            $data['message'] = 'Product sale price update';

            $updated = $this->productService->update($id, $data);

            if ($updated) {
                return ApiHelper::apiResponse($this->success, 'Record has been update successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $result = $this->productService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function editSalePrice(int $id): JsonResponse
    {
        try {
            $product = $this->productService->find($id);

            if (! $product) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $product);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function addStock(Request $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_add_stock')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            if ($request->purchase_price && $request->purchase_price < 0) {
                return ApiHelper::apiResponse($this->error, 'Purchase price must be greater than 0', false);
            }
            if ($request->quantity && $request->quantity < 0) {
                return ApiHelper::apiResponse($this->error, 'Quantity must be greater than 0', false);
            }

            $result = $this->productService->addStock($id, $request->all());

            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function productStock(int $id): View
    {
        if (! Gate::allows('product_stock_detail')) {
            abort(401);
        }

        return view('admin.products.stock_detail', compact('id'));
    }

    public function productInventory(int $id): View
    {
        return view('admin.products.inventories', compact('id'));
    }

    public function productStockDetail(Request $request, int $id): JsonResponse
    {
        if (! Gate::allows('product_stock_detail')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $stockData = $this->productService->getStockDetailData($id);

        $iTotalRecords = $stockData['total'];
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        return ApiHelper::apiDataTable([
            'data' => $stockData['data'],
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ],
        ]);
    }

    public function productInventoryDetail(Request $request, int $id): JsonResponse
    {
        if (! Gate::allows('product_stock_detail')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $inventoryData = $this->productService->getInventoryDetailData($id);

        $iTotalRecords = $inventoryData['total'];
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        return ApiHelper::apiDataTable([
            'data' => $inventoryData['data'],
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        if (! Gate::allows('product_active')) {
            abort(401);
        }

        $product = $this->productService->toggleStatus((int) $request->id, (int) $request->status);

        if ($product) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Product not found.', false);
    }

    public function transferProductGetData(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_transfer')) {
                abort(401);
            }

            $data = $this->productService->getTransferProductData($id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function transferProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->error, 'Quantity can not be less than 0', false);
        }

        try {
            if (! Gate::allows('product_transfer')) {
                abort(401);
            }

            $toLocation = $request->to_location_id ?? $request->to_warehouse_id;

            if ($toLocation === null) {
                return ApiHelper::apiResponse($this->error, 'Please select any Branch/Warehouse.', false);
            }

            if ($request->product_type_option_to === 'in_warehouse') {
                if ($request->from_warehouse_id == $request->to_warehouse_id) {
                    return ApiHelper::apiResponse($this->error, 'Please different location add.', false);
                }
            } else {
                if ($request->from_location_id == $request->to_location_id) {
                    return ApiHelper::apiResponse($this->error, 'Please different location add.', false);
                }
            }

            if ($request->quantity <= 0) {
                return ApiHelper::apiResponse($this->error, "Transfer quantity can't be 0.", false);
            }
            if ($request->product_type_option_to === 'in_warehouse' && $request->to_warehouse_id === null) {
                return ApiHelper::apiResponse($this->error, 'Please select warehouse', false);
            }
            if ($request->product_type_option_to === 'in_branch' && $request->to_location_id === null) {
                return ApiHelper::apiResponse($this->error, 'Please select any centre.', false);
            }

            $result = $this->productService->transferProduct($request->all());

            return ApiHelper::apiResponse(
                $result['success'] ? $this->success : $this->error,
                $result['message'],
                $result['success'],
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function logs(int $id): View|JsonResponse
    {
        try {
            if (! Gate::allows('product_log')) {
                abort(401);
            }

            $records = $this->productService->getActivityLogs($id);

            return view('admin.products.logs', compact('records'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function editInventory(int $id): JsonResponse
    {
        $data = $this->productService->getEditInventoryData($id);

        return response()->json([
            'status' => 1,
            'inventory' => $data['inventory'],
            'warehouse' => $data['warehouse'],
            'locations' => $data['locations'],
        ]);
    }

    public function displaylocation(int $id): JsonResponse
    {
        try {
            $data = $this->productService->getLocationAllocation($id);

            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function saveAllocate(Request $request): JsonResponse
    {
        try {
            $this->productService->saveAllocation($request->all());

            return ApiHelper::apiResponse($this->success, 'Success', true, []);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $products = $this->productService->searchProducts($request->search ?? '');

            return ApiHelper::apiResponse($this->success, 'Success', true, ['products' => $products]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
