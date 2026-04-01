<?php



namespace App\Http\Controllers\Api;



use App\Exceptions\CashflowException;

use App\Helpers\CashflowHelper;

use App\Http\Controllers\Controller;

use App\Models\CashFlow\CashflowAuditLog;

use App\Models\CashFlow\Expense;

use App\Http\Requests\CashFlow\RejectExpenseRequest;

use App\Http\Requests\CashFlow\StoreExpenseRequest;

use App\Http\Requests\CashFlow\UpdateExpenseRequest;

use App\Http\Requests\CashFlow\VoidExpenseRequest;

use App\Http\Requests\CashFlow\StoreTransferRequest;

use App\Http\Requests\CashFlow\StoreVendorRequest as StoreVendorFormRequest;

use App\Http\Requests\CashFlow\StoreStaffAdvanceRequest;

use App\Http\Requests\CashFlow\StoreStaffReturnRequest;

use App\Http\Requests\CashFlow\UpdateVendorRequest as UpdateVendorFormRequest;

use App\Http\Requests\CashFlow\StoreVendorPurchaseRequest;

use App\Http\Requests\CashFlow\StoreCategorySuggestionRequest;

use App\Http\Requests\CashFlow\StoreVendorSuggestionRequest;

use App\Services\CashFlow\CashflowAuditService;

use App\Services\CashFlow\CashflowSettingService;

use App\Services\CashFlow\CategoryService;

use App\Services\CashFlow\ExpenseService;

use App\Services\CashFlow\NotificationService;

use App\Services\CashFlow\PoolService;

use App\Services\CashFlow\TransferService;

use App\Services\CashFlow\VendorService;

use App\Services\CashFlow\StaffAdvanceService;

use App\Services\CashFlow\DashboardService;

use App\Services\CashFlow\ReportService;

use App\Services\CashFlow\ExportService;

use App\Services\CashFlow\PeriodLockService;

use App\Services\CashFlow\FlaggingService;

use App\Models\Order;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Gate;



class CashFlowController extends Controller

{

    private ExpenseService $expenseService;

    private PoolService $poolService;

    private CategoryService $categoryService;

    private CashflowSettingService $settingService;

    private NotificationService $notificationService;

    private CashflowAuditService $auditService;

    private TransferService $transferService;

    private VendorService $vendorService;

    private StaffAdvanceService $staffAdvanceService;

    private DashboardService $dashboardService;

    private ReportService $reportService;

    private ExportService $exportService;

    private PeriodLockService $periodLockService;

    private FlaggingService $flaggingService;



    public function __construct(

        ExpenseService $expenseService,

        PoolService $poolService,

        CategoryService $categoryService,

        CashflowSettingService $settingService,

        NotificationService $notificationService,

        CashflowAuditService $auditService,

        TransferService $transferService,

        VendorService $vendorService,

        StaffAdvanceService $staffAdvanceService,

        DashboardService $dashboardService,

        ReportService $reportService,

        ExportService $exportService,

        PeriodLockService $periodLockService,

        FlaggingService $flaggingService

    ) {

        $this->expenseService = $expenseService;

        $this->poolService = $poolService;

        $this->categoryService = $categoryService;

        $this->settingService = $settingService;

        $this->notificationService = $notificationService;

        $this->auditService = $auditService;

        $this->transferService = $transferService;

        $this->vendorService = $vendorService;

        $this->staffAdvanceService = $staffAdvanceService;

        $this->dashboardService = $dashboardService;

        $this->reportService = $reportService;

        $this->exportService = $exportService;

        $this->periodLockService = $periodLockService;

        $this->flaggingService = $flaggingService;

    }



    // ===================== SETTINGS =====================



    /**

     * Get all settings data for the settings screen.

     */

