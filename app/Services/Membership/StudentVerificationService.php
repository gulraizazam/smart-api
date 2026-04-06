<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MembershipType;
use App\Models\StudentVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentVerificationService
{
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf',
    ];

    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB

    public function storeVerification(array $data): ?StudentVerification
    {
        $documentPaths = [];

        if (isset($data['documents']) && is_array($data['documents'])) {
            $documentPaths = $this->processDocuments($data['documents']);
        }

        if (empty($documentPaths)) {
            Log::warning('No valid documents to store for student verification.');
            return null;
        }

        $verification = StudentVerification::create([
            'patient_id' => $data['patient_id'],
            'membership_id' => $data['membership_id'] ?? null,
            'membership_type_id' => $data['membership_type_id'],
            'package_id' => $data['package_id'] ?? null,
            'document_paths' => $documentPaths,
            'status' => 'pending',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        Log::info('Student verification created.', [
            'verification_id' => $verification->id,
            'patient_id' => $data['patient_id'],
            'documents_count' => count($documentPaths),
        ]);

        return $verification;
    }

    public function storeDocumentsImmediately(array $documents): array
    {
        return $this->processDocuments($documents);
    }

    public function hasDocuments(array $documents): bool
    {
        foreach ($documents as $document) {
            if ($document instanceof UploadedFile && $document->isValid()) {
                return true;
            }
        }

        return false;
    }

    public function createVerificationRecord(array $data): ?StudentVerification
    {
        if (empty($data['document_paths'])) {
            Log::warning('createVerificationRecord: No document paths provided.');
            return null;
        }

        $verification = StudentVerification::create([
            'patient_id' => $data['patient_id'],
            'membership_id' => $data['membership_id'] ?? null,
            'membership_type_id' => $data['membership_type_id'],
            'package_id' => $data['package_id'] ?? null,
            'document_paths' => $data['document_paths'],
            'status' => 'pending',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        Log::info('Student verification record created.', [
            'verification_id' => $verification->id,
            'patient_id' => $data['patient_id'],
            'documents_count' => count($data['document_paths']),
        ]);

        return $verification;
    }

    public function isStudentMembership(int $membershipTypeId): bool
    {
        $membershipType = MembershipType::find($membershipTypeId);

        return $membershipType !== null && stripos($membershipType->name, 'student') !== false;
    }

    public function getVerificationByMembership(int $patientId, int $membershipId): ?StudentVerification
    {
        return StudentVerification::where('patient_id', $patientId)
            ->where('membership_id', $membershipId)
            ->first();
    }

    public function approveVerification(int $verificationId): StudentVerification
    {
        $verification = StudentVerification::findOrFail($verificationId);

        $verification->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        Log::info('Student verification approved.', [
            'verification_id' => $verificationId,
            'reviewed_by' => Auth::id(),
        ]);

        return $verification;
    }

    public function rejectVerification(int $verificationId, string $reason): StudentVerification
    {
        $verification = StudentVerification::findOrFail($verificationId);

        $verification->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        Log::info('Student verification rejected.', [
            'verification_id' => $verificationId,
            'reviewed_by' => Auth::id(),
        ]);

        return $verification;
    }

    public function deleteDocuments(StudentVerification $verification): void
    {
        if (! empty($verification->document_paths)) {
            foreach ($verification->document_paths as $path) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Process and store uploaded documents with MIME/extension validation.
     *
     * @param  array<int, mixed>  $documents
     * @return array<int, string>
     */
    private function processDocuments(array $documents): array
    {
        $storedPaths = [];

        foreach ($documents as $index => $document) {
            if (! $document instanceof UploadedFile || ! $document->isValid()) {
                continue;
            }

            if (! $this->isAllowedFile($document)) {
                Log::warning('Document rejected: invalid type or size.', [
                    'index' => $index,
                    'mime' => $document->getClientMimeType(),
                    'extension' => $document->getClientOriginalExtension(),
                    'size' => $document->getSize(),
                ]);
                continue;
            }

            try {
                $extension = strtolower($document->getClientOriginalExtension());
                if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    $extension = 'jpg';
                }

                $filename = 'student_doc_' . now()->timestamp . "_{$index}_" . Str::random(16) . '.' . $extension;
                $path = $document->storeAs('student_verifications', $filename, 'public');

                if ($path) {
                    $storedPaths[] = $path;
                    Log::info('Document stored.', [
                        'path' => $path,
                        'original_name' => $document->getClientOriginalName(),
                        'size' => $document->getSize(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to store document.', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $storedPaths;
    }

    private function isAllowedFile(UploadedFile $file): bool
    {
        $mime = $file->getClientMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return false;
        }

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        if ($size === false || $size > self::MAX_FILE_SIZE_BYTES) {
            return false;
        }

        return true;
    }
}
