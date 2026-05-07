<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    private const MAX_FILE_SIZE = 5120; // 5MB in KB
    private const DISK = 'r2';

    public function store(Request $request, User $user): JsonResponse
    {
        try {
            $request->validate([
                'document' => [
                    'required',
                    'file',
                    'max:' . self::MAX_FILE_SIZE,
                    'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
                ],
            ], [
                'document.max' => 'File size must not exceed 5MB.',
                'document.mimes' => 'Allowed file types: PDF, DOC, DOCX, JPG, PNG.',
            ]);

            $file = $request->file('document');
            $fileName = $file->getClientOriginalName();
            $accountId = Auth::user()->account_id;
            $path = $file->store("accounts/{$accountId}/hr/documents/{$user->id}", self::DISK);

            $document = EmployeeDocument::create([
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_path' => $path,
                'uploaded_by' => Auth::id(),
                'account_id' => $accountId,
            ]);

            return $this->successResponse('Document uploaded successfully.', [
                'id' => (int) $document->id,
                'file_name' => (string) $document->file_name,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeDocumentController@store');
        }
    }

    public function preview(EmployeeDocument $document)
    {
        $this->authorizeDocumentAccess($document);

        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return response($disk->get($document->file_path), 200, [
            'Content-Type' => $disk->mimeType($document->file_path),
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
        ]);
    }

    public function download(EmployeeDocument $document)
    {
        $this->authorizeDocumentAccess($document);

        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return response($disk->get($document->file_path), 200, [
            'Content-Type' => $disk->mimeType($document->file_path),
            'Content-Disposition' => 'attachment; filename="' . $document->file_name . '"',
        ]);
    }

    private function authorizeDocumentAccess(EmployeeDocument $document): void
    {
        $user = Auth::user();
        $isOwner = (int) $document->user_id === $user->id;
        $hasPermission = $user->can('hr_documents_manage');

        abort_unless($isOwner || $hasPermission, 403, 'Unauthorized.');
    }

    public function destroy(EmployeeDocument $document): JsonResponse
    {
        try {
            Storage::disk(self::DISK)->delete($document->file_path);
            $document->delete();

            return $this->successResponse('Document deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeDocumentController@destroy');
        }
    }
}
