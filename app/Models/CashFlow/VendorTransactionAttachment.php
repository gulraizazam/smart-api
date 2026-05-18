<?php

declare(strict_types=1);

namespace App\Models\CashFlow;

use App\Models\Concerns\GuardsTenantBoundary;
use App\Models\User;
use App\Services\Storage\R2DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One file attached to a vendor transaction (purchase / payment).
 *
 * Exact mirror of {@see ExpenseAttachment}: a row points at a single
 * object in the private `invoices` R2 bucket via `file_path` (the R2
 * key), content-addressed by SHA-256 so byte-identical re-uploads share
 * one blob. Same orphan-upload lifecycle (`vendor_transaction_id` null
 * until the form is saved) and same keep-forever soft-delete policy.
 */
class VendorTransactionAttachment extends Model
{
    use SoftDeletes;
    use GuardsTenantBoundary;

    protected $fillable = [
        'account_id',
        'vendor_transaction_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'vendor_transaction_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function vendorTransaction(): BelongsTo
    {
        return $this->belongsTo(VendorTransaction::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to a single tenant. Always combine with this — never list
     * attachments across accounts.
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to a single vendor transaction (parent row).
     */
    public function scopeForTransaction($query, int $vendorTransactionId)
    {
        return $query->where('vendor_transaction_id', $vendorTransactionId);
    }

    /**
     * Scope to unbound uploads older than $hours. Driven by the same
     * prune command pattern as expense attachments.
     */
    public function scopeOrphaned($query, int $hours = 48)
    {
        return $query->whereNull('vendor_transaction_id')
            ->where('created_at', '<', now()->subHours($hours));
    }

    // ─── R2CleanupObserver opt-in ────────────────────────────────────
    // Lets R2CleanupObserver wipe the R2 blob on forceDelete(); plain
    // soft-delete is a no-op (keep-forever policy), same as
    // ExpenseAttachment.

    public function r2DocumentKey(): string
    {
        return (string) $this->file_path;
    }

    public function r2DocumentService(): R2DocumentService
    {
        return new R2DocumentService(Storage::disk('r2_invoices'));
    }
}
