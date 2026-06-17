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

/**
 * Packages REST API.
 *
 * Schema note: in this system the UI label "Packages" maps to the
 * `bundles` database table (the literal `packages` table holds patient
 * plans — a different concept). Permission keys are `packages_*` and the
 * canonical source of truth is `App\Services\Bundle\BundleService`. This
 * controller is the UI-aligned alias of `Api\BundlesController`; both
 * controllers wrap the same service so behaviour can't drift.
 *
 * The parallel admin page is `/admin/bundles` (served by the bundles
 * datatable + `BundleDatatableResource`). See
 * `app/Http/Controllers/Api/BundlesController.php` for the sibling API.
 */
final class PackagesController extends Controller
{
    public function __construct(
        private readonly BundleService $bundleService,
    ) {}

    /**
     * GET /api/packages
     *
     * Paginated list. Accepts the same filters as the legacy
     * bundles datatable — `name`, `price`, `total_services`, `status`,
     * `created_from`, `created_to`, `startdate`, `enddate`, `per_page`.
     *
     * Permission: `packages.list.view`. Inactive rows are hidden unless
     * `view_inactive_packages` is granted.
     */
    public function index(Request $request): JsonResponse
    {
        if (! Gate::allows('packages.list.view')) {
            return $this->unauthorizedResponse();
        }

        try {
            $user = Auth::user();
            $accountId = (int) $user->account_id;

            $filters = getFilters($request->all());
            $records = ['data' => []];

            if (hasFilter($filters, 'delete')) {
                $ids = array_filter(array_map('intval', explode(',', $filters['delete'])));
                $this->bundleService->bulkDelete($ids, $accountId);
                $records['status'] = true;
                $records['message'] = 'Records have been deleted successfully!';
            }

            $this->applyFilters($filters, (int) $user->id);
            $filters = $this->restoreSavedFilters($filters, (int) $user->id);

            [$orderBy, $order] = getSortBy($request);
            $canViewInactive = Gate::allows('view_inactive_packages');

            $totalRecords = $this->bundleService->getTotalRecords($filters, $accountId, $canViewInactive);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

            $bundles = $this->bundleService->getBundlesList(
                $filters,
                $accountId,
                $canViewInactive,
                $displayStart,
                $displayLength,
            );

            $records['data'] = BundleDatatableResource::collection($bundles)->resolve();
            $records['filter_values'] = BundleHelper::getFilterValues();
            $records['active_filters'] = Filters::all((int) $user->id, 'bundles');
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
     * POST /api/packages/datatable
     *
     * Legacy-shape datatable endpoint — identical payload to `index`
     * but accepts POST so legacy admin-side callers can plug in without
     * URL changes.
     */
    public function datatable(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * POST /api/packages/create
     *
     * Permission: `packages.create`.
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
     * GET /api/packages/{id}
     *
     * Detail (bundle + bundle_services + relationships).
     * Permission: `packages.detail.view`.
     */
    public function show(int $id): JsonResponse
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

    /**
     * GET /api/packages/{id}/edit
     *
     * Form data for the edit screen.
     * Permission: `packages.edit`.
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
     * PATCH /api/packages/{id}
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
     * DELETE /api/packages/{id}
     *
     * Permission: `packages.destroy`.
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
     * POST /api/packages/status
     *
     * Body: `{ id, status }`. Gates are enforced by `UpdateBundleStatusRequest`.
     */
    public function status(UpdateBundleStatusRequest $request): JsonResponse
    {
        try {
            $id = (int) $request->validated('id');
            $status = (int) $request->validated('status');

            $this->bundleService->updateStatus($id, $status);

            $message = $status === 1
                ? 'Package has been activated successfully.'
                : 'Package has been inactivated successfully.';

            return $this->successResponse($message);
        } catch (BundleException $e) {
            return $this->failResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET /api/packages/sort/get
     *
     * Active packages ordered for drag-and-drop reorder.
     */
    public function sortOrderGet(): JsonResponse
    {
        if (! Gate::allows('packages.edit')) {
            return $this->unauthorizedResponse();
        }

        try {
            $bundles = $this->bundleService->getBundlesForSort((int) Auth::user()->account_id);

            return $this->successResponse('Success', $bundles);
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * POST /api/packages/sort/save
     *
     * Body: `{ item_ids: [3, 1, 2] }`.
     */
    public function sortOrderSave(Request $request): JsonResponse
    {
        if (! Gate::allows('packages.edit')) {
            return $this->unauthorizedResponse();
        }

        try {
            $itemIds = $request->input('item_ids', []);

            if (empty($itemIds) || ! is_array($itemIds)) {
                return $this->failResponse('No items to sort.');
            }

            $saved = $this->bundleService->saveSortOrder($itemIds, (int) Auth::user()->account_id);

            return $saved
                ? $this->successResponse('Sort order saved!')
                : $this->failResponse('Something went wrong.');
        } catch (\Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    // ── filter persistence helpers (mirror Api\BundlesController) ──

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(array $filters, int $userId): void
    {
        $filename = 'bundles';
        $applyFilter = checkFilters($filters, $filename);

        foreach (['name', 'price', 'total_services', 'status'] as $key) {
            if (hasFilter($filters, $key)) {
                Filters::put($userId, $filename, $key, $filters[$key]);
            } elseif ($applyFilter) {
                Filters::forget($userId, $filename, $key);
            }
        }

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
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function restoreSavedFilters(array $filters, int $userId): array
    {
        $filename = 'bundles';

        if (checkFilters($filters, $filename)) {
            return $filters;
        }

        foreach (['name', 'price', 'total_services'] as $key) {
            if (! hasFilter($filters, $key)) {
                $saved = Filters::get($userId, $filename, $key);
                if ($saved !== null) {
                    $filters[$key] = $saved;
                }
            }
        }

        if (! hasFilter($filters, 'status')) {
            $saved = Filters::get($userId, $filename, 'status');
            if (in_array($saved, [0, 1, '0', '1'], true)) {
                $filters['status'] = $saved;
            }
        }

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

    // ── response helpers (mirror Api\BundlesController) ──

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
            ? $e->getMessage().' Line '.$e->getLine().' File '.$e->getFile()
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
