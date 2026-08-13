<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service for Cloudflare R2-backed document storage.
 *
 * Consolidates three previously-inline R2 flows (HR employee documents,
 * HR candidate CVs, patient records) behind one API. The service itself
 * is bucket-agnostic — AppServiceProvider wires two contextual bindings
 * so `EmployeeDocumentController` and `RecruitmentController` receive an
 * instance pointed at the HRM bucket, and `PatientService` receives one
 * pointed at the clients bucket. Callers always type-hint this class.
 */
final class R2DocumentService
{
    /** Max TTL for signed download URLs (CLAUDE.md §API: 5–15 min). */
    public const DEFAULT_URL_TTL_MINUTES = 15;

    public function __construct(protected readonly Filesystem $disk) {}

    /**
     * Upload a file and return the bucket key.
     *
     * When $filename is null Laravel's default hash naming is used; pass
     * an explicit name (e.g. UUID.ext) when the caller needs a
     * deterministic path that doesn't leak the source filename.
     *
     * Set $allowedExtensions to an empty array to skip extension gating;
     * callers usually also validate via FormRequest `mimes:` rules, so
     * the in-service check here is a defense-in-depth layer, not the
     * primary validator.
     *
     * @param  array<int, string>  $allowedExtensions  lowercase, no leading dot
     *
     * @throws \InvalidArgumentException when the extension is not permitted
     */
    public function store(
        UploadedFile $file,
        string $directory,
        array $allowedExtensions = [],
        ?string $filename = null,
    ): string {
        if ($allowedExtensions !== []) {
            $ext = strtolower(
                $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: '')
            );
            if (! in_array($ext, $allowedExtensions, true)) {
                throw new \InvalidArgumentException(
                    'File type not allowed. Allowed: '.implode(', ', $allowedExtensions)
                );
            }
        }

        /**
         * Both putFile / putFileAs live on FilesystemAdapter (the concrete
         * class Storage::disk() returns), not on the Filesystem contract.
         * At runtime the methods resolve fine; the @phpstan-ignore pins
         * suppress the static-analysis false positive from the contract
         * typing.
         *
         * @phpstan-ignore-next-line
         */
        return $filename !== null
            /** @phpstan-ignore-next-line */
            ? (string) $this->disk->putFileAs($directory, $file, $filename)
            /** @phpstan-ignore-next-line */
            : (string) $this->disk->putFile($directory, $file);
    }

    /** True if the key exists in the bucket. */
    public function exists(string $key): bool
    {
        return $this->disk->exists($key);
    }

    /**
     * Delete a key. Silent on missing keys or transient R2 errors — the
     * DB row is the source of truth, and a stray orphaned object in the
     * bucket is preferable to a 500 on user-initiated deletion.
     */
    public function delete(string $key): void
    {
        if ($key === '') {
            return;
        }

        try {
            $this->disk->delete($key);
        } catch (\Throwable) {
            // intentional: see method docblock.
        }
    }

    /**
     * Short-lived signed URL the browser can fetch directly.
     *
     * Use when the request originates from a SPA that can't pass session
     * cookies on a navigation (e.g. bearer-token auth, `<a target="_blank">`).
     * The URL embeds an R2 presigned signature and expires after $ttlMinutes.
     */
    public function temporaryUrl(
        string $key,
        int $ttlMinutes = self::DEFAULT_URL_TTL_MINUTES,
    ): string {
        /** @phpstan-ignore-next-line temporaryUrl lives on FilesystemAdapter */
        return (string) $this->disk->temporaryUrl($key, now()->addMinutes($ttlMinutes));
    }

    /**
     * Stream the object through the app with content headers. Use when the
     * route is behind session auth / Gate checks and we want the download
     * to pass through our own authorization middleware (HRM preview pattern).
     *
     * @param  string  $disposition  'inline' renders in browser, 'attachment' forces save
     */
    public function stream(
        string $key,
        string $displayName,
        string $disposition = 'inline',
    ): Response {
        $contents = $this->disk->get($key);
        /** @phpstan-ignore-next-line mimeType lives on FilesystemAdapter */
        $mime = $this->disk->mimeType($key) ?: 'application/octet-stream';
        $safeName = addcslashes($displayName, '"\\');

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$safeName.'"',
        ]);
    }

    /** Escape hatch for edge cases — prefer the higher-level methods above. */
    public function disk(): Filesystem
    {
        return $this->disk;
    }
}
