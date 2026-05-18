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
 * One file attached to a cash-flow Transfer (pool→pool movement).
 *
 * Direct sibling of {@see ExpenseAttachment} — same R2 bucket, same
 * content-addressed key derivation (SHA-256), same orphan/bind
 * lifecycle, same soft-delete + keep-forever policy. The two tables
 * stay split (rather than polymorphic) so each side's controllers and
 * permission gates can be kept linear and obvious.
 */
class CashTransferAttachment extends Model
{
    use SoftDeletes;
    use GuardsTenantBoundary;

    protected $fillable = [
        'account_id',
        'cash_transfer_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'cash_transfer_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(CashTransfer::class, 'cash_transfer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeForTransfer($query, int $transferId)
    {
        return $query->where('cash_transfer_id', $transferId);
    }

    public function scopeOrphaned($query, int $hours = 48)
    {
        return $query->whereNull('cash_transfer_id')
            ->where('created_at', '<', now()->subHours($hours));
    }

    // ─── R2CleanupObserver opt-in ────────────────────────────────────
    public function r2DocumentKey(): string
    {
        return (string) $this->file_path;
    }

    public function r2DocumentService(): R2DocumentService
    {
        return new R2DocumentService(Storage::disk('r2_invoices'));
    }
}
