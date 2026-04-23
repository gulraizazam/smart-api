<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CentreStatusRequest;
use App\Http\Requests\Admin\SortCentresRequest;
use App\Http\Requests\Admin\StoreCentreRequest;
use App\Http\Requests\Admin\UpdateCentreRequest;
use App\Http\Resources\Centre\CentreResource;
use App\Models\Locations;
use App\Services\Location\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Centres REST API — backed by the `Locations` model/service internally.
 * "Centres" is the business term used by callers; legacy admin routes
 * (`/locations/...`) continue to serve the admin UI untouched.
 */
class CentresController extends Controller
{
    public function __construct(
        private readonly LocationService $service,
    ) {}

    /**
     * GET /api/centres
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_locations` see active rows only. Only `slug='custom'`
     * rows are returned (the root `all`/`region` rows are internal).
     *
     * Permission: `locations_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('locations_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'region_id' => ['nullable', 'integer'],
                'city_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Locations::query()
                ->with(['city:id,name', 'region:id,name'])
                ->where('account_id', $accountId)
                ->where('slug', 'custom');

            if (! Gate::allows('view_inactive_locations')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('region_id')) {
                $query->where('region_id', (int) $request->integer('region_id'));
            }

            if ($request->filled('city_id')) {
                $query->where('city_id', (int) $request->integer('city_id'));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('sort_no')->paginate($perPage);

            return $this->successResponse(
                'Centres retrieved successfully.',
                [
                    'items' => CentreResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\CentresController@index');
        }
    }

    /**
     * GET /api/centres/dropdown
     *
     * Active centres as `{ id, name, city_id, region_id }[]` ordered by
     * `sort_no` — the shape mobile pickers expect. Optional `city_id` /
     * `region_id` filters.
     */
    public function dropdown(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('locations_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'city_id' => ['nullable', 'integer'],
                'region_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Locations::query()
                ->where('account_id', $accountId)
                ->where('slug', 'custom')
                ->where('active', 1);

            if ($request->filled('city_id')) {
                $query->where('city_id', (int) $request->integer('city_id'));
            }

            if ($request->filled('region_id')) {
                $query->where('region_id', (int) $request->integer('region_id'));
            }

            $centres = $query->orderBy('sort_no')->get(['id', 'name', 'city_id', 'region_id']);

            return $this->successResponse(
                'Centres dropdown retrieved successfully.',
                $centres->map(fn (Locations $c): array => [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'city_id' => $c->city_id !== null ? (int) $c->city_id : null,
                    'region_id' => $c->region_id !== null ? (int) $c->region_id : null,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@dropdown');
        }
    }

    /**
     * GET /api/centres/sorted
     *
     * Full list in sort order — used by the drag-and-drop reorder UI.
     */
    public function sorted(): JsonResponse
    {
        try {
            if (! Gate::allows('locations_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $centres = $this->service->getSortableLocations((int) Auth::user()->account_id);

            return $this->successResponse(
                'Sorted centres retrieved successfully.',
                CentreResource::collection($centres),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@sorted');
        }
    }

    /**
     * POST /api/centres/sort
     *
     * Bulk reorder. Body: `{ "item_ids": [3, 1, 2] }` — the resulting
     * `sort_no` is the array index.
     */
    public function sort(SortCentresRequest $request): JsonResponse
    {
        try {
            $saved = $this->service->saveSortOrder($request->validated()['item_ids']);

            return $saved
                ? $this->successResponse('Centres sorted successfully.')
                : $this->errorResponse('Could not save sort order.', 500);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@sort');
        }
    }

    /**
     * POST /api/centres/create
     *
     * Accepts `multipart/form-data` when an image is attached (`file` field).
     * Wraps the create + post-creation user/service assignment in a single
     * transaction — side-effects on failure are rolled back together.
     */
    public function store(StoreCentreRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            $centre = DB::transaction(function () use ($request, $accountId): ?Locations {
                $created = $this->service->createLocation($request, $accountId);

                if (! $created) {
                    return null;
                }

                $this->service->handlePostCreation($created, $request->all(), $accountId);

                return $created;
            });

            if (! $centre) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $centre->load(['city:id,name', 'region:id,name', 'service_has_locations']);

            return $this->successResponse(
                'Centre created successfully.',
                new CentreResource($centre),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@store');
        }
    }

    /**
     * GET /api/centres/{centre}
     */
    public function show(Locations $centre): JsonResponse
    {
        try {
            if (! Gate::allows('locations_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($centre)) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $centre->load(['city:id,name', 'region:id,name', 'service_has_locations']);

            return $this->successResponse(
                'Centre retrieved successfully.',
                new CentreResource($centre),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@show');
        }
    }

    /**
     * PATCH /api/centres/{centre}
     *
     * Accepts `multipart/form-data` when an image is attached. Wraps the
     * update + service-reassignment in a single transaction.
     */
    public function update(UpdateCentreRequest $request, Locations $centre): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($centre)) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;

            $updated = DB::transaction(function () use ($request, $centre, $accountId): ?Locations {
                $record = $this->service->updateLocation((int) $centre->id, $request, $accountId);

                if (! $record) {
                    return null;
                }

                $this->service->handlePostUpdate($record, $request->all(), $accountId);

                return $record;
            });

            if (! $updated) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $updated->load(['city:id,name', 'region:id,name', 'service_has_locations']);

            return $this->successResponse(
                'Centre updated successfully.',
                new CentreResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@update');
        }
    }

    /**
     * PATCH /api/centres/{centre}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced
     * in the Form Request — `locations_active` vs `locations_inactive`.
     */
    public function status(CentreStatusRequest $request, Locations $centre): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($centre)) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $ok = $this->service->changeStatus((int) $centre->id, (string) $request->validated()['status']);

            if (! $ok) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $fresh = $centre->fresh();
            $fresh?->load(['city:id,name', 'region:id,name']);

            return $this->successResponse(
                'Status has been changed successfully.',
                $fresh ? new CentreResource($fresh) : null,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@status');
        }
    }

    /**
     * GET /api/centres/{centre}/services
     *
     * Returns the services tree for the centre — mirrors the legacy
     * `LocationsController@getServices` shape so the mobile app can render
     * the service picker without divergence.
     */
    public function services(Locations $centre): JsonResponse
    {
        try {
            if (! Gate::allows('locations_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($centre)) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $data = $this->service->getServicesForLocation(
                (int) $centre->id,
                (int) Auth::user()->account_id,
            );

            return $this->successResponse('Centre services retrieved successfully.', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@services');
        }
    }

    /**
     * DELETE /api/centres/{centre}
     *
     * Soft-delete. Returns **409** when `Locations::isChildExists` flags a
     * dependency; preserves the legacy guard verbatim.
     */
    public function destroy(Locations $centre): JsonResponse
    {
        try {
            if (! Gate::allows('locations_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($centre)) {
                return $this->errorResponse('Centre not found.', 404);
            }

            $result = $this->service->deleteLocation((int) $centre->id);

            if (! $result['status']) {
                $message = strtolower((string) $result['message']);
                $status = str_contains($message, 'child') ? 409 : 404;

                return $this->errorResponse($result['message'], $status);
            }

            return $this->successResponse($result['message']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentresController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Locations $centre): bool
    {
        return (int) $centre->account_id === (int) Auth::user()->account_id;
    }
}
