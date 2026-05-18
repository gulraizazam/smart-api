<?php

declare(strict_types=1);
namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\StaffTransfer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffAdvanceService
{
    public function __construct(
        private readonly CashflowAuditService $auditService,
        private readonly CashflowSettingService $settingService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Get staff advance summary (grouped by staff member).
     *
     * `$dateFrom` / `$dateTo` are optional ISO `YYYY-MM-DD` strings. When
     * supplied, advances + returns are filtered by `created_at` and expenses
     * by `expense_date` — the staff list pane shows only activity inside the
     * chosen window. Outstanding still totals across all history because the
     * "what does this staff member still owe" question is range-independent.
     */
    public function getStaffSummary(
        int $accountId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): \Illuminate\Support\Collection {
        $advancesQ = StaffAdvance::forAccount($accountId)->whereNull('voided_at');
        $returnsQ = StaffReturn::forAccount($accountId)->whereNull('voided_at');
        $expensesQ = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereNotNull('staff_id');

        // Null-safe, movement-date scope (advance_date / return_date /
        // expense_date) — open-ended ranges work, and the window keys
        // on the movement date, not bookkeeping created_at.
        $advancesQ->inDateRange($dateFrom, $dateTo);
        $returnsQ->inDateRange($dateFrom, $dateTo);
        $expensesQ->inDateRange($dateFrom, $dateTo);

        $advances = $advancesQ
            ->select('user_id', DB::raw('SUM(amount) as total_advances'))
            ->groupBy('user_id')
            ->pluck('total_advances', 'user_id');

        $returns = $returnsQ
            ->select('user_id', DB::raw('SUM(amount) as total_returns'))
            ->groupBy('user_id')
            ->pluck('total_returns', 'user_id');

        $expenses = $expensesQ
            ->select('staff_id', DB::raw('SUM(amount) as total_expenses'))
            ->groupBy('staff_id')
            ->pluck('total_expenses', 'staff_id');

        $userIds = $advances->keys()->merge($returns->keys())->merge($expenses->keys())->unique();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'is_advance_eligible']);
        // One batched set of GROUP BY queries instead of getOutstanding()
        // per user (was 5 SUM queries × N staff on every Movements load).
        $outstanding = $this->getOutstandingForUsers($userIds->all(), $accountId);

        return $users->map(function ($user) use ($advances, $returns, $expenses, $outstanding) {
            $totalAdvances = $advances->get($user->id, 0);
            $totalReturns = $returns->get($user->id, 0);
            $totalExpenses = $expenses->get($user->id, 0);
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'is_advance_eligible' => $user->is_advance_eligible,
                'total_advances' => (float) $totalAdvances,
                'total_returns' => (float) $totalReturns,
                'total_expenses' => (float) $totalExpenses,
                'outstanding' => $outstanding[$user->id] ?? 0,
            ];
        })->filter(fn($item) => $item['total_advances'] != 0 || $item['total_returns'] != 0 || $item['total_expenses'] != 0)
          ->values();
    }

    /**
     * Get advances and returns for a specific staff member.
     *
     * Optional date range filters all lists by their respective date
     * columns (`created_at` for advances/returns/transfers, `expense_date`
     * for expenses). KPIs are computed over the filtered set, so outstanding
     * here is window-scoped — useful when reconciling a specific period.
     *
     * Phase B adds `transfers_in` (handovers RECEIVED by this user) and
     * `transfers_out` (handovers GIVEN by this user). Outstanding now also
     * factors them in so the number matches what's visible in the lists.
     */
    public function getStaffLedger(
        int $userId,
        int $accountId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        // Order by the movement date (created_at only breaks ties /
        // orders pre-2026-05-15 nulls) so the staff ledger sorts the
        // same as the All-movements list and pool ledger.
        $advancesQ = StaffAdvance::forAccount($accountId)
            ->forStaff($userId)
            ->with(['pool:id,name', 'creator:id,name'])
            ->orderBy('advance_date', 'desc')
            ->orderBy('created_at', 'desc');

        $returnsQ = StaffReturn::forAccount($accountId)
            ->forStaff($userId)
            ->with(['pool:id,name', 'creator:id,name'])
            ->orderBy('return_date', 'desc')
            ->orderBy('created_at', 'desc');

        $expensesQ = Expense::forAccount($accountId)
            ->where('staff_id', $userId)
            ->whereNull('voided_at')
            ->with(['category:id,name', 'creator:id,name'])
            ->orderBy('expense_date', 'desc');

        $transfersInQ = StaffTransfer::forAccount($accountId)
            ->where('to_user_id', $userId)
            ->whereNull('voided_at')
            ->with(['fromUser:id,name', 'creator:id,name'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc');

        $transfersOutQ = StaffTransfer::forAccount($accountId)
            ->where('from_user_id', $userId)
            ->whereNull('voided_at')
            ->with(['toUser:id,name', 'creator:id,name'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Null-safe, movement-date scope on every leg (advance_date /
        // return_date / transfer_date / expense_date) — open-ended
        // ranges work and the window keys on the movement date.
        $advancesQ->inDateRange($dateFrom, $dateTo);
        $returnsQ->inDateRange($dateFrom, $dateTo);
        $expensesQ->inDateRange($dateFrom, $dateTo);
        $transfersInQ->inDateRange($dateFrom, $dateTo);
        $transfersOutQ->inDateRange($dateFrom, $dateTo);

        $advances = $advancesQ->get();
        $returns = $returnsQ->get();
        $expenses = $expensesQ->get();
        $transfersIn = $transfersInQ->get();
        $transfersOut = $transfersOutQ->get();

        $user = User::find($userId, ['id', 'name', 'is_advance_eligible']);

        $totalAdvances = $advances->sum('amount');
        $totalReturns = $returns->sum('amount');
        $totalExpenses = $expenses->sum('amount');
        $totalTransfersIn = $transfersIn->sum('amount');
        $totalTransfersOut = $transfersOut->sum('amount');

        return [
            'user' => $user,
            'advances' => $advances,
            'returns' => $returns,
            'expenses' => $expenses,
            'transfers_in' => $transfersIn,
            'transfers_out' => $transfersOut,
            'total_advances' => (float) $totalAdvances,
            'total_returns' => (float) $totalReturns,
            'total_expenses' => (float) $totalExpenses,
            'total_transfers_in' => (float) $totalTransfersIn,
            'total_transfers_out' => (float) $totalTransfersOut,
            'outstanding' => (float) $totalAdvances
                - (float) $totalReturns
                - (float) $totalExpenses
                - (float) $totalTransfersOut
                + (float) $totalTransfersIn,
        ];
    }

    /**
     * Create a staff advance.
     *
     * `advance_date` (added 2026-05-15) defaults to today; backdating
     * up to 7 days is allowed by the form. The period-lock guard runs
     * against the chosen date, not always `now()`, so a backdated entry
     * still respects a closed prior month.
     */
    public function createAdvance(array $data, int $accountId): StaffAdvance
    {
        $user = User::where('account_id', $accountId)->findOrFail($data['user_id']);

        if (!$user->is_advance_eligible) {
            throw new CashflowException('This staff member is not eligible for cash advances.');
        }

        $advanceDate = !empty($data['advance_date'])
            ? (string) $data['advance_date']
            : now()->toDateString();
        if (CashflowHelper::isDateInLockedPeriod($advanceDate, $accountId)) {
            $d = \Illuminate\Support\Carbon::parse($advanceDate);
            throw CashflowException::periodLocked((int) $d->month, (int) $d->year);
        }

        // Check cumulative threshold
        $threshold = (float) $this->settingService->get('cumulative_advance_threshold', $accountId, 100000);
        $currentOutstanding = $this->getOutstanding($data['user_id'], $accountId);
        $newTotal = $currentOutstanding + (float) $data['amount'];

        if ($newTotal > $threshold && $threshold > 0) {
            throw new CashflowException(
                "This advance would bring total outstanding to PKR " . number_format($newTotal, 2) .
                " which exceeds the threshold of PKR " . number_format($threshold, 2) . "."
            );
        }

        // Observer handles pool balance deduction
        $advance = StaffAdvance::create([
            'account_id' => $accountId,
            'user_id' => $data['user_id'],
            'pool_id' => $data['pool_id'],
            'amount' => $data['amount'],
            'advance_date' => $advanceDate,
            'description' => $data['description'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_STAFF_ADVANCE,
            $advance->id,
            null,
            $advance->toArray()
        );

        $this->notificationService->notifyStaffAdvanceGiven(
            $user->name,
            (float) $data['amount'],
            $accountId
        );

        return $advance->load(['staffUser:id,name', 'pool:id,name', 'creator:id,name']);
    }

    /**
     * Create a staff return (cash returned by staff).
     *
     * `return_date` (added 2026-05-15) follows the same backdating
     * window as advances — defaults today, up to 7 days back, period-
     * lock checked against the chosen date.
     */
    public function createReturn(array $data, int $accountId): StaffReturn
    {
        $outstanding = $this->getOutstanding($data['user_id'], $accountId);

        if ((float) $data['amount'] > $outstanding) {
            throw new CashflowException(
                'Return amount (PKR ' . number_format($data['amount'], 2) .
                ') exceeds outstanding balance (PKR ' . number_format($outstanding, 2) . ').'
            );
        }

        $returnDate = !empty($data['return_date'])
            ? (string) $data['return_date']
            : now()->toDateString();
        if (CashflowHelper::isDateInLockedPeriod($returnDate, $accountId)) {
            $d = \Illuminate\Support\Carbon::parse($returnDate);
            throw CashflowException::periodLocked((int) $d->month, (int) $d->year);
        }

        // Observer handles pool balance increment
        $return = StaffReturn::create([
            'account_id' => $accountId,
            'user_id' => $data['user_id'],
            'pool_id' => $data['pool_id'],
            'amount' => $data['amount'],
            'return_date' => $returnDate,
            'description' => $data['description'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_STAFF_RETURN,
            $return->id,
            null,
            $return->toArray()
        );

        return $return->load(['staffUser:id,name', 'pool:id,name', 'creator:id,name']);
    }

    /**
     * Void a staff advance (reverses pool balance).
     */
    public function voidAdvance(int $advanceId, string $reason, int $accountId): StaffAdvance
    {
        $advance = StaffAdvance::forAccount($accountId)->findOrFail($advanceId);

        if ($advance->isVoided()) {
            throw new CashflowException('This advance is already voided.');
        }

        // Period-lock guard — voiding rewinds the pool deduction, which
        // counts as a write against the advance's movement month. Guard
        // on advance_date (the persisted movement date), NOT created_at:
        // a backdated advance can sit in a now-locked prior month while
        // created_at is today. Fall back to created_at only for any
        // pre-2026-05-15 NULL straggler (mirrors mapStaffAdvance).
        $movementDate = $advance->advance_date ?? $advance->created_at;
        if (CashflowHelper::isDateInLockedPeriod($movementDate->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked(
                (int) $movementDate->month,
                (int) $movementDate->year,
            );
        }

        return DB::transaction(function () use ($advance, $reason) {
            // Reverse: credit pool back (advance took money from pool)
            DB::table('cash_pools')
                ->where('id', $advance->pool_id)
                ->increment('cached_balance', $advance->amount);

            $oldValues = $advance->only(['voided_at', 'voided_by', 'void_reason']);

            $advance->update([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => Auth::id(),
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_VOIDED,
                CashflowAuditLog::ENTITY_STAFF_ADVANCE,
                $advance->id,
                $oldValues,
                ['voided_at' => now()->toDateTimeString(), 'void_reason' => $reason],
                $reason
            );

            return $advance->fresh();
        });
    }

    /**
     * Edit a staff advance (amount, pool, description).
     */
    public function editAdvance(int $advanceId, array $data, int $accountId): StaffAdvance
    {
        // Pre-check (cheap, no lock).
        $preCheck = StaffAdvance::forAccount($accountId)->findOrFail($advanceId);

        if ($preCheck->isVoided()) {
            throw new CashflowException('Cannot edit a voided advance.');
        }

        // Period-lock guard — editing reshapes the pool deduction booked
        // against the advance's movement month. Guard on advance_date,
        // not created_at (a backdated advance can sit in a now-locked
        // month); fall back to created_at only for pre-2026-05-15 nulls.
        $movementDate = $preCheck->advance_date ?? $preCheck->created_at;
        if (CashflowHelper::isDateInLockedPeriod($movementDate->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked(
                (int) $movementDate->month,
                (int) $movementDate->year,
            );
        }

        return DB::transaction(function () use ($advanceId, $data, $accountId) {
            // Lock the row inside the transaction so concurrent edits
            // can't both read the same `oldAmount`/`oldPoolId` and apply
            // overlapping pool deltas.
            $advance = StaffAdvance::forAccount($accountId)
                ->lockForUpdate()
                ->findOrFail($advanceId);

            if ($advance->isVoided()) {
                throw new CashflowException('Cannot edit a voided advance.');
            }

            $auditRelations = ['staffUser:id,name', 'pool:id,name', 'creator:id,name'];
            $advance->load($auditRelations);
            $oldValues = $advance->toArray();
            $oldAmount = (float) $advance->amount;
            $oldPoolId = $advance->pool_id;
            $newAmount = isset($data['amount']) ? (float) $data['amount'] : $oldAmount;
            $newPoolId = $data['pool_id'] ?? $oldPoolId;

            // Reverse old pool deduction
            DB::table('cash_pools')->where('id', $oldPoolId)->increment('cached_balance', $oldAmount);
            // Apply new pool deduction
            DB::table('cash_pools')->where('id', $newPoolId)->decrement('cached_balance', $newAmount);

            $advance->update([
                'amount' => $newAmount,
                'pool_id' => $newPoolId,
                'description' => $data['description'] ?? $advance->description,
            ]);

            $newValues = $advance->fresh()->load($auditRelations)->toArray();

            $this->auditService->log(
                CashflowAuditLog::ACTION_UPDATED,
                CashflowAuditLog::ENTITY_STAFF_ADVANCE,
                $advance->id,
                $oldValues,
                $newValues,
                $data['edit_reason'] ?? 'Advance edited'
            );

            return $advance->fresh()->load($auditRelations);
        });
    }

    /**
     * Void a staff return (reverses pool balance).
     */
    public function voidReturn(int $returnId, string $reason, int $accountId): StaffReturn
    {
        $return = StaffReturn::forAccount($accountId)->findOrFail($returnId);

        if ($return->isVoided()) {
            throw new CashflowException('This return is already voided.');
        }

        // Period-lock guard — voiding reverses the pool credit booked
        // against the return's movement month. Guard on return_date,
        // not created_at (a backdated return can sit in a now-locked
        // month); fall back to created_at only for pre-2026-05-15 nulls.
        $movementDate = $return->return_date ?? $return->created_at;
        if (CashflowHelper::isDateInLockedPeriod($movementDate->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked(
                (int) $movementDate->month,
                (int) $movementDate->year,
            );
        }

        return DB::transaction(function () use ($return, $reason) {
            // Reverse: debit pool (return had credited pool)
            DB::table('cash_pools')
                ->where('id', $return->pool_id)
                ->decrement('cached_balance', $return->amount);

            $oldValues = $return->only(['voided_at', 'voided_by', 'void_reason']);

            $return->update([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => Auth::id(),
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_VOIDED,
                CashflowAuditLog::ENTITY_STAFF_RETURN,
                $return->id,
                $oldValues,
                ['voided_at' => now()->toDateTimeString(), 'void_reason' => $reason],
                $reason
            );

            return $return->fresh();
        });
    }

    /**
     * Get outstanding advance balance for a staff member.
     *
     * Formula (Phase B): advances - returns - expenses - transfers_out + transfers_in.
     * Peer handovers (`staff_transfers`) shift outstanding between users
     * atomically — the source loses the amount, the destination gains it.
     */
    public function getOutstanding(int $userId, int $accountId): float
    {
        $advances = StaffAdvance::forAccount($accountId)->forStaff($userId)->whereNull('voided_at')->sum('amount');
        $returns = StaffReturn::forAccount($accountId)->forStaff($userId)->whereNull('voided_at')->sum('amount');
        // Expenses with this staff_id reduce the advance balance (Sec 8.2)
        $expenses = \App\Models\CashFlow\Expense::forAccount($accountId)
            ->where('staff_id', $userId)
            ->whereNull('voided_at')
            ->sum('amount');

        $transfersOut = StaffTransfer::forAccount($accountId)
            ->where('from_user_id', $userId)
            ->whereNull('voided_at')
            ->sum('amount');
        $transfersIn = StaffTransfer::forAccount($accountId)
            ->where('to_user_id', $userId)
            ->whereNull('voided_at')
            ->sum('amount');

        return (float) $advances
            - (float) $expenses
            - (float) $returns
            - (float) $transfersOut
            + (float) $transfersIn;
    }

    /**
     * Get advance-eligible staff for dropdown.
     */
    public function getEligibleStaff(int $accountId): \Illuminate\Support\Collection
    {
        return CashflowHelper::getAdvanceEligibleStaff($accountId);
    }

    /**
     * Batched outstanding lookup for many users at once. Returns
     * [user_id => outstanding] for every user in $userIds, defaulting
     * to 0 for users with no advances.
     *
     * Replaces the N+1 pattern of calling getOutstanding() in a loop
     * (which fires 3 SUM queries per user). This version fires 3
     * GROUP BY queries total — same SQL count regardless of how many
     * users are in the list.
     */
    public function getOutstandingForUsers(array $userIds, int $accountId): array
    {
        if (empty($userIds)) {
            return [];
        }

        $advances = StaffAdvance::forAccount($accountId)
            ->whereIn('user_id', $userIds)
            ->whereNull('voided_at')
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->toArray();

        $returns = StaffReturn::forAccount($accountId)
            ->whereIn('user_id', $userIds)
            ->whereNull('voided_at')
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->toArray();

        $expenses = Expense::forAccount($accountId)
            ->whereIn('staff_id', $userIds)
            ->whereNull('voided_at')
            ->select('staff_id', DB::raw('SUM(amount) as total'))
            ->groupBy('staff_id')
            ->pluck('total', 'staff_id')
            ->toArray();

        // Phase B: factor peer handovers into the cumulative balance.
        // Two more GROUP BY queries — still O(1) in user count.
        $transfersOut = StaffTransfer::forAccount($accountId)
            ->whereIn('from_user_id', $userIds)
            ->whereNull('voided_at')
            ->select('from_user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('from_user_id')
            ->pluck('total', 'from_user_id')
            ->toArray();

        $transfersIn = StaffTransfer::forAccount($accountId)
            ->whereIn('to_user_id', $userIds)
            ->whereNull('voided_at')
            ->select('to_user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('to_user_id')
            ->pluck('total', 'to_user_id')
            ->toArray();

        $balances = [];
        foreach ($userIds as $userId) {
            $balances[$userId] = (float) ($advances[$userId] ?? 0)
                - (float) ($returns[$userId] ?? 0)
                - (float) ($expenses[$userId] ?? 0)
                - (float) ($transfersOut[$userId] ?? 0)
                + (float) ($transfersIn[$userId] ?? 0);
        }
        return $balances;
    }

    /**
     * Get recent advances and returns across all staff (for overview cards).
     */
    public function getRecentActivity(int $accountId, int $limit = 5): array
    {
        $advances = StaffAdvance::forAccount($accountId)
            ->whereNull('voided_at')
            ->with(['staffUser:id,name', 'pool:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'staff_name'  => $a->staffUser?->name,
                'pool_name'   => $a->pool?->name,
                'amount'      => (float) $a->amount,
                'date'        => $a->created_at?->toDateString(),
            ]);

        $returns = StaffReturn::forAccount($accountId)
            ->whereNull('voided_at')
            ->with(['staffUser:id,name', 'pool:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'description' => $r->description,
                'staff_name'  => $r->staffUser?->name,
                'pool_name'   => $r->pool?->name,
                'amount'      => (float) $r->amount,
                'date'        => $r->created_at?->toDateString(),
            ]);

        return [
            'advances' => $advances->values(),
            'returns'  => $returns->values(),
        ];
    }
}
