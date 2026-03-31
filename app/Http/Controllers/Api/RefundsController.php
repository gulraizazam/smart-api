<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Refund\RefundDatatableRequest;
use App\Http\Requests\Refund\StoreRefundRequest;
use App\Http\Resources\Refund\RefundCalculationResource;
use App\Http\Resources\Refund\RefundDatatableCollection;
use App\Http\Resources\Refund\PatientLedgerResource;
use App\Services\Refund\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class RefundsController extends Controller
{
    public function __construct(
        private readonly RefundService $refundService,
    ) {}

    // ── Global Refunds Datatable ──────────────────────────

    public function datatable(RefundDatatableRequest $request): JsonResponse
    {
        try {
            $this->handleFilterCancellation($request, 'plansrefunds');

            $filters = $request->filters();
            $applyFilter = checkFilters($filters, 'plansrefunds');

            $datatableData = $this->refundService->getGlobalDatatableData($filters, $applyFilter);
            $pagination = $request->paginationParams($datatableData['total']);

            $rows = $this->refundService->buildGlobalRefundRows(
                $pagination['start'],
                $pagination['perpage'],
                $applyFilter,
            );

            $totalPages = $pagination['perpage'] > 0
                ? (int) ceil($datatableData['total'] / $pagination['perpage'])
                : 1;

            // Adjust total if rows were filtered out (zero-refund packages)
            $adjustedTotal = $datatableData['total'] - ($datatableData['total'] - count($rows));

            $permissions = $this->resolvePermissions($request);

            $collection = new RefundDatatableCollection($rows);
            $collection->setContext(
                meta: [
                    'field'   => $request->input('sort.field', 'id'),
                    'page'    => $pagination['page'],
                    'pages'   => $totalPages,
                    'perpage' => $pagination['perpage'],
                    'total'   => max($adjustedTotal, count($rows)),
                    'sort'    => strtolower($request->input('sort.sort', 'desc')),
                ],
                permissions: $permissions,
                filterValues: $datatableData['filter_values'],
                activeFilters: Filters::all(Auth::id(), 'plansrefunds'),
            );

            return response()->json($collection);
        } catch (\Throwable $e) {
            Log::error('Refunds Datatable Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('An error occurred while fetching refunds data.', 500);
        }
    }

    // ── Patient Refunds Datatable ─────────────────────────

    public function patientDatatable(RefundDatatableRequest $request, int $patientId): JsonResponse
    {
        try {
            $this->handleFilterCancellation($request, 'patientrefunds');

            $filters = $request->filters();
            $applyFilter = checkFilters($filters, 'patientrefunds');

            $datatableData = $this->refundService->getPatientDatatableData($patientId, $applyFilter);
            $pagination = $request->paginationParams($datatableData['total']);

            $rows = $this->refundService->buildPatientRefundRows(
                $patientId,
                $pagination['start'],
                $pagination['perpage'],
                $applyFilter,
            );

            $totalPages = $pagination['perpage'] > 0
                ? (int) ceil($datatableData['total'] / $pagination['perpage'])
                : 1;

            $collection = new RefundDatatableCollection($rows);
            $collection->setContext(
                meta: [
                    'field'   => $request->input('sort.field', 'id'),
                    'page'    => $pagination['page'],
                    'pages'   => $totalPages,
                    'perpage' => $pagination['perpage'],
                    'total'   => max(count($rows), 0),
                    'sort'    => strtolower($request->input('sort.sort', 'desc')),
                ],
                permissions: $this->refundService->patientPermissions(),
                filterValues: $datatableData['filter_values'],
                activeFilters: Filters::all(Auth::id(), 'patientrefunds'),
            );

            return response()->json($collection);
        } catch (\Throwable $e) {
            Log::error('Patient Refunds Datatable Error', [
                'message'    => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return $this->errorResponse('An error occurred while fetching patient refunds.', 500);
        }
    }

    // ── Refund Calculation ────────────────────────────────

    public function calculate(int $id): JsonResponse
    {
        if (Gate::denies('refunds_create')) {
            return $this->unauthorizedResponse();
        }

        try {
            $result = $this->refundService->calculateRefund($id);

            if ($result['error'] ?? false) {
                return $this->errorResponse($result['message'], 404);
            }

            return $this->successResponse(
                new RefundCalculationResource($result),
                'Record found',
            );
        } catch (\Throwable $e) {
            Log::error('Refund Calculation Error', [
                'package_id' => $id,
                'message'    => $e->getMessage(),
            ]);

            return $this->errorResponse('An error occurred while calculating refund.', 500);
        }
    }

    // ── Store Refund ──────────────────────────────────────

    public function store(StoreRefundRequest $request): JsonResponse
    {
        try {
            $result = $this->refundService->createRefund(
                $request->validated(),
                (int) Auth::user()->account_id,
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 422);
            }

            return $this->successResponse(null, $result['message']);
        } catch (\Throwable $e) {
            Log::error('Store Refund Error', [
                'message' => $e->getMessage(),
                'data'    => $request->safe()->except('refund_note'),
            ]);

            return $this->errorResponse('An error occurred while creating refund.', 500);
        }
    }

    // ── Patient Ledger Detail ─────────────────────────────

    public function detail(int $id): JsonResponse
    {
        if (Gate::denies('refunds_manage')) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->refundService->getPatientLedger($id);

            return $this->successResponse(
                new PatientLedgerResource($data),
                'Patient ledger loaded.',
            );
        } catch (\Throwable $e) {
            Log::error('Patient Ledger Error', [
                'patient_id' => $id,
                'message'    => $e->getMessage(),
            ]);

            return $this->errorResponse('An error occurred while fetching ledger.', 500);
        }
    }

    // ── Private Helpers ───────────────────────────────────

    private function handleFilterCancellation(RefundDatatableRequest $request, string $filterKey): void
    {
        if ($request->isFilterCancel()) {
            Filters::flush(Auth::id(), $filterKey);
        }
    }

    private function resolvePermissions(RefundDatatableRequest $request): array
    {
        if ($request->has('patient_id') && $request->input('patient_id')) {
            return $this->refundService->patientPermissions();
        }

        return $this->refundService->globalPermissions();
    }

    private function successResponse(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ]);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => [],
        ], $status);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to access this resource.',
            'data'    => null,
            'errors'  => [],
        ], 403);
    }
}
