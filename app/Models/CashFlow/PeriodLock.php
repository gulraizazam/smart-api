<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PeriodLock extends Model
{
    protected $table = 'period_locks';

    protected $fillable = [
        'account_id', 'month', 'year', 'locked_by',
        'balance_snapshot', 'unlock_reason', 'unlocked_by', 'unlocked_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'balance_snapshot' => 'array',
        'unlocked_at' => 'datetime',
    ];

    /**
     * User who locked the period.
     */
    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by')->withTrashed();
    }

    /**
     * User who unlocked the period.
     */
    public function unlockedByUser()
    {
        return $this->belongsTo(User::class, 'unlocked_by')->withTrashed();
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Check if a specific month/year is locked.
     */
    public static function isLocked(int $accountId, int $month, int $year): bool
    {
        return static::where('account_id', $accountId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();
    }

    /**
     * Check if any period has ever been locked (for opening balance freeze).
     */
    public static function hasAnyLock(int $accountId): bool
    {
        return static::where('account_id', $accountId)->exists();
    }

    /**
     * Get the latest locked period.
     */
    public static function getLatestLock(int $accountId): ?self
    {
        return static::where('account_id', $accountId)
            ->orderByRaw('year DESC, month DESC')
            ->first();
    }
}
