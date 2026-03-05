<?php

namespace App\Services\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FlaggingService
{
    private CashflowSettingService $settingService;

    public function __construct(CashflowSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Run all applicable flag checks on an expense after create/update.
     * Returns array of flag reasons (empty if clean).
     */
    public function flagExpense(Expense $expense): array
    {
        $accountId = $expense->account_id;
        $reasons = [];

        // 1. Backdated entry
        $backdateDays = (int) $this->settingService->get('backdate_flag_days', $accountId, 7);
        $daysDiff = Carbon::parse($expense->created_at)->diffInDays(Carbon::parse($expense->expense_date), false);
        if ($daysDiff < -$backdateDays) {
            $reasons[] = 'Entry created ' . abs($daysDiff) . ' days after expense date';
        }

        // 2. No attachment for cash expense
        if ($this->isCashPayment($expense) && empty($expense->attachment_url)) {
            $reasons[] = 'No receipt for cash expense';
        }

        // 3. Duplicate payment: same vendor + same amount within 24hrs
        if ($expense->vendor_id) {
            $duplicate = Expense::forAccount($accountId)
                ->where('id', '!=', $expense->id)
                ->where('vendor_id', $expense->vendor_id)
                ->where('amount', $expense->amount)
                ->whereNull('voided_at')
                ->where('created_at', '>=', Carbon::parse($expense->created_at)->subHours(24))
                ->exists();

            if ($duplicate) {
                $reasons[] = 'Potential duplicate — same vendor + amount within 24 hours';
            }
        }

        // 4. Admin self-approval
        if ($expense->status === Expense::STATUS_APPROVED
            && $expense->verified_by
            && $expense->verified_by === $expense->created_by) {
            $reasons[] = 'Self-approved by admin';
        }

        // 5. Vendor pending (expense saved without vendor when category has vendor emphasis)
        if (!$expense->vendor_id && $expense->category && $expense->category->vendor_emphasis) {
            $reasons[] = 'Vendor pending — high-vendor category without vendor';
        }

        // 6. Vendor overpayment
        if ($expense->vendor_id) {
            $vendor = Vendor::find($expense->vendor_id);
            if ($vendor && (float) $vendor->cached_balance < 0) {
                $reasons[] = 'Exceeds vendor balance — overpayment';
            }
        }

        // 7. Daily splitting: total auto-approved in one day > threshold
        $dailyLimit = (float) $this->settingService->get('daily_auto_approved_limit', $accountId, 50000);
        $approvalThreshold = (float) $this->settingService->get('approval_threshold', $accountId, 10000);

        $dailyTotal = (float) Expense::forAccount($accountId)
            ->whereDate('expense_date', $expense->expense_date)
            ->where('status', Expense::STATUS_APPROVED)
            ->where('amount', '<', $approvalThreshold)
            ->whereNull('voided_at')
            ->sum('amount');

        if ($dailyTotal > $dailyLimit) {
            $reasons[] = 'High daily auto-approved total — PKR ' . number_format($dailyTotal, 0);
        }

        // Apply flags
        if (!empty($reasons)) {
            $expense->update([
                'is_flagged' => true,
                'flag_reason' => implode('; ', $reasons),
            ]);
        }

        return $reasons;
    }

    /**
     * Check negative pool balance after any transaction.
     */
    public function checkNegativePool(int $poolId, int $accountId): ?string
    {
        $pool = CashPool::find($poolId);
        if ($pool && (float) $pool->cached_balance < 0) {
            return 'Negative pool balance — ' . $pool->name . ': PKR ' . number_format($pool->cached_balance, 0);
        }
        return null;
    }

    /**
     * Check advance perfect-match: expenses = advance, zero return.
     * Called when a staff advance is fully expensed.
     */
    public function checkAdvancePerfectMatch(int $userId, int $accountId): ?string
    {
        $totalAdvances = (float) StaffAdvance::where('account_id', $accountId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->sum('amount');

        $totalReturns = (float) StaffReturn::where('account_id', $accountId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->sum('amount');

        $totalExpenses = (float) Expense::forAccount($accountId)
            ->where('staff_id', $userId)
            ->whereNull('voided_at')
            ->sum('amount');

        $outstanding = $totalAdvances - $totalExpenses - $totalReturns;

        // Perfect match: outstanding is zero (or near zero) and returns are zero
        if (abs($outstanding) < 1 && $totalReturns < 1 && $totalAdvances > 0) {
            return 'Advance fully spent, no return — staff ID ' . $userId;
        }

        return null;
    }

    /**
     * Batch flag check: run all daily checks (for scheduled job or manual trigger).
     */
    public function runDailyFlagChecks(int $accountId): array
    {
        $flags = [];

        // Check all negative pools
        $pools = CashPool::forAccount($accountId)->active()->get();
        foreach ($pools as $pool) {
            if ((float) $pool->cached_balance < 0) {
                $flags[] = 'Negative pool: ' . $pool->name . ' — PKR ' . number_format($pool->cached_balance, 0);
            }
        }

        // Check advance perfect-matches for all staff with advances
        $staffWithAdvances = StaffAdvance::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($staffWithAdvances as $userId) {
            $flag = $this->checkAdvancePerfectMatch($userId, $accountId);
            if ($flag) $flags[] = $flag;
        }

        return $flags;
    }

    /**
     * Check if expense uses a cash payment method.
     */
    private function isCashPayment(Expense $expense): bool
    {
        if (!$expense->payment_method_id) return false;

        $paymentMode = \App\Models\PaymentModes::find($expense->payment_method_id);
        if (!$paymentMode) return false;

        return stripos($paymentMode->name, 'cash') !== false;
    }
}
