<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTownRequest;
use App\Http\Requests\Admin\TownStatusRequest;
use App\Http\Requests\Admin\UpdateTownRequest;
use App\Http\Resources\Town\TownResource;
use App\Models\Towns;
use App\Services\Town\TownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TownsController extends Controller
{
    public function __construct(
        private readonly TownService $service,
    ) {}

    /**
     * GET /api/towns
     *
     * Paginated account-scoped list. Callers without `view_inactive_towns`
     * see active rows only — mirrors the legacy datatable behaviour.
     *
     * Permission: `towns_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('towns_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'city_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Towns::query()
                ->with(['city:id,name'])
                ->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_towns')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('city_id')) {
                $query->where('city_id', (int) $request->integer('city_id'));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('name')->paginate($perPage);

            return $this->successResponse(
                'Towns retrieved successfully.',
                [
                    'items' => TownResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\TownsController@index');
        }
    }

    /**
     * GET /api/towns/dropdown
     *
     * Active towns as `{ id, name, city_id }[]` ordered by name — the shape
     * mobile pickers expect. Optional `city_id` filter.
     */
    public function dropdown(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('towns_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'city_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Towns::query()
                ->where('account_id', $accountId)
                ->where('active', 1);

            if ($request->filled('city_id')) {
                $query->where('city_id', (int) $request->integer('city_id'));
            }

            $towns = $query->orderBy('name')->get(['id', 'name', 'city_id']);

            return $this->successResponse(
                'Towns dropdown retrieved successfully.',
                $towns->map(fn (Towns $t) => [
                    'id' => (int) $t->id,
                    'name' => (string) $t->name,
                    'city_id' => $t->city_id !== null ? (int) $t->city_id : null,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\TownsController@dropdown');
        }
    }

    /**
     * POST /api/towns/create
     */
    public function store(StoreTownRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $request->merge([
                'active' => array_key_exists('active', $validated) ? (int) $validated['active'] : 1,
            ]);

            $record = Towns::createRecord($request, $accountId);

            if (! $record) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $record->load('city:id,name');

            return $this->successResponse(
                'Town created successfully.',
                new TownResource($record),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\TownsController@store');
        }
    }

    /**
     * GET /api/towns/{town}
     */
    public function show(Towns $town): JsonResponse
    {
        try {
            if (! Gate::allows('towns_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($town)) {
                return $this->errorResponse('Town not found.', 404);
            }

            $town->load('city:id,name');

            return $this->successResponse(
                'Town retrieved successfully.',
                new TownResource($town),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\TownsController@show');
        }
    }

    /**
     * PATCH /api/towns/{town}
     */
    public function update(UpdateTownRequest $request, Towns $town): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($town)) {
                return $this->errorResponse('Town not found.', 404);
            }

            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $request->merge([
                'active' => array_key_exists('active', $validated)
                    ? (int) $validated['active']
                    : (int) $town->active,
            ]);

            $updated = Towns::updateRecord((int) $town->id, $request, $accountId);

            if (! $updated) {
                return $this->errorResponse('Town not found.', 404);
            }

            $updated->load('city:id,name');

            return $this->successResponse(
                'Town updated successfully.',
                new TownResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\TownsController@update');
        }
    }

    /**
     * PATCH /api/towns/{town}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced in
     * the Form Request — `towns_active` vs `towns_inactive`.
     */
    public function status(TownStatusRequest $request, Towns $town): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($town)) {
                return $this->errorResponse('Town not found.', 404);
            }

            $ok = $this->service->toggleStatus((int) $town->id, (int) $request->validated()['status']);

            if (! $ok) {
                return $this->errorResponse('Town not found.', 404);
            }

            return $this->successResponse(
                'Status has been changed successfully.',
                new TownResource($town->fresh()->load('city:id,name')),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\TownsController@status');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Towns $town): bool
    {
        return (int) $town->account_id === (int) Auth::user()->account_id;
    }
}
