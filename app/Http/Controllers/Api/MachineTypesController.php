<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MachineTypeStatusRequest;
use App\Http\Requests\Admin\StoreMachineTypeRequest;
use App\Http\Requests\Admin\UpdateMachineTypeRequest;
use App\Http\Resources\MachineType\MachineTypeResource;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use App\Models\Services;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MachineTypesController extends Controller
{
    /**
     * GET /api/machine_types
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_machine_types` see active rows only — mirrors the
     * legacy datatable behaviour.
     *
     * Permission: `machineType_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('machineType_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'service_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = MachineType::query()
                ->with('services')
                ->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_machine_types')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('service_id')) {
                $serviceId = (int) $request->integer('service_id');
                $query->whereHas('services', fn ($q) => $q->where('id', $serviceId));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('created_at')->paginate($perPage);

            return $this->successResponse(
                'Machine types retrieved successfully.',
                [
                    'items' => MachineTypeResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\MachineTypesController@index');
        }
    }

    /**
     * GET /api/machine_types/dropdown
     *
     * Active machine types as `{ id, name }[]` — the shape mobile pickers
     * expect.
     */
    public function dropdown(): JsonResponse
    {
        try {
            if (! Gate::allows('machineType_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $types = MachineType::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            return $this->successResponse(
                'Machine types dropdown retrieved successfully.',
                $types->map(fn (MachineType $t): array => [
                    'id' => (int) $t->id,
                    'name' => (string) $t->name,
                ])->values(),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\MachineTypesController@dropdown');
        }
    }

    /**
     * POST /api/machine_types/create
     *
     * Accepts `services[]` — leaf-level services only. Parent services are
     * stripped automatically if their children are also in the list (same
     * behaviour as the legacy admin controller).
     *
     * Creating the machine type and the service pivot rows is wrapped in a
     * single `DB::transaction()` so partial writes don't leak on failure.
     */
    public function store(StoreMachineTypeRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            $machineType = DB::transaction(function () use ($request, $accountId): ?MachineType {
                $created = MachineType::createRecord($request, $accountId);

                if (! $created) {
                    return null;
                }

                $this->syncServices($created, (array) $request->input('services', []));

                return $created;
            });

            if (! $machineType) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $machineType->load('services');

            return $this->successResponse(
                'Machine type created successfully.',
                new MachineTypeResource($machineType),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\MachineTypesController@store');
        }
    }

    /**
     * GET /api/machine_types/{machineType}
     */
    public function show(MachineType $machineType): JsonResponse
    {
        try {
            if (! Gate::allows('machineType_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($machineType)) {
                return $this->errorResponse('Machine type not found.', 404);
            }

            $machineType->load('services');

            return $this->successResponse(
                'Machine type retrieved successfully.',
                new MachineTypeResource($machineType),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\MachineTypesController@show');
        }
    }

    /**
     * PATCH /api/machine_types/{machineType}
     *
     * Services are fully replaced on every update (old pivot rows are
     * deleted before new ones are inserted) — same behaviour as legacy.
     * Wrapped in a transaction.
     */
    public function update(UpdateMachineTypeRequest $request, MachineType $machineType): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($machineType)) {
                return $this->errorResponse('Machine type not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;

            $updated = DB::transaction(function () use ($request, $machineType, $accountId): ?MachineType {
                $record = MachineType::updateRecord((int) $machineType->id, $request, $accountId);

                if (! $record) {
                    return null;
                }

                $record->machinetype_has_services()->delete();
                $this->syncServices($record, (array) $request->input('services', []));

                return $record;
            });

            if (! $updated) {
                return $this->errorResponse('Machine type not found.', 404);
            }

            $updated->load('services');

            return $this->successResponse(
                'Machine type updated successfully.',
                new MachineTypeResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\MachineTypesController@update');
        }
    }

    /**
     * PATCH /api/machine_types/{machineType}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced
     * in the Form Request — `machineType_active` vs `machineType_inactive`.
     */
    public function status(MachineTypeStatusRequest $request, MachineType $machineType): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($machineType)) {
                return $this->errorResponse('Machine type not found.', 404);
            }

            $statusValue = (int) $request->validated()['status'];

            $result = $statusValue === 1
                ? MachineType::activeRecord((int) $machineType->id)
                : MachineType::inactiveRecord((int) $machineType->id);

            if (! $result->get('status')) {
                return $this->errorResponse($result->get('message'), 404);
            }

            return $this->successResponse(
                $result->get('message'),
                new MachineTypeResource($machineType->fresh()->load('services')),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\MachineTypesController@status');
        }
    }

    /**
     * DELETE /api/machine_types/{machineType}
     *
     * Soft-delete. Returns **409** when the machine type has linked
     * Resources — mirrors `MachineType::isChildExists`.
     */
    public function destroy(MachineType $machineType): JsonResponse
    {
        try {
            if (! Gate::allows('machineType_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($machineType)) {
                return $this->errorResponse('Machine type not found.', 404);
            }

            $result = MachineType::deleteRecord((int) $machineType->id);

            if (! $result->get('status')) {
                $message = strtolower((string) $result->get('message'));
                $status = str_contains($message, 'child') ? 409 : 404;

                return $this->errorResponse($result->get('message'), $status);
            }

            return $this->successResponse($result->get('message'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\MachineTypesController@destroy');
        }
    }

    // ── internal helpers ──

    /**
     * Attach leaf-level services to the machine type. Parent services whose
     * children are also in the list are removed (preserves legacy
     * `array_diff($services, $childServices)` behaviour).
     *
     * @param  list<int>  $serviceIds
     */
    private function syncServices(MachineType $machineType, array $serviceIds): void
    {
        $serviceIds = array_values(array_filter(array_map('intval', $serviceIds)));

        if (empty($serviceIds)) {
            return;
        }

        $childServices = Services::whereIn('parent_id', $serviceIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $filtered = array_values(array_diff($serviceIds, $childServices));

        if (empty($filtered)) {
            return;
        }

        $rows = array_map(fn (int $serviceId): array => [
            'machine_type_id' => $machineType->id,
            'service_id' => $serviceId,
        ], $filtered);

        MachineTypeHasServices::createRecord($rows, $machineType);
    }

    private function ownedByCaller(MachineType $machineType): bool
    {
        return (int) $machineType->account_id === (int) Auth::user()->account_id;
    }
}
