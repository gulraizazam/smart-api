<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreMovementAttachmentRequest;
use App\Models\CashFlow\CashTransferAttachment;
use App\Models\CashFlow\MovementAttachment;
use App\Services\Storage\R2DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Attachments for staff-side movement kinds (advance / return /
 * staff transfer). Mirrors {@see CashTransferAttachmentsController} —
 * same R2 bucket, same SHA-keyed dedup, same orphan→bind lifecycle.
 */
class MovementAttachmentsController extends Controller
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

    public function store(StoreMovementAttachmentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;

        /** @var UploadedFile $file */
        $file = $request->file('file');

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

        $movementKind = $request->input('movement_kind');
        $movementId = $request->input('movement_id');
        if ($movementKind !== null && $movementKind !== '' && $movementId !== null && $movementId !== '') {
            $alreadyAttached = MovementAttachment::forAccount($accountId)
                ->forMovement($movementKind, (int) $movementId)
                ->where('sha256', $sha)
                ->exists();
            if ($alreadyAttached) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['This file is already attached to this movement.'],
                ]);
            }
        }

        // Same key shape as expense + cash transfer attachments, so a
        // single `accounts/<id>/invoices/` prefix scan covers all
        // three domains.
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
            /** @var MovementAttachment $row */
            $row = DB::transaction(function () use ($accountId, $movementKind, $movementId, $file, $key, $sha, $detectedMime, $user) {
                return MovementAttachment::create([
                    'account_id' => $accountId,
                    'movement_kind' => $movementKind ?: null,
                    'movement_id' => $movementId ? (int) $movementId : null,
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
        $row = MovementAttachment::forAccount((int) $user->account_id)->findOrFail($id);

        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        $hasView = $user->can('cashflow.staff_advance.view')
            || $user->can('cashflow.transfer.view')
            || $user->can('cashflow.manage');
        if (! $isOwner && ! $hasView) {
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
        $row = MovementAttachment::forAccount($accountId)->findOrFail($id);

        $isOwner = (int) $row->uploaded_by === (int) $user->id;
        $canEdit = $user->can('cashflow.staff_advance.create')
            || $user->can('cashflow.staff_return.create')
            || $user->can('cashflow.staff_transfer.create')
            || $user->can('cashflow.manage');
        if (! $isOwner && ! $canEdit) {
            abort(403);
        }

        $sha = $row->sha256;
        $key = $row->file_path;

        $row->delete();

        // Cross-domain SHA share: cash transfer + payment attachments
        // can point at the same blob too. Only nuke the R2 object when
        // no live row in this account (across all three tables)
        // references the SHA.
        $stillReferenced = MovementAttachment::forAccount($accountId)
            ->where('sha256', $sha)->exists();
        if (! $stillReferenced) {
            $stillReferenced = CashTransferAttachment::forAccount($accountId)
                ->where('sha256', $sha)->exists();
        }
        if (! $stillReferenced) {
            $stillReferenced = \App\Models\CashFlow\ExpenseAttachment::forAccount($accountId)
                ->where('sha256', $sha)->exists();
        }

        if (! $stillReferenced) {
            try {
                Storage::disk('r2_invoices')->delete($key);
            } catch (\Throwable $e) {
                Log::warning('cashflow.movement_attachment.r2_cleanup_failed', [
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

    private function payload(MovementAttachment $row): array
    {
        $expiresAt = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);

        return [
            'id' => $row->id,
            'movement_kind' => $row->movement_kind,
            'movement_id' => $row->movement_id,
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
