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

class CashTransfer extends Model
{
    use HasFactory;
    use SoftDeletes;
    use FiltersByDateRange;
    use GuardsTenantBoundary;

    protected $table = 'cash_transfers';

    /** {@see FiltersByDateRange} — list/ledger filters on the cash date. */
    protected function dateRangeColumn(): string
    {
        return 'transfer_date';
    }

    protected $fillable = [
        'account_id', 'transfer_date', 'amount', 'from_pool_id', 'to_pool_id',
        'method', 'reference_no', 'attachment_url', 'description', 'created_by',
        'voided_at', 'void_reason', 'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'from_pool_id' => 'integer',
            'to_pool_id' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    // Method constants
    const METHOD_PHYSICAL_CASH = 'physical_cash';
    const METHOD_BANK_DEPOSIT = 'bank_deposit';

    /**
     * Source pool.
     */
    public function fromPool(): BelongsTo
    {
        return $this->belongsTo(CashPool::class, 'from_pool_id')->withTrashed();
    }

    /**
     * Destination pool.
     */
    public function toPool(): BelongsTo
    {
        return $this->belongsTo(CashPool::class, 'to_pool_id')->withTrashed();
    }

    /**
     * User who recorded the transfer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * User who voided the transfer.
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /**
     * Multi-file attachments uploaded via the drop-zone (post 2026-05-15).
     * The legacy {@see CashTransfer::$attachment_url} column stays
     * authoritative for pre-cutover rows and continues to render in the
     * UI side-by-side with anything in this relation.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CashTransferAttachment::class, 'cash_transfer_id');
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

    public function scopeInvolvingPool($query, int $poolId)
    {
        return $query->where(function ($q) use ($poolId) {
            $q->where('from_pool_id', $poolId)->orWhere('to_pool_id', $poolId);
        });
    }
}
