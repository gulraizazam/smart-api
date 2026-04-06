<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Brand\StoreBrandRequest;
use App\Services\Brand\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BrandsController extends Controller
{
    private readonly int $success;

    private readonly int $error;

    private readonly int $unauthorized;

    public function __construct(
        private readonly BrandService $brandService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index(): View
    {
        if (! Gate::allows('brand_manage')) {
            abort(401);
        }

        return view('admin.brands.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'warehouse';

            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (isset($filters['delete'])) {
                $ids = explode(',', $filters['delete']);
                $bulkResult = $this->brandService->bulkDelete($ids);
                $records['status'] = $bulkResult['status'];
                $records['message'] = $bulkResult['message'];
            }

            $params = [
                'name' => $filters['name'] ?? null,
                'apply_filter' => $apply_filter,
            ];

            $iTotalRecords = $this->brandService->getDatatableCount($params);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $brands = $this->brandService->getDatatableRecords([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            $records['data'] = $brands;
            $records['permissions'] = $this->brandService->getPermissions();
            $records['active_filters'] = $apply_filter;
            $records['filter_values'] = [
                'status' => config('constants.status'),
            ];
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

    public function store(StoreBrandRequest $request): JsonResponse
    {
        try {
            if (! Gate::allows('brand_create')) {
                abort(401);
            }

            $record = $this->brandService->create($request->validated());

            if ($record) {
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
            if (! Gate::allows('brand_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $brand = $this->brandService->find($id);

            if (! $brand) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $brand);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(StoreBrandRequest $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('brand_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $record = $this->brandService->update($id, $request->validated());

            if ($record) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('brand_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $result = $this->brandService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        if (! Gate::allows('product_active')) {
            abort(401);
        }

        $brand = $this->brandService->toggleStatus((int) $request->id, (int) $request->status);

        if ($brand) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Brand not found.', false);
    }
}
