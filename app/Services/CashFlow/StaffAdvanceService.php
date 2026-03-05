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
            ->select('user_id', DB::raw('SUM(amount) as total_advances'))
            ->groupBy('user_id')
            ->pluck('total_advances', 'user_id');

        $returns = StaffReturn::forAccount($accountId)
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
     * Get outstanding advance balance for a staff member.
     */
    public function getOutstanding(int $userId, int $accountId): float
    {
        $advances = StaffAdvance::forAccount($accountId)->forStaff($userId)->sum('amount');
        $returns = StaffReturn::forAccount($accountId)->forStaff($userId)->sum('amount');

        return (float) $advances - (float) $returns;
    }

    /**
     * Get advance-eligible staff for dropdown.
     */
    public function getEligibleStaff(int $accountId)
    {
        return CashflowHelper::getAdvanceEligibleStaff($accountId);
    }
}
