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
     *
     * Optional `date_from` / `date_to` query params (ISO `YYYY-MM-DD`) scope
     * the per-staff totals to a window. Malformed input is dropped silently
     * — the service treats absent params as "all history".
     */
    public function staffSummary(Request $request): JsonResponse
    {

        try {

            if (!Gate::any(['cashflow.staff_advance.view', 'cashflow.staff_advance.view'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;

            [$dateFrom, $dateTo] = $this->parseDateRange($request);

            return response()->json([

                'success' => true,

                'data' => $this->staffAdvanceService->getStaffSummary($accountId, $dateFrom, $dateTo),

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

            if (!Gate::any(['cashflow.staff_advance.view', 'cashflow.staff_advance.view'])) {

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
     *
     * Same `date_from` / `date_to` contract as `staffSummary` — windows
     * each list by its own date column.
     */
    public function staffLedger(int $userId, Request $request): JsonResponse
    {

        try {

            if (!Gate::any(['cashflow.staff_advance.view', 'cashflow.staff_advance.view'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;

            [$dateFrom, $dateTo] = $this->parseDateRange($request);

            return response()->json([

                'success' => true,

                'data' => $this->staffAdvanceService->getStaffLedger($userId, $accountId, $dateFrom, $dateTo),

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

            if (!Gate::any(['cashflow.staff_advance.view', 'cashflow.staff_advance.view'])) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;



            $staff = $this->staffAdvanceService->getEligibleStaff($accountId);

            // Attach outstanding balance for each staff member.
            // Batched: 3 GROUP BY queries total instead of 3 × N for
            // a per-user loop.
            $balances = $this->staffAdvanceService->getOutstandingForUsers(
                $staff->pluck('id')->toArray(),
                $accountId,
            );

            $staff->each(function ($user) use ($balances) {
                $user->outstanding = $balances[$user->id] ?? 0;
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

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render field-level 422.
            throw $e;

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

            if (!Gate::allows('cashflow.staff_advance.void')) {

                throw CashflowException::unauthorized('void staff advances');

            }



            $request->validate(['void_reason' => 'required|string|min:5|max:100']);

            $accountId = Auth::user()->account_id;

            $advance = $this->staffAdvanceService->voidAdvance($id, $request->void_reason, $accountId);

            return response()->json(['success' => true, 'data' => $advance, 'message' => 'Advance voided successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render field-level 422.
            throw $e;

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

            if (!Gate::allows('cashflow.staff_advance.edit')) {

                throw CashflowException::unauthorized('edit staff advances');

            }



            $accountId = Auth::user()->account_id;

            $request->validate([

                'amount' => 'required|numeric|min:1|integer',

                'pool_id' => ['required', \Illuminate\Validation\Rule::exists('cash_pools', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],

                'description' => 'nullable|string|max:500',

                'edit_reason' => 'required|string|min:5|max:50',

            ]);

            $advance = $this->staffAdvanceService->editAdvance($id, $request->all(), $accountId);

            return response()->json(['success' => true, 'data' => $advance, 'message' => 'Advance updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render field-level 422.
            throw $e;

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

            if (!Gate::allows('cashflow.audit.view')) {

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

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render field-level 422.
            throw $e;

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

            if (!Gate::allows('cashflow.staff_return.void')) {

                throw CashflowException::unauthorized('void staff returns');

            }



            $request->validate(['void_reason' => 'required|string|min:5|max:100']);

            $accountId = Auth::user()->account_id;

            $return = $this->staffAdvanceService->voidReturn($id, $request->void_reason, $accountId);

            return response()->json(['success' => true, 'data' => $return, 'message' => 'Return voided successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render field-level 422.
            throw $e;

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

            if (!Gate::allows('cashflow.audit.view')) {

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

    /**
     * Parse `date_from` / `date_to` query params (ISO `YYYY-MM-DD`). Both
     * must be present and well-formed; any other state returns [null, null]
     * so the service falls back to "all history". Swaps if `from > to`.
     */
    private function parseDateRange(Request $request): array
    {
        $rawFrom = $request->input('date_from');
        $rawTo = $request->input('date_to');

        if (!is_string($rawFrom) || !is_string($rawTo)) {
            return [null, null];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom)) {
            return [null, null];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)) {
            return [null, null];
        }

        if (strcmp($rawFrom, $rawTo) > 0) {
            [$rawFrom, $rawTo] = [$rawTo, $rawFrom];
        }

        return [$rawFrom, $rawTo];
    }
}
