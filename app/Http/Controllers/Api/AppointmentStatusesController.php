<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppointmentStatusStatusRequest;
use App\Http\Requests\Admin\SaveAppointmentStatusRequest;
use App\Http\Resources\AppointmentStatus\AppointmentStatusApiResource;
use App\Models\AppointmentStatuses;
use App\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AppointmentStatusesController extends Controller
{
    public function __construct(
        private readonly AppointmentStatusService $service,
    ) {}

    /**
     * GET /api/appointment_statuses
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_appointmentstatuses` see active rows only. Supports
     * filtering by `parent_id` (pass `0` for top-level parents only).
     *
     * Permission: `appointment_statuses_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'parent_id' => ['nullable', 'integer', 'min:0'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = AppointmentStatuses::query()->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_appointmentstatuses')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->has('parent_id')) {
                $parentId = (int) $request->integer('parent_id');
                $query->when(
                    $parentId === 0,
                    fn ($q) => $q->where(fn ($inner) => $inner->whereNull('parent_id')->orWhere('parent_id', 0)),
                    fn ($q) => $q->where('parent_id', $parentId),
                );
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('sort_no')->paginate($perPage);

            AppointmentStatusApiResource::$allStatuses = AppointmentStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Appointment statuses retrieved successfully.',
                [
                    'items' => AppointmentStatusApiResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\AppointmentStatusesController@index');
        }
    }

    /**
     * GET /api/appointment_statuses/dropdown
     *
     * Active appointment statuses as `{ id, name, parent_id }[]` ordered by
     * `sort_no` — the shape mobile pickers expect. Optional `parent_id`
     * filter (pass a specific parent to get its children).
     */
    public function dropdown(Request $request): JsonResponse
    {
        try {
            // No permission gate: this is a read-only reference lookup that
            // powers the status FILTER on the treatments/consultations lists.
            // Gating it on `appointment_statuses_manage` (a Settings-manage
            // permission) wrongly hid the filter from list-viewing roles
            // (e.g. Feedback) that can't manage statuses. Filtering a list you
            // can already view needs no extra permission; results stay
            // account-scoped below. The CRUD methods keep their manage gate.
            $request->validate([
                'parent_id' => ['nullable', 'integer', 'min:0'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = AppointmentStatuses::query()
                ->where('account_id', $accountId)
                ->where('active', 1);

            if ($request->has('parent_id')) {
                $parentId = (int) $request->integer('parent_id');
                $query->when(
                    $parentId === 0,
                    fn ($q) => $q->where(fn ($inner) => $inner->whereNull('parent_id')->orWhere('parent_id', 0)),
                    fn ($q) => $q->where('parent_id', $parentId),
                );
            }

            // `is_*` flags are surfaced so SPA filter pills can hide
            // automation-only statuses (Arrived → comes from invoice
            // payment, Converted → comes from package payment,
            // Un-Scheduled → derived from missing scheduled_date+time).
            // Same flag shape `AppointmentStatusApiResource` already
            // uses everywhere else — keeps the SPA's dropdown vs row
            // sources aligned.
            $statuses = $query->orderBy('sort_no')->get([
                'id', 'name', 'parent_id',
                'is_arrived', 'is_converted', 'is_unscheduled', 'is_cancelled',
            ]);

            return $this->successResponse(
                'Appointment statuses dropdown retrieved successfully.',
                $statuses->map(fn (AppointmentStatuses $s): array => [
                    'id' => (int) $s->id,
                    'name' => (string) $s->name,
                    'parent_id' => $s->parent_id ? (int) $s->parent_id : null,
                    'is_arrived' => (bool) $s->is_arrived,
                    'is_converted' => (bool) $s->is_converted,
                    'is_unscheduled' => (bool) $s->is_unscheduled,
                    'is_cancelled' => (bool) $s->is_cancelled,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@dropdown');
        }
    }

    /**
     * GET /api/appointment_statuses/parents
     *
     * Active top-level statuses (parent_id IS NULL or 0) as
     * `{ id, name }[]` — used by create/edit forms for the "Parent Group"
     * picker. Optional `skip_id` excludes the status being edited so it
     * can't become its own parent.
     */
    public function parents(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'skip_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $skipIds = $request->filled('skip_id') ? [(int) $request->integer('skip_id')] : [];

            $parents = AppointmentStatuses::getParentRecords(false, $accountId, $skipIds, true);

            return $this->successResponse(
                'Parent appointment statuses retrieved successfully.',
                $parents->map(fn (string $name, int|string $id): array => [
                    'id' => (int) $id,
                    'name' => (string) $name,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@parents');
        }
    }

    /**
     * POST /api/appointment_statuses/create
     *
     * Note: setting any of `is_default`, `is_arrived`, `is_cancelled`,
     * `is_unscheduled`, or `is_converted` to 1 will atomically clear that
     * flag on every other status in the account (enforced by
     * `AppointmentStatuses::createRecord`).
     */
    public function store(SaveAppointmentStatusRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            if (! $this->service->create($request, $accountId)) {
                return $this->errorResponse('Something went wrong, please try again later.', 500);
            }

            $record = AppointmentStatuses::where('account_id', $accountId)
                ->orderByDesc('id')
                ->first();

            AppointmentStatusApiResource::$allStatuses = AppointmentStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Appointment status created successfully.',
                $record ? new AppointmentStatusApiResource($record) : null,
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@store');
        }
    }

    /**
     * GET /api/appointment_statuses/{appointmentStatus}
     */
    public function show(AppointmentStatuses $appointmentStatus): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($appointmentStatus)) {
                return $this->errorResponse('Appointment status not found.', 404);
            }

            AppointmentStatusApiResource::$allStatuses = AppointmentStatuses::getAllRecordsDictionary(
                (int) Auth::user()->account_id,
            );

            return $this->successResponse(
                'Appointment status retrieved successfully.',
                new AppointmentStatusApiResource($appointmentStatus),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@show');
        }
    }

    /**
     * PATCH /api/appointment_statuses/{appointmentStatus}
     */
    public function update(SaveAppointmentStatusRequest $request, AppointmentStatuses $appointmentStatus): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($appointmentStatus)) {
                return $this->errorResponse('Appointment status not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;

            if (! $this->service->update((int) $appointmentStatus->id, $request, $accountId)) {
                return $this->errorResponse('Appointment status not found.', 404);
            }

            AppointmentStatusApiResource::$allStatuses = AppointmentStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Appointment status updated successfully.',
                new AppointmentStatusApiResource($appointmentStatus->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@update');
        }
    }

    /**
     * PATCH /api/appointment_statuses/{appointmentStatus}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced
     * in the Form Request — `appointment_statuses_active` vs
     * `appointment_statuses_inactive`.
     */
    public function status(AppointmentStatusStatusRequest $request, AppointmentStatuses $appointmentStatus): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($appointmentStatus)) {
                return $this->errorResponse('Appointment status not found.', 404);
            }

            $result = $this->service->toggleStatus(
                (int) $appointmentStatus->id,
                (int) $request->validated()['status'],
            );

            if (! $result->get('status')) {
                return $this->errorResponse($result->get('message'), 404);
            }

            AppointmentStatusApiResource::$allStatuses = AppointmentStatuses::getAllRecordsDictionary(
                (int) Auth::user()->account_id,
            );

            return $this->successResponse(
                $result->get('message'),
                new AppointmentStatusApiResource($appointmentStatus->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@status');
        }
    }

    /**
     * DELETE /api/appointment_statuses/{appointmentStatus}
     *
     * Soft-delete. Returns **409** when the status has child records —
     * mirrors `AppointmentStatuses::isChildExists`.
     */
    public function destroy(AppointmentStatuses $appointmentStatus): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($appointmentStatus)) {
                return $this->errorResponse('Appointment status not found.', 404);
            }

            $result = $this->service->delete((int) $appointmentStatus->id);

            if (! $result->get('status')) {
                $message = strtolower((string) $result->get('message'));
                $status = str_contains($message, 'child') ? 409 : 404;

                return $this->errorResponse($result->get('message'), $status);
            }

            return $this->successResponse($result->get('message'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\AppointmentStatusesController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(AppointmentStatuses $appointmentStatus): bool
    {
        return (int) $appointmentStatus->account_id === (int) Auth::user()->account_id;
    }
}
