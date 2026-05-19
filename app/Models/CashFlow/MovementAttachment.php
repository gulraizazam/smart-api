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
 * Polymorphic attachment for the three staff-side movement kinds
 * (staff_advance, staff_return, staff_transfer). Pool→pool transfers
 * live in {@see CashTransferAttachment} — different surface, same
 * R2 bucket, same content-addressed-by-SHA storage.
 *
 * Lifecycle:
 *  - Created with NULL kind+id by the upload endpoint while the form
 *    is still being filled.
 *  - Bound (kind+id set) by {@see MovementService::create} when the
 *    user submits.
 *  - Pruned later if it stays orphan past the configured TTL.
 *
 * Soft-delete + keep-forever R2 policy mirrors the rest of cashflow.
 */
class MovementAttachment extends Model
{
    use SoftDeletes;
    use GuardsTenantBoundary;

    public const KIND_STAFF_ADVANCE = 'staff_advance';
    public const KIND_STAFF_RETURN = 'staff_return';
    public const KIND_STAFF_TRANSFER = 'staff_transfer';

    protected $fillable = [
        'account_id',
        'movement_kind',
        'movement_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'movement_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /** Scope to one bound row by (kind, id). */
    public function scopeForMovement($query, string $kind, int $id)
    {
        return $query->where('movement_kind', $kind)->where('movement_id', $id);
    }

    public function scopeOrphaned($query, int $hours = 48)
    {
        return $query->whereNull('movement_id')
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
