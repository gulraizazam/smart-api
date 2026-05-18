<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreCashTransferAttachmentRequest;
use App\Models\CashFlow\CashTransferAttachment;
use App\Services\Storage\R2DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Multi-file attachments for cash-flow Transfers (pool→pool).
 *
 * Direct mirror of {@see ExpenseAttachmentsController} — same R2 bucket,
 * same SHA-256-derived content-addressed keys, same orphan-then-bind
 * lifecycle, same soft-delete + keep-forever policy. Keeping the two
 * controllers split (rather than introducing a polymorphic shared one)
 * keeps each side's permission gates linear and obvious.
 */
class CashTransferAttachmentsController extends Controller
{
    private const MAGIC_BYTE_ALLOWLIST = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/webp' => 'webp',
    ];

    private const SIGNED_URL_TTL_MINUTES = 15;

    public function __construct(private readonly R2DocumentService $r2) {}

    public function store(StoreCashTransferAttachmentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;

        /** @var UploadedFile $file */
        $file = $request->file('file');

        // Magic-byte sniff — catches "rename .exe to .pdf" past the `mimes:` rule.
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if (! array_key_exists($detectedMime, self::MAGIC_BYTE_ALLOWLIST)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['File content does not match an allowed type (detected: '.($detectedMime ?: 'unknown').').'],
            ]);
        }
        $ext = self::MAGIC_BYTE_ALLOWLIST[$detectedMime];

        $sha = hash_file('sha256', $file->getRealPath()) ?: '';
        if (strlen($sha) !== 64) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['Failed to hash file.'],
            ]);
        }

        // Defense-in-depth: if pre-bound (edit flow — future), reject
        // same file twice on the same transfer.
        $transferId = $request->input('cash_transfer_id');
        if ($transferId !== null && $transferId !== '') {
            $alreadyAttached = CashTransferAttachment::forAccount($accountId)
                ->where('cash_transfer_id', (int) $transferId)
                ->where('sha256', $sha)
                ->exists();
            if ($alreadyAttached) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['This file is already attached to this transfer.'],
                ]);
            }
        }

        // Content-addressed key — same shape as expense attachments so a
        // single `accounts/<id>/invoices/` prefix scan covers both.
        $key = sprintf(
            'accounts/%d/invoices/%s/%s/%s.%s',
            $accountId,
            substr($sha, 0, 2),
            substr($sha, 2, 2),
            $sha,
            $ext,
        );

        $disk = Storage::disk('r2_invoices');
        $freshlyUploaded = false;
        if (! $disk->exists($key)) {
            $disk->putFileAs(dirname($key), $file, basename($key));
            $freshlyUploaded = true;
        }

        try {
            /** @var CashTransferAttachment $row */
            $row = DB::transaction(function () use ($accountId, $request, $file, $key, $sha, $detectedMime, $user) {
                return CashTransferAttachment::create([
                    'account_id' => $accountId,
                    'cash_transfer_id' => $request->input('cash_transfer_id'),
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

    public function signedUrl(int $id): JsonResponse
    {
        $user = Auth::user();
        $row = CashTransferAttachment::forAccount((int) $user->account_id)->findOrFail($id);

        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        if (! $isOwner && ! $user->can('cashflow_transfer_view') && ! $user->can('cashflow_manage')) {
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

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;
        $row = CashTransferAttachment::forAccount($accountId)->findOrFail($id);

        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        if (! $isOwner && ! $user->can('cashflow_transfer_create') && ! $user->can('cashflow_manage')) {
            abort(403);
        }

        $sha = $row->sha256;
        $key = $row->file_path;

        $row->delete();

        $stillReferenced = CashTransferAttachment::forAccount($accountId)
            ->where('sha256', $sha)
            ->exists();

        // Also check whether an expense attachment in the same account
        // points at the same SHA — payments + transfers share one R2
        // bucket via the content-addressed key, so deleting the blob
        // would yank it out from under a payment too.
        if (! $stillReferenced) {
            $stillReferenced = \App\Models\CashFlow\ExpenseAttachment::forAccount($accountId)
                ->where('sha256', $sha)
                ->exists();
        }

        if (! $stillReferenced) {
            try {
                Storage::disk('r2_invoices')->delete($key);
            } catch (\Throwable $e) {
                Log::warning('cashflow.transfer_attachment.r2_cleanup_failed', [
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

    private function payload(CashTransferAttachment $row): array
    {
        $expiresAt = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);

        return [
            'id' => $row->id,
            // Frontend convention is `expense_id` for the payment dropzone,
            // so the parallel field name keeps the SPA's AttachmentDropZone
            // API symmetric across the two domains.
            'cash_transfer_id' => $row->cash_transfer_id,
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
