<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoadCentreTargetCentresRequest;
use App\Http\Requests\Admin\StoreCentreTargetRequest;
use App\Http\Requests\Admin\UpdateCentreTargetRequest;
use App\Http\Resources\CentreTarget\CentreTargetResource;
use App\Models\Centertarget;
use App\Services\CentreTarget\CentreTargetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CentreTargetsController extends Controller
{
    public function __construct(
        private readonly CentreTargetService $service,
    ) {}

    /**
     * GET /api/centre_targets
     *
     * Paginated account-scoped list. Supports `year` / `month` filters.
     *
     * Permission: `centre_targets_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('centre_targets_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'year' => ['nullable', 'integer', 'between:2000,2100'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Centertarget::query()
                ->with(['center_target_meta'])
                ->where('account_id', $accountId);

            if ($request->filled('year')) {
                $query->where('year', (int) $request->integer('year'));
            }

            if ($request->filled('month')) {
                $query->where('month', (int) $request->integer('month'));
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('year')
                ->orderByDesc('month')
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return $this->successResponse(
                'Centre targets retrieved successfully.',
                [
                    'items' => CentreTargetResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\CentreTargetsController@index');
        }
    }

    /**
     * GET /api/centre_targets/form-data
     *
     * Picker data for the create/edit screens: month list (1–12 with
     * names) and the last 10 years. Mirrors legacy `getCreateData`.
     */
    public function formData(): JsonResponse
    {
        try {
            if (! Gate::allows('centre_targets_create') && ! Gate::allows('centre_targets_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $monthsMap = Config::get('constants.months_array', []);
            $months = [];
            foreach ($monthsMap as $key => $value) {
                $months[] = [
                    'id' => (int) $key,
                    'name' => (string) $value,
                ];
            }

            $currentYear = Carbon::now()->year;
            $years = [];
            for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                $years[] = $y;
            }

            return $this->successResponse('Centre target form data retrieved successfully.', [
                'months' => $months,
                'years' => $years,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentreTargetsController@formData');
        }
    }

    /**
     * POST /api/centre_targets/centres
     *
     * Given `{month, year}`, returns the centre list with their currently
     * saved target amounts (mirrors legacy `leadtargetcentre` /
     * `Locations::LoadtargetLocationdata`). Used by the create/edit UI to
     * render the per-centre amount inputs.
     */
    public function loadCentres(LoadCentreTargetCentresRequest $request): JsonResponse
    {
        try {
            $req = new Request($request->validated());
            $data = $this->service->loadTargetCentre($req);

            return $this->successResponse(
                'Centres retrieved successfully.',
                $data,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentreTargetsController@loadCentres');
        }
    }

    /**
     * POST /api/centre_targets/create
     *
     * Creates or upserts a centre target for the given `month`+`year`.
     * `target_amount` is a map keyed by centre/location id — same shape
     * the legacy form submits. Wrapped in a transaction so a half-written
     * record + meta pair can't be left behind on failure.
     */
    public function store(StoreCentreTargetRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            $record = DB::transaction(function () use ($request, $accountId): ?Centertarget {
                $req = new Request($request->validated());

                $existing = Centertarget::where([
                    'month' => $request->input('month'),
                    'year' => $request->input('year'),
                    'account_id' => $accountId,
                ])->first();

                return $existing
                    ? Centertarget::updateRecord($existing->id, $req, $accountId)
                    : Centertarget::createRecord($req, $accountId);
            });

            if (! $record) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $record->load(['center_target_meta.location']);

            return $this->successResponse(
                'Centre target saved successfully.',
                new CentreTargetResource($record),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentreTargetsController@store');
        }
    }

    /**
     * GET /api/centre_targets/{centreTarget}
     *
     * Returns the centre target with its per-centre meta rows (location
     * + saved amount).
     */
    public function show(Centertarget $centreTarget): JsonResponse
    {
        try {
            if (! Gate::allows('centre_targets_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($centreTarget)) {
                return $this->errorResponse('Centre target not found.', 404);
            }

            $centreTarget->load(['center_target_meta.location']);

            return $this->successResponse(
                'Centre target retrieved successfully.',
                new CentreTargetResource($centreTarget),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentreTargetsController@show');
        }
    }

    /**
     * PATCH /api/centre_targets/{centreTarget}
     *
     * Wrapped in a transaction so the parent row + per-location meta
     * writes are atomic.
     */
    public function update(UpdateCentreTargetRequest $request, Centertarget $centreTarget): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($centreTarget)) {
                return $this->errorResponse('Centre target not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;

            $updated = DB::transaction(function () use ($request, $centreTarget, $accountId): ?Centertarget {
                $req = new Request($request->validated());

                return Centertarget::updateRecord((int) $centreTarget->id, $req, $accountId);
            });

            if (! $updated) {
                return $this->errorResponse('Centre target not found.', 404);
            }

            $updated->load(['center_target_meta.location']);

            return $this->successResponse(
                'Centre target updated successfully.',
                new CentreTargetResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentreTargetsController@update');
        }
    }

    /**
     * DELETE /api/centre_targets/{centreTarget}
     *
     * Soft-deletes the parent target and cascades a soft-delete onto
     * `center_target_meta` — same behaviour as legacy `deleteRecord`.
     */
    public function destroy(Centertarget $centreTarget): JsonResponse
    {
        try {
            if (! Gate::allows('centre_targets_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($centreTarget)) {
                return $this->errorResponse('Centre target not found.', 404);
            }

            DB::transaction(function () use ($centreTarget): void {
                $this->service->deleteRecord((int) $centreTarget->id);
            });

            return $this->successResponse('Centre target deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\CentreTargetsController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Centertarget $centreTarget): bool
    {
        return (int) $centreTarget->account_id === (int) Auth::user()->account_id;
    }
}
