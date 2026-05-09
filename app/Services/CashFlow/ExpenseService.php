<?php

declare(strict_types=1);
namespace App\Services\CashFlow;

use App\Enums\ExpenseStatus;
use App\Enums\VendorTransactionType;
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
    public function __construct(
        private readonly CashflowAuditService $auditService,
        private readonly CashflowSettingService $settingService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Get paginated expenses with filters for datatable.
     */
    public function getExpenses(int $accountId, array $filters = [], int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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
                $query->where('status', ExpenseStatus::Pending)->where('created_by', Auth::id())->whereNull('voided_at');
            } elseif ($status === 'my_rejected') {
                $query->where('status', ExpenseStatus::Rejected)->where('created_by', Auth::id())->whereNull('voided_at');
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

        // Pool filter (paid from)
        if (!empty($filters['pool_id'])) {
            $query->where('paid_from_pool_id', $filters['pool_id']);
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
                (int) date('n', strtotime($data['expense_date'])),
                (int) date('Y', strtotime($data['expense_date']))
            );
        }

        // Determine status based on threshold
        $threshold = $this->settingService->getApprovalThreshold($accountId);
        $status = (float) $data['amount'] <= $threshold
            ? ExpenseStatus::Approved
            : ExpenseStatus::Pending;

        return DB::transaction(function () use ($data, $accountId, $user, $status) {
            $expense = Expense::create([
                'account_id' => $accountId,
                'expense_date' => $data['expense_date'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'],
                'paid_from_pool_id' => $data['paid_from_pool_id'] ?? null,
                'for_branch_id' => !empty($data['is_for_general']) ? null : ($data['for_branch_id'] ?? null),
                'payment_method_id' => $data['payment_method_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'description' => $data['description'],
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'attachment_image' => $data['attachment_image'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'verified_by' => $status === ExpenseStatus::Approved ? $user->id : null,
                'is_flagged' => 0,
                'is_for_general' => !empty($data['is_for_general']) ? 1 : 0,
                'created_by' => $user->id,
            ]);

            // Auto-create vendor payment transaction if vendor is selected and expense is upfront
            if ($expense->vendor_id) {
                VendorTransaction::create([
                    'account_id' => $accountId,
                    'vendor_id' => $expense->vendor_id,
                    'type' => VendorTransactionType::Payment,
                    'amount' => $expense->amount,
                    'expense_id' => $expense->id,
                    'description' => 'Payment via expense #' . $expense->id,
                    'reference_no' => $expense->reference_no,
                    'transaction_date' => $expense->expense_date->format('Y-m-d'),
                    'for_branch_id' => $expense->for_branch_id,
                    'is_for_general' => $expense->is_for_general ? 1 : 0,
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
            if ($status === ExpenseStatus::Pending) {
                $this->notificationService->notifyExpensePending($expense, $accountId);
            }

            // Notify branch manager when expense recorded for their branch
            $this->notificationService->notifyExpenseForBranch($expense, $accountId);

            // Check for negative pool and notify
            $pool = $expense->paid_from_pool_id ? \App\Models\CashFlow\CashPool::find($expense->paid_from_pool_id) : null;
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

        // Attachment must be present before approval (Sec 5.2). Either the
        // Drive URL or the uploaded image satisfies the rule.
        if (empty($expense->attachment_url) && empty($expense->attachment_image)) {
            throw new CashflowException('Cannot approve: attachment must be present before approval.');
        }

        $oldValues = $expense->only(['status', 'verified_by']);

        $updateData = [
            'status' => ExpenseStatus::Approved,
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
            ['status' => ExpenseStatus::Approved, 'verified_by' => Auth::id()]
        );

        $this->notificationService->notifyExpenseApproved($expense);

        return $expense->fresh();
    }

    /**
     * Reject an expense (reverses pool deduction via observer, reverses vendor balance here).
     */
    public function reject(int $expenseId, string $reason, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if (!$expense->isPending()) {
            throw new CashflowException('Only pending expenses can be rejected.');
        }

        $oldValues = $expense->only(['status', 'verified_by', 'rejection_reason']);

        return DB::transaction(function () use ($expense, $reason, $oldValues) {
            // Reverse vendor transaction if exists
            $vendorTx = $expense->vendorTransaction;
            if ($vendorTx) {
                DB::table('cashflow_vendors')
                    ->where('id', $expense->vendor_id)
                    ->increment('cached_balance', $vendorTx->amount);
                $vendorTx->delete();
            }

            $expense->update([
                'status' => ExpenseStatus::Rejected,
                'verified_by' => Auth::id(),
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_REJECTED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                ['status' => ExpenseStatus::Rejected, 'rejection_reason' => $reason]
            );

            $this->notificationService->notifyExpenseRejected($expense);

            return $expense->fresh();
        });
    }

    /**
     * Resubmit a rejected expense (with optional edits).
     */
    public function resubmit(int $expenseId, array $data, int $accountId): Expense
    {
        $auditRelations = ['category:id,name', 'paidFromPool:id,name', 'paymentMethod:id,name', 'forBranch:id,name', 'vendor:id,name', 'staff:id,name'];
        $expense = Expense::forAccount($accountId)->with($auditRelations)->findOrFail($expenseId);

        if (!$expense->isRejected()) {
            throw new CashflowException('Only rejected expenses can be resubmitted.');
        }

        $oldValues = $expense->toArray();
        $oldVendorId = $expense->vendor_id;

        $updateData = [
            'status' => ExpenseStatus::Pending,
            'verified_by' => null,
            'rejection_reason' => null,
        ];

        // Allow updating all editable fields on resubmit
        $allowed = ['expense_date', 'amount', 'category_id', 'paid_from_pool_id', 'payment_method_id', 'description', 'reference_no', 'attachment_url', 'attachment_image', 'notes'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '' && $data[$field] !== null) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle merged branch/general field
        if (!empty($data['is_for_general'])) {
            $updateData['is_for_general'] = true;
            $updateData['for_branch_id'] = null;
        } elseif (isset($data['for_branch_id'])) {
            $updateData['is_for_general'] = false;
            $updateData['for_branch_id'] = $data['for_branch_id'] ?: null;
        }

        // Handle vendor_id (allow clearing)
        if (array_key_exists('vendor_id', $data)) {
            $updateData['vendor_id'] = $data['vendor_id'] ?: null;
        }

        // Handle staff_id (allow clearing when switching to pool)
        if (array_key_exists('staff_id', $data)) {
            $updateData['staff_id'] = $data['staff_id'] ?: null;
        }
        if (!empty($data['staff_id'])) {
            $updateData['paid_from_pool_id'] = null;
        }

        return DB::transaction(function () use ($expense, $updateData, $oldValues, $oldVendorId, $accountId, $auditRelations) {
            // Update fields first (before status change triggers observer pool debit)
            $fieldsOnly = $updateData;
            unset($fieldsOnly['status'], $fieldsOnly['verified_by'], $fieldsOnly['rejection_reason']);
            if (!empty($fieldsOnly)) {
                $expense->updateQuietly($fieldsOnly);
                $expense->refresh();
            }

            // Now set status to pending — observer will re-debit pool with updated amount/pool
            $expense->update([
                'status' => ExpenseStatus::Pending,
                'verified_by' => null,
                'rejection_reason' => null,
            ]);

            $freshExpense = Expense::with($auditRelations)->find($expense->id);
            $newVendorId = $freshExpense->vendor_id;

            // Sync vendor transaction
            $existingVendorTx = VendorTransaction::where('expense_id', $expense->id)->whereNull('deleted_at')->first();

            if ($oldVendorId && !$newVendorId) {
                // Vendor removed: delete any leftover transaction (balance was already reversed on rejection)
                if ($existingVendorTx) {
                    $existingVendorTx->delete();
                }
            } elseif ($newVendorId && !$existingVendorTx) {
                // Vendor exists but no transaction (was deleted on rejection): re-create
                VendorTransaction::create([
                    'account_id' => $accountId,
                    'vendor_id' => $newVendorId,
                    'type' => VendorTransactionType::Payment,
                    'amount' => $freshExpense->amount,
                    'expense_id' => $expense->id,
                    'description' => 'Payment via expense #' . $expense->id,
                    'reference_no' => $freshExpense->reference_no,
                    'transaction_date' => $freshExpense->expense_date->format('Y-m-d'),
                    'for_branch_id' => $freshExpense->for_branch_id,
                    'is_for_general' => $freshExpense->is_for_general ? 1 : 0,
                    'created_by' => Auth::id(),
                ]);
            }

            $this->auditService->log(
                CashflowAuditLog::ACTION_RESUBMITTED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                $freshExpense->toArray()
            );

            $this->notificationService->notifyExpensePending($freshExpense, $accountId);

            return $freshExpense;
        });
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
        $oldVendorId = $expense->vendor_id;
        $oldAmount = (float) $expense->amount;

        $allowed = ['expense_date', 'amount', 'category_id', 'paid_from_pool_id', 'payment_method_id', 'description', 'reference_no', 'attachment_url', 'attachment_image', 'notes', 'vendor_id', 'staff_id'];
        $updateData = ['edit_reason' => $data['edit_reason']];

        // Handle merged branch/general field
        if (!empty($data['is_for_general'])) {
            $updateData['is_for_general'] = true;
            $updateData['for_branch_id'] = null;
        } elseif (isset($data['for_branch_id'])) {
            $updateData['is_for_general'] = false;
            $updateData['for_branch_id'] = $data['for_branch_id'] ?: null;
        }

        // Handle vendor_id (allow clearing)
        if (array_key_exists('vendor_id', $data)) {
            $updateData['vendor_id'] = $data['vendor_id'] ?: null;
        }

        // Handle staff_id (allow clearing when switching to pool)
        if (array_key_exists('staff_id', $data)) {
            $updateData['staff_id'] = $data['staff_id'] ?: null;
        }
        // If staff is selected, clear pool; if pool is selected, clear staff
        if (!empty($data['staff_id'])) {
            $updateData['paid_from_pool_id'] = null;
        }

        foreach ($allowed as $field) {
            if ($field === 'vendor_id' || $field === 'staff_id') continue; // handled above
            if (array_key_exists($field, $data)) {
                $updateData[$field] = ($data[$field] !== '' && $data[$field] !== null) ? $data[$field] : null;
            }
        }

        return DB::transaction(function () use ($expense, $updateData, $auditRelations, $oldValues, $oldVendorId, $oldAmount, $data, $accountId) {
            $expense->update($updateData);

            $newVendorId = $expense->fresh()->vendor_id;
            $newAmount = (float) $expense->fresh()->amount;

            // Sync vendor transaction when vendor or amount changes
            $existingVendorTx = VendorTransaction::where('expense_id', $expense->id)->whereNull('deleted_at')->first();

            if ($oldVendorId && !$newVendorId) {
                // Vendor removed: reverse old vendor balance and delete transaction
                if ($existingVendorTx) {
                    DB::table('cashflow_vendors')->where('id', $oldVendorId)
                        ->increment('cached_balance', $existingVendorTx->amount);
                    $existingVendorTx->delete();
                }
            } elseif (!$oldVendorId && $newVendorId) {
                // Vendor added: create new vendor transaction
                $freshExpense = $expense->fresh();
                VendorTransaction::create([
                    'account_id' => $accountId,
                    'vendor_id' => $newVendorId,
                    'type' => VendorTransactionType::Payment,
                    'amount' => $newAmount,
                    'expense_id' => $expense->id,
                    'description' => 'Payment via expense #' . $expense->id,
                    'reference_no' => $freshExpense->reference_no,
                    'transaction_date' => $freshExpense->expense_date->format('Y-m-d'),
                    'for_branch_id' => $freshExpense->for_branch_id,
                    'is_for_general' => $freshExpense->is_for_general ? 1 : 0,
                    'created_by' => \Illuminate\Support\Facades\Auth::id(),
                ]);
                // Observer handles balance decrement for new vendor
            } elseif ($oldVendorId && $newVendorId) {
                if ($oldVendorId != $newVendorId) {
                    // Vendor changed: reverse old, apply to new
                    if ($existingVendorTx) {
                        // Reverse old vendor balance
                        DB::table('cashflow_vendors')->where('id', $oldVendorId)
                            ->increment('cached_balance', $existingVendorTx->amount);

                        // Update transaction to new vendor with new amount
                        $existingVendorTx->update([
                            'vendor_id' => $newVendorId,
                            'amount' => $newAmount,
                            'description' => 'Payment via expense #' . $expense->id,
                            'reference_no' => $expense->fresh()->reference_no,
                        ]);

                        // Decrement new vendor balance
                        DB::table('cashflow_vendors')->where('id', $newVendorId)
                            ->decrement('cached_balance', $newAmount);
                    }
                } elseif ($oldAmount != $newAmount) {
                    // Same vendor, amount changed: adjust balance delta
                    if ($existingVendorTx) {
                        $amountDiff = $newAmount - (float) $existingVendorTx->amount;
                        $existingVendorTx->update(['amount' => $newAmount]);

                        if ($amountDiff > 0) {
                            DB::table('cashflow_vendors')->where('id', $newVendorId)
                                ->decrement('cached_balance', $amountDiff);
                        } elseif ($amountDiff < 0) {
                            DB::table('cashflow_vendors')->where('id', $newVendorId)
                                ->increment('cached_balance', abs($amountDiff));
                        }
                    }
                }
            }

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
        });
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

        $oldValues = $expense->only(['voided_at', 'voided_by', 'void_reason']);

        return DB::transaction(function () use ($expense, $reason, $accountId, $oldValues) {
            // Reverse pool balance: increment pool (give money back)
            if ($expense->status !== ExpenseStatus::Rejected && $expense->paid_from_pool_id) {
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

        // Cash payment without any attachment (Drive URL or uploaded image)
        $paymentMethod = $expense->paymentMethod;
        if ($paymentMethod
            && strtolower($paymentMethod->name) === 'cash'
            && empty($expense->attachment_url)
            && empty($expense->attachment_image)) {
            $flags[] = 'Cash payment without receipt attachment';
        }

        // Self-approval check
        if ($expense->status === ExpenseStatus::Approved && $expense->created_by === $expense->verified_by) {
            $flags[] = 'Self-approved expense';
        }

        // Daily splitting check
        $dailyLimit = (float) $this->settingService->get('daily_auto_approved_limit', $accountId, 50000);
        $dailyTotal = Expense::forAccount($accountId)
            ->where('expense_date', $expense->expense_date)
            ->where('status', ExpenseStatus::Approved)
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
        $pool = $expense->paid_from_pool_id ? CashPool::find($expense->paid_from_pool_id) : null;
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
