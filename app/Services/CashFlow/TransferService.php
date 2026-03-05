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

    public function __construct(CashflowAuditService $auditService)
    {
        $this->auditService = $auditService;
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
                'reference_no' => $data['reference_no'],
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

            return $transfer->load([
                'fromPool:id,name,type', 'fromPool.location:id,name',
                'toPool:id,name,type', 'toPool.location:id,name',
                'creator:id,name',
            ]);
        });
    }
}
