<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'vendor_transactions';

    protected $fillable = [
        'account_id', 'vendor_id', 'type', 'amount',
        'expense_id', 'description', 'reference_no', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Type constants
    const TYPE_PURCHASE = 'purchase';
    const TYPE_PAYMENT = 'payment';

    /**
     * Vendor this transaction belongs to.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    /**
     * Linked expense (for payments).
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id')->withTrashed();
    }

    /**
     * User who created this transaction.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopePurchases($query)
    {
        return $query->where('type', self::TYPE_PURCHASE);
    }

    public function scopePayments($query)
    {
        return $query->where('type', self::TYPE_PAYMENT);
    }
}
