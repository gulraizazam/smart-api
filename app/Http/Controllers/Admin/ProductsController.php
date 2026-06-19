<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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


    public function __construct(
        private readonly ProductService $productService,
    ) {


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
                if (! Gate::allows('product_destroy')) {
                    return $this->errorResponse('You are not authorized to delete these records.', 403);
                }
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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (! Gate::allows('product_create')) {
                abort(401);
            }

            return $this->successResponse('Record found', $this->productService->getCreateFormData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            if (! Gate::allows('product_create')) {
                abort(401);
            }

            if ($request->sale_price && $request->sale_price < 0) {
                return $this->errorResponse('Sale price must be greater than 0', 500);
            }

            $product = $this->productService->create($request->validated());

            if ($product) {
                return $this->successResponse('Record has been created successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $data = $this->productService->getEditFormData($id);

            if (! $data) {
                return $this->errorResponse('No Record Found!', 404);
            }

            return $this->successResponse('Success', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function update(Request $request, int $id, int $detail): JsonResponse
    {
        try {
            if (! Gate::allows('product_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $data = $request->all();
            $data['type'] = 'product_update';
            $data['message'] = 'Product update';

            $product = $this->productService->update($id, $data);

            if ($product) {
                return $this->successResponse('Record has been updated successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function updateSalePrice(Request $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_sale_price')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $product = $this->productService->find($id);

            if (! $product) {
                return $this->errorResponse('No Record Found!', 404);
            }

            if ($request->sale_price && $request->sale_price < 0) {
                return $this->errorResponse('Sale price must be greater than 0', 500);
            }
            if ($product->product_type === 'in_house_use') {
                return $this->errorResponse("Sale price can't be added against in-house product!", 400);
            }
            if ($product->purchase_price > $request->sale_price) {
                return $this->errorResponse('Sale price must be equal or greater than purchase price!', 404);
            }

            $data = $request->all();
            $data['type'] = 'product_sale_price_update';
            $data['message'] = 'Product sale price update';

            $updated = $this->productService->update($id, $data);

            if ($updated) {
                return $this->successResponse('Record has been update successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->productService->delete($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function editSalePrice(int $id): JsonResponse
    {
        try {
            $product = $this->productService->find($id);

            if (! $product) {
                return $this->errorResponse('No Record Found!', 404);
            }

            return $this->successResponse('Success', $product);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function addStock(Request $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_add_stock')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if ($request->purchase_price && $request->purchase_price < 0) {
                return $this->errorResponse('Purchase price must be greater than 0', 500);
            }
            if ($request->quantity && $request->quantity < 0) {
                return $this->errorResponse('Quantity must be greater than 0', 500);
            }

            $result = $this->productService->addStock($id, $request->all());

            if ($result) {
                return $this->successResponse('Record has been created successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
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
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $stockData = $this->productService->getStockDetailData($id);

        $iTotalRecords = $stockData['total'];
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        return response()->json([
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
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $inventoryData = $this->productService->getInventoryDetailData($id);

        $iTotalRecords = $inventoryData['total'];
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        return response()->json([
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

    // productBatches() removed — FIFO batches are not used in the legacy
    // (crm2) stock model. Stock on-hand is read from inventories.quantity.

    public function status(Request $request): JsonResponse
    {
        if (! Gate::allows('product_active')) {
            abort(401);
        }

        $product = $this->productService->toggleStatus((int) $request->id, (int) $request->status);

        if ($product) {
            return $this->successResponse('Status has been changed successfully.');
        }

        return $this->errorResponse('Product not found.', 404);
    }

    public function transferProductGetData(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('product_transfer')) {
                abort(401);
            }

            $data = $this->productService->getTransferProductData($id);

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function transferProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Quantity can not be less than 0', 500);
        }

        try {
            if (! Gate::allows('product_transfer')) {
                abort(401);
            }

            $toLocation = $request->to_location_id ?? $request->to_warehouse_id;

            if ($toLocation === null) {
                return $this->errorResponse('Please select any Branch/Warehouse.', 500);
            }

            if ($request->product_type_option_to === 'in_warehouse') {
                if ($request->from_warehouse_id == $request->to_warehouse_id) {
                    return $this->errorResponse('Please different location add.', 500);
                }
            } else {
                if ($request->from_location_id == $request->to_location_id) {
                    return $this->errorResponse('Please different location add.', 500);
                }
            }

            if ($request->quantity <= 0) {
                return $this->errorResponse("Transfer quantity can't be 0.", 500);
            }
            if ($request->product_type_option_to === 'in_warehouse' && $request->to_warehouse_id === null) {
                return $this->errorResponse('Please select warehouse', 500);
            }
            if ($request->product_type_option_to === 'in_branch' && $request->to_location_id === null) {
                return $this->errorResponse('Please select any centre.', 500);
            }

            $result = $this->productService->transferProduct($request->all());

            return $this->successResponse($result['message'], $result['success']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
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
            return $this->handleException($e, 'ProductsController');
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

            return $this->successResponse('Service Allocated', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function saveAllocate(Request $request): JsonResponse
    {
        try {
            $this->productService->saveAllocation($request->all());

            return $this->successResponse('Success', []);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }

    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $products = $this->productService->searchProducts($request->search ?? '');

            return $this->successResponse('Success', ['products' => $products]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ProductsController');
        }
    }
}
