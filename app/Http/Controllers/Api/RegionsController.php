<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegionStatusRequest;
use App\Http\Requests\Admin\SortRegionsRequest;
use App\Http\Requests\Admin\StoreRegionRequest;
use App\Http\Requests\Admin\UpdateRegionRequest;
use App\Http\Resources\Region\RegionResource;
use App\Models\Regions;
use App\Services\Region\RegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RegionsController extends Controller
{
    public function __construct(
        private readonly RegionService $service,
    ) {}

    /**
     * GET /api/regions
     *
     * Paginated account-scoped list. Callers without `view_inactive_regions`
     * see active rows only — mirrors the legacy datatable behaviour. Only
     * `slug='custom'` rows are returned (the root `all` row is internal).
     *
     * Permission: `regions_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('regions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Regions::query()
                ->withCount(['locations', 'locationsActive'])
                ->where('account_id', $accountId)
                ->where('slug', 'custom');

            if (! Gate::allows('view_inactive_regions')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('sort_number')->paginate($perPage);

            return $this->successResponse(
                'Regions retrieved successfully.',
                [
                    'items' => RegionResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@index');
        }
    }

    /**
     * GET /api/regions/dropdown
     *
     * Active regions as `{ id, name }[]` ordered by `sort_number` — the shape
     * mobile pickers expect.
     */
    public function dropdown(): JsonResponse
    {
        try {
            if (! Gate::allows('regions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $regions = Regions::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->where('slug', 'custom')
                ->orderBy('sort_number')
                ->get(['id', 'name']);

            return $this->successResponse(
                'Regions dropdown retrieved successfully.',
                $regions->map(fn (Regions $r) => [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                ])->values(),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@dropdown');
        }
    }

    /**
     * GET /api/regions/sorted
     *
     * Full list in sort order — used by the drag-and-drop reorder UI. Mirrors
     * `RegionService::getSortedRegions`.
     */
    public function sorted(): JsonResponse
    {
        try {
            if (! Gate::allows('regions_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $regions = $this->service->getSortedRegions((int) Auth::user()->account_id);

            return $this->successResponse(
                'Sorted regions retrieved successfully.',
                RegionResource::collection($regions),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@sorted');
        }
    }

    /**
     * POST /api/regions/sort
     *
     * Bulk reorder. Body: `{ "item_ids": [3, 1, 2] }` — the resulting
     * `sort_number` is the array index.
     */
    public function sort(SortRegionsRequest $request): JsonResponse
    {
        try {
            $saved = $this->service->saveSortOrder($request->validated()['item_ids']);

            return $saved
                ? $this->successResponse('Regions sorted successfully.')
                : $this->errorResponse('Could not save sort order.', 500);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@sort');
        }
    }

    /**
     * POST /api/regions
     */
    public function store(StoreRegionRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $request->merge(['active' => array_key_exists('active', $validated) ? (int) $validated['active'] : 1]);

            $record = Regions::createRecord($request, $accountId);

            if (! $record) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $record->loadCount(['locations', 'locationsActive']);

            return $this->successResponse(
                'Region created successfully.',
                new RegionResource($record),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@store');
        }
    }

    /**
     * GET /api/regions/{region}
     */
    public function show(Regions $region): JsonResponse
    {
        try {
            if (! Gate::allows('regions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($region)) {
                return $this->errorResponse('Region not found.', 404);
            }

            $region->loadCount(['locations', 'locationsActive']);

            return $this->successResponse(
                'Region retrieved successfully.',
                new RegionResource($region),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@show');
        }
    }

    /**
     * PATCH /api/regions/{region}
     */
    public function update(UpdateRegionRequest $request, Regions $region): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($region)) {
                return $this->errorResponse('Region not found.', 404);
            }

            if ($region->slug === 'all') {
                return $this->errorResponse('The root region cannot be modified.', 422);
            }

            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $request->merge([
                'active' => array_key_exists('active', $validated)
                    ? (int) $validated['active']
                    : (int) $region->active,
            ]);

            $updated = Regions::updateRecord((int) $region->id, $request, $accountId);

            if (! $updated) {
                return $this->errorResponse('Region not found.', 404);
            }

            $updated->loadCount(['locations', 'locationsActive']);

            return $this->successResponse(
                'Region updated successfully.',
                new RegionResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@update');
        }
    }

    /**
     * PATCH /api/regions/{region}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced in
     * the Form Request — `regions_active` vs `regions_inactive`.
     */
    public function status(RegionStatusRequest $request, Regions $region): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($region)) {
                return $this->errorResponse('Region not found.', 404);
            }

            if ($region->slug === 'all') {
                return $this->errorResponse('The root region cannot be deactivated.', 422);
            }

            $result = $this->service->changeStatus((int) $region->id, (int) $request->validated()['status']);

            if (! $result['status']) {
                return $this->errorResponse($result['message'], 404);
            }

            return $this->successResponse(
                $result['message'],
                new RegionResource($region->fresh()->loadCount(['locations', 'locationsActive'])),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@status');
        }
    }

    /**
     * DELETE /api/regions/{region}
     *
     * Soft-delete. Returns **409** on the two guards enforced by
     * `Regions::DeleteRecord`: the root `all` region is protected, and a
     * region with linked Leads or Appointments cannot be removed without
     * migrating them first. Cities + Locations under the region are
     * cascade-deleted inside the service — preserved exactly from legacy.
     */
    public function destroy(Regions $region): JsonResponse
    {
        try {
            if (! Gate::allows('regions_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($region)) {
                return $this->errorResponse('Region not found.', 404);
            }

            $result = $this->service->deleteRegion((int) $region->id);

            if (! $result['status']) {
                $message = strtolower((string) $result['message']);
                $status = match (true) {
                    str_contains($message, 'child') => 409,
                    str_contains($message, 'root') => 422,
                    default => 404,
                };

                return $this->errorResponse($result['message'], $status);
            }

            return $this->successResponse($result['message']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\RegionsController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Regions $region): bool
    {
        return (int) $region->account_id === (int) Auth::user()->account_id;
    }
}
