<?php

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffAdvanceService
{
    private CashflowAuditService $auditService;
    private CashflowSettingService $settingService;
    private NotificationService $notificationService;

    public function __construct(CashflowAuditService $auditService, CashflowSettingService $settingService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->settingService = $settingService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get staff advance summary (grouped by staff member).
     */
    public function getStaffSummary(int $accountId)
    {
        $advances = StaffAdvance::forAccount($accountId)
            ->whereNull('voided_at')
            ->select('user_id', DB::raw('SUM(amount) as total_advances'))
            ->groupBy('user_id')
            ->pluck('total_advances', 'user_id');

        $returns = StaffReturn::forAccount($accountId)
            ->whereNull('voided_at')
            ->select('user_id', DB::raw('SUM(amount) as total_returns'))
            ->groupBy('user_id')
            ->pluck('total_returns', 'user_id');

        $userIds = $advances->keys()->merge($returns->keys())->unique();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'is_advance_eligible']);

        return $users->map(function ($user) use ($advances, $returns) {
            $totalAdvances = $advances->get($user->id, 0);
            $totalReturns = $returns->get($user->id, 0);
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'is_advance_eligible' => $user->is_advance_eligible,
                'total_advances' => (float) $totalAdvances,
                'total_returns' => (float) $totalReturns,
                'outstanding' => (float) $totalAdvances - (float) $totalReturns,
            ];
        })->filter(fn($item) => $item['total_advances'] > 0 || $item['total_returns'] > 0)
          ->values();
    }

    /**
     * Get advances and returns for a specific staff member.
     */
    public function getStaffLedger(int $userId, int $accountId)
    {
        $advances = StaffAdvance::forAccount($accountId)
            ->forStaff($userId)
            ->with(['pool:id,name', 'creator:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        $returns = StaffReturn::forAccount($accountId)
            ->forStaff($userId)
            ->with(['pool:id,name', 'creator:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        $user = User::find($userId, ['id', 'name', 'is_advance_eligible']);

        $totalAdvances = $advances->sum('amount');
        $totalReturns = $returns->sum('amount');

        return [
            'user' => $user,
            'advances' => $advances,
            'returns' => $returns,
            'total_advances' => (float) $totalAdvances,
            'total_returns' => (float) $totalReturns,
            'outstanding' => (float) $totalAdvances - (float) $totalReturns,
        ];
    }

    /**
     * Create a staff advance.
     */
    public function createAdvance(array $data, int $accountId): StaffAdvance
    {
        $user = User::where('account_id', $accountId)->findOrFail($data['user_id']);

        if (!$user->is_advance_eligible) {
            throw new CashflowException('This staff member is not eligible for cash advances.');
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

        // Observer handles pool balance increment
        $return = StaffReturn::create([
            'account_id' => $accountId,
            'user_id' => $data['user_id'],
            'pool_id' => $data['pool_id'],
            'amount' => $data['amount'],
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

        return DB::transaction(function () use ($advance, $reason) {
            // Reverse: credit pool back (advance took money from pool)
            DB::table('cash_pools')
                ->where('id', $advance->pool_id)
                ->increment('cached_balance', $advance->amount);

            $oldValues = $advance->toArray();

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
                $advance->fresh()->toArray(),
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
        $advance = StaffAdvance::forAccount($accountId)->findOrFail($advanceId);

        if ($advance->isVoided()) {
            throw new CashflowException('Cannot edit a voided advance.');
        }

        return DB::transaction(function () use ($advance, $data) {
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

            $this->auditService->log(
                CashflowAuditLog::ACTION_UPDATED,
                CashflowAuditLog::ENTITY_STAFF_ADVANCE,
                $advance->id,
                $oldValues,
                $advance->fresh()->toArray(),
                $data['edit_reason'] ?? 'Advance edited'
            );

            return $advance->fresh()->load(['staffUser:id,name', 'pool:id,name', 'creator:id,name']);
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

        return DB::transaction(function () use ($return, $reason) {
            // Reverse: debit pool (return had credited pool)
            DB::table('cash_pools')
                ->where('id', $return->pool_id)
                ->decrement('cached_balance', $return->amount);

            $oldValues = $return->toArray();

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
                $return->fresh()->toArray(),
                $reason
            );

            return $return->fresh();
        });
    }

    /**
     * Get outstanding advance balance for a staff member.
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

        return (float) $advances - (float) $expenses - (float) $returns;
    }

    /**
     * Get advance-eligible staff for dropdown.
     */
    public function getEligibleStaff(int $accountId)
    {
        return CashflowHelper::getAdvanceEligibleStaff($accountId);
    }
}
