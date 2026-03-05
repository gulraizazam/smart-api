<?php

namespace App\Services\CashFlow;

use App\Models\CashFlow\CashflowAuditLog;
use Illuminate\Support\Facades\Auth;

class CashflowAuditService
{
    /**
     * Log an action to the immutable audit trail.
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $userId = null
    ): CashflowAuditLog {
        return CashflowAuditLog::create([
            'account_id' => Auth::user()->account_id,
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get audit logs for a specific entity.
     */
    public function getEntityLogs(string $entityType, int $entityId, int $accountId)
    {
        return CashflowAuditLog::forAccount($accountId)
            ->forEntity($entityType, $entityId)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get paginated audit logs with optional filters.
     */
    public function getPaginatedLogs(int $accountId, array $filters = [], int $perPage = 25)
    {
        $query = CashflowAuditLog::forAccount($accountId)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query->paginate($perPage);
    }
}
