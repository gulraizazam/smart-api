<?php

namespace App\Models\CashFlow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffReturn extends Model
{
    use SoftDeletes;

    protected $table = 'staff_returns';

    protected $fillable = [
        'account_id', 'user_id', 'pool_id', 'amount', 'description', 'created_by',
        'voided_at', 'void_reason', 'voided_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    /**
     * Staff member who returned cash.
     */
    public function staffUser()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Pool the return was deposited to.
     */
    public function pool()
    {
        return $this->belongsTo(CashPool::class, 'pool_id')->withTrashed();
    }

    /**
     * User who recorded the return.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * User who voided this return.
     */
    public function voidedByUser()
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

    public function scopeForStaff($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
