<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceStatusRequest;
use App\Http\Requests\Admin\StoreResourceRequest;
use App\Http\Requests\Admin\UpdateResourceRequest;
use App\Http\Resources\Resource\ResourceApiResource;
use App\Models\Locations;
use App\Models\MachineType;
use App\Models\Resources;
use App\Models\ResourceTypes;
use App\Services\Resource\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResourcesController extends Controller
{
    public function __construct(
        private readonly ResourceService $service,
    ) {}

    /**
     * GET /api/resources
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_resources` see active rows only — mirrors the legacy
     * datatable behaviour.
     *
     * Permission: `resources_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('resources_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'location_id' => ['nullable', 'integer'],
                'resource_type_id' => ['nullable', 'integer'],
                'machine_type_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Resources::query()
                ->with(['location:id,name', 'resourcetype:id,name,slug', 'MachineType:id,name'])
                ->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_resources')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('location_id')) {
                $query->where('location_id', (int) $request->integer('location_id'));
            }

            if ($request->filled('resource_type_id')) {
                $query->where('resource_type_id', (int) $request->integer('resource_type_id'));
            }

            if ($request->filled('machine_type_id')) {
                $query->where('machine_type_id', (int) $request->integer('machine_type_id'));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('created_at')->paginate($perPage);

            return $this->successResponse(
                'Resources retrieved successfully.',
                [
                    'items' => ResourceApiResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\ResourcesController@index');
        }
    }

    /**
     * GET /api/resources/dropdown
     *
     * Active resources as `{ id, name, location_id, resource_type_id,
     * machine_type_id }[]` — the shape mobile pickers expect. Optional
     * `location_id` filter.
     */
    public function dropdown(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('resources_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'location_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Resources::query()
                ->where('account_id', $accountId)
                ->where('active', 1);

            if ($request->filled('location_id')) {
                $query->where('location_id', (int) $request->integer('location_id'));
            }

            $resources = $query->orderBy('name')
                ->get(['id', 'name', 'location_id', 'resource_type_id', 'machine_type_id']);

            return $this->successResponse(
                'Resources dropdown retrieved successfully.',
                $resources->map(fn (Resources $r): array => [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                    'location_id' => $r->location_id !== null ? (int) $r->location_id : null,
                    'resource_type_id' => $r->resource_type_id !== null ? (int) $r->resource_type_id : null,
                    'machine_type_id' => $r->machine_type_id !== null ? (int) $r->machine_type_id : null,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@dropdown');
        }
    }

    /**
     * GET /api/resources/form-data
     *
     * Picker data for the create/edit screens: resource types, centres
     * (scoped to the caller's ACL), and machine types. Mirrors the legacy
     * `getCreateData` payload but in a clean API envelope.
     */
    public function formData(): JsonResponse
    {
        try {
            if (! Gate::allows('resources_create') && ! Gate::allows('resources_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $resourceTypes = ResourceTypes::getallresource();

            $locations = Locations::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->where('slug', 'custom')
                ->whereIn('id', ACL::getUserCentres())
                ->orderBy('name')
                ->get(['id', 'name']);

            $machineTypes = MachineType::query()
                ->where('active', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            return $this->successResponse('Resource form data retrieved successfully.', [
                'resource_types' => $resourceTypes,
                'locations' => $locations->map(fn (Locations $l): array => [
                    'id' => (int) $l->id,
                    'name' => (string) $l->name,
                ])->values(),
                'machine_types' => $machineTypes->map(fn (MachineType $m): array => [
                    'id' => (int) $m->id,
                    'name' => (string) $m->name,
                ])->values(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@formData');
        }
    }

    /**
     * GET /api/resources/machine-types
     *
     * Machine types filtered to those whose configured services are fully
     * covered by the given centre's service assignments — mirrors the
     * legacy `get_machinetype` logic. Query params: `location_id`
     * (required).
     */
    public function machineTypesByLocation(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('resources_create') && ! Gate::allows('resources_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'location_id' => ['required', 'integer'],
            ]);

            $result = $this->service->getMachineTypesByLocation((int) $request->integer('location_id'));

            $types = collect($result['machinetypes'] ?? [])
                ->map(fn ($m): array => [
                    'id' => (int) $m->id,
                    'name' => (string) $m->name,
                ])
                ->values();

            return $this->successResponse(
                'Machine types retrieved successfully.',
                $types,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@machineTypesByLocation');
        }
    }

    /**
     * POST /api/resources/create
     *
     * The legacy create logic zeroes `external_id` (it's populated only
     * for doctor-linked resources via a different flow) — preserved by
     * reusing `Resources::createRecord` via the service.
     */
    public function store(StoreResourceRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            if (! $this->service->store($request->validated(), $accountId)) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $record = Resources::query()
                ->with(['location:id,name', 'resourcetype:id,name,slug', 'MachineType:id,name'])
                ->where('account_id', $accountId)
                ->orderByDesc('id')
                ->first();

            return $this->successResponse(
                'Resource created successfully.',
                $record ? new ResourceApiResource($record) : null,
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@store');
        }
    }

    /**
     * GET /api/resources/{resource}
     */
    public function show(Resources $resource): JsonResponse
    {
        try {
            if (! Gate::allows('resources_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($resource)) {
                return $this->errorResponse('Resource not found.', 404);
            }

            $resource->load(['location:id,name', 'resourcetype:id,name,slug', 'MachineType:id,name']);

            return $this->successResponse(
                'Resource retrieved successfully.',
                new ResourceApiResource($resource),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@show');
        }
    }

    /**
     * PATCH /api/resources/{resource}
     */
    public function update(UpdateResourceRequest $request, Resources $resource): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($resource)) {
                return $this->errorResponse('Resource not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;

            if (! $this->service->update((int) $resource->id, $request->validated(), $accountId)) {
                return $this->errorResponse('Resource not found.', 404);
            }

            $fresh = $resource->fresh();
            $fresh?->load(['location:id,name', 'resourcetype:id,name,slug', 'MachineType:id,name']);

            return $this->successResponse(
                'Resource updated successfully.',
                $fresh ? new ResourceApiResource($fresh) : null,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@update');
        }
    }

    /**
     * PATCH /api/resources/{resource}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates enforced
     * in the Form Request — `resources_active` vs `resources_inactive`.
     */
    public function status(ResourceStatusRequest $request, Resources $resource): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($resource)) {
                return $this->errorResponse('Resource not found.', 404);
            }

            $ok = $this->service->changeStatus(
                (int) $resource->id,
                (int) $request->validated()['status'],
            );

            if (! $ok) {
                return $this->errorResponse('Resource not found.', 404);
            }

            $fresh = $resource->fresh();
            $fresh?->load(['location:id,name', 'resourcetype:id,name,slug', 'MachineType:id,name']);

            return $this->successResponse(
                'Status has been changed successfully.',
                $fresh ? new ResourceApiResource($fresh) : null,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\ResourcesController@status');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Resources $resource): bool
    {
        return (int) $resource->account_id === (int) Auth::user()->account_id;
    }
}
