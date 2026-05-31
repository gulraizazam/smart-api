<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Refund\RefundDatatableRequest;
use App\Http\Requests\Refund\StoreRefundRequest;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PaymentModes;
use App\Models\User;
use App\Services\Refund\RefundService;
use Carbon\Carbon;
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
        if (Gate::denies('refunds.list.view')) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access this resource.',
                'data' => null,
            ], 403);
        }

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

            $permissions = $this->resolvePermissions($request);

            return response()->json([
                'meta' => [
                    'field'   => $request->input('sort.field', 'id'),
                    'page'    => $pagination['page'],
                    'pages'   => $totalPages,
                    'perpage' => $pagination['perpage'],
                    'total'   => count($rows),
                    'sort'    => strtolower($request->input('sort.sort', 'desc')),
                ],
                'data'           => $rows,
                'permissions'    => $permissions,
                'filter_values'  => $datatableData['filter_values'],
                'active_filters' => Filters::all(Auth::id(), 'plansrefunds'),
            ]);
        } catch (\Throwable $e) {
            // Round 4 Crypto-H3 — drop trace, keep file/line.
            Log::error('Refunds Datatable Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'meta' => ['total' => 0],
                'data' => [],
            ], 500);
        }
    }

    // ── Patient Refunds Datatable ─────────────────────────

    public function patientDatatable(RefundDatatableRequest $request, int $id): JsonResponse
    {
        try {
            $this->handleFilterCancellation($request, 'patientrefunds');

            $filters = $request->filters();
            $applyFilter = checkFilters($filters, 'patientrefunds');

            $datatableData = $this->refundService->getPatientDatatableData($id, $applyFilter);
            $pagination = $request->paginationParams($datatableData['total']);

            $rows = $this->refundService->buildPatientRefundRows(
                $id,
                $pagination['start'],
                $pagination['perpage'],
                $applyFilter,
            );

            $totalPages = $pagination['perpage'] > 0
                ? (int) ceil($datatableData['total'] / $pagination['perpage'])
                : 1;

            return response()->json([
                'meta' => [
                    'field'   => $request->input('sort.field', 'id'),
                    'page'    => $pagination['page'],
                    'pages'   => $totalPages,
                    'perpage' => $pagination['perpage'],
                    'total'   => count($rows),
                    'sort'    => strtolower($request->input('sort.sort', 'desc')),
                ],
                'data'           => $rows,
                'permissions'    => $this->refundService->patientPermissions(),
                'filter_values'  => $datatableData['filter_values'],
                'active_filters' => Filters::all(Auth::id(), 'patientrefunds'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Patient Refunds Datatable Error', [
                'message'    => $e->getMessage(),
                'patient_id' => $id,
            ]);

            return response()->json([
                'meta' => ['total' => 0],
                'data' => [],
            ], 500);
        }
    }

    // ── Refund Calculation ────────────────────────────────

    public function calculate(int $id): JsonResponse
    {
        if (Gate::denies('refunds.create')) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access this resource.',
                'data' => null,
            ], 403);
        }

        try {
            $result = $this->refundService->calculateRefund($id);

            if ($result['error'] ?? false) {
                return response()->json([
                    'status' => 404,
                    'msg' => $result['message'],
                    'data' => $result['package_id'],
                ]);
            }

            unset($result['error']);

            return response()->json([
                'status' => true,
                'message' => 'Record found',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Refund Calculation Error', [
                'package_id' => $id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while calculating refund.',
                'data' => null,
            ], 500);
        }
    }

    // ── Store Refund ──────────────────────────────────────

    public function store(StoreRefundRequest $request): JsonResponse
    {
        // StoreRefundRequest::authorize() already checks refunds.refund OR
        // patients_refund_refund; mirror the gate here for visibility.
        if (Gate::denies('refunds.refund') && Gate::denies('patients_refund_refund')) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access this resource.',
                'data' => null,
            ], 403);
        }

        try {
            $result = $this->refundService->createRefund(
                $request->validated(),
                (int) Auth::user()->account_id,
            );

            return response()->json([
                'status' => $result['success'],
                'message' => $result['message'],
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Store Refund Error', [
                'message' => $e->getMessage(),
                'data'    => $request->safe()->except('refund_note'),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating refund.',
                'data' => null,
            ], 500);
        }
    }

    // ── Patient Ledger Detail ─────────────────────────────

    public function detail(int $id): JsonResponse
    {
        if (Gate::denies('refunds.detail.view')) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access this resource.',
                'data' => null,
            ], 403);
        }

        try {
            $data = $this->refundService->getPatientLedger($id);

            return response()->json([
                'status' => true,
                'message' => 'Patient ledger loaded.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Patient Ledger Error', [
                'patient_id' => $id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching ledger.',
                'data' => null,
            ], 500);
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
}
