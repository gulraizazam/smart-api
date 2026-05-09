<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

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
use App\Support\SafeFilename;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

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

                'flagged', 'voided', 'search',

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

            $data = $request->validated();

            // Persist the receipt image (if any) on the public disk before
            // handing the data array to the service. The validator already
            // guarantees mime/size; we still sanitise the client filename.
            if ($request->hasFile('attachment_image')) {
                $data['attachment_image'] = $this->storeAttachmentImage(
                    $request->file('attachment_image'),
                    $accountId
                );
            } else {
                unset($data['attachment_image']);
            }

            $expense = $this->expenseService->create($data, $accountId);



            // Load pool for response (Sec 15.1: success popup shows new balance)

            $expense->load('paidFromPool:id,name,cached_balance');



            return response()->json([

                'success' => true,

                'data' => $expense,

                'message' => $expense->status === 'approved'

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

    /**
     * Approve an expense.
     */
    public function expensesApprove(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_expense_approve')) {

                throw CashflowException::unauthorized('approve expenses');

            }



            $accountId = Auth::user()->account_id;

            $expense = $this->expenseService->approve($id, $accountId);



            return response()->json(['success' => true, 'data' => $expense, 'message' => 'Expense approved.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Reject an expense.
     */
    public function expensesReject(RejectExpenseRequest $request, int $id): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $expense = $this->expenseService->reject($id, $request->input('rejection_reason'), $accountId);



            return response()->json(['success' => true, 'data' => $expense, 'message' => 'Expense rejected.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Resubmit a rejected expense.
     */
    public function expensesResubmit(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_expense_resubmit')) {

                throw CashflowException::unauthorized('resubmit expenses');

            }



            $accountId = Auth::user()->account_id;

            $data = $request->except('attachment_image');

            // Replace the receipt image if a new one was uploaded; the
            // request->all() flow used to send a string-y "fakepath"
            // attachment_image which silently overwrote real paths.
            if ($request->hasFile('attachment_image')) {
                $request->validate([
                    'attachment_image' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
                ]);

                $existing = Expense::forAccount($accountId)->findOrFail($id);
                $data['attachment_image'] = $this->storeAttachmentImage(
                    $request->file('attachment_image'),
                    $accountId
                );

                if ($existing->attachment_image) {
                    Storage::disk('public')->delete($existing->attachment_image);
                }
            }

            $expense = $this->expenseService->resubmit($id, $data, $accountId);



            return response()->json(['success' => true, 'data' => $expense, 'message' => 'Expense resubmitted for approval.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Admin edit an expense.
     */
    public function expensesEdit(UpdateExpenseRequest $request, int $id): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $request->validated();

            // Replace the receipt image if a new one was uploaded; otherwise
            // strip the field so the service won't overwrite the existing
            // value with NULL on a partial update.
            if ($request->hasFile('attachment_image')) {
                $existing = Expense::forAccount($accountId)->findOrFail($id);
                $data['attachment_image'] = $this->storeAttachmentImage(
                    $request->file('attachment_image'),
                    $accountId
                );

                if ($existing->attachment_image) {
                    Storage::disk('public')->delete($existing->attachment_image);
                }
            } else {
                unset($data['attachment_image']);
            }

            $expense = $this->expenseService->adminEdit($id, $data, $accountId);



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

            $expense->update(['is_flagged' => false, 'flag_reason' => null]);



            $this->auditService->log(
                CashflowAuditLog::ACTION_UNFLAGGED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                ['is_flagged' => true],
                ['is_flagged' => false],
                'Expense unflagged by admin'
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
     */
    public function expensesExport(Request $request): \Illuminate\Http\JsonResponse
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

                        number_format($exp->amount, 0),

                        $exp->category?->name ?? '',

                        $exp->paidFromPool?->name ?? '',

                        $exp->is_for_general ? 'General' : ($exp->forBranch?->name ?? ''),

                        $exp->vendor?->name ?? '',

                        $exp->description,

                        $exp->voided_at ? 'Voided' : $exp->status,

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

    /**
     * Persist an uploaded receipt image on the `public` disk and return
     * the stored relative path (e.g. `cashflow_attachments/12/1715234567_receipt.jpg`).
     *
     * The browser-supplied filename is fully attacker-controlled, so we
     * route it through SafeFilename and prepend a timestamp to defuse
     * collisions. Files land under a per-account folder so listing one
     * account's bucket can never enumerate another's.
     */
    private function storeAttachmentImage(UploadedFile $file, int $accountId): string
    {
        $safeName = SafeFilename::sanitize(
            $file->getClientOriginalName(),
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        );

        $fileName = time() . '_' . $safeName;

        return $file->storeAs("cashflow_attachments/{$accountId}", $fileName, 'public');
    }
}
