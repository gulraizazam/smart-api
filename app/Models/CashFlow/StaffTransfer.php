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

/**
 * Phase B of Cash Movements — represents a peer cash handover between
 * two staff members. Unlike StaffAdvance / StaffReturn there is no pool
 * leg: the cash never re-enters a pool. Outstanding shifts atomically
 * between source and destination staff.
 *
 * Mirrors StaffAdvance shape (void/audit/soft-delete) so MovementService
 * can dispatch and normalise it without special-casing.
 */
class StaffTransfer extends Model
{
    use HasFactory;
    use SoftDeletes;
    use FiltersByDateRange;
    use GuardsTenantBoundary;

    /** {@see FiltersByDateRange} — list/ledger filters on the handover date. */
    protected function dateRangeColumn(): string
    {
        return 'transfer_date';
    }

    protected $table = 'staff_transfers';

    protected $fillable = [
        'account_id', 'from_user_id', 'to_user_id', 'amount', 'transfer_date', 'description', 'created_by',
        'voided_at', 'void_reason', 'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date:Y-m-d',
            'voided_at' => 'datetime',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /**
     * Drop-zone attachments (added 2026-05-15) — polymorphic via
     * `movement_attachments.movement_kind = staff_transfer`.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MovementAttachment::class, 'movement_id')
            ->where('movement_kind', MovementAttachment::KIND_STAFF_TRANSFER);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to rows where the given user is either the source or the
     * destination. Used by the staff ledger view to surface both sides
     * of a handover under one query.
     */
    public function scopeInvolvingUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
        });
    }
}
