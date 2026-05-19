<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\Concerns\FiltersByDateRange;
use App\Models\Concerns\GuardsTenantBoundary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffReturn extends Model
{
    use HasFactory;
    use SoftDeletes;
    use FiltersByDateRange;
    use GuardsTenantBoundary;

    /** {@see FiltersByDateRange} — list/ledger filters on the return date. */
    protected function dateRangeColumn(): string
    {
        return 'return_date';
    }

    protected $table = 'staff_returns';

    protected $fillable = [
        'account_id', 'user_id', 'pool_id', 'amount', 'return_date', 'description', 'created_by',
        'voided_at', 'void_reason', 'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'return_date' => 'date:Y-m-d',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Staff member who returned cash.
     */
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Pool the return was deposited to.
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(CashPool::class, 'pool_id')->withTrashed();
    }

    /**
     * User who recorded the return.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * User who voided this return.
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /**
     * Drop-zone attachments (added 2026-05-15) — polymorphic via
     * `movement_attachments.movement_kind = staff_return`.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MovementAttachment::class, 'movement_id')
            ->where('movement_kind', MovementAttachment::KIND_STAFF_RETURN);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeForStaff($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
