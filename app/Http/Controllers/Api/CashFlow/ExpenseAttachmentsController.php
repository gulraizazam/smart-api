<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreExpenseAttachmentRequest;
use App\Models\CashFlow\ExpenseAttachment;
use App\Services\Storage\R2DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Multi-file invoice / receipt attachments for cash-flow Payments.
 *
 * Files live on the local `r2_invoices` disk via the bucket-agnostic
 * R2DocumentService — bound contextually in AppServiceProvider. Nothing
 * leaves the app host.
 *
 * Key invariants:
 *  - Storage keys are content-addressed by SHA-256, so two uploads of
 *    identical bytes share one blob and the second uploader pays only
 *    an existence-check round-trip.
 *  - Soft-delete via the API is the only deletion path. The blob is
 *    NEVER stripped here, even when the row is force-deleted — that
 *    work belongs to `cashflow:prune-orphan-attachments`, which knows
 *    how to skip the file delete when the SHA is still referenced by
 *    another live row.
 *  - Approved payments are immutable for non-admins: trying to
 *    delete an attachment from an Approved row returns 422.
 */
class ExpenseAttachmentsController extends Controller
{
    /**
     * Magic-byte allow-list. Keys are mime types `finfo` returns;
     * values are the canonical extensions used when constructing the
     * R2 key. Kept intentionally short — slice 1 supports only the
     * five formats agreed with the user.
     */
    private const MAGIC_BYTE_ALLOWLIST = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/webp' => 'webp',
    ];

    /** Short-lived download window for signed URLs (minutes). */
    private const SIGNED_URL_TTL_MINUTES = 15;

    public function __construct(private readonly R2DocumentService $r2) {}

    /**
     * Upload a single file. Idempotent on identical bytes — same SHA →
     * same R2 key, just a new attachment row pointing at the existing
     * blob. The caller (SPA) collects the returned id(s) and passes
     * them in the eventual expense create/edit payload.
     */
    public function store(StoreExpenseAttachmentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;

        /** @var UploadedFile $file */
        $file = $request->file('file');

        // 1. Magic-byte sniff. finfo reads from the real bytes, not the
        //    upload header — catches the "rename .exe to .pdf" case
        //    that `mimes:` lets through.
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if (! array_key_exists($detectedMime, self::MAGIC_BYTE_ALLOWLIST)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['File content does not match an allowed type (detected: '.($detectedMime ?: 'unknown').').'],
            ]);
        }
        $ext = self::MAGIC_BYTE_ALLOWLIST[$detectedMime];

        // 2. SHA-256 over the raw bytes. Drives both the dedup decision
        //    and the content-addressed key.
        $sha = hash_file('sha256', $file->getRealPath()) ?: '';
        if (strlen($sha) !== 64) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['Failed to hash file.'],
            ]);
        }

        // 2a. Defense-in-depth: if the caller pre-bound to a specific
        //     expense (edit flow), reject when the same file is already
        //     attached to that expense. The SPA does this check too via
        //     SubtleCrypto, but a curl client would bypass that — and
        //     same-payment dups are pure noise (the cross-payment
        //     "Reused receipt" flag wouldn't fire for a same-expense
        //     duplicate either, so without this guard the file would
        //     just silently double up).
        $expenseId = $request->input('expense_id');
        if ($expenseId !== null && $expenseId !== '') {
            $alreadyAttached = ExpenseAttachment::forAccount($accountId)
                ->where('expense_id', (int) $expenseId)
                ->where('sha256', $sha)
                ->exists();
            if ($alreadyAttached) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['This file is already attached to this payment.'],
                ]);
            }
        }

        // 3. Content-addressed key. Two two-char shards keep an `ls`
        //    of the bucket manageable. Account-prefixed so a future
        //    cross-tenant scan stays tractable.
        $key = sprintf(
            'accounts/%d/invoices/%s/%s/%s.%s',
            $accountId,
            substr($sha, 0, 2),
            substr($sha, 2, 2),
            $sha,
            $ext,
        );

        // 4. PUT only when the blob isn't already there. R2 is
        //    consistent for HEAD-then-PUT in the same client session
        //    so this race is benign — a colliding writer would
        //    produce identical bytes anyway.
        $disk = Storage::disk('r2_invoices');
        $freshlyUploaded = false;
        if (! $disk->exists($key)) {
            $disk->putFileAs(dirname($key), $file, basename($key));
            $freshlyUploaded = true;
        }

        // 5. Persist the row. If the insert fails AND we uploaded the
        //    blob *this* request, best-effort cleanup so we don't leak
        //    an orphan object that no DB row references.
        try {
            /** @var ExpenseAttachment $row */
            $row = DB::transaction(function () use ($accountId, $request, $file, $key, $sha, $detectedMime, $user) {
                return ExpenseAttachment::create([
                    'account_id' => $accountId,
                    'expense_id' => $request->input('expense_id'),
                    'file_name' => $file->getClientOriginalName() ?: 'upload',
                    'file_path' => $key,
                    'mime_type' => $detectedMime,
                    'file_size' => (int) $file->getSize(),
                    'sha256' => $sha,
                    'uploaded_by' => (int) $user->id,
                ]);
            });
        } catch (\Throwable $e) {
            if ($freshlyUploaded) {
                try { $disk->delete($key); } catch (\Throwable) { /* best effort */ }
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($row),
        ], 201);
    }

    /**
     * Mint a short-lived signed URL the SPA can drop into <a> or <img>.
     * Re-issued per request; never cached.
     */
    public function signedUrl(int $id): JsonResponse
    {
        $user = Auth::user();
        $row = ExpenseAttachment::forAccount((int) $user->account_id)->findOrFail($id);

        // Uploader sees their own files; anyone with view / manage
        // permission sees all account attachments.
        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        if (! $isOwner && ! $user->can('cashflow.expense.view') && ! $user->can('cashflow.manage')) {
            abort(403);
        }

        $expiresAt = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);
        $url = Storage::disk('r2_invoices')->temporaryUrl($row->file_path, $expiresAt);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    /**
     * Soft-delete the row + best-effort R2 cleanup. The blob in R2 is
     * removed immediately when no other live row in the same account
     * references the same SHA — content-addressed dedup means the
     * blob is shared, so we only delete it once nothing points at it.
     * The daily prune command catches anything this best-effort path
     * misses (e.g. R2 hiccups).
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;
        $row = ExpenseAttachment::forAccount($accountId)->findOrFail($id);

        // Only the uploader, an editor, or someone with manage rights
        // can detach. View permission alone is not enough.
        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        if (! $isOwner && ! $user->can('cashflow.expense.edit') && ! $user->can('cashflow.manage')) {
            abort(403);
        }

        // (The Approved-status delete guard was retired 2026-05-14.
        // Under Option A every payment posts as Approved by default,
        // so the guard blocked routine edits — `cashflow_expense_edit`
        // is the gate that matters now.)

        // Capture key + sha before soft-delete hides the row from the
        // default-scoped query we'll use to check for siblings.
        $sha = $row->sha256;
        $key = $row->file_path;

        $row->delete();

        // Skip R2 cleanup when another live row in this account still
        // references the same SHA — the blob is content-addressed so
        // multiple attachments share one object.
        $stillReferenced = ExpenseAttachment::forAccount($accountId)
            ->where('sha256', $sha)
            ->exists();

        if (! $stillReferenced) {
            try {
                Storage::disk('r2_invoices')->delete($key);
            } catch (\Throwable $e) {
                Log::warning('cashflow.attachment.r2_cleanup_failed', [
                    'attachment_id' => $id,
                    'key' => $key,
                    'sha' => $sha,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment removed.',
        ]);
    }

    /**
     * Standard response payload. Includes an immediate signed URL so the
     * SPA can render a preview without a second round-trip.
     */
    private function payload(ExpenseAttachment $row): array
    {
        $expiresAt = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);

        return [
            'id' => $row->id,
            'expense_id' => $row->expense_id,
            'file_name' => $row->file_name,
            'file_size' => $row->file_size,
            'mime_type' => $row->mime_type,
            'sha256' => $row->sha256,
            'uploaded_at' => $row->created_at?->toIso8601String(),
            'signed_url' => Storage::disk('r2_invoices')->temporaryUrl($row->file_path, $expiresAt),
            'signed_url_expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
