<?php

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferService
{
    private CashflowAuditService $auditService;
    private NotificationService $notificationService;

    public function __construct(CashflowAuditService $auditService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get paginated transfers with filters.
     */
    public function getTransfers(int $accountId, array $filters = [], int $perPage = 25)
    {
        $query = CashTransfer::forAccount($accountId)
            ->with([
                'fromPool:id,name,type',
                'fromPool.location:id,name',
                'toPool:id,name,type',
                'toPool.location:id,name',
                'creator:id,name',
            ])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }

        if (!empty($filters['pool_id'])) {
            $query->involvingPool($filters['pool_id']);
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Void a cash transfer (reverses pool balance changes).
     */
    public function void(int $transferId, string $reason, int $accountId): CashTransfer
    {
        $transfer = CashTransfer::forAccount($accountId)->findOrFail($transferId);

        if ($transfer->isVoided()) {
            throw new CashflowException('Transfer is already voided.');
        }

        if (CashflowHelper::isDateInLockedPeriod($transfer->transfer_date->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked($transfer->transfer_date->month, $transfer->transfer_date->year);
        }

        return DB::transaction(function () use ($transfer, $reason) {
            // Reverse pool balances: credit from_pool, debit to_pool
            DB::table('cash_pools')
                ->where('id', $transfer->from_pool_id)
                ->increment('cached_balance', $transfer->amount);

            DB::table('cash_pools')
                ->where('id', $transfer->to_pool_id)
                ->decrement('cached_balance', $transfer->amount);

            $oldValues = $transfer->toArray();

            $transfer->update([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => Auth::id(),
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_VOIDED,
                CashflowAuditLog::ENTITY_TRANSFER,
                $transfer->id,
                $oldValues,
                $transfer->fresh()->toArray(),
                $reason
            );

            return $transfer->fresh();
        });
    }

    /**
     * Edit a cash transfer (amount, pools, method, description).
     */
    public function edit(int $transferId, array $data, int $accountId): CashTransfer
    {
        $transfer = CashTransfer::forAccount($accountId)->findOrFail($transferId);

        if ($transfer->isVoided()) {
            throw new CashflowException('Cannot edit a voided transfer.');
        }

        if (CashflowHelper::isDateInLockedPeriod($transfer->transfer_date->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked($transfer->transfer_date->month, $transfer->transfer_date->year);
        }

        // Validate from != to
        $newFromPool = $data['from_pool_id'] ?? $transfer->from_pool_id;
        $newToPool = $data['to_pool_id'] ?? $transfer->to_pool_id;
        if ($newFromPool == $newToPool) {
            throw new CashflowException('Source and destination pools cannot be the same.');
        }

        return DB::transaction(function () use ($transfer, $data, $accountId) {
            $transfer->load(['fromPool:id,name', 'toPool:id,name', 'creator:id,name']);
            $oldValues = $transfer->toArray();

            $oldAmount = (float) $transfer->amount;
            $oldFromPoolId = $transfer->from_pool_id;
            $oldToPoolId = $transfer->to_pool_id;
            $newAmount = isset($data['amount']) ? (float) $data['amount'] : $oldAmount;
            $newFromPoolId = $data['from_pool_id'] ?? $oldFromPoolId;
            $newToPoolId = $data['to_pool_id'] ?? $oldToPoolId;

            // Reverse old transfer: credit old source, debit old destination
            DB::table('cash_pools')->where('id', $oldFromPoolId)->increment('cached_balance', $oldAmount);
            DB::table('cash_pools')->where('id', $oldToPoolId)->decrement('cached_balance', $oldAmount);

            // Apply new transfer: debit new source, credit new destination
            DB::table('cash_pools')->where('id', $newFromPoolId)->decrement('cached_balance', $newAmount);
            DB::table('cash_pools')->where('id', $newToPoolId)->increment('cached_balance', $newAmount);

            $transfer->update([
                'amount' => $newAmount,
                'from_pool_id' => $newFromPoolId,
                'to_pool_id' => $newToPoolId,
                'method' => $data['method'] ?? $transfer->method,
                'description' => $data['description'] ?? $transfer->description,
                'attachment_url' => $data['attachment_url'] ?? $transfer->attachment_url,
            ]);

            $transfer->load(['fromPool:id,name', 'toPool:id,name', 'creator:id,name']);

            $this->auditService->log(
                CashflowAuditLog::ACTION_UPDATED,
                CashflowAuditLog::ENTITY_TRANSFER,
                $transfer->id,
                $oldValues,
                $transfer->toArray(),
                $data['edit_reason'] ?? 'Transfer edited'
            );

            return $transfer;
        });
    }

    /**
     * Create a cash transfer between pools.
     */
    public function create(array $data, int $accountId): CashTransfer
    {
        $user = Auth::user();

        // Check period lock
        if (CashflowHelper::isDateInLockedPeriod($data['transfer_date'], $accountId)) {
            throw CashflowException::periodLocked(
                date('n', strtotime($data['transfer_date'])),
                date('Y', strtotime($data['transfer_date']))
            );
        }

        // Validate from != to
        if ($data['from_pool_id'] == $data['to_pool_id']) {
            throw new CashflowException('Source and destination pools cannot be the same.');
        }

        // Validate pools belong to same account
        $fromPool = CashPool::forAccount($accountId)->findOrFail($data['from_pool_id']);
        $toPool = CashPool::forAccount($accountId)->findOrFail($data['to_pool_id']);

        return DB::transaction(function () use ($data, $accountId, $user, $fromPool, $toPool) {
            // Observer handles balance updates
            $transfer = CashTransfer::create([
                'account_id' => $accountId,
                'transfer_date' => $data['transfer_date'],
                'amount' => $data['amount'],
                'from_pool_id' => $data['from_pool_id'],
                'to_pool_id' => $data['to_pool_id'],
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'],
                'description' => $data['description'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_CREATED,
                CashflowAuditLog::ENTITY_TRANSFER,
                $transfer->id,
                null,
                $transfer->toArray()
            );

            // Notify branch managers when transfer involves their branch pool
            $this->notificationService->notifyTransferForBranch(
                $transfer->from_pool_id,
                $transfer->to_pool_id,
                (float) $transfer->amount,
                $accountId
            );

            // Check for negative pool after transfer
            $fromPool->refresh();
            if ((float) $fromPool->cached_balance < 0) {
                $this->notificationService->notifyNegativePool(
                    $fromPool->name,
                    (float) $fromPool->cached_balance,
                    $fromPool->location_id,
                    $accountId
                );
            }

            return $transfer->load([
                'fromPool:id,name,type', 'fromPool.location:id,name',
                'toPool:id,name,type', 'toPool.location:id,name',
                'creator:id,name',
            ]);
        });
    }
}
