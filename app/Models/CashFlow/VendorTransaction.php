<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Enums\VendorTransactionStatus;
use App\Enums\VendorTransactionType;
use App\Models\Concerns\GuardsTenantBoundary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;
    use GuardsTenantBoundary;

    protected $table = 'vendor_transactions';

    protected $fillable = [
        'account_id', 'vendor_id', 'type', 'status', 'amount',
        'expense_id', 'description', 'reference_no', 'attachment_url',
        'transaction_date', 'for_branch_id', 'is_for_general',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date:Y-m-d',
            'is_for_general' => 'boolean',
            'type' => VendorTransactionType::class,
            'status' => VendorTransactionStatus::class,
        ];
    }

    // Type enum: App\Enums\VendorTransactionType
    // Status enum: App\Enums\VendorTransactionStatus

    /**
     * Vendor this transaction belongs to.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    /**
     * Linked expense (for payments).
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id')->withTrashed();
    }

    /**
     * Branch this transaction is for.
     */
    public function forBranch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Locations::class, 'for_branch_id');
    }

    /**
     * User who created this transaction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Uploaded receipts/invoices (R2-backed). Legacy rows use
     * `attachment_url` instead and have an empty collection here.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(VendorTransactionAttachment::class);
    }

    // Scopes

    public function scopeForAccount($query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopePurchases($query): Builder
    {
        return $query->where('type', VendorTransactionType::Purchase);
    }

    public function scopePayments($query): Builder
    {
        return $query->where('type', VendorTransactionType::Payment);
    }
}
