<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves attachment files from the local `r2` / `r2_invoices` disks behind
 * a short-lived signed URL. All client documents (HR, recruitment CVs,
 * centre logos, cash-flow invoices) live on the app server — nothing goes
 * to a third-party bucket. `AppServiceProvider::configureLocalSignedDisks()`
 * teaches those disks to mint `files.serve` URLs when `->temporaryUrl()`
 * is called, and this controller re-validates the signature on the way in.
 *
 * Why a PHP-streamed, EXTENSIONLESS endpoint (`/files/serve?disk=…&p=…`):
 * some shared hosts (Hostinger/LiteSpeed) intercept requests whose path
 * ends in an image extension (.jpg/.png) — they strip the query string
 * and/or route them away from a freshly-stored file, so both Laravel's
 * built-in signed `serve` route AND plain static `/storage/…jpg` URLs 404
 * for new images. A path that carries no extension (the filename rides in
 * the `p` query param) is served normally with its `?signature` intact.
 */
class LocalSignedFileController extends Controller
{
    /** Only the two document disks may be served — never `local`/`public`. */
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
