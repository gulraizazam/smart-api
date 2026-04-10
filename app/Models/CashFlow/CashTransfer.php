<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransfer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cash_transfers';

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

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transfer_date', [$startDate, $endDate]);
    }

    public function scopeInvolvingPool($query, int $poolId)
    {
        return $query->where(function ($q) use ($poolId) {
            $q->where('from_pool_id', $poolId)->orWhere('to_pool_id', $poolId);
        });
    }
}
