<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\Concerns\GuardsTenantBoundary;
use App\Models\Locations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashPool extends Model
{
    use HasFactory;
    use SoftDeletes;
    use GuardsTenantBoundary;

    protected $table = 'cash_pools';

    protected $fillable = [
        'account_id', 'type', 'location_id', 'name',
        'opening_balance', 'cached_balance', 'is_active', 'opening_balance_frozen',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'cached_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'opening_balance_frozen' => 'boolean',
        ];
    }

    // Pool types
    const TYPE_BRANCH_CASH = 'branch_cash';
    const TYPE_HEAD_OFFICE_CASH = 'head_office_cash';
    const TYPE_BANK_ACCOUNT = 'bank_account';

    /**
     * Get the location (branch) this pool belongs to.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    /**
     * Expenses paid from this pool.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'paid_from_pool_id');
    }

    /**
     * Transfers out from this pool.
     */
    public function transfersOut(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'from_pool_id');
    }

    /**
     * Transfers in to this pool.
     */
    public function transfersIn(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'to_pool_id');
    }

    /**
     * Staff advances from this pool.
     */
    public function staffAdvances(): HasMany
    {
        return $this->hasMany(StaffAdvance::class, 'pool_id');
    }

    /**
     * Staff returns to this pool.
     */
    public function staffReturns(): HasMany
    {
        return $this->hasMany(StaffReturn::class, 'pool_id');
    }

    // Scopes

    public function scopeActive($query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeForAccount($query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeBranchPools($query): Builder
    {
        return $query->where('type', self::TYPE_BRANCH_CASH);
    }

    /**
     * Get display name with type label.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $typeLabels = [
                    self::TYPE_BRANCH_CASH => 'Branch',
                    self::TYPE_HEAD_OFFICE_CASH => 'Head Office',
                    self::TYPE_BANK_ACCOUNT => 'Bank',
                ];

                $typeLabel = $typeLabels[$this->type] ?? '';
                return $this->name . ' (' . $typeLabel . ')';
            },
        );
    }
}
