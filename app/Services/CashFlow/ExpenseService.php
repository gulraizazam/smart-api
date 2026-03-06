<?php

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    private CashflowAuditService $auditService;
    private CashflowSettingService $settingService;
    private NotificationService $notificationService;

    public function __construct(
        CashflowAuditService $auditService,
        CashflowSettingService $settingService,
        NotificationService $notificationService
    ) {
        $this->auditService = $auditService;
        $this->settingService = $settingService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get paginated expenses with filters for datatable.
     */
    public function getExpenses(int $accountId, array $filters = [], int $perPage = 25)
    {
        $query = Expense::forAccount($accountId)
            ->with([
                'category:id,name',
                'paidFromPool:id,name,type',
                'forBranch:id,name',
                'paymentMethod:id,name',
                'vendor:id,name',
                'staff:id,name',
                'creator:id,name',
                'verifier:id,name',
                'lastEditLog',
            ])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        // Status filter (including special filters)
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'flagged') {
                $query->where('is_flagged', true)->whereNull('voided_at');
            } elseif ($status === 'voided') {
                $query->whereNotNull('voided_at');
            } elseif ($status === 'edited') {
                $query->whereNotNull('edit_reason')->whereNull('voided_at');
            } elseif ($status === 'my_pending') {
                $query->where('status', 'pending')->where('created_by', Auth::id())->whereNull('voided_at');
            } elseif ($status === 'my_rejected') {
                $query->where('status', 'rejected')->where('created_by', Auth::id())->whereNull('voided_at');
            } else {
                $query->where('status', $status)->whereNull('voided_at');
            }
        }

        // Branch filter
        if (!empty($filters['branch_id'])) {
            if ($filters['branch_id'] === 'general') {
                $query->where('is_for_general', 1);
            } else {
                $query->where('for_branch_id', $filters['branch_id']);
            }
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Date range
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }

        // Flagged only
        if (!empty($filters['flagged'])) {
            $query->flagged();
        }

        // Voided filter
        if (isset($filters['voided'])) {
            if ($filters['voided']) {
                $query->voided();
            } else {
                $query->notVoided();
            }
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vq) use ($search) {
                        $vq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new expense.
     */
    public function create(array $data, int $accountId): Expense
    {
        $user = Auth::user();

        // Check period lock
        if (CashflowHelper::isDateInLockedPeriod($data['expense_date'], $accountId)) {
            throw CashflowException::periodLocked(
                date('n', strtotime($data['expense_date'])),
                date('Y', strtotime($data['expense_date']))
            );
        }

        // Determine status based on threshold
        $threshold = $this->settingService->getApprovalThreshold($accountId);
        $status = (float) $data['amount'] <= $threshold
            ? Expense::STATUS_APPROVED
            : Expense::STATUS_PENDING;

        return DB::transaction(function () use ($data, $accountId, $user, $status) {
            $expense = Expense::create([
                'account_id' => $accountId,
                'expense_date' => $data['expense_date'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'],
                'paid_from_pool_id' => $data['paid_from_pool_id'],
                'for_branch_id' => !empty($data['is_for_general']) ? null : ($data['for_branch_id'] ?? null),
                'payment_method_id' => $data['payment_method_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'description' => $data['description'],
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'verified_by' => $status === Expense::STATUS_APPROVED ? $user->id : null,
                'is_flagged' => 0,
                'is_for_general' => !empty($data['is_for_general']) ? 1 : 0,
                'created_by' => $user->id,
            ]);

            // Auto-create vendor payment transaction if vendor is selected and expense is upfront
            if ($expense->vendor_id) {
                VendorTransaction::create([
                    'account_id' => $accountId,
                    'vendor_id' => $expense->vendor_id,
                    'type' => VendorTransaction::TYPE_PAYMENT,
                    'amount' => $expense->amount,
                    'expense_id' => $expense->id,
                    'description' => 'Payment via expense #' . $expense->id,
                    'reference_no' => $expense->reference_no,
                    'created_by' => $user->id,
                ]);
            }

            // Run flagging checks
            $this->checkAndFlag($expense, $accountId);

            $this->auditService->log(
                CashflowAuditLog::ACTION_CREATED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                null,
                $expense->toArray()
            );

            // Notify admins if pending
            if ($status === Expense::STATUS_PENDING) {
                $this->notificationService->notifyExpensePending($expense, $accountId);
            }

            // Notify branch manager when expense recorded for their branch
            $this->notificationService->notifyExpenseForBranch($expense, $accountId);

            // Check for negative pool and notify
            $pool = \App\Models\CashFlow\CashPool::find($expense->paid_from_pool_id);
            if ($pool && (float) $pool->cached_balance < 0) {
                $this->notificationService->notifyNegativePool(
                    $pool->name,
                    (float) $pool->cached_balance,
                    $pool->location_id,
                    $accountId
                );
            }

            return $expense->load([
                'category:id,name', 'paidFromPool:id,name', 'forBranch:id,name',
                'paymentMethod:id,name', 'vendor:id,name', 'creator:id,name',
            ]);
        });
    }

    /**
     * Approve an expense.
     */
    public function approve(int $expenseId, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if (!$expense->isPending()) {
            throw new CashflowException('Only pending expenses can be approved.');
        }

        // Attachment must be present before approval (Sec 5.2)
        if (empty($expense->attachment_url)) {
            throw new CashflowException('Cannot approve: attachment must be present before approval.');
        }

        $oldValues = $expense->only(['status', 'verified_by']);

        $updateData = [
            'status' => Expense::STATUS_APPROVED,
            'verified_by' => Auth::id(),
        ];

        // Auto-flag admin self-approval (Sec 11.1)
        if ($expense->created_by === Auth::id()) {
            $updateData['is_flagged'] = true;
            $updateData['flag_reason'] = 'Self-approved by admin';
        }

        $expense->update($updateData);

        $this->auditService->log(
            CashflowAuditLog::ACTION_APPROVED,
            CashflowAuditLog::ENTITY_EXPENSE,
            $expense->id,
            $oldValues,
            ['status' => Expense::STATUS_APPROVED, 'verified_by' => Auth::id()]
        );

        $this->notificationService->notifyExpenseApproved($expense);

        return $expense->fresh();
    }

    /**
     * Reject an expense (reverses pool deduction via observer).
     */
    public function reject(int $expenseId, string $reason, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if (!$expense->isPending()) {
            throw new CashflowException('Only pending expenses can be rejected.');
        }

        $oldValues = $expense->only(['status', 'verified_by', 'rejection_reason']);

        $expense->update([
            'status' => Expense::STATUS_REJECTED,
            'verified_by' => Auth::id(),
            'rejection_reason' => $reason,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_REJECTED,
            CashflowAuditLog::ENTITY_EXPENSE,
            $expense->id,
            $oldValues,
            ['status' => Expense::STATUS_REJECTED, 'rejection_reason' => $reason]
        );

        $this->notificationService->notifyExpenseRejected($expense);

        return $expense->fresh();
    }

    /**
     * Resubmit a rejected expense.
     */
    public function resubmit(int $expenseId, array $data, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if (!$expense->isRejected()) {
            throw new CashflowException('Only rejected expenses can be resubmitted.');
        }

        $oldValues = $expense->toArray();

        $updateData = [
            'status' => Expense::STATUS_PENDING,
            'verified_by' => null,
            'rejection_reason' => null,
        ];

        // Allow updating details on resubmit
        foreach (['description', 'reference_no', 'attachment_url', 'notes'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $expense->update($updateData);

        $this->auditService->log(
            CashflowAuditLog::ACTION_RESUBMITTED,
            CashflowAuditLog::ENTITY_EXPENSE,
            $expense->id,
            $oldValues,
            $expense->fresh()->toArray()
        );

        $this->notificationService->notifyExpensePending($expense->fresh(), $accountId);

        return $expense->fresh();
    }

    /**
     * Admin edit of an expense (requires reason).
     */
    public function adminEdit(int $expenseId, array $data, int $accountId): Expense
    {
        $auditRelations = ['category:id,name', 'paidFromPool:id,name', 'paymentMethod:id,name', 'forBranch:id,name', 'vendor:id,name', 'staff:id,name'];
        $expense = Expense::forAccount($accountId)->with($auditRelations)->findOrFail($expenseId);

        if ($expense->isVoided()) {
            throw new CashflowException('Voided expenses cannot be edited.');
        }

        if (CashflowHelper::isDateInLockedPeriod($expense->expense_date->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked($expense->expense_date->month, $expense->expense_date->year);
        }

        $oldValues = $expense->toArray();

        $allowed = ['amount', 'category_id', 'paid_from_pool_id', 'payment_method_id', 'description', 'reference_no', 'attachment_url', 'notes'];
        $updateData = ['edit_reason' => $data['edit_reason']];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $expense->update($updateData);

        $newValues = $expense->fresh()->load($auditRelations)->toArray();

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_EXPENSE,
            $expense->id,
            $oldValues,
            $newValues,
            $data['edit_reason']
        );

        return $expense->fresh();
    }

    /**
     * Void an expense (requires reason, min 10 chars).
     */
    public function void(int $expenseId, string $reason, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if ($expense->isVoided()) {
            throw new CashflowException('Expense is already voided.');
        }

        if (CashflowHelper::isDateInLockedPeriod($expense->expense_date->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked($expense->expense_date->month, $expense->expense_date->year);
        }

        $oldValues = $expense->toArray();

        return DB::transaction(function () use ($expense, $reason, $accountId, $oldValues) {
            // Reverse pool balance: increment pool (give money back)
            if ($expense->status !== Expense::STATUS_REJECTED) {
                DB::table('cash_pools')
                    ->where('id', $expense->paid_from_pool_id)
                    ->increment('cached_balance', $expense->amount);
            }

            // Reverse vendor transaction if exists
            $vendorTx = $expense->vendorTransaction;
            if ($vendorTx) {
                DB::table('cashflow_vendors')
                    ->where('id', $expense->vendor_id)
                    ->increment('cached_balance', $vendorTx->amount);
                $vendorTx->delete();
            }

            $expense->update([
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_VOIDED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                ['voided_at' => now()->toDateTimeString(), 'void_reason' => $reason],
                $reason
            );

            return $expense->fresh();
        });
    }

    /**
     * Check and apply auto-flags to an expense.
     */
    private function checkAndFlag(Expense $expense, int $accountId): void
    {
        $flags = [];

        // Backdated check
        $backdateDays = (int) $this->settingService->get('backdate_flag_days', $accountId, 7);
        $daysDiff = now()->startOfDay()->diffInDays($expense->expense_date, false);
        if ($daysDiff < -$backdateDays) {
            $flags[] = 'Backdated by ' . abs($daysDiff) . ' days';
        }

        // Cash payment without attachment
        $paymentMethod = $expense->paymentMethod;
        if ($paymentMethod && strtolower($paymentMethod->name) === 'cash' && empty($expense->attachment_url)) {
            $flags[] = 'Cash payment without receipt attachment';
        }

        // Self-approval check
        if ($expense->status === Expense::STATUS_APPROVED && $expense->created_by === $expense->verified_by) {
            $flags[] = 'Self-approved expense';
        }

        // Daily splitting check
        $dailyLimit = (float) $this->settingService->get('daily_auto_approved_limit', $accountId, 50000);
        $dailyTotal = Expense::forAccount($accountId)
            ->where('expense_date', $expense->expense_date)
            ->where('status', Expense::STATUS_APPROVED)
            ->where('created_by', $expense->created_by)
            ->sum('amount');

        if ($dailyTotal > $dailyLimit) {
            $flags[] = 'Daily auto-approved total exceeds ' . CashflowHelper::formatCurrency($dailyLimit);
        }

        // Duplicate vendor payment: same vendor + same amount within 24hrs (Sec 11.2)
        if ($expense->vendor_id) {
            $duplicateExists = Expense::forAccount($accountId)
                ->where('vendor_id', $expense->vendor_id)
                ->where('amount', $expense->amount)
                ->where('id', '!=', $expense->id)
                ->where('created_at', '>=', now()->subHours(24))
                ->whereNull('voided_at')
                ->exists();

            if ($duplicateExists) {
                $flags[] = 'Potential duplicate: same vendor + amount within 24 hours';
            }
        }

        // Vendor overpayment: payment exceeds outstanding balance (Sec 11.2)
        if ($expense->vendor_id) {
            $vendor = \App\Models\CashFlow\Vendor::find($expense->vendor_id);
            if ($vendor && $vendor->cached_balance < 0) {
                $flags[] = 'Vendor overpayment: exceeds vendor balance';
            }
        }

        // Perfect-match advance: expenses exactly equal advance, zero return (Sec 8.3/11.2)
        if ($expense->staff_id) {
            $totalAdvances = \App\Models\CashFlow\StaffAdvance::where('account_id', $accountId)
                ->where('user_id', $expense->staff_id)->whereNull('deleted_at')->sum('amount');
            $totalReturns = \App\Models\CashFlow\StaffReturn::where('account_id', $accountId)
                ->where('user_id', $expense->staff_id)->whereNull('deleted_at')->sum('amount');
            $totalExpenses = Expense::forAccount($accountId)->whereNull('voided_at')
                ->where('staff_id', $expense->staff_id)->sum('amount');

            if ($totalAdvances > 0 && abs($totalAdvances - $totalExpenses) < 1 && $totalReturns == 0) {
                $flags[] = 'Advance fully spent with zero return';
            }
        }

        // Vendor Pending: high-vendor category but no vendor selected (Sec 5.4/11.2)
        if (!$expense->vendor_id && $expense->category) {
            $cat = $expense->category;
            if ($cat->vendor_emphasis) {
                $flags[] = 'Vendor pending';
            }
        }

        // Negative pool balance (Sec 11.2)
        $pool = CashPool::find($expense->paid_from_pool_id);
        if ($pool && $pool->cached_balance < 0) {
            $flags[] = 'Negative pool balance after this expense';
        }

        if (!empty($flags)) {
            $expense->update([
                'is_flagged' => 1,
                'flag_reason' => implode('; ', $flags),
            ]);
        }
    }

    /**
     * Get expense counts by status for dashboard widgets.
     */
    public function getStatusCounts(int $accountId): array
    {
        return [
            'pending' => Expense::forAccount($accountId)->pending()->notVoided()->count(),
            'approved' => Expense::forAccount($accountId)->approved()->notVoided()->count(),
            'rejected' => Expense::forAccount($accountId)->rejected()->notVoided()->count(),
            'flagged' => Expense::forAccount($accountId)->flagged()->notVoided()->count(),
        ];
    }
}
