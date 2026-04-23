<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CityStatusRequest;
use App\Http\Requests\Admin\SortCitiesRequest;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Http\Resources\City\CityResource;
use App\Models\Cities;
use App\Services\City\CityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CitiesController extends Controller
{
    public function __construct(
        private readonly CityService $service,
    ) {}

    /**
     * GET /api/cities
     *
     * Paginated account-scoped list. Callers without `view_inactive_cities`
     * see active rows only — mirrors the legacy datatable behaviour. Only
     * `slug='custom'` rows are returned.
     *
     * Permission: `cities_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('cities_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'region_id' => ['nullable', 'integer'],
                'is_featured' => ['nullable', 'integer', 'in:0,1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Cities::query()
                ->with(['region:id,name'])
                ->withCount(['locations', 'locationsActive'])
                ->where('account_id', $accountId)
                ->where('slug', 'custom');

            if (! Gate::allows('view_inactive_cities')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('region_id')) {
                $query->where('region_id', (int) $request->integer('region_id'));
            }

            if ($request->filled('is_featured')) {
                $query->where('is_featured', (int) $request->integer('is_featured'));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('sort_number')->paginate($perPage);

            return $this->successResponse(
                'Cities retrieved successfully.',
                [
                    'items' => CityResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\CitiesController@index');
        }
    }

    /**
     * GET /api/cities/dropdown
     *
     * Active cities as `{ id, name, region_id }[]` ordered by `sort_number`
     * — the shape mobile pickers expect. Optional `region_id` filter.
     */
    public function dropdown(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('cities_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'region_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Cities::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->where('slug', 'custom');

            if ($request->filled('region_id')) {
                $query->where('region_id', (int) $request->integer('region_id'));
            }

            $cities = $query->orderBy('sort_number')->get(['id', 'name', 'region_id']);

            return $this->successResponse(
                'Cities dropdown retrieved successfully.',
                $cities->map(fn (Cities $c) => [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'region_id' => $c->region_id !== null ? (int) $c->region_id : null,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@dropdown');
        }
    }

    /**
     * GET /api/cities/sorted
     *
     * Full list in sort order — used by the drag-and-drop reorder UI. Mirrors
     * `CityService::getSortedCities`.
     */
    public function sorted(): JsonResponse
    {
        try {
            if (! Gate::allows('cities_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $cities = $this->service->getSortedCities((int) Auth::user()->account_id);

            return $this->successResponse(
                'Sorted cities retrieved successfully.',
                CityResource::collection($cities),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@sorted');
        }
    }

    /**
     * POST /api/cities/sort
     *
     * Bulk reorder. Body: `{ "item_ids": [3, 1, 2] }` — the resulting
     * `sort_number` is the array index.
     */
    public function sort(SortCitiesRequest $request): JsonResponse
    {
        try {
            $saved = $this->service->saveSortOrder($request->validated()['item_ids']);

            return $saved
                ? $this->successResponse('Cities sorted successfully.')
                : $this->errorResponse('Could not save sort order.', 500);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@sort');
        }
    }

    /**
     * POST /api/cities
     */
    public function store(StoreCityRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $request->merge([
                'active' => array_key_exists('active', $validated) ? (int) $validated['active'] : 1,
                'is_featured' => array_key_exists('is_featured', $validated) ? (int) $validated['is_featured'] : 0,
            ]);

            $record = Cities::createRecord($request, $accountId);

            if (! $record) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $record->load('region:id,name')->loadCount(['locations', 'locationsActive']);

            return $this->successResponse(
                'City created successfully.',
                new CityResource($record),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@store');
        }
    }

    /**
     * GET /api/cities/{city}
     */
    public function show(Cities $city): JsonResponse
    {
        try {
            if (! Gate::allows('cities_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($city)) {
                return $this->errorResponse('City not found.', 404);
            }

            $city->load('region:id,name')->loadCount(['locations', 'locationsActive']);

            return $this->successResponse(
                'City retrieved successfully.',
                new CityResource($city),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@show');
        }
    }

    /**
     * PATCH /api/cities/{city}
     */
    public function update(UpdateCityRequest $request, Cities $city): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($city)) {
                return $this->errorResponse('City not found.', 404);
            }

            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $request->merge([
                'active' => array_key_exists('active', $validated)
                    ? (int) $validated['active']
                    : (int) $city->active,
                'is_featured' => array_key_exists('is_featured', $validated)
                    ? (int) $validated['is_featured']
                    : (int) $city->is_featured,
            ]);

            $updated = Cities::updateRecord((int) $city->id, $request, $accountId);

            if (! $updated) {
                return $this->errorResponse('City not found.', 404);
            }

            $updated->load('region:id,name')->loadCount(['locations', 'locationsActive']);

            return $this->successResponse(
                'City updated successfully.',
                new CityResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@update');
        }
    }

    /**
     * PATCH /api/cities/{city}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced in
     * the Form Request — `cities_active` vs `cities_inactive`.
     */
    public function status(CityStatusRequest $request, Cities $city): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($city)) {
                return $this->errorResponse('City not found.', 404);
            }

            $result = $this->service->changeStatus((int) $city->id, (int) $request->validated()['status']);

            if (! $result['status']) {
                return $this->errorResponse($result['message'], 404);
            }

            return $this->successResponse(
                $result['message'],
                new CityResource($city->fresh()->load('region:id,name')->loadCount(['locations', 'locationsActive'])),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@status');
        }
    }

    /**
     * DELETE /api/cities/{city}
     *
     * Soft-delete. Returns **409** when the city has linked Locations, Leads,
     * or Appointments — mirrors `Cities::isChildExists`.
     */
    public function destroy(Cities $city): JsonResponse
    {
        try {
            if (! Gate::allows('cities_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($city)) {
                return $this->errorResponse('City not found.', 404);
            }

            $result = $this->service->deleteCity((int) $city->id);

            if (! $result['status']) {
                $message = strtolower((string) $result['message']);
                $status = str_contains($message, 'child') ? 409 : 404;

                return $this->errorResponse($result['message'], $status);
            }

            return $this->successResponse($result['message']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CitiesController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Cities $city): bool
    {
        return (int) $city->account_id === (int) Auth::user()->account_id;
    }
}
