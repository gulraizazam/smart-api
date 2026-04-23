<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\LeadSourceStatusRequest;
use App\Http\Requests\Lead\SortLeadSourcesRequest;
use App\Http\Requests\Lead\StoreLeadSourceRequest;
use App\Http\Requests\Lead\UpdateLeadSourceRequest;
use App\Http\Resources\Lead\LeadSourceResource;
use App\Models\LeadSources;
use App\Services\Lead\LeadSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeadSourcesController extends Controller
{
    public function __construct(
        private readonly LeadSourceService $service,
    ) {}

    /**
     * GET /api/lead_sources
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_leadsources` see active rows only — mirrors the legacy
     * datatable behaviour.
     *
     * Permission: `lead_sources_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('lead_sources_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = LeadSources::query()->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_leadsources')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('sort_no')->paginate($perPage);

            return $this->successResponse(
                'Lead sources retrieved successfully.',
                [
                    'items' => LeadSourceResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\LeadSourcesController@index');
        }
    }

    /**
     * GET /api/lead_sources/dropdown
     *
     * Active lead sources as `{ id, name }[]` ordered by `sort_no` — the
     * shape mobile pickers expect.
     */
    public function dropdown(): JsonResponse
    {
        try {
            if (! Gate::allows('lead_sources_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $sources = LeadSources::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('sort_no')
                ->get(['id', 'name']);

            return $this->successResponse(
                'Lead sources dropdown retrieved successfully.',
                $sources->map(fn (LeadSources $s): array => [
                    'id' => (int) $s->id,
                    'name' => (string) $s->name,
                ])->values(),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@dropdown');
        }
    }

    /**
     * GET /api/lead_sources/sorted
     *
     * Full list in sort order — used by the drag-and-drop reorder UI.
     */
    public function sorted(): JsonResponse
    {
        try {
            if (! Gate::allows('lead_sources_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $records = $this->service->getSortableRecords((int) Auth::user()->account_id);

            return $this->successResponse(
                'Sorted lead sources retrieved successfully.',
                LeadSourceResource::collection($records),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@sorted');
        }
    }

    /**
     * POST /api/lead_sources/sort
     *
     * Bulk reorder. Body: `{ "item_ids": [3, 1, 2] }` — the resulting
     * `sort_no` is the array index.
     */
    public function sort(SortLeadSourcesRequest $request): JsonResponse
    {
        try {
            $this->service->saveSortOrder($request->validated()['item_ids']);

            return $this->successResponse('Lead sources sorted successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@sort');
        }
    }

    /**
     * POST /api/lead_sources/create
     */
    public function store(StoreLeadSourceRequest $request): JsonResponse
    {
        try {
            $record = $this->service->create($request->validated(), (int) Auth::user()->account_id);

            return $this->successResponse(
                'Lead source created successfully.',
                new LeadSourceResource($record),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@store');
        }
    }

    /**
     * GET /api/lead_sources/{leadSource}
     */
    public function show(LeadSources $leadSource): JsonResponse
    {
        try {
            if (! Gate::allows('lead_sources_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($leadSource)) {
                return $this->errorResponse('Lead source not found.', 404);
            }

            return $this->successResponse(
                'Lead source retrieved successfully.',
                new LeadSourceResource($leadSource),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@show');
        }
    }

    /**
     * PATCH /api/lead_sources/{leadSource}
     */
    public function update(UpdateLeadSourceRequest $request, LeadSources $leadSource): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($leadSource)) {
                return $this->errorResponse('Lead source not found.', 404);
            }

            $updated = $this->service->update(
                (int) $leadSource->id,
                $request->validated(),
                (int) Auth::user()->account_id,
            );

            if (! $updated) {
                return $this->errorResponse('Lead source not found.', 404);
            }

            return $this->successResponse(
                'Lead source updated successfully.',
                new LeadSourceResource($updated),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@update');
        }
    }

    /**
     * PATCH /api/lead_sources/{leadSource}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates are enforced
     * in the Form Request — `lead_sources_active` vs `lead_sources_inactive`.
     */
    public function status(LeadSourceStatusRequest $request, LeadSources $leadSource): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($leadSource)) {
                return $this->errorResponse('Lead source not found.', 404);
            }

            $result = $this->service->toggleStatus(
                (int) $leadSource->id,
                (int) $request->validated()['status'],
            );

            if (! $result['status']) {
                return $this->errorResponse($result['message'], 404);
            }

            return $this->successResponse(
                $result['message'],
                new LeadSourceResource($leadSource->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@status');
        }
    }

    /**
     * DELETE /api/lead_sources/{leadSource}
     *
     * Soft-delete. Returns **409** when the lead source has linked Leads —
     * mirrors `LeadSources::isChildExists`.
     */
    public function destroy(LeadSources $leadSource): JsonResponse
    {
        try {
            if (! Gate::allows('lead_sources_destroy')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($leadSource)) {
                return $this->errorResponse('Lead source not found.', 404);
            }

            $result = $this->service->delete((int) $leadSource->id);

            if (! $result['status']) {
                $message = strtolower((string) $result['message']);
                $status = str_contains($message, 'child') ? 409 : 404;

                return $this->errorResponse($result['message'], $status);
            }

            return $this->successResponse($result['message']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadSourcesController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(LeadSources $leadSource): bool
    {
        return (int) $leadSource->account_id === (int) Auth::user()->account_id;
    }
}
