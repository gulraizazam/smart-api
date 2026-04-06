<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRequest extends Model
{
    protected $table = 'vendor_requests';

    protected $fillable = [
        'account_id', 'name', 'contact_person', 'phone', 'email',
        'payment_terms', 'category_id', 'opening_balance', 'address', 'note',
        'requested_by', 'status', 'admin_notes', 'vendor_id',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DISMISSED = 'dismissed';

    /**
     * User who requested the vendor.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * Linked vendor if approved.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
