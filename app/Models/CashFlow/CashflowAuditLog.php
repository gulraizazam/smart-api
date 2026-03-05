<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CashflowAuditLog extends Model
{
    // NO SoftDeletes — immutable, write-only
    public $timestamps = false;

    protected $table = 'cashflow_audit_logs';

    protected $fillable = [
        'account_id', 'user_id', 'action', 'entity_type',
        'entity_id', 'old_values', 'new_values', 'reason', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Action constants
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_VOIDED = 'voided';
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';
    const ACTION_RESUBMITTED = 'resubmitted';
    const ACTION_LOCKED = 'locked';
    const ACTION_UNLOCKED = 'unlocked';
    const ACTION_DEACTIVATED = 'deactivated';
    const ACTION_AUTO_CREATED = 'auto_created';
    const ACTION_RESET = 'reset';
    const ACTION_DELETED = 'deleted';

    // Entity type constants
    const ENTITY_EXPENSE = 'expense';
    const ENTITY_TRANSFER = 'transfer';
    const ENTITY_VENDOR = 'vendor';
    const ENTITY_VENDOR_TRANSACTION = 'vendor_transaction';
    const ENTITY_VENDOR_REQUEST = 'vendor_request';
    const ENTITY_CATEGORY = 'category';
    const ENTITY_CATEGORY_REQUEST = 'category_request';
    const ENTITY_STAFF_ADVANCE = 'staff_advance';
    const ENTITY_STAFF_RETURN = 'staff_return';
    const ENTITY_PERIOD_LOCK = 'period_lock';
    const ENTITY_CASH_POOL = 'cash_pool';
    const ENTITY_SETTING = 'setting';
    const ENTITY_MODULE = 'module';

    /**
     * User who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    /**
     * Prevent updates — this model is write-only.
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \RuntimeException('Cashflow audit logs are immutable and cannot be updated.');
    }

    /**
     * Prevent deletes — this model is write-only.
     */
    public function delete()
    {
        throw new \RuntimeException('Cashflow audit logs are immutable and cannot be deleted.');
    }
}
