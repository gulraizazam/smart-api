<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreVendorTransactionAttachmentRequest;
use App\Models\CashFlow\VendorTransactionAttachment;
use App\Services\Storage\R2DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Multi-file invoice / receipt attachments for vendor transactions.
 *
 * Exact mirror of {@see ExpenseAttachmentsController}: same private
 * `invoices` R2 bucket via the bucket-agnostic R2DocumentService (bound
 * contextually in AppServiceProvider), same content-addressed-by-SHA-256
 * keys, same keep-forever soft-delete policy (the blob is only stripped
 * by `cashflow:prune-orphan-attachments`).
 */
class VendorTransactionAttachmentsController extends Controller
{
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
     * blob. The SPA collects the returned id(s) and passes them in the
     * eventual purchase create/edit / deliver payload.
     */
    public function store(StoreVendorTransactionAttachmentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;

        /** @var UploadedFile $file */
        $file = $request->file('file');

        // 1. Magic-byte sniff — reads real bytes, not the upload header.
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if (! array_key_exists($detectedMime, self::MAGIC_BYTE_ALLOWLIST)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['File content does not match an allowed type (detected: '.($detectedMime ?: 'unknown').').'],
            ]);
        }
        $ext = self::MAGIC_BYTE_ALLOWLIST[$detectedMime];

        // 2. SHA-256 over the raw bytes — dedup + content-addressed key.
        $sha = hash_file('sha256', $file->getRealPath()) ?: '';
        if (strlen($sha) !== 64) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['Failed to hash file.'],
            ]);
        }

        // 2a. Defense-in-depth: reject a same-file dup already attached
        //     to this transaction (the SPA pre-checks via SubtleCrypto;
        //     a raw client would bypass that).
        $txId = $request->input('vendor_transaction_id');
        if ($txId !== null && $txId !== '') {
            $alreadyAttached = VendorTransactionAttachment::forAccount($accountId)
                ->where('vendor_transaction_id', (int) $txId)
                ->where('sha256', $sha)
                ->exists();
            if ($alreadyAttached) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['This file is already attached to this transaction.'],
                ]);
            }
        }

        // 3. Content-addressed key (same bucket + layout as expenses).
        $key = sprintf(
            'accounts/%d/invoices/%s/%s/%s.%s',
            $accountId,
            substr($sha, 0, 2),
            substr($sha, 2, 2),
            $sha,
            $ext,
        );

        // 4. PUT only on cache miss.
        $disk = Storage::disk('r2_invoices');
        $freshlyUploaded = false;
        if (! $disk->exists($key)) {
            $disk->putFileAs(dirname($key), $file, basename($key));
            $freshlyUploaded = true;
        }

        // 5. Persist; best-effort R2 cleanup if the insert fails after a
        //    fresh upload so we never leak an unreferenced blob.
        try {
            /** @var VendorTransactionAttachment $row */
            $row = DB::transaction(function () use ($accountId, $request, $file, $key, $sha, $detectedMime, $user) {
                return VendorTransactionAttachment::create([
                    'account_id' => $accountId,
                    'vendor_transaction_id' => $request->input('vendor_transaction_id'),
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
     * Mint a short-lived signed URL the SPA can drop into <a> / <img>.
     */
    public function signedUrl(int $id): JsonResponse
    {
        $user = Auth::user();
        $row = VendorTransactionAttachment::forAccount((int) $user->account_id)->findOrFail($id);

        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        if (! $isOwner
            && ! $user->can('cashflow.vendor.view')
            && ! $user->can('cashflow.vendor.manage')
            && ! $user->can('cashflow.manage')) {
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
     * Soft-delete the row + best-effort R2 cleanup (only when no other
     * live row in this account references the same SHA — blobs are
     * content-addressed and shared).
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;
        $row = VendorTransactionAttachment::forAccount($accountId)->findOrFail($id);

        // An invoice is removed while *editing* an order, so the edit
        // grant must let you detach — not just create/manage. Without it a
        // user who can edit a purchase but not create one is 403'd trying
        // to remove an invoice (mirrors the expense attachment gate, which
        // accepts `cashflow.expense.edit`). The owner can always detach
        // their own upload.
        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        if (! $isOwner
            && ! $user->can('cashflow.vendor.transaction.create')
            && ! $user->can('cashflow.vendor.transaction.edit')
            && ! $user->can('cashflow.vendor.manage')
            && ! $user->can('cashflow.manage')) {
            abort(403);
        }

        $sha = $row->sha256;
        $key = $row->file_path;

        $row->delete();

        $stillReferenced = VendorTransactionAttachment::forAccount($accountId)
            ->where('sha256', $sha)
            ->exists();

        if (! $stillReferenced) {
            try {
                Storage::disk('r2_invoices')->delete($key);
            } catch (\Throwable $e) {
                Log::warning('cashflow.vendor_attachment.r2_cleanup_failed', [
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
     * Standard response payload — includes an immediate signed URL so the
     * SPA can preview without a second round-trip.
     */
    private function payload(VendorTransactionAttachment $row): array
    {
        $expiresAt = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);

        return [
            'id' => $row->id,
            'vendor_transaction_id' => $row->vendor_transaction_id,
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
