<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'account_id', 'expense_date', 'amount', 'category_id', 'paid_from_pool_id',
        'for_branch_id', 'payment_method_id', 'vendor_id', 'staff_id', 'description',
        'reference_no', 'attachment_url', 'notes', 'status', 'verified_by',
        'rejection_reason', 'is_flagged', 'flag_reason', 'created_by',
        'voided_at', 'voided_by', 'void_reason', 'edit_reason', 'is_for_general',
    ];

    protected $casts = [
        'expense_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'is_flagged' => 'boolean',
        'is_for_general' => 'boolean',
        'voided_at' => 'datetime',
    ];

    // Status constants
    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_REJECTED = 'rejected';

    /**
     * Category of the expense.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id')->withTrashed();
    }

    /**
     * Pool this expense was paid from.
     */
    public function paidFromPool(): BelongsTo
    {
        return $this->belongsTo(CashPool::class, 'paid_from_pool_id')->withTrashed();
    }

    /**
     * Branch this expense is for (null = General/Company-wide).
     */
    public function forBranch(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'for_branch_id')->withTrashed();
    }

    /**
     * Payment method used.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentModes::class, 'payment_method_id')->withTrashed();
    }

    /**
     * Vendor linked to this expense.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    /**
     * Staff member who made the expense.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }

    /**
     * Admin who approved/rejected.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    /**
     * User who created the expense.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Admin who voided.
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /**
     * Vendor transaction linked to this expense (auto-created payment).
     */
    /**
     * Alias for paidFromPool (used by dashboard).
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(CashPool::class, 'paid_from_pool_id');
    }

    public function vendorTransaction(): HasOne
    {
        return $this->hasOne(VendorTransaction::class, 'expense_id');
    }

    /**
     * Last edit audit log entry (for "Edited" badge hover detail).
     */
    public function lastEditLog(): HasOne
    {
        return $this->hasOne(CashflowAuditLog::class, 'entity_id')
            ->where('entity_type', 'expense')
            ->where('action', CashflowAuditLog::ACTION_UPDATED)
            ->with('user:id,name')
            ->latest();
    }

    // Scopes

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('expenses.account_id', $accountId);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', 1);
    }

    public function scopeNotVoided($query)
    {
        return $query->whereNull('voided_at');
    }

    public function scopeVoided($query)
    {
        return $query->whereNotNull('voided_at');
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        if ($branchId === null) {
            return $query->where('is_for_general', 1);
        }
        return $query->where('for_branch_id', $branchId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    // Helpers

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isEdited(): bool
    {
        return $this->edit_reason !== null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
