<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VendorRequest extends Model
{
    protected $table = 'vendor_requests';

    protected $fillable = [
        'account_id', 'name', 'phone', 'note',
        'requested_by', 'status', 'admin_notes', 'vendor_id',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DISMISSED = 'dismissed';

    /**
     * User who requested the vendor.
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * Linked vendor if approved.
     */
    public function vendor()
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
