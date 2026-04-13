<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends BaseModel
{
    protected $fillable = [
        'user_id',
        'leave_type_id',
        'fiscal_year',
        'allocated',
        'used',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'allocated' => 'decimal:2',
            'used' => 'decimal:2',
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

    // ── Accessors ──

    public function getRemainingAttribute(): string
    {
        return bcsub((string) $this->allocated, (string) $this->used, 2);
    }

    // ── Scopes ──

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForFiscalYear(Builder $query, string $fiscalYear): Builder
    {
        return $query->where('fiscal_year', $fiscalYear);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    // ── Helpers ──

    /**
     * Get the current fiscal year string (e.g., "2025-26" for Jul 2025 – Jun 2026).
     */
    public static function currentFiscalYear(): string
    {
        $now = now();
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;
        $endYear = ($startYear + 1) % 100;

        return sprintf('%d-%02d', $startYear, $endYear);
    }
}
