<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves attachment files from the LOCAL filesystem fallback (R2_DRIVER=local /
 * R2_INVOICES_DRIVER=local) behind a short-lived signed URL.
 *
 * Why a PHP-streamed, EXTENSIONLESS endpoint (`/files/serve?disk=…&p=…`):
 * this host (Hostinger/LiteSpeed) intercepts requests whose path ends in an
 * image extension (.jpg/.png) — it strips the query string and/or routes them
 * away from a freshly-stored file, so both Laravel's built-in signed `serve`
 * route AND plain static `/storage/…jpg` URLs 404 for new images. A path that
 * carries no extension (the filename rides in the `p` query param) is served
 * normally with its `?signature` intact. Cloud (s3/R2) mode never reaches here
 * — the disk mints its own presigned URLs.
 */
class LocalSignedFileController extends Controller
{
    /** Only the two local-fallback disks may be served — never `local`/`public`. */
    private const ALLOWED_DISKS = ['r2', 'r2_invoices'];

    public function __invoke(Request $request): StreamedResponse
    {
        // The signature is the only authorization — validate it relatively so a
        // TLS-terminating proxy rewriting scheme/host can't break it.
        abort_unless($request->hasValidRelativeSignature(), 404);

        $disk = (string) $request->query('disk', '');
        $path = (string) $request->query('p', '');

        abort_unless(in_array($disk, self::ALLOWED_DISKS, true), 404);
        // Defence in depth — the signature already pins `p`, but never let a
        // traversal sequence through to the adapter.
        abort_if($path === '' || str_contains($path, '..'), 404);

        $fs = Storage::disk($disk);
        abort_unless($fs->exists($path), 404);

        // Inline so the SPA can render images/PDFs in the gallery; no-store so a
        // shared cache never holds a file fetched under a soon-to-expire URL.
        return $fs->response($path, null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}