    public function settingsData(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => [

                    'settings' => $this->settingService->getAll($accountId),

                    'pools' => $this->poolService->getAllPools($accountId),

                    'categories' => $this->categoryService->getAll($accountId),

                    'payment_modes' => CashflowHelper::getActivePaymentModes(),

                    'has_period_locks' => \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists(),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Update settings.

     */

    public function settingsUpdate(Request $request): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('manage settings');

            }



            $accountId = Auth::user()->account_id;

            $settings = $request->input('settings', []);

            // Support flat key-value pairs (e.g. from PM mapping save)

            if (empty($settings)) {

                $settings = $request->except(['_token']);

            }

            $oldSettings = $this->settingService->getAll($accountId);

            $this->settingService->updateMany($settings, $accountId);



            $this->auditService->log(

                CashflowAuditLog::ACTION_UPDATED,

                CashflowAuditLog::ENTITY_SETTING,

                0,

                $oldSettings,

                $settings

            );



            return response()->json(['success' => true, 'message' => 'Settings updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== POOLS =====================



    /**

     * Get all pools.

     */

    public function poolsIndex(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            return response()->json([

                'success' => true,

                'data' => $this->poolService->getAllPools($accountId),

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Create a new pool (head office or bank account).

     */

    public function poolsStore(Request $request): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $request->validate([

                'name' => 'required|string|max:255',

                'type' => 'required|in:head_office_cash,bank_account',

                'opening_balance' => 'nullable|numeric|min:0',

            ]);



            $accountId = Auth::user()->account_id;

            $pool = $this->poolService->createPool($request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $pool, 'message' => 'Pool created successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Update a pool.

     */

    public function poolsUpdate(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $request->validate([

                'name' => 'nullable|string|max:255',

                'opening_balance' => 'nullable|numeric|min:0',

                'is_active' => 'nullable|boolean',

            ]);



            $accountId = Auth::user()->account_id;



            // Opening balance frozen after first period lock (Sec 4.2)

            $data = $request->all();

            if (isset($data['opening_balance'])) {

                $hasLocks = \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists();

                if ($hasLocks) {

                    unset($data['opening_balance']);

                }

            }



            $pool = $this->poolService->updatePool($id, $data, $accountId);



            return response()->json(['success' => true, 'data' => $pool, 'message' => 'Pool updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Delete a pool.

     */

    public function poolsDelete(int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $accountId = Auth::user()->account_id;

            $this->poolService->deletePool($id, $accountId);



            return response()->json(['success' => true, 'message' => 'Pool deleted successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Initialize pools for existing branches.

     */

    public function poolsInit(): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $accountId = Auth::user()->account_id;

            $count = $this->poolService->initializePoolsForExistingBranches($accountId);



            return response()->json([

                'success' => true,

                'message' => $count > 0

                    ? "{$count} branch pool(s) created successfully."

                    : 'All branches already have pools.',

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Recalculate all pool balances from opening balances + all transactions since go-live.

     */

    public function poolsRecalculate(): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('recalculate pool balances');

            }



            $accountId = Auth::user()->account_id;

            $results = $this->poolService->recalculatePoolBalances($accountId);



            $message = count($results) > 0

                ? count($results) . ' pool(s) adjusted.'

                : 'All pool balances are already accurate.';



            return response()->json([

                'success' => true,

                'message' => $message,

                'data' => $results,

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== CATEGORIES =====================



    /**

     * Get all categories.

     */

    public function categoriesIndex(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            return response()->json([

                'success' => true,

                'data' => $this->categoryService->getAll($accountId),

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Create a category.

     */

    public function categoriesStore(Request $request): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_category_manage')) {

                throw CashflowException::unauthorized('manage categories');

            }



            $request->validate([

                'name' => 'required|string|max:255',

                'description' => 'nullable|string|max:500',

                'vendor_emphasis' => 'nullable|boolean',

            ]);



            $accountId = Auth::user()->account_id;

            $category = $this->categoryService->create($request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $category, 'message' => 'Category created successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Update a category.

     */

    public function categoriesUpdate(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_category_manage')) {

                throw CashflowException::unauthorized('manage categories');

            }



            $request->validate([

                'name' => 'nullable|string|max:255',

                'description' => 'nullable|string|max:500',

                'vendor_emphasis' => 'nullable|boolean',

            ]);



            $accountId = Auth::user()->account_id;

            $category = $this->categoryService->update($id, $request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $category, 'message' => 'Category updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Toggle category active/inactive.

     */

    public function categoriesToggle(int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_category_manage')) {

                throw CashflowException::unauthorized('manage categories');

            }



            $accountId = Auth::user()->account_id;

            $category = $this->categoryService->toggle($id, $accountId);



            return response()->json([

                'success' => true,

                'data' => $category,

                'message' => $category->is_active ? 'Category activated.' : 'Category deactivated.',

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    // ===================== EXPENSES =====================



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



            $perPage = $request->input('per_page', 25);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

                'message' => $expense->status === 'approved'

                    ? 'Expense recorded and auto-approved.'

                    : 'Expense submitted for approval.',

            ]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            $expense = $this->expenseService->resubmit($id, $request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $expense, 'message' => 'Expense resubmitted for approval.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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



            $this->auditService->log('unflagged', 'expense', $expense->id, ['is_flagged' => true], ['is_flagged' => false], 'Expense unflagged by admin');



            return response()->json(['success' => true, 'message' => 'Expense unflagged.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Export expenses as CSV with current filters.

     */

    public function expensesExport(Request $request)

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

                    fputcsv($handle, [

                        $exp->expense_date ? $exp->expense_date->format('d/m/Y') : '',

                        number_format($exp->amount, 0),

                        $exp->category ? $exp->category->name : '',

                        $exp->paidFromPool ? $exp->paidFromPool->name : '',

                        $exp->is_for_general ? 'General' : ($exp->forBranch ? $exp->forBranch->name : ''),

                        $exp->vendor ? $exp->vendor->name : '',

                        $exp->description,

                        $exp->voided_at ? 'Voided' : $exp->status,

                        $exp->is_flagged ? $exp->flag_reason : '',

                        $exp->creator ? $exp->creator->name : '',

                    ]);

                }



                fclose($handle);

            }, $filename, ['Content-Type' => 'text/csv']);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== NOTIFICATIONS =====================



    /**

     * Get notifications for current user.

     */

    public function notificationsIndex(): JsonResponse

    {

        try {

            $userId = Auth::id();



            return response()->json([

                'success' => true,

                'data' => $this->notificationService->getForUser($userId),

                'unread_count' => $this->notificationService->getUnreadCount($userId),

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Mark notifications as read.

     */

    public function notificationsMarkRead(Request $request): JsonResponse

    {

        try {

            $userId = Auth::id();



            if ($request->has('notification_id')) {

                $this->notificationService->markRead($request->input('notification_id'), $userId);

            } else {

                $this->notificationService->markAllRead($userId);

            }



            return response()->json(['success' => true, 'message' => 'Notifications marked as read.']);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== LOOKUPS =====================



    /**

     * Get common lookup data used across multiple screens.

     */

    public function lookups(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => [

                    'pools' => CashflowHelper::getActivePools($accountId),

                    'categories' => CashflowHelper::getActiveCategories($accountId),

                    'branches' => CashflowHelper::getActiveBranches($accountId),

                    'payment_modes' => CashflowHelper::getActivePaymentModes(),

                    'vendors' => CashflowHelper::getActiveVendors($accountId),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== TRANSFERS =====================



    /**

     * Get transfers list (paginated with filters).

     */

    public function transfersData(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'pool_id', 'method', 'search']);

            $transfers = $this->transferService->getTransfers($accountId, $filters, $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $transfers->items(),

                'meta' => [

                    'current_page' => $transfers->currentPage(),

                    'last_page' => $transfers->lastPage(),

                    'per_page' => $transfers->perPage(),

                    'total' => $transfers->total(),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Store a new cash transfer.

     */

    public function transfersStore(StoreTransferRequest $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $transfer = $this->transferService->create($request->validated(), $accountId);



            return response()->json([

                'success' => true,

                'data' => $transfer,

                'message' => 'Transfer recorded successfully.',

            ]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Void a cash transfer.

     */

    public function transfersVoid(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_transfer_void')) {

                throw CashflowException::unauthorized('void transfers');

            }



            $request->validate([

                'void_reason' => 'required|string|min:5|max:100',

            ]);



            $accountId = Auth::user()->account_id;

            $transfer = $this->transferService->void($id, $request->void_reason, $accountId);



            return response()->json(['success' => true, 'data' => $transfer, 'message' => 'Transfer voided successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Edit a cash transfer.

     */

    public function transfersEdit(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_transfer_edit')) {

                throw CashflowException::unauthorized('edit transfers');

            }



            $request->validate([

                'amount' => 'required|numeric|min:1|integer',

                'from_pool_id' => 'required|exists:cash_pools,id',

                'to_pool_id' => 'required|exists:cash_pools,id|different:from_pool_id',

                'method' => 'required|in:physical_cash,bank_deposit',

                'attachment_url' => ['required', 'string', 'max:500'],

                'description' => 'nullable|string|max:50',

                'edit_reason' => 'required|string|min:5|max:50',

            ]);



            $accountId = Auth::user()->account_id;

            $transfer = $this->transferService->edit($id, $request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $transfer, 'message' => 'Transfer updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Get audit trail for a specific transfer.

     */

    public function transfersAudit(int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_audit_view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('transfer', $id, $accountId);



            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== VENDORS =====================



    /**

     * Get vendors list (paginated).

     */

    public function vendorsData(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['search', 'is_active', 'sort']);

            $vendors = $this->vendorService->getVendors($accountId, $filters, $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $vendors->items(),

                'meta' => [

                    'current_page' => $vendors->currentPage(),

                    'last_page' => $vendors->lastPage(),

                    'per_page' => $vendors->perPage(),

                    'total' => $vendors->total(),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Create a vendor.

     */

    public function vendorsStore(StoreVendorFormRequest $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $vendor = $this->vendorService->createVendor($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $vendor, 'message' => 'Vendor created successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Update a vendor.

     */

    public function vendorsUpdate(UpdateVendorFormRequest $request, int $id): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $vendor = $this->vendorService->updateVendor($id, $request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $vendor, 'message' => 'Vendor updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Toggle vendor active/inactive.

     */

    public function vendorsToggle(int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_vendor_toggle')) {

                throw CashflowException::unauthorized('activate/deactivate vendors');

            }



            $accountId = Auth::user()->account_id;

            $vendor = \App\Models\CashFlow\Vendor::forAccount($accountId)->findOrFail($id);

            $oldActive = $vendor->is_active;

            $vendor->update(['is_active' => !$oldActive]);



            $this->auditService->log(

                $oldActive ? CashflowAuditLog::ACTION_DEACTIVATED : CashflowAuditLog::ACTION_UPDATED,

                CashflowAuditLog::ENTITY_VENDOR,

                $vendor->id,

                ['is_active' => $oldActive],

                ['is_active' => !$oldActive]

            );



            return response()->json([

                'success' => true,

                'data' => $vendor->fresh(),

                'message' => $vendor->fresh()->is_active ? 'Vendor activated.' : 'Vendor deactivated.',

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Get vendors overview dashboard (summary stats, top outstanding, recent transactions).

     */

    public function vendorsOverview(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $params = $request->only(['purchase_page', 'payment_page', 'purchase_status', 'payment_vendor_id']);

            $data = $this->vendorService->getVendorsOverview($accountId, $params);

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Record a purchase for a specific vendor (shortcut endpoint).

     */

    public function vendorsPurchase(StoreVendorPurchaseRequest $request, int $id): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $transaction = $this->vendorService->recordTransaction(array_merge(

                $request->validated(),

                ['vendor_id' => $id, 'type' => 'purchase']

            ), $accountId);



            return response()->json(['success' => true, 'data' => $transaction, 'message' => 'Purchase recorded.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Get vendor ledger (transactions for a specific vendor).

     */

    public function vendorsLedger(Request $request, int $id): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['type', 'date_from', 'date_to', 'status']);

            $result = $this->vendorService->getVendorLedger($id, $accountId, $filters, $request->input('per_page', 50));



            return response()->json([

                'success' => true,

                'data' => $result,

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Update a standalone vendor transaction (purchase).

     */

    public function vendorsTransactionUpdate(StoreVendorPurchaseRequest $request, int $vendorId, int $txId): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $tx = $this->vendorService->updateTransaction($txId, $request->validated(), $accountId);

            return response()->json(['success' => true, 'data' => $tx, 'message' => 'Transaction updated.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Mark an ordered purchase as delivered (attach Drive URL). Gated by create permission.

     */

    public function vendorsTransactionDeliver(Request $request, int $vendorId, int $txId): JsonResponse

    {

        try {

            if (!Auth::user()->can('cashflow_vendor_deliver')) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $request->validate([
                'attachment_url' => 'required|url|max:500',
            ]);

            $accountId = Auth::user()->account_id;

            $tx = $this->vendorService->deliverTransaction($txId, $request->input('attachment_url'), $accountId);

            return response()->json(['success' => true, 'data' => $tx, 'message' => 'Purchase marked as delivered.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json(['success' => false, 'message' => 'A valid Google Drive URL is required to mark as delivered.', 'errors' => $e->errors()], 422);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Delete a standalone vendor transaction (purchase).

     */

    public function vendorsTransactionDelete(Request $request, int $vendorId, int $txId): JsonResponse

    {

        try {

            if (!Auth::user()->can('cashflow_vendor_transaction')) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;

            $this->vendorService->deleteTransaction($txId, $accountId);

            return response()->json(['success' => true, 'message' => 'Transaction deleted.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Get audit trail for a specific vendor transaction.

     */

    public function vendorsTransactionAudit(int $vendorId, int $txId): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_audit_view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('vendor_transaction', $txId, $accountId);



            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Export vendor ledger as CSV.

     */

    public function vendorsLedgerExport(Request $request, int $id)

    {

        try {

            if (!Gate::allows('cashflow_vendor_ledger_export')) {

                throw CashflowException::unauthorized('export vendor ledger');

            }

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['type', 'date_from', 'date_to']);

            $data = $this->vendorService->exportVendorLedger($id, $accountId, $filters);



            $vendor = $data['vendor'];

            $filename = 'vendor_ledger_' . \Illuminate\Support\Str::slug($vendor->name) . '_' . $data['date_from'] . '_to_' . $data['date_to'] . '.csv';



            $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'];



            $callback = function () use ($data) {

                $out = fopen('php://output', 'w');

                fputcsv($out, ['Vendor: ' . $data['vendor']->name]);

                fputcsv($out, ['Period: ' . $data['date_from'] . ' to ' . $data['date_to']]);

                fputcsv($out, ['Opening Balance: ' . $data['opening_balance']]);

                fputcsv($out, []);

                fputcsv($out, ['Date', 'Type', 'Description', 'Reference', 'Branch', 'Purchase', 'Payment', 'Balance', 'Recorded By']);

                foreach ($data['rows'] as $row) {

                    fputcsv($out, array_values($row));

                }

                fclose($out);

            };



            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== VENDOR REQUESTS =====================



    /**

     * Get vendor requests list.

     */

    public function vendorRequestsData(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $status = $request->input('status');

            $requests = $this->vendorService->getVendorRequests($accountId, $status, $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $requests->items(),

                'meta' => [

                    'current_page' => $requests->currentPage(),

                    'last_page' => $requests->lastPage(),

                    'per_page' => $requests->perPage(),

                    'total' => $requests->total(),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Create a vendor request.

     */

    public function vendorRequestsStore(StoreVendorSuggestionRequest $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $vendorRequest = $this->vendorService->createVendorRequest($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $vendorRequest, 'message' => 'Vendor request submitted.']);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Approve a vendor request.

     */

    public function vendorRequestsApprove(int $id): JsonResponse

    {

        try {

            if (!Auth::user()->can('cashflow_vendor_manage')) {

                throw CashflowException::unauthorized('approve vendor requests');

            }



            $accountId = Auth::user()->account_id;

            $vendorRequest = $this->vendorService->approveVendorRequest($id, $accountId);



            return response()->json(['success' => true, 'data' => $vendorRequest, 'message' => 'Vendor request approved and vendor created.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Dismiss a vendor request.

     */

    public function vendorRequestsDismiss(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_vendor_manage')) {

                throw CashflowException::unauthorized('dismiss vendor requests');

            }



            $accountId = Auth::user()->account_id;

            $vendorRequest = $this->vendorService->dismissVendorRequest($id, $request->input('admin_notes'), $accountId);



            return response()->json(['success' => true, 'data' => $vendorRequest, 'message' => 'Vendor request dismissed.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== STAFF ADVANCES =====================



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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

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

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== CATEGORY REQUESTS =====================



    /**

     * Get category requests list.

     */

    public function categoryRequestsData(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $status = $request->input('status');

            $requests = $this->categoryService->getCategoryRequests($accountId, $status, $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $requests->items(),

                'meta' => [

                    'current_page' => $requests->currentPage(),

                    'last_page' => $requests->lastPage(),

                    'per_page' => $requests->perPage(),

                    'total' => $requests->total(),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Create a category suggestion/request.

     */

    public function categoryRequestsStore(StoreCategorySuggestionRequest $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $catRequest = $this->categoryService->createCategoryRequest($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $catRequest, 'message' => 'Category suggestion submitted.']);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Approve a category request.

     */

    public function categoryRequestsApprove(int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_category_manage')) {

                throw CashflowException::unauthorized('approve category requests');

            }



            $accountId = Auth::user()->account_id;

            $catRequest = $this->categoryService->approveCategoryRequest($id, $accountId);



            return response()->json(['success' => true, 'data' => $catRequest, 'message' => 'Category request approved and category created.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Dismiss a category request.

     */

    public function categoryRequestsDismiss(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_category_manage')) {

                throw CashflowException::unauthorized('dismiss category requests');

            }



            $accountId = Auth::user()->account_id;

            $catRequest = $this->categoryService->dismissCategoryRequest($id, $request->input('admin_notes'), $accountId);



            return response()->json(['success' => true, 'data' => $catRequest, 'message' => 'Category request dismissed.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== SETTINGS RESET =====================



    /**

     * Reset module (first month only, before any period lock).

     */

    public function settingsResetModule(Request $request): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('reset module');

            }



            $accountId = Auth::user()->account_id;



            // Check no period locks exist

            $hasLocks = \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists();

            if ($hasLocks) {

                throw new CashflowException('Module cannot be reset after a period has been locked.');

            }



            // Double confirmation

            if ($request->input('confirm') !== 'RESET') {

                return response()->json(['success' => false, 'message' => 'Type RESET to confirm module reset.'], 422);

            }



            \Illuminate\Support\Facades\DB::transaction(function () use ($accountId) {

                \App\Models\CashFlow\CashflowNotification::where('account_id', $accountId)->delete();

                \App\Models\CashFlow\StaffReturn::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\StaffAdvance::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\VendorTransaction::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\Expense::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\CashTransfer::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\Vendor::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\CashPool::where('account_id', $accountId)->update(['cached_balance' => \Illuminate\Support\Facades\DB::raw('opening_balance')]);



                $this->auditService->log(

                    \App\Models\CashFlow\CashflowAuditLog::ACTION_RESET,

                    \App\Models\CashFlow\CashflowAuditLog::ENTITY_MODULE,

                    0,

                    null,

                    ['action' => 'full_module_reset'],

                    'Full module reset performed'

                );

            });



            CashflowHelper::clearAllCaches($accountId);



            return response()->json(['success' => true, 'message' => 'Module has been reset. All transaction data cleared.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== AUDIT LOGS (Settings Viewer) =====================



    /**

     * Paginated audit logs for admin viewer.

     */

    public function auditLogs(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $query = \App\Models\CashFlow\CashflowAuditLog::where('account_id', $accountId)

                ->with('user:id,name')

                ->orderByDesc('id');



            if ($request->filled('entity_type')) {

                $query->where('entity_type', $request->input('entity_type'));

            }



            if ($request->filled('action')) {

                $query->where('action', $request->input('action'));

            }



            $perPage = min((int) $request->input('per_page', 25), 100);

            $logs = $query->paginate($perPage);



            return response()->json([

                'success' => true,

                'data' => $logs->items(),

                'meta' => [

                    'current_page' => $logs->currentPage(),

                    'last_page' => $logs->lastPage(),

                    'per_page' => $logs->perPage(),

                    'total' => $logs->total(),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== ADVANCE-ELIGIBLE STAFF (Sec 27.5) =====================



    /**

     * List all non-patient staff with their advance eligibility flag.

     */

    public function eligibleStaffList(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;



            $staff = \App\Models\User::where('account_id', $accountId)

                ->where('active', 1)

                ->whereNull('deleted_at')

                ->whereNotIn('user_type_id', [3])

                ->orderBy('name')

                ->get(['id', 'name', 'email', 'is_advance_eligible']);



            return response()->json(['success' => true, 'data' => $staff]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Toggle advance eligibility for a staff member.

     */

    public function toggleStaffEligibility(Request $request): JsonResponse

    {

        try {

            $request->validate([

                'user_id' => 'required|exists:users,id',

                'is_advance_eligible' => 'required|boolean',

            ]);



            $accountId = Auth::user()->account_id;

            $user = \App\Models\User::where('account_id', $accountId)->findOrFail($request->user_id);



            $user->update(['is_advance_eligible' => $request->is_advance_eligible]);



            $this->auditService->log(

                'updated',

                'user',

                $user->id,

                ['is_advance_eligible' => !$request->is_advance_eligible],

                ['is_advance_eligible' => (bool) $request->is_advance_eligible],

                'Advance eligibility toggled'

            );



            return response()->json([

                'success' => true,

                'message' => $user->name . ' advance eligibility ' . ($request->is_advance_eligible ? 'enabled' : 'disabled') . '.',

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    // ===================== DASHBOARD (Phase 4) =====================



    /**

     * Dashboard data endpoint.

     */

    public function dashboardData(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id']);



            $data = $this->dashboardService->getDashboardData($accountId, $filters);



            // Add accountant widgets if user is accountant

            if (Gate::allows('cashflow_expense_create') && !Gate::allows('cashflow_settings')) {

                $data['accountant_widgets'] = $this->dashboardService->getAccountantWidgets($accountId, Auth::id());

            }



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Reconciliation check (admin only).

     */

    public function dashboardReconciliation(): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('run reconciliation');

            }



            $accountId = Auth::user()->account_id;

            $result = $this->dashboardService->reconciliationCheck($accountId);



            return response()->json(['success' => true, 'data' => $result]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== FDM (Phase 4) =====================



    /**

     * FDM Cash View data — read-only, shows all assigned branches.

     * Returns: current balance, opening balance (Sunday), transfers, expenses, staff advances for the current week.

     */

    public function fdmData(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $userBranches = CashflowHelper::getUserBranches();

            if ($userBranches->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No branch assigned.'], 403);
            }

            // Return branches list for dropdown if requested
            if ($request->has('branches_only')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'branches' => $userBranches->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values(),
                    ],
                ]);
            }

            // Filter by single branch if provided
            $filterBranchId = $request->input('branch_id');
            if ($filterBranchId && $userBranches->pluck('id')->contains((int) $filterBranchId)) {
                $branchIds = [(int) $filterBranchId];
                $branchName = $userBranches->firstWhere('id', (int) $filterBranchId)->name;
            } else {
                $branchIds = $userBranches->pluck('id')->toArray();
                $branchName = $userBranches->count() === 1
                    ? $userBranches->first()->name
                    : 'All Centres';
            }

            // Get branch cash pools for selected branches
            $pools = \App\Models\CashFlow\CashPool::forAccount($accountId)
                ->whereIn('location_id', $branchIds)
                ->where('type', 'branch_cash')
                ->get();



            $poolIds = $pools->pluck('id')->toArray();

            $currentBalance = (float) $pools->sum('cached_balance');

            

            // Add inventory sales since go-live to current balance (cash only for branch pools)

            $goLiveDate = app(CashflowSettingService::class)->getGoLiveDate($accountId) ?? '2026-03-08';

            $branchPoolMap = $pools->pluck('id', 'location_id')->toArray();

            $inventoryCashSales = (float) Order::where('account_id', $accountId)
                ->where('order_type', 'sale')
                ->where('payment_mode', 1)
                ->whereIn('location_id', array_keys($branchPoolMap))
                ->where('created_at', '>=', $goLiveDate . ' 00:00:00')
                ->sum('total_price');

            $inventoryCashRefunds = (float) Order::where('account_id', $accountId)
                ->where('order_type', 'refund')
                ->where('payment_mode', 1)
                ->whereIn('location_id', array_keys($branchPoolMap))
                ->where('created_at', '>=', $goLiveDate . ' 00:00:00')
                ->sum('total_price');

            $currentBalance += $inventoryCashSales - $inventoryCashRefunds;

            // Week range: Sunday (start) to today - for opening balance calculation
            $sunday = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
            $today = \Carbon\Carbon::now()->toDateString();
            $sundayStart = $sunday . ' 00:00:00';
            $nowEnd = $today . ' 23:59:59';
            
            // Debug information
            $debugInfo = [
                'cached_balance' => (float) $pools->sum('cached_balance'),
                'inventory_cash_sales' => $inventoryCashSales,
                'inventory_cash_refunds' => $inventoryCashRefunds,
                'total_current_balance' => $currentBalance,
                'static_opening_balance' => (float) $pools->sum('opening_balance'),
                'current_date' => \Carbon\Carbon::now()->toDateTimeString(),
                'calculated_sunday' => $sunday,
                'calculated_today' => $today,
            ];

            // Last 7 days range - for transaction records display
            $sevenDaysAgo = \Carbon\Carbon::now()->subDays(6)->toDateString(); // 7 days including today
            $sevenDaysAgoStart = $sevenDaysAgo . ' 00:00:00';



            // ---- Individual records for the last 7 days ----



            // Cash Transfers (involving these pools)

            $transfers = [];

            if (!empty($poolIds)) {

                $transfers = \App\Models\CashFlow\CashTransfer::forAccount($accountId)

                    ->whereNull('voided_at')

                    ->where(function ($q) use ($poolIds) {

                        $q->whereIn('from_pool_id', $poolIds)

                          ->orWhereIn('to_pool_id', $poolIds);

                    })

                    ->whereBetween('transfer_date', [$sevenDaysAgo, $today])

                    ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name'])

                    ->orderBy('transfer_date', 'desc')

                    ->get()

                    ->map(function ($t) use ($poolIds) {

                        $direction = in_array($t->from_pool_id, $poolIds) ? 'out' : 'in';

                        return [

                            'id' => $t->id,

                            'date' => $t->transfer_date->format('Y-m-d'),

                            'amount' => (float) $t->amount,

                            'direction' => $direction,

                            'from_pool' => $t->fromPool ? $t->fromPool->name : 'N/A',

                            'to_pool' => $t->toPool ? $t->toPool->name : 'N/A',

                            'description' => $t->description,

                            'method' => $t->method,

                            'created_by' => $t->creator ? $t->creator->name : 'N/A',

                        ];

                    })->toArray();

            }



            // Expenses (paid from these pools)

            $expenses = [];

            if (!empty($poolIds)) {

                $expenses = \App\Models\CashFlow\Expense::forAccount($accountId)

                    ->whereNull('voided_at')

                    ->where('status', '!=', 'rejected')

                    ->whereIn('paid_from_pool_id', $poolIds)

                    ->whereBetween('expense_date', [$sevenDaysAgo, $today])

                    ->with(['category:id,name', 'paidFromPool:id,name'])

                    ->orderBy('expense_date', 'desc')

                    ->get()

                    ->map(function ($e) {

                        return [

                            'id' => $e->id,

                            'date' => $e->expense_date->format('Y-m-d'),

                            'amount' => (float) $e->amount,

                            'description' => $e->description,

                            'category' => $e->category ? $e->category->name : 'N/A',

                            'pool' => $e->paidFromPool ? $e->paidFromPool->name : 'N/A',

                            'status' => $e->status,

                        ];

                    })->toArray();

            }



            // Staff Advances (from these pools)

            $staffAdvances = [];

            if (!empty($poolIds)) {

                $staffAdvances = \App\Models\CashFlow\StaffAdvance::forAccount($accountId)

                    ->whereNull('voided_at')

                    ->whereIn('pool_id', $poolIds)

                    ->whereBetween('created_at', [$sevenDaysAgoStart, $nowEnd])

                    ->with(['staffUser:id,name', 'pool:id,name'])

                    ->orderBy('created_at', 'desc')

                    ->get()

                    ->map(function ($a) {

                        return [

                            'id' => $a->id,

                            'date' => $a->created_at->format('Y-m-d'),

                            'amount' => (float) $a->amount,

                            'staff_name' => $a->staffUser ? $a->staffUser->name : 'N/A',

                            'pool' => $a->pool ? $a->pool->name : 'N/A',

                            'description' => $a->description,

                        ];

                    })->toArray();

            }

            // Get cash payment mode IDs (needed for opening balance calculation)
            $cashModeIds = \App\Models\PaymentModes::where('active', 1)
                ->get()
                ->filter(fn($pm) => stripos($pm->name, 'cash') !== false)
                ->pluck('id')
                ->toArray();

            // ---- Calculate opening balance (last week's closing balance) ----
            // If we're in the first week since go-live, use the static opening balance
            // Otherwise, calculate what the balance was at the end of last Saturday
            $lastSaturday = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->subDay()->endOfDay();

            if ($lastSaturday->lt(\Carbon\Carbon::parse($goLiveDate))) {
                // We're in the first week, use static opening balance
                $openingBalance = (float) $pools->sum('opening_balance');
            } else {
                // Calculate balance at end of last Saturday
                // Start with static opening balance
                $openingBalance = (float) $pools->sum('opening_balance');
                
                // Add all transactions from go-live to end of last Saturday
                $branchPoolMap = $pools->pluck('id', 'location_id')->toArray();
                
                // Add package advances (services cash inflows)
                $servicesIn = 0;
                $servicesOut = 0;
                if (!empty($cashModeIds)) {
                    // Services inflows
                    $servicesIn = \App\Models\PackageAdvances::where('account_id', $accountId)
                        ->where('cash_flow', 'in')
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->whereIn('payment_mode_id', $cashModeIds)
                        ->whereIn('location_id', array_keys($branchPoolMap))
                        ->whereBetween('system_created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                        ->sum('cash_amount');
                    $openingBalance += (float) $servicesIn;
                    
                    // Services refunds (outflows)
                    $servicesOut = \App\Models\PackageAdvances::where('account_id', $accountId)
                        ->where('cash_flow', 'out')
                        ->where('is_refund', 1)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->whereIn('payment_mode_id', $cashModeIds)
                        ->whereIn('location_id', array_keys($branchPoolMap))
                        ->whereBetween('system_created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                        ->sum('cash_amount');
                    $openingBalance -= (float) $servicesOut;
                }
                
                // Add inventory cash sales
                $inventorySales = (float) Order::where('account_id', $accountId)
                    ->where('order_type', 'sale')
                    ->where('payment_mode', 1)
                    ->whereIn('location_id', array_keys($branchPoolMap))
                    ->whereBetween('created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                    ->sum('total_price');
                $openingBalance += $inventorySales;

                // Subtract inventory cash refunds
                $inventoryRefunds = (float) Order::where('account_id', $accountId)
                    ->where('order_type', 'refund')
                    ->where('payment_mode', 1)
                    ->whereIn('location_id', array_keys($branchPoolMap))
                    ->whereBetween('created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                    ->sum('total_price');
                $openingBalance -= $inventoryRefunds;
                
                // Subtract expenses
                $obExpenses = \App\Models\CashFlow\Expense::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->where('status', '!=', 'rejected')
                    ->whereIn('paid_from_pool_id', $poolIds)
                    ->whereBetween('expense_date', [\Carbon\Carbon::parse($goLiveDate)->toDateString(), $lastSaturday->toDateString()])
                    ->sum('amount');
                $openingBalance -= (float) $obExpenses;
                
                // Handle transfers
                $transfersOut = 0;
                $transfersIn = 0;
                $transfersOut = \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('from_pool_id', $poolIds)
                    ->whereBetween('transfer_date', [\Carbon\Carbon::parse($goLiveDate)->toDateString(), $lastSaturday->toDateString()])
                    ->sum('amount');
                $openingBalance -= (float) $transfersOut;
                
                $transfersIn = \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('to_pool_id', $poolIds)
                    ->whereBetween('transfer_date', [\Carbon\Carbon::parse($goLiveDate)->toDateString(), $lastSaturday->toDateString()])
                    ->sum('amount');
                $openingBalance += (float) $transfersIn;
                
                // Subtract staff advances
                $obStaffAdvances = \App\Models\CashFlow\StaffAdvance::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('pool_id', $poolIds)
                    ->whereBetween('created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                    ->sum('amount');
                $openingBalance -= (float) $obStaffAdvances;

                // Add staff returns
                $obStaffReturns = \App\Models\CashFlow\StaffReturn::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('pool_id', $poolIds)
                    ->whereBetween('created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                    ->sum('amount');
                $openingBalance += (float) $obStaffReturns;
                
                // Debug opening balance calculation
                $debugInfo['opening_balance_calc'] = [
                    'static_opening' => (float) $pools->sum('opening_balance'),
                    'services_in' => (float) $servicesIn,
                    'services_out' => (float) $servicesOut,
                    'inventory_sales' => $inventorySales,
                    'inventory_refunds' => $inventoryRefunds,
                    'expenses' => (float) $obExpenses,
                    'transfers_out' => (float) $transfersOut,
                    'transfers_in' => (float) $transfersIn,
                    'staff_advances' => (float) $obStaffAdvances,
                    'staff_returns' => (float) $obStaffReturns,
                    'calculated_opening' => $openingBalance,
                    'last_saturday' => $lastSaturday->toDateTimeString(),
                ];
            }

            // ---- Calculate current week card totals (all NET values) ----

            // Services Cash Inflows: NET = inflows - refunds
            $servicesCashInflows = 0;
            $servicesCashInflowsCount = 0;
            if (!empty($cashModeIds)) {
                // Inflows
                $svcInQuery = \App\Models\PackageAdvances::where('account_id', $accountId)
                    ->where('cash_flow', 'in')
                    ->where('is_cancel', 0)
                    ->whereNull('deleted_at')
                    ->where('cash_amount', '>', 0)
                    ->whereIn('payment_mode_id', $cashModeIds)
                    ->whereIn('location_id', array_keys($branchPoolMap))
                    ->whereDate('system_created_at', '>=', $sunday)
                    ->whereDate('system_created_at', '<=', $today);
                $svcIn = (float) $svcInQuery->sum('cash_amount');
                $svcInCount = $svcInQuery->count();

                // Refunds (outflows)
                $svcOutQuery = \App\Models\PackageAdvances::where('account_id', $accountId)
                    ->where('cash_flow', 'out')
                    ->where('is_refund', 1)
                    ->where('is_cancel', 0)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_mode_id', $cashModeIds)
                    ->whereIn('location_id', array_keys($branchPoolMap))
                    ->whereDate('system_created_at', '>=', $sunday)
                    ->whereDate('system_created_at', '<=', $today);
                $svcOut = (float) $svcOutQuery->sum('cash_amount');
                $svcOutCount = $svcOutQuery->count();

                $servicesCashInflows = $svcIn - $svcOut;
                $servicesCashInflowsCount = $svcInCount + $svcOutCount;
            }

            // Inventory Cash Inflows (sales minus refunds)
            $inventorySalesQuery = Order::where('account_id', $accountId)
                ->where('order_type', 'sale')
                ->where('payment_mode', 1)
                ->whereIn('location_id', array_keys($branchPoolMap))
                ->whereDate('created_at', '>=', $sunday)
                ->whereDate('created_at', '<=', $today);
            $inventorySalesAmount = (float) $inventorySalesQuery->sum('total_price');
            $inventorySalesCount = $inventorySalesQuery->count();

            $inventoryRefundsQuery = Order::where('account_id', $accountId)
                ->where('order_type', 'refund')
                ->where('payment_mode', 1)
                ->whereIn('location_id', array_keys($branchPoolMap))
                ->whereDate('created_at', '>=', $sunday)
                ->whereDate('created_at', '<=', $today);
            $inventoryRefundsAmount = (float) $inventoryRefundsQuery->sum('total_price');
            $inventoryRefundsCount = $inventoryRefundsQuery->count();

            $inventoryCashInflows = $inventorySalesAmount - $inventoryRefundsAmount;
            $inventoryCashInflowsCount = $inventorySalesCount + $inventoryRefundsCount;

            // Expenses Total (current week)
            $weekExpensesTotal = 0;
            $weekExpensesCount = 0;
            if (!empty($poolIds)) {
                $weekExpensesQuery = \App\Models\CashFlow\Expense::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->where('status', '!=', 'rejected')
                    ->whereIn('paid_from_pool_id', $poolIds)
                    ->whereBetween('expense_date', [$sunday, $today]);
                $weekExpensesTotal = (float) $weekExpensesQuery->sum('amount');
                $weekExpensesCount = $weekExpensesQuery->count();
            }

            // Cash Transfers Total: NET = out - in (current week)
            $weekTransfersTotal = 0;
            $weekTransfersCount = 0;
            if (!empty($poolIds)) {
                // Transfers OUT of branch pools
                $trfOutQuery = \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('from_pool_id', $poolIds)
                    ->whereBetween('transfer_date', [$sunday, $today]);
                $trfOut = (float) $trfOutQuery->sum('amount');
                $trfOutCount = $trfOutQuery->count();

                // Transfers IN to branch pools
                $trfInQuery = \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('to_pool_id', $poolIds)
                    ->whereBetween('transfer_date', [$sunday, $today]);
                $trfIn = (float) $trfInQuery->sum('amount');
                $trfInCount = $trfInQuery->count();

                $weekTransfersTotal = $trfOut - $trfIn;
                $weekTransfersCount = $trfOutCount + $trfInCount;
            }

            // Staff Advances Total: NET = advances - returns (current week)
            $weekAdvancesTotal = 0;
            $weekAdvancesCount = 0;
            if (!empty($poolIds)) {
                $advQuery = \App\Models\CashFlow\StaffAdvance::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('pool_id', $poolIds)
                    ->whereBetween('created_at', [$sundayStart, $nowEnd]);
                $advOut = (float) $advQuery->sum('amount');
                $advOutCount = $advQuery->count();

                $retQuery = \App\Models\CashFlow\StaffReturn::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('pool_id', $poolIds)
                    ->whereBetween('created_at', [$sundayStart, $nowEnd]);
                $retIn = (float) $retQuery->sum('amount');
                $retInCount = $retQuery->count();

                $weekAdvancesTotal = $advOut - $retIn;
                $weekAdvancesCount = $advOutCount + $retInCount;
            }

            // ---- Live Cash Balance = Opening + Services + Inventory - Expenses - Transfers - Advances ----
            $liveCashBalance = round($openingBalance, 2)
                + $servicesCashInflows
                + $inventoryCashInflows
                - $weekExpensesTotal
                - $weekTransfersTotal
                - $weekAdvancesTotal;

            return response()->json([

                'success' => true,

                'data' => [

                    'branch_name' => $branchName,

                    'pool_balance' => round($liveCashBalance, 2),

                    'opening_balance' => round($openingBalance, 2),

                    'week_start' => $sunday,

                    'records_period_start' => $sevenDaysAgo,

                    'records_period_end' => $today,

                    'services_cash_inflows' => (float) $servicesCashInflows,

                    'services_cash_inflows_count' => $servicesCashInflowsCount,

                    'inventory_cash_inflows' => (float) $inventoryCashInflows,

                    'inventory_cash_inflows_count' => $inventoryCashInflowsCount,

                    'week_expenses_total' => (float) $weekExpensesTotal,

                    'week_expenses_count' => $weekExpensesCount,

                    'week_transfers_total' => (float) $weekTransfersTotal,

                    'week_transfers_count' => $weekTransfersCount,

                    'week_advances_total' => (float) $weekAdvancesTotal,

                    'week_advances_count' => $weekAdvancesCount,

                    'transfers' => $transfers,

                    'expenses' => $expenses,

                    'staff_advances' => $staffAdvances,
                    
                    'debug' => array_merge($debugInfo, [
                        'live_balance_formula' => [
                            'opening_balance' => round($openingBalance, 2),
                            'services_net' => $servicesCashInflows,
                            'inventory' => $inventoryCashInflows,
                            'expenses' => $weekExpensesTotal,
                            'transfers_net' => $weekTransfersTotal,
                            'advances_net' => $weekAdvancesTotal,
                            'live_cash_balance' => round($liveCashBalance, 2),
                        ],
                    ]),

                ],

            ]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== REPORTS (Phase 4) =====================



    /**

     * Cash Flow Statement (primary report).

     */

    public function reportCashFlowStatement(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id', 'pool_id']);

            $data = $this->reportService->cashFlowStatement($accountId, $filters);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Branch comparison report.

     */

    public function reportBranchComparison(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->branchComparison($accountId, $request->only(['date_from', 'date_to']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Category trend report.

     */

    public function reportCategoryTrend(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->categoryTrend($accountId, $request->only(['months']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Vendor outstanding report.

     */

    public function reportVendorOutstanding(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->vendorOutstanding($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Staff advance summary report.

     */

    public function reportStaffAdvance(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->staffAdvanceSummary($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Daily cash movement report.

     */

    public function reportDailyMovement(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->dailyMovement($accountId, $request->only(['date_from', 'date_to', 'pool_id']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Transfer log report.

     */

    public function reportTransferLog(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->transferLog($accountId, $request->only(['date_from', 'date_to', 'pool_id']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Flagged entries report.

     */

    public function reportFlaggedEntries(Request $request): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->flaggedEntries($accountId, $request->only(['date_from', 'date_to']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Dormant vendors report.

     */

    public function reportDormantVendors(): JsonResponse

    {

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->dormantVendors($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Export report as CSV.

     */

    public function reportExport(Request $request, string $type)

    {

        try {

            if (!Gate::allows('cashflow_reports_export')) {

                throw CashflowException::unauthorized('export reports');

            }



            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id', 'pool_id', 'months']);



            return $this->exportService->exportCsv($type, $accountId, $filters);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    // ===================== PERIOD LOCKS (Phase 5) =====================



    /**

     * Get all period locks.

     */

    public function periodLocksData(): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('view period locks');

            }



            $accountId = Auth::user()->account_id;

            $data = $this->periodLockService->getLocks($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);

        }

    }



    /**

     * Lock a period.

     */

    public function periodLocksLock(Request $request): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('lock periods');

            }



            $request->validate([

                'month' => 'required|integer|between:1,12',

                'year' => 'required|integer|min:2020',

            ]);



            $accountId = Auth::user()->account_id;

            $lock = $this->periodLockService->lockPeriod(

                (int) $request->input('month'),

                (int) $request->input('year'),

                $accountId

            );



            return response()->json(['success' => true, 'data' => $lock, 'message' => 'Period locked successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }



    /**

     * Unlock a period (mandatory reason).

     */

    public function periodLocksUnlock(Request $request, int $id): JsonResponse

    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('unlock periods');

            }



            $request->validate([

                'reason' => 'required|string|min:5',

            ]);



            $accountId = Auth::user()->account_id;

            $lock = $this->periodLockService->unlockPeriod($id, $request->input('reason'), $accountId);



            return response()->json(['success' => true, 'data' => $lock, 'message' => 'Period unlocked.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        }

    }

}

