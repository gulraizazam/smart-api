<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveDurationType;
use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveApplication extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'duration_type',
        'total_days',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_type' => LeaveDurationType::class,
            'total_days' => 'decimal:2',
            'status' => LeaveStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    // ── Scopes ──

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForFiscalYear(Builder $query, string $fiscalYear): Builder
    {
        [$startYear] = explode('-', $fiscalYear);
        $from = "{$startYear}-07-01";
        $to = ($startYear + 1) . '-06-30';

        return $query->where('start_date', '>=', $from)
            ->where('start_date', '<=', $to);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    // ── Helpers ──

    /**
     * Calculate total days based on duration type and date range.
     */
    public static function calculateTotalDays(LeaveDurationType $durationType, string $startDate, string $endDate): float
    {
        if ($durationType !== LeaveDurationType::Full) {
            return $durationType->dayValue();
        }

        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        return (float) $start->diffInDays($end) + 1;
    }

    /**
     * Check if this application overlaps with existing applications for the same user.
     */
    public static function hasOverlap(int $userId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        return static::where('user_id', $userId)
            ->where('account_id', \Illuminate\Support\Facades\Auth::user()->account_id)
            ->whereNotIn('status', [LeaveStatus::Rejected, LeaveStatus::Cancelled])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}
