<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreBusinessClosureRequest;
use App\Http\Requests\Schedule\UpdateBusinessClosureRequest;
use App\Http\Resources\Schedule\BusinessClosureResource;
use App\Models\BusinessClosure;
use App\Models\Locations;
use App\Services\Schedule\BusinessClosureService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BusinessClosureController extends Controller
{
    public function __construct(
        protected readonly BusinessClosureService $service,
    ) {}

    /**
     * GET /api/catalogue/business-closures
     *
     * Paginated REST list of business closures with locations + creator
     * eager-loaded. Filters: `location_id`, `start_date`, `end_date`,
     * `search` (title), `per_page` (1â€“200; default 25).
     *
     * Permission: `business_closures_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        if (! Gate::allows('business_closures_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $request->validate([
                'location_id' => ['nullable', 'integer'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'search' => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = BusinessClosure::with(['locations:id,name', 'creator:id,name'])
                ->where('account_id', $accountId);

            if ($request->filled('location_id')) {
                $locationId = (int) $request->integer('location_id');
                $query->where(function ($q) use ($locationId): void {
                    $q->whereHas('locations', fn ($sub) => $sub->where('locations.id', $locationId))
                        ->orWhereDoesntHave('locations');
                });
            }

            if ($request->filled('start_date')) {
                $query->whereDate('end_date', '>=', $request->date('start_date')?->toDateString());
            }

            if ($request->filled('end_date')) {
                $query->whereDate('start_date', '<=', $request->date('end_date')?->toDateString());
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search')->trim().'%';
                $query->where('title', 'like', $term);
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('start_date')->paginate($perPage);

            return $this->successResponse(
                'Business closures retrieved successfully.',
                [
                    'items' => BusinessClosureResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\BusinessClosureController@index');
        }
    }

    /**
     * GET /api/catalogue/business-closures/{businessClosure}
     *
     * REST detail endpoint. Unlike `edit`, does not include a locations
     * dropdown payload â€” pure resource shape for mobile / read clients.
     */
    public function show(BusinessClosure $businessClosure): JsonResponse
    {
        if (! Gate::allows('business_closures_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        if ((int) $businessClosure->account_id !== (int) Auth::user()->account_id) {
            return $this->errorResponse('Business closure not found.', 404);
        }

        try {
            $businessClosure->load(['locations:id,name', 'creator:id,name']);

            return $this->successResponse(
                'Business closure retrieved successfully.',
                new BusinessClosureResource($businessClosure),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessClosureController@show');
        }
    }

    /**
     * GET /api/catalogue/business-closures/upcoming
     *
     * Closures that overlap the next `days` window (default 30, max 365).
     * Useful for dashboards and mobile "upcoming closures" widgets.
     */
    public function upcoming(Request $request): JsonResponse
    {
        if (! Gate::allows('business_closures_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'location_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $days = (int) ($request->integer('days') ?: 30);
            $today = Carbon::now()->toDateString();
            $horizon = Carbon::now()->addDays($days)->toDateString();

            $query = BusinessClosure::with(['locations:id,name', 'creator:id,name'])
                ->where('account_id', $accountId)
                ->where('end_date', '>=', $today)
                ->where('start_date', '<=', $horizon);

            if ($request->filled('location_id')) {
                $locationId = (int) $request->integer('location_id');
                $query->where(function ($q) use ($locationId): void {
                    $q->whereHas('locations', fn ($sub) => $sub->where('locations.id', $locationId))
                        ->orWhereDoesntHave('locations');
                });
            }

            $closures = $query->orderBy('start_date')->get();

            return $this->successResponse(
                'Upcoming business closures retrieved successfully.',
                [
                    'window_days' => $days,
                    'from' => $today,
                    'to' => $horizon,
                    'items' => BusinessClosureResource::collection($closures),
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessClosureController@upcoming');
        }
    }

    /**
     * POST /api/catalogue/business-closures/check
     *
     * Is the business closed on a given date (+ optional location)?
     * Designed for pre-booking checks on mobile / booking flows.
     */
    public function check(Request $request): JsonResponse
    {
        if (! Gate::allows('business_closures_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $validated = $request->validate([
                'date' => ['required', 'date'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $date = Carbon::parse($validated['date'])->toDateString();
            $locationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;

            // Closure-lookup lives in the service (C1: controller validates →
            // delegates → returns a Resource). Same query, identical response.
            $closures = $this->service->closuresOnDate($accountId, $date, $locationId);

            return $this->successResponse('Business closure check complete.', [
                'date' => $date,
                'location_id' => $locationId,
                'is_closed' => $closures->isNotEmpty(),
                'closures' => BusinessClosureResource::collection($closures),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessClosureController@check');
        }
    }

    /**
     * POST /api/catalogue/business-closures/bulk-delete
     *
     * Clean REST endpoint over the existing service bulk-delete. The legacy
     * datatable endpoint keeps its comma-separated `delete` filter; this one
     * accepts a proper `ids` array.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        if (! Gate::allows('business_closures_delete')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1', 'max:500'],
                'ids.*' => ['integer', 'distinct'],
            ]);

            $deleted = $this->service->bulkDelete($validated['ids']);

            return $this->successResponse("{$deleted} business closures deleted successfully.", [
                'deleted_count' => $deleted,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessClosureController@bulkDelete');
        }
    }

    /**
     * Get business closures datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = getFilters($request->all());

            // Handle bulk delete
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $deleted = $this->service->bulkDelete($ids);

                return $this->successResponse("{$deleted} records deleted successfully.");
            }

            $datatableData = $this->service->getDatatableData($filters);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $closures = $datatableData['query']
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($datatableData['orderBy'], $datatableData['order'])
                ->get();

            $records = [
                'data' => [],
            ];

            // "All Centres" detection (mirrors the pattern in App\Helpers\ACL
            // and PatientAccessScope): pivot empty (our "all" sentinel), the
            // configured All-Centres virtual location attached, any pseudo
            // rollup centre by name, or every real (non-pseudo) active
            // location for the account attached.
            $accountId = (int) Auth::user()->account_id;
            $pseudoCentreNames = ['All Centres', 'All South Region', 'All Central Region'];
            $allCentresVirtualId = (int) config('constants.all_centres_location_id');

            $realActiveLocationCount = Locations::where('account_id', $accountId)
                ->where('active', '1')
                ->whereNotIn('name', $pseudoCentreNames)
                ->count();

            foreach ($closures as $closure) {
                $attachedIds = $closure->locations->pluck('id');
                $attachedNames = $closure->locations->pluck('name');
                $realAttachedCount = $closure->locations
                    ->reject(fn ($loc) => in_array($loc->name, $pseudoCentreNames, true))
                    ->count();

                $isAllCentres = $closure->locations->isEmpty()
                    || ($allCentresVirtualId > 0 && $attachedIds->contains($allCentresVirtualId))
                    || $attachedNames->intersect($pseudoCentreNames)->isNotEmpty()
                    || ($realActiveLocationCount > 0 && $realAttachedCount >= $realActiveLocationCount);

                $locationNames = $isAllCentres
                    ? 'All Centres'
                    : $closure->locations->pluck('name')->implode(', ');

                $records['data'][] = [
                    'id' => $closure->id,
                    'locations' => $locationNames,
                    'start_date' => Carbon::parse($closure->start_date)->format('D M j, Y'),
                    'end_date' => Carbon::parse($closure->end_date)->format('D M j, Y'),
                    'title' => $closure->title ?? '-',
                    'created_by' => $closure->creator->name ?? 'N/A',
                    'created_at' => Carbon::parse($closure->created_at)->format('M j, Y h:i A'),
                ];
            }

            $records['meta'] = [
                'page' => $page,
                'pages' => $pages,
                'perpage' => $displayLength,
                'total' => $datatableData['total'],
                'sort' => $datatableData['order'],
                'field' => $datatableData['orderBy'],
            ];

            // Add filter values
            $filterData = $this->service->getFilterValues();
            $records['filter_values'] = $filterData['filter_values'];
            $records['active_filters'] = $filterData['active_filters'];

            $records['permissions'] = [
                'create' => Gate::allows('business_closures_create'),
                'edit' => Gate::allows('business_closures_edit'),
                'delete' => Gate::allows('business_closures_delete'),
            ];

            return response()->json($records);

        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Get form data for creating a new closure
     */
    public function create(): JsonResponse
    {
        try {
            $userCentres = ACL::getUserCentres();
            $locationsQuery = Locations::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
            ]);

            if ($userCentres && is_array($userCentres) && ! empty($userCentres)) {
                $locationsQuery->whereIn('id', $userCentres);
            }

            $locations = $locationsQuery->orderBy('name', 'asc')->get(['id', 'name']);

            return $this->successResponse('Data loaded successfully.', [
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Store a new business closure
     */
    public function store(StoreBusinessClosureRequest $request): JsonResponse
    {
        try {
            $closure = $this->service->create($request->validated());

            return $this->successResponse('Business closure created successfully.', [
                'closure' => $closure,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Get a single business closure for editing
     */
    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('business_closures_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $closure = $this->service->getById($id);

            if (! $closure) {
                return $this->errorResponse('Business closure not found.', 500);
            }

            $userCentres = ACL::getUserCentres();
            $locationsQuery = Locations::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
            ]);

            if ($userCentres && is_array($userCentres) && ! empty($userCentres)) {
                $locationsQuery->whereIn('id', $userCentres);
            }

            $locations = $locationsQuery->orderBy('name', 'asc')->get(['id', 'name']);

            return $this->successResponse('Data loaded successfully.', [
                'closure' => $closure,
                'location_ids' => $closure->locations->pluck('id')->toArray(),
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Update a business closure
     */
    public function update(UpdateBusinessClosureRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('business_closures_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $closure = $this->service->update($id, $request->validated());

            return $this->successResponse('Business closure updated successfully.', [
                'closure' => $closure,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }

    /**
     * Delete a business closure
     */
    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('business_closures_delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $this->service->delete($id);

            return $this->successResponse('Business closure deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'BusinessClosureController');
        }
    }
}
