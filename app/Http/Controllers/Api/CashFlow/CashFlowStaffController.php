<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreStaffAdvanceRequest;
use App\Http\Requests\CashFlow\StoreStaffReturnRequest;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\StaffAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowStaffController extends Controller
{
    public function __construct(
        private readonly StaffAdvanceService $staffAdvanceService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Get staff advance summary.
     */
    public function staffSummary(): JsonResponse
    {

        try {

            if (!Gate::any(['cashflow_staff_advance_view', 'cashflow_staff_advance'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => $this->staffAdvanceService->getStaffSummary($accountId),

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get recent staff advances and returns (for overview activity cards).
     */
    public function staffRecentActivity(): JsonResponse
    {

        try {

            if (!Gate::any(['cashflow_staff_advance_view', 'cashflow_staff_advance'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => $this->staffAdvanceService->getRecentActivity($accountId),

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get staff ledger (advances + returns for one staff member).
     */
    public function staffLedger(int $userId): JsonResponse
    {

        try {

            if (!Gate::any(['cashflow_staff_advance_view', 'cashflow_staff_advance'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => $this->staffAdvanceService->getStaffLedger($userId, $accountId),

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get eligible staff for advance dropdown.
     */
    public function staffEligible(): JsonResponse
    {

        try {

            if (!Gate::any(['cashflow_staff_advance_view', 'cashflow_staff_advance'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;



            $staff = $this->staffAdvanceService->getEligibleStaff($accountId);



            // Attach outstanding balance for each staff member

            $staff->each(function ($user) use ($accountId) {

                $user->outstanding = $this->staffAdvanceService->getOutstanding($user->id, $accountId);

            });



            return response()->json([

                'success' => true,

                'data' => $staff,

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a staff advance.
     */
    public function staffAdvanceStore(StoreStaffAdvanceRequest $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $advance = $this->staffAdvanceService->createAdvance($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $advance, 'message' => 'Advance recorded successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Void a staff advance.
     */
    public function staffAdvanceVoid(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_staff_advance_void')) {

                throw CashflowException::unauthorized('void staff advances');

            }



            $request->validate(['void_reason' => 'required|string|min:5|max:100']);

            $accountId = Auth::user()->account_id;

            $advance = $this->staffAdvanceService->voidAdvance($id, $request->void_reason, $accountId);

            return response()->json(['success' => true, 'data' => $advance, 'message' => 'Advance voided successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Edit a staff advance.
     */
    public function staffAdvanceUpdate(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_staff_advance_edit')) {

                throw CashflowException::unauthorized('edit staff advances');

            }



            $request->validate([

                'amount' => 'required|numeric|min:1|integer',

                'pool_id' => 'required|exists:cash_pools,id',

                'description' => 'nullable|string|max:50',

                'edit_reason' => 'required|string|min:5|max:50',

            ]);

            $accountId = Auth::user()->account_id;

            $advance = $this->staffAdvanceService->editAdvance($id, $request->all(), $accountId);

            return response()->json(['success' => true, 'data' => $advance, 'message' => 'Advance updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get audit trail for a specific staff advance.
     */
    public function staffAdvanceAudit(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_audit_view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('staff_advance', $id, $accountId);

            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a staff return.
     */
    public function staffReturnStore(StoreStaffReturnRequest $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $return = $this->staffAdvanceService->createReturn($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $return, 'message' => 'Return recorded successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Void a staff return.
     */
    public function staffReturnVoid(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_staff_return_void')) {

                throw CashflowException::unauthorized('void staff returns');

            }



            $request->validate(['void_reason' => 'required|string|min:5|max:100']);

            $accountId = Auth::user()->account_id;

            $return = $this->staffAdvanceService->voidReturn($id, $request->void_reason, $accountId);

            return response()->json(['success' => true, 'data' => $return, 'message' => 'Return voided successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get audit trail for a specific staff return.
     */
    public function staffReturnAudit(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_audit_view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('staff_return', $id, $accountId);

            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
