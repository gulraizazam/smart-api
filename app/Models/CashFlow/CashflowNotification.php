<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CashflowNotification extends Model
{
    public $timestamps = false;

    protected $table = 'cashflow_notifications';

    protected $fillable = [
        'account_id', 'user_id', 'type', 'title', 'message', 'data', 'read_at', 'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Notification type constants
    const TYPE_EXPENSE_PENDING = 'expense_pending';
    const TYPE_EXPENSE_APPROVED = 'expense_approved';
    const TYPE_EXPENSE_REJECTED = 'expense_rejected';
    const TYPE_VENDOR_REQUEST = 'vendor_request';
    const TYPE_CATEGORY_REQUEST = 'category_request';
    const TYPE_STAFF_ADVANCE = 'staff_advance';
    const TYPE_NEGATIVE_POOL = 'negative_pool';
    const TYPE_EXPENSE_FOR_BRANCH = 'expense_for_branch';
    const TYPE_TRANSFER_FOR_BRANCH = 'transfer_for_branch';

    /**
     * User this notification belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    // Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Mark this notification as read.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark all unread notifications for a user as read.
     */
    public static function markAllReadForUser(int $userId): int
    {
        return static::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
