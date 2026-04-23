<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\LeadStatusStatusRequest;
use App\Http\Requests\Lead\SaveLeadStatusRecordRequest;
use App\Http\Requests\Lead\SortLeadStatusesRequest;
use App\Http\Resources\Lead\LeadStatusApiResource;
use App\Models\LeadStatuses;
use App\Services\Lead\LeadStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeadStatusesController extends Controller
{
    public function __construct(
        private readonly LeadStatusService $service,
    ) {}

    /**
     * GET /api/lead_statuses
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_leadstatuses` see active rows only. Supports filtering
     * by `parent_id` (pass `0` for top-level parents only).
     *
     * Permission: `lead_statuses_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('lead_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'parent_id' => ['nullable', 'integer', 'min:0'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = LeadStatuses::query()->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_leadstatuses')) {
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

            LeadStatusApiResource::$allStatuses = LeadStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Lead statuses retrieved successfully.',
                [
                    'items' => LeadStatusApiResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\LeadStatusesController@index');
        }
    }

    /**
     * GET /api/lead_statuses/dropdown
     *
     * Active lead statuses as `{ id, name, parent_id }[]` ordered by
     * `sort_no` — the shape mobile pickers expect.
     */
    public function dropdown(): JsonResponse
    {
        try {
            if (! Gate::allows('lead_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $statuses = LeadStatuses::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('sort_no')
                ->get(['id', 'name', 'parent_id']);

            return $this->successResponse(
                'Lead statuses dropdown retrieved successfully.',
                $statuses->map(fn (LeadStatuses $s): array => [
                    'id' => (int) $s->id,
                    'name' => (string) $s->name,
                    'parent_id' => $s->parent_id ? (int) $s->parent_id : null,
                ])->values(),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@dropdown');
        }
    }

    /**
     * GET /api/lead_statuses/parents
     *
     * Active top-level statuses (parent_id IS NULL or 0) as
     * `{ id, name }[]` — used by create/edit forms for the "Parent Group"
     * picker. Optional `skip_id` excludes the status being edited so it
     * can't become its own parent.
     */
    public function parents(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('lead_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'skip_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $skipIds = $request->filled('skip_id') ? [(int) $request->integer('skip_id')] : [];

            $parents = $this->service->getParentRecords($accountId, $skipIds);

            return $this->successResponse(
                'Parent lead statuses retrieved successfully.',
                $parents->map(fn (string $name, int|string $id): array => [
                    'id' => (int) $id,
                    'name' => (string) $name,
                ])->values(),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@parents');
        }
    }

    /**
     * GET /api/lead_statuses/sorted
     *
     * Full list in sort order — used by the drag-and-drop reorder UI.
     */
    public function sorted(): JsonResponse
    {
        try {
            if (! Gate::allows('lead_statuses_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;
            $records = $this->service->getSortableRecords($accountId);

            LeadStatusApiResource::$allStatuses = LeadStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Sorted lead statuses retrieved successfully.',
                LeadStatusApiResource::collection($records),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@sorted');
        }
    }

    /**
     * POST /api/lead_statuses/sort
     *
     * Bulk reorder. Body: `{ "item_ids": [3, 1, 2] }` — the resulting
     * `sort_no` is the array index.
     */
    public function sort(SortLeadStatusesRequest $request): JsonResponse
    {
        try {
            $this->service->saveSortOrder($request->validated()['item_ids']);

            return $this->successResponse('Lead statuses sorted successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@sort');
        }
    }

    /**
     * POST /api/lead_statuses/create
     *
     * Note: setting any of `is_default`, `is_arrived`, `is_converted`,
     * `is_junk`, or `is_booked` to 1 will atomically clear the flag on all
     * other statuses in the account (enforced by `LeadStatusService`).
     */
    public function store(SaveLeadStatusRecordRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;
            $record = $this->service->create($request->validated(), $accountId);

            LeadStatusApiResource::$allStatuses = LeadStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Lead status created successfully.',
                new LeadStatusApiResource($record),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@store');
        }
    }

    /**
     * GET /api/lead_statuses/{leadStatus}
     */
    public function show(LeadStatuses $leadStatus): JsonResponse
    {
        try {
            if (! Gate::allows('lead_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($leadStatus)) {
                return $this->errorResponse('Lead status not found.', 404);
            }

            LeadStatusApiResource::$allStatuses = LeadStatuses::getAllRecordsDictionary(
                (int) Auth::user()->account_id,
            );

            return $this->successResponse(
                'Lead status retrieved successfully.',
                new LeadStatusApiResource($leadStatus),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@show');
        }
    }

    /**
     * PATCH /api/lead_statuses/{leadStatus}
     */
    public function update(SaveLeadStatusRecordRequest $request, LeadStatuses $leadStatus): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($leadStatus)) {
                return $this->errorResponse('Lead status not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;
            $updated = $this->service->update(
                (int) $leadStatus->id,
                $request->validated(),
                $accountId,
            );

            if (! $updated) {
                return $this->errorResponse('Lead status not found.', 404);
            }

            LeadStatusApiResource::$allStatuses = LeadStatuses::getAllRecordsDictionary($accountId);

            return $this->successResponse(
                'Lead status updated successfully.',
                new LeadStatusApiResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@update');
        }
    }

    /**
     * PATCH /api/lead_statuses/{leadStatus}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced
     * in the Form Request — `lead_statuses_active` vs `lead_statuses_inactive`.
     */
    public function status(LeadStatusStatusRequest $request, LeadStatuses $leadStatus): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($leadStatus)) {
                return $this->errorResponse('Lead status not found.', 404);
            }

            $result = $this->service->toggleStatus(
                (int) $leadStatus->id,
                (int) $request->validated()['status'],
            );

            if (! $result['status']) {
                return $this->errorResponse($result['message'], 404);
            }

            LeadStatusApiResource::$allStatuses = LeadStatuses::getAllRecordsDictionary(
                (int) Auth::user()->account_id,
            );

            return $this->successResponse(
                $result['message'],
                new LeadStatusApiResource($leadStatus->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@status');
        }
    }

    /**
     * DELETE /api/lead_statuses/{leadStatus}
     *
     * Soft-delete. Returns **409** when the status has child statuses —
     * mirrors `LeadStatuses::isChildExists`.
     */
    public function destroy(LeadStatuses $leadStatus): JsonResponse
    {
        try {
            if (! Gate::allows('lead_statuses_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($leadStatus)) {
                return $this->errorResponse('Lead status not found.', 404);
            }

            $result = $this->service->delete((int) $leadStatus->id);

            if (! $result['status']) {
                $message = strtolower((string) $result['message']);
                $status = str_contains($message, 'child') ? 409 : 404;

                return $this->errorResponse($result['message'], $status);
            }

            return $this->successResponse($result['message']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadStatusesController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(LeadStatuses $leadStatus): bool
    {
        return (int) $leadStatus->account_id === (int) Auth::user()->account_id;
    }
}
