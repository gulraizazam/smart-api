<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $table = 'cashflow_vendors';

    protected $fillable = [
        'account_id', 'name', 'contact_person', 'phone', 'email', 'address',
        'payment_terms', 'category', 'opening_balance', 'cached_balance',
        'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'cached_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Payment terms constants
    const TERMS_UPFRONT = 'upfront';
    const TERMS_NET_7 = 'net_7';
    const TERMS_NET_15 = 'net_15';
    const TERMS_NET_30 = 'net_30';
    const TERMS_CUSTOM = 'custom';

    /**
     * Transactions for this vendor.
     */
    public function transactions()
    {
        return $this->hasMany(VendorTransaction::class, 'vendor_id');
    }

    /**
     * Expenses linked to this vendor.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'vendor_id');
    }

    /**
     * User who created this vendor.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
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
}
