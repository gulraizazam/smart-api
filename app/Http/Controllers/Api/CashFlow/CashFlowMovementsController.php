<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreMovementRequest;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\MovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Unified Cash Movements API — Phase A.
 *
 * Wraps Transfers + StaffAdvances + StaffReturns behind a single surface.
 * Every write call routes through MovementService → per-kind service so
 * existing invariants (period-lock, eligibility, threshold, attachment,
 * audit) keep firing untouched. The old per-feature controllers stay
 * alive throughout the shadow-run window.
 */
class CashFlowMovementsController extends Controller
{
    public function __construct(
        private readonly MovementService $movementService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Paginated list of movements with combined filters.
     */
    public function movementsData(Request $request): JsonResponse
    {
        try {
            if (!$this->canViewMovements()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $accountId = Auth::user()->account_id;
            $filters = $request->only([
                'date_from', 'date_to', 'source_type', 'source_id',
                'dest_type', 'dest_id', 'kind', 'search', 'voided',
            ]);

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 25);

            $movements = $this->movementService->list($accountId, $filters, $perPage, $page);

            return response()->json([
                'success' => true,
                'data' => $movements->items(),
                'meta' => [
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Form-data endpoint — pools + eligible staff list for the picker.
     * Returns a flat shape the SPA can consume directly.
     */
    public function formData(Request $request): JsonResponse
    {
        try {
            if (!$this->canViewMovements()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $accountId = Auth::user()->account_id;

            $pools = \App\Models\CashFlow\CashPool::forAccount($accountId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'location_id']);

            $eligibleStaff = \App\Models\User::where('account_id', $accountId)
                ->where('active', 1)
                ->where('is_advance_eligible', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'pools' => $pools,
                    'eligible_staff' => $eligibleStaff,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function store(StoreMovementRequest $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $movement = $this->movementService->create($accountId, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Movement recorded successfully.',
            ]);
        } catch (CashflowException $e) {
            return $e->render(request());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function void(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            if (!$this->canVoidMovement($kind)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $data = $request->validate([
                'void_reason' => 'required|string|min:5|max:100',
            ]);

            $accountId = Auth::user()->account_id;
            $movement = $this->movementService->void($accountId, $kind, $id, $data['void_reason']);

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Movement voided successfully.',
            ]);
        } catch (CashflowException $e) {
            return $e->render(request());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function audit(string $kind, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('cashflow_audit_view')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $accountId = Auth::user()->account_id;
            $entityType = $this->movementService->auditEntityFor($kind);
            $logs = $this->auditService->getEntityLogs($entityType, $id, $accountId);

            return response()->json(['success' => true, 'data' => $logs]);
        } catch (CashflowException $e) {
            return $e->render(request());
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function poolLedger(Request $request, int $poolId): JsonResponse
    {
        try {
            if (!$this->canViewMovements()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $accountId = Auth::user()->account_id;
            $filters = $request->only(['date_from', 'date_to']);
            $rows = $this->movementService->getPoolLedger($accountId, $poolId, $filters);

            return response()->json([
                'success' => true,
                'data' => $rows->values(),
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Movement view is derived from any of the underlying view slugs — a
     * user who can see Transfers OR Staff Advances can see the unified list
     * (filtered client-side to the bits they care about).
     */
    private function canViewMovements(): bool
    {
        return Gate::any([
            'cashflow_transfer_view',
            'cashflow_staff_advance_view',
            'cashflow_staff_advance',
            'cashflow_manage',
        ]);
    }

    /**
     * Void permission is per-kind so a user who can void transfers can't
     * silently void staff advances by hitting the unified endpoint.
     */
    private function canVoidMovement(string $kind): bool
    {
        if (Gate::allows('cashflow_manage')) {
            return true;
        }

        return match ($kind) {
            MovementService::KIND_TRANSFER => Gate::allows('cashflow_transfer_void'),
            MovementService::KIND_STAFF_ADVANCE => Gate::allows('cashflow_staff_advance_void'),
            MovementService::KIND_STAFF_RETURN => Gate::allows('cashflow_staff_return_void'),
            default => false,
        };
    }
}
