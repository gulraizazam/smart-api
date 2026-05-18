<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Enums\ExpenseStatus;
use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\RejectExpenseRequest;
use App\Http\Requests\CashFlow\StoreExpenseRequest;
use App\Http\Requests\CashFlow\UpdateExpenseRequest;
use App\Http\Requests\CashFlow\VoidExpenseRequest;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\Expense;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\CategoryService;
use App\Services\CashFlow\ExpenseService;
use App\Services\CashFlow\PoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowExpensesController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly PoolService $poolService,
        private readonly CategoryService $categoryService,
        private readonly CashflowSettingService $settingService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Get expenses list (paginated with filters).
     */
    public function expensesData(Request $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;



            $filters = $request->only([

                'status', 'branch_id', 'pool_id', 'category_id', 'date_from', 'date_to',

                'flagged', 'voided', 'search', 'id',

            ]);



            $perPage = (int) $request->input('per_page', 25);

            $expenses = $this->expenseService->getExpenses($accountId, $filters, $perPage);



            return response()->json([

                'success' => true,

                'data' => $expenses->items(),

                'meta' => [

                    'current_page' => $expenses->currentPage(),

                    'last_page' => $expenses->lastPage(),

                    'per_page' => $expenses->perPage(),

                    'total' => $expenses->total(),

                ],

                'status_counts' => $this->expenseService->getStatusCounts($accountId),

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get dropdown data for expense form.
     */
    public function expensesFormData(): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => [

                    'pools' => $this->poolService->getActivePools($accountId),

                    'categories' => $this->categoryService->getActive($accountId),

                    'branches' => CashflowHelper::getActiveBranches($accountId),

                    'payment_modes' => CashflowHelper::getActivePaymentModes(),

                    'vendors' => CashflowHelper::getActiveVendors($accountId),

                    'staff' => \App\Models\User::where('account_id', $accountId)->where('active', 1)->whereNotIn('user_type_id', [3])->orderBy('name')->get(['id', 'name']),

                    'threshold' => $this->settingService->getApprovalThreshold($accountId),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Store a new expense.
     */
    public function expensesStore(StoreExpenseRequest $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;



            if (!$this->settingService->isModuleConfigured($accountId)) {

                throw CashflowException::moduleNotConfigured();

            }



            $expense = $this->expenseService->create($request->validated(), $accountId);



            // Load pool for response (Sec 15.1: success popup shows new balance)

            $expense->load('paidFromPool:id,name,cached_balance');



            return response()->json([
                'success' => true,
                'data' => $expense,
                // $expense->status is cast to the ExpenseStatus enum —
                // comparing it to a raw string is always false (that bug
                // made every create say "submitted for approval").
                'message' => $expense->status === ExpenseStatus::Approved
                    ? 'Expense recorded and auto-approved.'
                    : 'Expense submitted for approval.',
            ]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    // expensesResubmit stays retired (Option A).
    //
    // `expensesReject` came back 2026-05-14 as the "return to accountant
    // for revisions" workflow: admin rejects with a reason, the row's
    // creator gains edit rights even without the global edit slug, the
    // accountant's save moves the row to Pending, and `expensesApprove`
    // closes the loop. See ExpenseService::reject / approve.
    public function expensesApprove(int $id): JsonResponse
    {
        if (! Auth::user()->can('cashflow_expense_approve')) {
            abort(403);
        }
        $accountId = (int) Auth::user()->account_id;
        try {
            $expense = $this->expenseService->approve($id, $accountId);
            return response()->json([
                'success' => true,
                'data' => $expense,
                'message' => 'Payment approved.',
            ]);
        } catch (CashflowException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('cashflow.expense.approve_failed', [
                'expense_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }

    public function expensesReject(RejectExpenseRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;
        try {
            $expense = $this->expenseService->reject(
                $id,
                $request->validated()['rejection_reason'],
                $accountId,
            );
            return response()->json([
                'success' => true,
                'data' => $expense,
                'message' => 'Payment rejected — returned to the creator for revision.',
            ]);
        } catch (CashflowException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('cashflow.expense.reject_failed', [
                'expense_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Admin edit an expense.
     */
    public function expensesEdit(UpdateExpenseRequest $request, int $id): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $expense = $this->expenseService->adminEdit($id, $request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $expense, 'message' => 'Expense updated.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Unflag an expense (admin only).
     */
    public function expensesUnflag(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_expense_unflag')) {

                throw CashflowException::unauthorized('unflag expenses');

            }



            $accountId = Auth::user()->account_id;

            $expense = Expense::forAccount($accountId)->findOrFail($id);

            // Snapshot the flag state BEFORE clearing so the audit trail
            // preserves *what* the row was flagged for. Without this the
            // reason is lost the moment the admin clicks Unflag, and
            // months later there's no record of why the row drew
            // attention in the first place.
            $previousFlagReason = $expense->flag_reason;
            $oldValues = [
                'is_flagged' => (bool) $expense->is_flagged,
                'flag_reason' => $previousFlagReason,
            ];

            $expense->update(['is_flagged' => false, 'flag_reason' => null]);

            // Use the original flag reason as the audit-log message — that
            // way the timeline entry reads with substance ("Reused receipt")
            // instead of a boilerplate "Expense unflagged by admin" line.
            $this->auditService->log(
                CashflowAuditLog::ACTION_UNFLAGGED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                ['is_flagged' => false, 'flag_reason' => null],
                $previousFlagReason ?: 'Unflagged'
            );



            return response()->json(['success' => true, 'message' => 'Expense unflagged.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Void an expense.
     */
    public function expensesVoid(VoidExpenseRequest $request, int $id): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $expense = $this->expenseService->void($id, $request->input('void_reason'), $accountId);



            return response()->json(['success' => true, 'data' => $expense, 'message' => 'Expense voided.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get audit trail for a specific expense.
     */
    public function expensesAudit(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_audit_view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('expense', $id, $accountId);



            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Export expenses as CSV with current filters.
     *
     * Return type covers both shapes the body can produce:
     *   - StreamedResponse on the happy path (the CSV stream)
     *   - JsonResponse on auth/error fall-through (`{success:false, message}`)
     * The previous narrow `JsonResponse` declaration caused a TypeError on
     * every successful export.
     */
    public function expensesExport(Request $request): \Symfony\Component\HttpFoundation\Response
    {

        try {

            if (!Gate::allows('cashflow_expense_export')) {

                throw CashflowException::unauthorized('export expenses');

            }

            $accountId = Auth::user()->account_id;



            $query = \App\Models\CashFlow\Expense::forAccount($accountId)

                ->with(['category:id,name', 'paidFromPool:id,name', 'forBranch:id,name', 'vendor:id,name', 'creator:id,name'])

                ->orderBy('expense_date', 'desc');



            if ($request->filled('status')) {

                $status = $request->input('status');

                if ($status === 'flagged') {

                    $query->where('is_flagged', true)->whereNull('voided_at');

                } elseif ($status === 'voided') {

                    $query->whereNotNull('voided_at');

                } else {

                    $query->where('status', $status)->whereNull('voided_at');

                }

            }

            if ($request->filled('branch_id')) {

                $query->where('for_branch_id', $request->input('branch_id'));

            }

            if ($request->filled('pool_id')) {

                $query->where('paid_from_pool_id', $request->input('pool_id'));

            }

            if ($request->filled('category_id')) {

                $query->where('category_id', $request->input('category_id'));

            }

            if ($request->filled('date_from') && $request->filled('date_to')) {

                $query->whereBetween('expense_date', [$request->input('date_from'), $request->input('date_to')]);

            }

            if ($request->filled('search')) {

                $search = $request->input('search');

                $query->where(function ($q) use ($search) {

                    $q->where('description', 'like', "%{$search}%")

                        ->orWhere('reference_no', 'like', "%{$search}%")

                        ->orWhere('notes', 'like', "%{$search}%")

                        ->orWhere('amount', 'like', "%{$search}%")

                        ->orWhereHas('vendor', fn($vq) => $vq->where('name', 'like', "%{$search}%"));

                });

            }



            $expenses = $query->get();

            $filename = 'cashflow_expenses_' . date('Y-m-d') . '.csv';



            return \Illuminate\Support\Facades\Response::streamDownload(function () use ($expenses) {

                $handle = fopen('php://output', 'w');

                fputcsv($handle, ['Date', 'Amount', 'Category', 'Pool', 'Branch', 'Vendor', 'Description', 'Status', 'Flagged', 'Created By']);



                foreach ($expenses as $exp) {

                    // Round 4 Inj-H1: defang formula-trigger characters
                    // in user-supplied fields (vendor name, description,
                    // flag reason) so opening the CSV in Excel cannot
                    // execute embedded `=cmd|...` payloads.
                    fputcsv_safe($handle, [

                        $exp->expense_date ? $exp->expense_date->format('d/m/Y') : '',

                        number_format((float) $exp->amount, 0),

                        $exp->category?->name ?? '',

                        $exp->paidFromPool?->name ?? '',

                        $exp->is_for_general ? 'General' : ($exp->forBranch?->name ?? ''),

                        $exp->vendor?->name ?? '',

                        $exp->description,

                        $exp->voided_at ? 'Voided' : $exp->status->value,

                        $exp->is_flagged ? $exp->flag_reason : '',

                        $exp->creator?->name ?? '',

                    ]);

                }



                fclose($handle);

            }, $filename, ['Content-Type' => 'text/csv']);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
