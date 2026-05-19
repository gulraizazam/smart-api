<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\Concerns\GuardsTenantBoundary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;
    use SoftDeletes;
    use GuardsTenantBoundary;

    protected $table = 'cashflow_vendors';

    protected $fillable = [
        'account_id', 'name', 'contact_person', 'phone', 'email', 'address',
        'payment_terms', 'category', 'category_id', 'opening_balance', 'cached_balance',
        'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'cached_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Payment terms constants
    const TERMS_UPFRONT = 'upfront';
    const TERMS_NET_7 = 'net_7';
    const TERMS_NET_15 = 'net_15';
    const TERMS_NET_30 = 'net_30';
    const TERMS_CUSTOM = 'custom';

    /**
     * Transactions for this vendor.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(VendorTransaction::class, 'vendor_id');
    }

    /**
     * Expenses linked to this vendor.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'vendor_id');
    }

    /**
     * User who created this vendor.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
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
}
