<?php

namespace App\Services\Membership;

use App\Models\StudentVerification;
use App\Models\MembershipType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StudentVerificationService
{
    /**
     * Store student verification documents
     *
     * @param array $data
     * @return StudentVerification
     */
    public function storeVerification(array $data)
    {
        try {
            $documentPaths = [];

            Log::info('Starting document storage', [
                'has_documents_key' => isset($data['documents']),
                'documents_type' => isset($data['documents']) ? gettype($data['documents']) : 'not set',
                'documents_count' => isset($data['documents']) && is_array($data['documents']) ? count($data['documents']) : 0
            ]);

            // Store uploaded documents
            if (isset($data['documents']) && is_array($data['documents'])) {
                foreach ($data['documents'] as $index => $document) {
                    // Check if document is a valid UploadedFile instance
                    if ($document instanceof \Illuminate\Http\UploadedFile && $document->isValid()) {
                        try {
                            // Check if the temp file still exists
                            $tempPath = $document->getRealPath();
                            if (empty($tempPath) || !file_exists($tempPath)) {
                                Log::warning('Document temp file no longer exists', [
                                    'index' => $index,
                                    'original_name' => $document->getClientOriginalName()
                                ]);
                                continue;
                            }
                            
                            // Generate a unique filename
                            $extension = $document->getClientOriginalExtension() ?: 'jpg';
                            $filename = 'student_doc_' . time() . '_' . $index . '_' . uniqid() . '.' . $extension;
                            
                            // Ensure the directory exists
                            $storagePath = storage_path('app/public/student_verifications');
                            if (!file_exists($storagePath)) {
                                mkdir($storagePath, 0755, true);
                            }
                            
                            // Move the file to storage
                            $destinationPath = $storagePath . '/' . $filename;
                            if (copy($tempPath, $destinationPath)) {
                                $path = 'student_verifications/' . $filename;
                                $documentPaths[] = $path;
                                Log::info('Document stored successfully', [
                                    'path' => $path,
                                    'original_name' => $document->getClientOriginalName(),
                                    'size' => $document->getSize()
                                ]);
                            } else {
                                Log::error('Failed to copy document', [
                                    'index' => $index,
                                    'from' => $tempPath,
                                    'to' => $destinationPath
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to store document', [
                                'index' => $index,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    } else {
                        Log::warning('Skipping invalid document', [
                            'index' => $index,
                            'type' => gettype($document)
                        ]);
                    }
                }
            }
            
            // Don't create verification record if no documents were uploaded
            if (empty($documentPaths)) {
                Log::warning('No valid documents to store for student verification');
                return null;
            }

            // Create verification record
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

            Log::info('Student verification created', [
                'verification_id' => $verification->id,
                'patient_id' => $data['patient_id'],
                'documents_count' => count($documentPaths)
            ]);

            return $verification;
        } catch (\Exception $e) {
            Log::error('Failed to store student verification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Check if membership type is student membership
     *
     * @param int $membershipTypeId
     * @return bool
     */
    public function isStudentMembership(int $membershipTypeId): bool
    {
        $membershipType = MembershipType::find($membershipTypeId);
        
        if (!$membershipType) {
            return false;
        }

        return stripos($membershipType->name, 'student') !== false;
    }

    /**
     * Check if documents are uploaded
     *
     * @param array $documents
     * @return bool
     */
    /**
     * Check if documents are uploaded and store them immediately
     * Returns array of stored paths, or empty array if no valid documents
     *
     * @param array $documents
     * @return array
     */
    public function storeDocumentsImmediately(array $documents): array
    {
        $storedPaths = [];
        
        if (empty($documents)) {
            \Log::info('storeDocumentsImmediately: empty documents array');
            return $storedPaths;
        }

        // Ensure the directory exists
        $storagePath = storage_path('app/public/student_verifications');
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        foreach ($documents as $index => $document) {
            // Check if it's a valid uploaded file object
            if ($document instanceof \Illuminate\Http\UploadedFile) {
                try {
                    // Get file info before any operations that might consume it
                    $originalName = $document->getClientOriginalName();
                    $extension = $document->getClientOriginalExtension() ?: 'jpg';
                    $mimeType = $document->getClientMimeType();
                    
                    \Log::info('storeDocumentsImmediately: processing file', [
                        'index' => $index,
                        'original_name' => $originalName,
                        'extension' => $extension,
                        'mime_type' => $mimeType
                    ]);
                    
                    // Try to get file contents directly using file_get_contents on the temp path
                    // or use the UploadedFile's get() method
                    $fileContent = null;
                    
                    // Method 1: Try to read from temp path
                    $tempPath = $document->getRealPath();
                    if (!empty($tempPath) && file_exists($tempPath)) {
                        $fileContent = file_get_contents($tempPath);
                        \Log::info('storeDocumentsImmediately: read from temp path', ['temp_path' => $tempPath]);
                    }
                    
                    // Method 2: If temp path failed, try getContent() method (Laravel 8+)
                    if (empty($fileContent) && method_exists($document, 'getContent')) {
                        $fileContent = $document->getContent();
                        \Log::info('storeDocumentsImmediately: read using getContent()');
                    }
                    
                    // Method 3: Try reading from php://input stream
                    if (empty($fileContent)) {
                        $tempPath = $document->getPathname();
                        if (!empty($tempPath) && file_exists($tempPath)) {
                            $fileContent = file_get_contents($tempPath);
                            \Log::info('storeDocumentsImmediately: read from pathname', ['pathname' => $tempPath]);
                        }
                    }
                    
                    if (!empty($fileContent)) {
                        // Generate a unique filename and write to disk
                        $filename = 'student_doc_' . time() . '_' . $index . '_' . uniqid() . '.' . $extension;
                        $destinationPath = $storagePath . '/' . $filename;
                        
                        if (file_put_contents($destinationPath, $fileContent)) {
                            $path = 'student_verifications/' . $filename;
                            $storedPaths[] = $path;
                            \Log::info('storeDocumentsImmediately: document stored', [
                                'path' => $path,
                                'original_name' => $originalName,
                                'size' => strlen($fileContent)
                            ]);
                        } else {
                            \Log::error('storeDocumentsImmediately: failed to write file', [
                                'destination' => $destinationPath
                            ]);
                        }
                    } else {
                        \Log::warning('storeDocumentsImmediately: could not read file content', [
                            'index' => $index,
                            'original_name' => $originalName
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('storeDocumentsImmediately: failed to store', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                \Log::warning('storeDocumentsImmediately: not an UploadedFile', [
                    'index' => $index,
                    'type' => gettype($document)
                ]);
            }
        }

        return $storedPaths;
    }
    
    /**
     * Check if documents are uploaded (legacy method for compatibility)
     *
     * @param array $documents
     * @return bool
     */
    public function hasDocuments(array $documents): bool
    {
        if (empty($documents)) {
            return false;
        }

        foreach ($documents as $document) {
            if ($document instanceof \Illuminate\Http\UploadedFile && $document->isValid()) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Create verification record with already-stored document paths
     *
     * @param array $data
     * @return StudentVerification
     */
    public function createVerificationRecord(array $data)
    {
        try {
            if (empty($data['document_paths'])) {
                Log::warning('createVerificationRecord: No document paths provided');
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

            Log::info('Student verification record created', [
                'verification_id' => $verification->id,
                'patient_id' => $data['patient_id'],
                'package_id' => $data['package_id'],
                'documents_count' => count($data['document_paths'])
            ]);

            return $verification;
        } catch (\Exception $e) {
            Log::error('Failed to create student verification record', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get verification by patient and membership
     *
     * @param int $patientId
     * @param int $membershipId
     * @return StudentVerification|null
     */
    public function getVerificationByMembership(int $patientId, int $membershipId)
    {
        return StudentVerification::where('patient_id', $patientId)
            ->where('membership_id', $membershipId)
            ->first();
    }

    /**
     * Approve verification
     *
     * @param int $verificationId
     * @return StudentVerification
     */
    public function approveVerification(int $verificationId)
    {
        $verification = StudentVerification::findOrFail($verificationId);
        
        $verification->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        Log::info('Student verification approved', [
            'verification_id' => $verificationId,
            'reviewed_by' => Auth::id()
        ]);

        return $verification;
    }

    /**
     * Reject verification
     *
     * @param int $verificationId
     * @param string $reason
     * @return StudentVerification
     */
    public function rejectVerification(int $verificationId, string $reason)
    {
        $verification = StudentVerification::findOrFail($verificationId);
        
        $verification->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        Log::info('Student verification rejected', [
            'verification_id' => $verificationId,
            'reviewed_by' => Auth::id(),
            'reason' => $reason
        ]);

        return $verification;
    }

    /**
     * Delete verification documents
     *
     * @param StudentVerification $verification
     * @return void
     */
    public function deleteDocuments(StudentVerification $verification)
    {
        if (!empty($verification->document_paths)) {
            foreach ($verification->document_paths as $path) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
