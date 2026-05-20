<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ServiceBundleException;
use App\Helpers\Filters;
use App\Helpers\ServiceBundleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceBundle\BulkCreateServiceBundleRequest;
use App\Http\Requests\ServiceBundle\StoreServiceBundleRequest;
use App\Http\Requests\ServiceBundle\UpdateServiceBundleRequest;
use App\Http\Requests\ServiceBundle\UpdateServiceBundleStatusRequest;
use App\Http\Resources\ServiceBundle\ServiceBundleDatatableResource;
use App\Services\ServiceBundle\ServiceBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ServiceBundlesController extends Controller
{
    public function __construct(
        private readonly ServiceBundleService $serviceBundleService,
    ) {}

    /**
     * Get bundles datatable data.
     */
    public function datatable(Request $request): JsonResponse
    {
        if (! Gate::allows('bundles.list.view')) {
            return $this->unauthorizedResponse();
        }

        try {
            $user = Auth::user();
            $accountId = $user->account_id;
            $filters = getFilters($request->all());
            $records = ['data' => []];

            // Handle bulk delete — re-gate inside the branch because
            // `bundles.list.view` only authorises reads.
            if (hasFilter($filters, 'delete')) {
                if (! Gate::allows('bundles.destroy')) {
                    return $this->unauthorizedResponse();
                }
                $ids = array_filter(array_map('intval', explode(',', $filters['delete'])));
                $this->serviceBundleService->bulkDelete($ids, $accountId);
                $records['status'] = true;
                $records['message'] = 'Records have been deleted successfully!';
            }

            // Persist/clear filters, then restore any saved filters
            $this->applyFilters($filters, $user->id);
            $filters = $this->restoreSavedFilters($filters, $user->id);

            $canViewInactive = Gate::allows('bundles.list.view_inactive');

            // Returns interleaved category + bundle rows (no pagination, like services)
            $items = $this->serviceBundleService->getBundlesList($filters, $accountId, $canViewInactive);

            // Transform: category rows pass through as-is, bundle rows go through resource
            $data = [];
            foreach ($items as $item) {
                if (is_array($item) && ($item['is_category'] ?? false)) {
                    $data[] = $item;
                } else {
                    $data[] = (new ServiceBundleDatatableResource($item))->resolve();
                }
            }

            $records = $this->appendFilterData($records, $user->id);
            $records['data'] = $data;
            $records['permissions'] = ServiceBundleHelper::getPermissions();
            $records['meta'] = [
                'field'   => 'sort_number',
                'page'    => 1,
                'pages'   => 1,
                'perpage' => max(count($data), 100),
                'total'   => count($data),
                'sort'    => 'asc',
            ];

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Store a new bundle.
     */
    public function store(StoreServiceBundleRequest $request): JsonResponse
    {
        try {
            $this->serviceBundleService->createBundle($request->validated());

            return $this->successResponse('Bundle has been created successfully.');
        } catch (ServiceBundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Bulk create bundles.
     */
    public function bulkStore(BulkCreateServiceBundleRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $created = $this->serviceBundleService->bulkCreate(
                $data['service_ids'],
                (int) $data['sessions'],
                (float) $data['discount_percentage']
            );

            return $this->successResponse("{$created} bundle(s) created successfully.");
        } catch (ServiceBundleException $e) {
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
        if (! Gate::allows('bundles.edit')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->serviceBundleService->getBundleForEdit($id);

            return $this->successResponse('Record found', $data);
        } catch (ServiceBundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update a bundle.
     */
    public function update(UpdateServiceBundleRequest $request, int $id): JsonResponse
    {
        try {
            $this->serviceBundleService->updateBundle($id, $request->validated());

            return $this->successResponse('Bundle has been updated successfully.');
        } catch (ServiceBundleException $e) {
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
        if (! Gate::allows('bundles.destroy')) {
            return $this->unauthorizedResponse();
        }

        try {
            $this->serviceBundleService->deleteBundle($id);

            return $this->successResponse('Bundle has been deleted successfully.');
        } catch (ServiceBundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update bundle status.
     */
    public function status(UpdateServiceBundleStatusRequest $request): JsonResponse
    {
        try {
            $id = (int) $request->validated('id');
            $status = (int) $request->validated('status');

            $this->serviceBundleService->updateStatus($id, $status);

            $message = match ($status) {
                1       => 'Bundle has been activated successfully.',
                default => 'Bundle has been inactivated successfully.',
            };

            return $this->successResponse($message);
        } catch (ServiceBundleException $e) {
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
        if (! Gate::allows('bundles.detail.view')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->serviceBundleService->getBundleDetails($id);

            return $this->successResponse('Record found', $data);
        } catch (ServiceBundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    // ── Sort ───────────────────────────────────────────

    /**
     * Get categories for sorting.
     */
    public function sortOrderGet(): JsonResponse
    {
        if (! Gate::allows('bundles.sort')) {
            return $this->unauthorizedResponse();
        }

        try {
            $categories = $this->serviceBundleService->getCategoriesForSort(Auth::user()->account_id);

            return $this->successResponse('Success', $categories);
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Save category sort order.
     */
    public function sortOrderSave(Request $request): JsonResponse
    {
        if (! Gate::allows('bundles.sort')) {
            return $this->unauthorizedResponse();
        }

        try {
            $categoryIds = $request->input('category_ids', []);

            if (empty($categoryIds) || ! is_array($categoryIds)) {
                return $this->failResponse('No categories to sort.');
            }

            $saved = $this->serviceBundleService->saveCategorySortOrder($categoryIds, Auth::user()->account_id);

            return $saved
                ? $this->successResponse('Category order saved!')
                : $this->failResponse('Something went wrong.');
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    // ── Private helpers ─────────────────────────────────

    private function applyFilters(array $filters, int $userId): void
    {
        $filename = 'service_bundles';
        $applyFilter = checkFilters($filters, $filename);

        $simpleFilters = ['name', 'category', 'sessions', 'status'];
        foreach ($simpleFilters as $key) {
            if (hasFilter($filters, $key)) {
                Filters::put($userId, $filename, $key, $filters[$key]);
            } elseif ($applyFilter) {
                Filters::forget($userId, $filename, $key);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreSavedFilters(array $filters, int $userId): array
    {
        $filename = 'service_bundles';
        $applyFilter = checkFilters($filters, $filename);

        if ($applyFilter) {
            return $filters;
        }

        foreach (['name', 'category', 'sessions'] as $key) {
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

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function appendFilterData(array $records, int $userId): array
    {
        $records['filter_values'] = ServiceBundleHelper::getFilterValues();
        $records['active_filters'] = Filters::all($userId, 'service_bundles');

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
