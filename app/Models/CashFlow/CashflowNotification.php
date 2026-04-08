<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Enums\CashflowNotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashflowNotification extends Model
{
    public $timestamps = false;

    protected $table = 'cashflow_notifications';

    protected $fillable = [
        'account_id', 'user_id', 'type', 'title', 'message', 'data', 'read_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'type' => CashflowNotificationType::class,
        ];
    }

    // Notification type enum: App\Enums\CashflowNotificationType

    /**
     * User this notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    // Scopes

    public function scopeForUser($query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRecent($query, int $limit = 20): Builder
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
