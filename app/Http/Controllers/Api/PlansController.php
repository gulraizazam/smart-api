<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\PlanException;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanDatatableRequest;
use App\Http\Resources\Plan\PlanDatatableCollection;
use App\Http\Resources\Plan\PlanStatisticsResource;
use App\Services\Plan\PlanService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class PlansController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    // ── Patient-scoped datatable ────────────────────────

    public function datatable(PlanDatatableRequest $request, int|string $patientId): JsonResponse
    {
        try {
            if ($this->isBulkDelete($request)) {
                return $this->handleBulkDeleteResponse($request);
            }

            $this->handleFilterCancellation($request, 'patient_packages');

            $filters = $request->filters();
            $datatableData = $this->planService->getDatatableData($filters, (int) $patientId);

            $pagination = $request->paginationParams($datatableData['total']);
            $packages = $this->executePaginatedQuery($datatableData, $pagination);

            return $this->buildDatatableResponse(
                packages: $packages,
                datatableData: $datatableData,
                pagination: $pagination,
                filters: $filters,
                permissions: $this->patientPermissions(),
            );
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            Log::error('Plans Datatable Error', [
                'message'    => $e->getMessage(),
                'patient_id' => $patientId,
                'trace'      => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('An error occurred while fetching plans data.', 500);
        }
    }

    // ── Patient-scoped lookup data ──────────────────────

    public function getLookupData(int|string $patientId): JsonResponse
    {
        if (Gate::denies('plans_manage')) {
            return $this->unauthorizedResponse();
        }

        try {
            return $this->successResponse('Success', $this->planService->getLookupData($patientId));
        } catch (\Throwable $e) {
            Log::error('Plans Lookup Data Error: ' . $e->getMessage());

            return $this->errorResponse('An error occurred while fetching lookup data.', 500);
        }
    }

    // ── Patient statistics ──────────────────────────────

    public function getStatistics(int|string $patientId): JsonResponse
    {
        if (Gate::denies('plans_manage')) {
            return $this->unauthorizedResponse();
        }

        try {
            $stats = $this->planService->getPatientStatistics($patientId);

            return $this->successResponse('Success', new PlanStatisticsResource($stats));
        } catch (\Throwable $e) {
            Log::error('Plans Statistics Error: ' . $e->getMessage());

            return $this->errorResponse('An error occurred while fetching statistics.', 500);
        }
    }

    // ── Global datatable (admin packages page) ──────────

    public function globalDatatable(PlanDatatableRequest $request): JsonResponse
    {
        try {
            if ($this->isGlobalBulkDelete($request)) {
                return $this->handleGlobalBulkDeleteResponse($request);
            }

            $this->handleFilterCancellation($request, 'packages');

            $filters = $request->filters();
            $datatableData = $this->planService->getGlobalDatatableData($filters);

            $pagination = $request->paginationParams($datatableData['total']);
            $packages = $this->executePaginatedQuery($datatableData, $pagination);

            return $this->buildDatatableResponse(
                packages: $packages,
                datatableData: $datatableData,
                pagination: $pagination,
                filters: $filters,
                permissions: $this->globalPermissions(),
            );
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            Log::error('Global Plans Datatable Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('An error occurred while fetching plans data.', 500);
        }
    }

    // ── Global lookup data ──────────────────────────────

    public function getGlobalLookupData(): JsonResponse
    {
        if (Gate::denies('plans_manage')) {
            return $this->unauthorizedResponse();
        }

        try {
            return $this->successResponse('Success', $this->planService->getGlobalLookupData());
        } catch (\Throwable $e) {
            Log::error('Global Plans Lookup Data Error: ' . $e->getMessage());

            return $this->errorResponse('An error occurred while fetching lookup data.', 500);
        }
    }

    // ── Private helpers ─────────────────────────────────

    private function isBulkDelete(PlanDatatableRequest $request): bool
    {
        return $request->input('customActionType') === 'group_action'
            && $request->has('id');
    }

    private function isGlobalBulkDelete(PlanDatatableRequest $request): bool
    {
        return $request->filled('delete');
    }

    private function handleBulkDeleteResponse(PlanDatatableRequest $request): JsonResponse
    {
        $result = $this->planService->handleBulkDelete($request->input('id', []));

        return response()->json([
            'success'             => $result['deleted'] > 0,
            'message'             => $result['message'],
            'data'                => null,
            'errors'              => [],
            'customActionStatus'  => 'OK',
            'customActionMessage' => $result['message'],
            'draw'                => (int) $request->input('draw'),
            'recordsTotal'        => 0,
            'recordsFiltered'     => 0,
        ]);
    }

    private function handleGlobalBulkDeleteResponse(PlanDatatableRequest $request): JsonResponse
    {
        $deleteInput = $request->input('delete', '');
        $ids = array_filter(
            array_map('intval', explode(',', $deleteInput)),
            static fn (int $id): bool => $id > 0,
        );

        $result = $this->planService->handleBulkDelete($ids);

        return response()->json([
            'success' => $result['deleted'] > 0,
            'message' => $result['message'],
            'data'    => null,
            'errors'  => [],
        ]);
    }

    private function handleFilterCancellation(PlanDatatableRequest $request, string $filterKey): void
    {
        $action = $request->input('action');

        if (is_array($action) && ($action[0] ?? null) === 'filter_cancel') {
            Filters::flush(Auth::id(), $filterKey);
        }
    }

    private function executePaginatedQuery(array $datatableData, array $pagination): Collection
    {
        return $datatableData['query']
            ->orderBy($datatableData['orderBy'], $datatableData['order'])
            ->offset($pagination['start'])
            ->limit($pagination['perpage'])
            ->get();
    }

    private function buildDatatableResponse(
        Collection $packages,
        array $datatableData,
        array $pagination,
        array $filters,
        array $permissions,
    ): JsonResponse {
        $totalPages = $pagination['perpage'] > 0
            ? (int) ceil($datatableData['total'] / $pagination['perpage'])
            : 1;

        $collection = new PlanDatatableCollection($packages);
        $collection->setContext(
            meta: [
                'page'    => $pagination['page'],
                'pages'   => $totalPages,
                'perpage' => $pagination['perpage'],
                'total'   => $datatableData['total'],
                'sort'    => strtolower($datatableData['order']),
                'field'   => $datatableData['orderBy'],
            ],
            permissions: $permissions,
            filterValues: $datatableData['filter_values'] ?? [],
            activeFilters: $filters,
        );

        return response()->json($collection);
    }

    private function patientPermissions(): array
    {
        return [
            'edit'                    => Gate::allows('plans_edit'),
            'delete'                  => Gate::allows('plans_destroy'),
            'active'                  => Gate::allows('plans_active'),
            'inactive'                => Gate::allows('plans_inactive'),
            'create'                  => Gate::allows('plans_create'),
            'log'                     => Gate::allows('plans_log'),
            'sms_log'                 => Gate::allows('plans_sms_log'),
            'patients_plan_cash_edit'   => Gate::allows('plans_cash_edit'),
            'patients_plan_cash_delete' => Gate::allows('plans_cash_delete'),
        ];
    }

    private function globalPermissions(): array
    {
        return [
            'edit'                        => Gate::allows('plans_edit'),
            'delete'                      => Gate::allows('plans_destroy'),
            'active'                      => Gate::allows('plans_active'),
            'inactive'                    => Gate::allows('plans_inactive'),
            'create'                      => Gate::allows('plans_create'),
            'log'                         => Gate::allows('plans_log'),
            'sms_log'                     => Gate::allows('plans_sms_log'),
            'plans_cash_edit'             => Gate::allows('plans_cash_edit'),
            'plans_cash_delete'           => Gate::allows('plans_cash_delete'),
            'plans_cash_edit_payment_mode' => Gate::allows('plans_cash_edit_payment_mode'),
            'plans_cash_edit_amount'       => Gate::allows('plans_cash_edit_amount'),
            'plans_cash_edit_date'         => Gate::allows('plans_cash_edit_date'),
            'plans_edit_sold_by'           => Gate::allows('plans_edit_sold_by'),
        ];
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse('You are not authorized to access this resource.', 403);
    }
}
