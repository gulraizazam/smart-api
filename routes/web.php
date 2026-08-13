<?php

use App\Http\Controllers\LocalSignedFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Land at the SPA (standalone host — config app.spa_url, e.g. crm3.cutera.pk).
    return redirect(config('app.spa_url'));
});

Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');

/*
 * Signed-URL passthrough for the local `r2` / `r2_invoices` disks.
 * The controller validates the signature (sole authorization) and streams
 * the file back inline. See LocalSignedFileController for the extensionless
 * path rationale (Hostinger/LiteSpeed drops the ?signature on .jpg/.pdf
 * URLs, so the filename rides in `?p=`).
 */
Route::get('/files/serve', LocalSignedFileController::class)->name('files.serve');
