<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Documents;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Round 4 C3 — sensitive uploads served only via authenticated, tenant-scoped routes.
 *
 * Patient profile pictures, patient document attachments, and student-membership
 * verification documents used to live under storage/app/public/ which is exposed
 * verbatim through the public/storage symlink. Anyone who knew (or guessed) the
 * filename pattern (unix timestamp + 13-char hex) could fetch them directly without
 * authenticating. For a healthcare-adjacent CRM, that is a HIPAA/PIPEDA/GDPR-class
 * exposure.
 *
 * The fix has two halves:
 *  1. Files now live under storage/app/{patient_image,student_verifications}/
 *     — outside the symlink, so they are NOT web-accessible.
 *  2. The only path to read them is through the actions in this controller, each
 *     of which (a) requires session auth via the surrounding admin middleware,
 *     and (b) verifies that Auth::user()->account_id matches the row that owns
 *     the file. Cross-tenant lookups return 404, never the file content.
 */
final class PatientFileController extends Controller
{
    /**
     * Stream a patient profile picture or patient-document attachment by filename.
     *
     * Files in this directory are referenced by either:
     *   - users.image_src (profile picture)
     *   - documents.url (uploaded patient documents, stored as 'patient_image/<filename>')
     *
     * We try profile pictures first because that path is hot, then fall back to
     * document lookup. In both cases the owning patient's account_id must match
     * the caller's account_id.
     */
    public function patientImage(string $filename): StreamedResponse
    {
        $this->guardFilename($filename);

        $accountId = (int) Auth::user()->account_id;

        // 1) Profile picture lookup
        $owner = User::where('image_src', $filename)
            ->where('account_id', $accountId)
            ->first();

        if (! $owner) {
            // 2) Document lookup. Documents.url is stored as 'patient_image/<filename>'.
            //    The patient relation lives on Documents -> User (user_id), so we
            //    constrain by the owning user's account_id.
            $document = Documents::where('url', 'patient_image/' . $filename)
                ->whereHas('patient', static fn ($q) => $q->where('account_id', $accountId))
                ->first();

            if (! $document) {
                abort(404);
            }
        }

        $diskPath = 'patient_image/' . $filename;
        if (! Storage::disk('local')->exists($diskPath)) {
            abort(404);
        }

        return Storage::disk('local')->response($diskPath, $filename, [
            'Cache-Control'        => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Stream a student-verification document by filename.
     *
     * StudentVerification.document_paths is a JSON array of relative paths in the
     * form 'student_verifications/<filename>'. We match by filename suffix and
     * verify the verification's patient belongs to the caller's account.
     */
    public function studentVerification(string $filename): StreamedResponse
    {
        $this->guardFilename($filename);

        $accountId = (int) Auth::user()->account_id;
        $relativePath = 'student_verifications/' . $filename;

        // JSON LIKE search narrows the candidate set; we then verify the exact
        // path is present in the decoded array to avoid a substring false-match.
        $verification = StudentVerification::where('document_paths', 'like', '%' . $filename . '%')
            ->whereHas('patient', static fn ($q) => $q->where('account_id', $accountId))
            ->first();

        if (! $verification) {
            abort(404);
        }

        $paths = (array) $verification->document_paths;
        if (! in_array($relativePath, $paths, true)) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($relativePath)) {
            abort(404);
        }

        return Storage::disk('local')->response($relativePath, $filename, [
            'Cache-Control'        => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Reject anything that is not a flat filename. Path traversal (`..`),
     * directory separators, NUL bytes, and any other oddity bail with 404.
     * The route regex already enforces this at the framework boundary, but
     * we keep the check defensively in case the action is invoked from
     * elsewhere (tests, console, etc.).
     */
    private function guardFilename(string $filename): void
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $filename) !== 1) {
            abort(404);
        }
    }
}
