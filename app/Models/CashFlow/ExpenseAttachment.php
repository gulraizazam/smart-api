<?php

declare(strict_types=1);

namespace App\Models\CashFlow;

use App\Models\User;
use App\Services\Storage\R2DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One file attached to a cash-flow Payment (Expense).
 *
 * Each row points at a single object in the private `invoices` R2 bucket
 * via `file_path` (the R2 key). The key is content-addressed by the
 * file's SHA-256, so two rows uploading byte-identical files share a
 * single blob — the second uploader pays only a HEAD round-trip, no
 * extra storage.
 *
 * Lifecycle:
 *  - Created by ExpenseAttachmentsController@store. Initially
 *    `expense_id` may be null (the SPA uploads while the user is still
 *    filling the form) and gets bound when the expense is saved.
 *  - Deleted via the API only soft-deletes the row — the R2 blob stays
 *    forever (per business rule). Hard-delete is reserved for the
 *    `cashflow:prune-orphan-attachments` command, which fires
 *    R2CleanupObserver only when no other live row references the
 *    same SHA.
 */
class ExpenseAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'expense_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'expense_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
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
     * Scope to a single expense (parent payment row).
     */
    public function scopeForExpense($query, int $expenseId)
    {
        return $query->where('expense_id', $expenseId);
    }

    /**
     * Scope to unbound uploads older than $hours. Driven by the prune
     * command; 48h default per the slice-1 plan but the command lets the
     * operator override via `--ttl=N`.
     */
    public function scopeOrphaned($query, int $hours = 48)
    {
        return $query->whereNull('expense_id')
            ->where('created_at', '<', now()->subHours($hours));
    }

    // ─── R2CleanupObserver opt-in ────────────────────────────────────
    // These two methods let R2CleanupObserver wipe the R2 blob when a
    // row is forceDelete()'d. Plain delete() (soft-delete) is a no-op
    // here — the keep-forever policy means we never strip the
    // attachment from storage without an explicit prune.

    public function r2DocumentKey(): string
    {
        return (string) $this->file_path;
    }

    public function r2DocumentService(): R2DocumentService
    {
        return new R2DocumentService(Storage::disk('r2_invoices'));
    }
}
