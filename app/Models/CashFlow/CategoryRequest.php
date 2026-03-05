<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CategoryRequest extends Model
{
    protected $table = 'category_requests';

    protected $fillable = [
        'account_id', 'name', 'description',
        'requested_by', 'status', 'admin_notes', 'category_id',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DISMISSED = 'dismissed';

    /**
     * User who requested the category.
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * Linked category if approved.
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id')->withTrashed();
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
