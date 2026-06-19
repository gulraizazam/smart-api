<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Brand\StoreBrandRequest;
use App\Services\Brand\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BrandsController extends Controller
{


    public function __construct(
        private readonly BrandService $brandService,
    ) {


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
                if (! Gate::allows('brand_destroy')) {
                    return $this->errorResponse('You are not authorized to delete these records.', 403);
                }
                $ids = explode(',', $filters['delete']);
                $bulkResult = $this->brandService->bulkDelete($ids);
                $records['status'] = $bulkResult['status'];
                $records['message'] = $bulkResult['message'];
            }

            $params = [
                'name' => $filters['name'] ?? null,
                'status' => $filters['status'] ?? null,
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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BrandsController');
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
                return $this->successResponse('Record has been created successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BrandsController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('brand_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $brand = $this->brandService->find($id);

            if (! $brand) {
                return $this->errorResponse('No Record Found!', 404);
            }

            return $this->successResponse('Success', $brand);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BrandsController');
        }
    }

    public function update(StoreBrandRequest $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('brand_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $record = $this->brandService->update($id, $request->validated());

            if ($record) {
                return $this->successResponse('Record has been updated successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BrandsController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('brand_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->brandService->delete($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BrandsController');
        }
    }

    public function status(Request $request): JsonResponse
    {
        if (! Gate::allows('brand_active')) {
            abort(401);
        }

        $brand = $this->brandService->toggleStatus((int) $request->id, (int) $request->status);

        if ($brand) {
            return $this->successResponse('Status has been changed successfully.');
        }

        return $this->errorResponse('Brand not found.', 404);
    }
}
