<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HrNotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrNotification extends BaseModel
{
    protected $table = 'hr_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'reference_id',
        'reference_type',
        'url',
        'is_read',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => HrNotificationType::class,
            'is_read' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    // ── Scopes ──

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    // ── Actions ──

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->update(['is_read' => true]);
        }
    }

    public static function markAllReadForUser(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
