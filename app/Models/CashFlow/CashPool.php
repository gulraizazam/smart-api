<?php

namespace App\Models\CashFlow;

use App\Models\Locations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashPool extends Model
{
    use SoftDeletes;

    protected $table = 'cash_pools';

    protected $fillable = [
        'account_id', 'type', 'location_id', 'name',
        'opening_balance', 'cached_balance', 'is_active', 'opening_balance_frozen',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'cached_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'opening_balance_frozen' => 'boolean',
    ];

    // Pool types
    const TYPE_BRANCH_CASH = 'branch_cash';
    const TYPE_HEAD_OFFICE_CASH = 'head_office_cash';
    const TYPE_BANK_ACCOUNT = 'bank_account';

    /**
     * Get the location (branch) this pool belongs to.
     */
    public function location()
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    /**
     * Expenses paid from this pool.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'paid_from_pool_id');
    }

    /**
     * Transfers out from this pool.
     */
    public function transfersOut()
    {
        return $this->hasMany(CashTransfer::class, 'from_pool_id');
    }

    /**
     * Transfers in to this pool.
     */
    public function transfersIn()
    {
        return $this->hasMany(CashTransfer::class, 'to_pool_id');
    }

    /**
     * Staff advances from this pool.
     */
    public function staffAdvances()
    {
        return $this->hasMany(StaffAdvance::class, 'pool_id');
    }

    /**
     * Staff returns to this pool.
     */
    public function staffReturns()
    {
        return $this->hasMany(StaffReturn::class, 'pool_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeBranchPools($query)
    {
        return $query->where('type', self::TYPE_BRANCH_CASH);
    }

    /**
     * Get display name with type label.
     */
    public function getDisplayNameAttribute(): string
    {
        $typeLabels = [
            self::TYPE_BRANCH_CASH => 'Branch',
            self::TYPE_HEAD_OFFICE_CASH => 'Head Office',
            self::TYPE_BANK_ACCOUNT => 'Bank',
        ];

        $typeLabel = $typeLabels[$this->type] ?? '';
        return $this->name . ' (' . $typeLabel . ')';
    }
}
