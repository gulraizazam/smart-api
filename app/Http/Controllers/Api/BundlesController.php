<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BundleException;
use App\Helpers\BundleHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bundle\StoreBundleRequest;
use App\Http\Requests\Bundle\UpdateBundleRequest;
use App\Http\Requests\Bundle\UpdateBundleStatusRequest;
use App\Http\Resources\Bundle\BundleDatatableResource;
use App\Http\Resources\Bundle\BundleDetailResource;
use App\Http\Resources\Bundle\BundleFormDataResource;
use App\Services\Bundle\BundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class BundlesController extends Controller
{
    public function __construct(
        private readonly BundleService $bundleService,
    ) {}

    /**
     * Get bundles datatable data.
     */
    public function datatable(Request $request): JsonResponse
    {
        if (! Gate::allows('packages.list.view')) {
            return $this->unauthorizedResponse();
        }

        try {
            $user = Auth::user();
            $accountId = $user->account_id;
            $filters = getFilters($request->all());
            $records = ['data' => []];

            // Bulk delete branch — re-gate inside because top-level
            // `packages.list.view` only authorises reads.
            if (hasFilter($filters, 'delete')) {
                if (! Gate::allows('packages.destroy')) {
                    return $this->unauthorizedResponse();
                }
                $ids = array_filter(array_map('intval', explode(',', $filters['delete'])));
                $this->bundleService->bulkDelete($ids, $accountId);
                $records['status'] = true;
                $records['message'] = 'Records have been deleted successfully!';
            }

            // Persist/clear filters, then restore any saved filters not in current request
            $this->applyFilters($filters, $user->id);
            $filters = $this->restoreSavedFilters($filters, $user->id);

            [$orderBy, $order] = getSortBy($request);
            $canViewInactive = Gate::allows('packages.list.view_inactive');

            $totalRecords = $this->bundleService->getTotalRecords($filters, $accountId, $canViewInactive);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

            $bundles = $this->bundleService->getBundlesList($filters, $accountId, $canViewInactive, $displayStart, $displayLength);

            $records = $this->appendFilterData($records, $user->id);
            $records['data'] = BundleDatatableResource::collection($bundles)->resolve();
            $records['permissions'] = BundleHelper::getPermissions();
            $records['meta'] = [
                'field'   => $orderBy,
                'page'    => $page,
                'pages'   => $pages,
                'perpage' => $displayLength,
                'total'   => $totalRecords,
                'sort'    => $order,
            ];

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Store a new bundle.
     */
    public function store(StoreBundleRequest $request): JsonResponse
    {
        if (! Gate::allows('packages.create')) {
            return $this->unauthorizedResponse();
        }

        try {
            $this->bundleService->createBundle($request->validated());

            return $this->successResponse('Package has been created successfully.');
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Get bundle data for editing.
     */
    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('packages.edit')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->bundleService->getBundleForEdit($id);

            return $this->successResponse('Record found', new BundleFormDataResource($data));
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update a bundle.
     */
    public function update(UpdateBundleRequest $request, int $id): JsonResponse
    {
        try {
            $this->bundleService->updateBundle($id, $request->validated());

            return $this->successResponse('Package has been updated successfully.');
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Delete a bundle.
     */
    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('packages.destroy')) {
            return $this->unauthorizedResponse();
        }

        try {
            $this->bundleService->deleteBundle($id);

            return $this->successResponse('Package has been deleted successfully.');
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update bundle status (active/inactive).
     */
    public function status(UpdateBundleStatusRequest $request): JsonResponse
    {
        try {
            $id = (int) $request->validated('id');
            $status = (int) $request->validated('status');

            $this->bundleService->updateStatus($id, $status);

            $message = match ($status) {
                1       => 'Package has been activated successfully.',
                default => 'Package has been inactivated successfully.',
            };

            return $this->successResponse($message);
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Get bundle details.
     */
    public function detail(int $id): JsonResponse
    {
        if (! Gate::allows('packages.detail.view')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->bundleService->getBundleDetails($id);

            return $this->successResponse('Record found', new BundleDetailResource($data));
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    // ── Sort ───────────────────────────────────────────

    /**
     * Get active bundles for sorting.
     */
    public function sortOrderGet(): JsonResponse
    {
        if (! Gate::allows('packages.sort')) {
            return $this->unauthorizedResponse();
        }

        try {
            $bundles = $this->bundleService->getBundlesForSort(Auth::user()->account_id);

            return $this->successResponse('Success', $bundles);
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Save bundle sort order.
     */
    public function sortOrderSave(Request $request): JsonResponse
    {
        if (! Gate::allows('packages.sort')) {
            return $this->unauthorizedResponse();
        }

        try {
            $itemIds = $request->input('item_ids', []);

            if (empty($itemIds) || ! is_array($itemIds)) {
                return $this->failResponse('No items to sort.');
            }

            $saved = $this->bundleService->saveSortOrder($itemIds, Auth::user()->account_id);

            return $saved
                ? $this->successResponse('Sort order saved!')
                : $this->failResponse('Something went wrong.');
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    // ── Private helpers ─────────────────────────────────

    private function applyFilters(array $filters, int $userId): void
    {
        $filename = 'bundles';
        $applyFilter = checkFilters($filters, $filename);

        $simpleFilters = ['name', 'price', 'total_services', 'status'];
        foreach ($simpleFilters as $key) {
            if (hasFilter($filters, $key)) {
                Filters::put($userId, $filename, $key, $filters[$key]);
            } elseif ($applyFilter) {
                Filters::forget($userId, $filename, $key);
            }
        }

        // Date filters with special keys
        $dateFilters = [
            'created_from' => 'created_from',
            'created_to'   => 'created_to',
            'startdate'    => 'start',
            'enddate'      => 'end',
        ];

        foreach ($dateFilters as $filterKey => $storageKey) {
            if (hasFilter($filters, $filterKey)) {
                Filters::put($userId, $filename, $storageKey, $filters[$filterKey]);
            } elseif ($applyFilter) {
                Filters::forget($userId, $filename, $storageKey);
            }
        }
    }

    /**
     * Restore saved filters from session when not explicitly applied in current request.
     * This preserves the old behavior where navigating back to the datatable
     * restores previously set filters.
     *
     * @return array<string, mixed>
     */
    private function restoreSavedFilters(array $filters, int $userId): array
    {
        $filename = 'bundles';
        $applyFilter = checkFilters($filters, $filename);

        if ($applyFilter) {
            return $filters;
        }

        // Restore simple filters
        foreach (['name', 'price', 'total_services'] as $key) {
            if (! hasFilter($filters, $key)) {
                $saved = Filters::get($userId, $filename, $key);
                if ($saved !== null) {
                    $filters[$key] = $saved;
                }
            }
        }

        // Restore status filter (accepts 0 as valid)
        if (! hasFilter($filters, 'status')) {
            $saved = Filters::get($userId, $filename, 'status');
            if (in_array($saved, [0, 1, '0', '1'], true)) {
                $filters['status'] = $saved;
            }
        }

        // Restore date filters
        $dateMapping = [
            'created_from' => 'created_from',
            'created_to'   => 'created_to',
            'startdate'    => 'start',
            'enddate'      => 'end',
        ];

        foreach ($dateMapping as $filterKey => $storageKey) {
            if (! hasFilter($filters, $filterKey)) {
                $saved = Filters::get($userId, $filename, $storageKey);
                if ($saved !== null) {
                    $filters[$filterKey] = $saved;
                }
            }
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function appendFilterData(array $records, int $userId): array
    {
        $records['filter_values'] = BundleHelper::getFilterValues();
        $records['active_filters'] = Filters::all($userId, 'bundles');

        return $records;
    }

    // ── Standardized response helpers ───────────────────

    protected function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status'  => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ], $code);
    }

    private function failResponse(string $message, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], 200);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status'  => false,
            'message' => 'You are not authorized to access this resource.',
            'data'    => null,
            'errors'  => [],
        ], 403);
    }

    private function exceptionToResponse(\Exception $e): JsonResponse
    {
        $message = config('app.debug')
            ? $e->getMessage() . ' Line ' . $e->getLine() . ' File ' . $e->getFile()
            : 'Something went wrong, please try again later.';

        return response()->json([
            'success' => false,
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => [],
        ], 500);
    }
}
