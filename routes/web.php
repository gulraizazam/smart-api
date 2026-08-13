<?php

use App\Http\Controllers\LocalSignedFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Land at the SPA (standalone host — config app.spa_url, e.g. crm3.cutera.pk).
    return redirect(config('app.spa_url'));
});

// Signed local-file serving (R2 local fallback). Extensionless path so the
// host's image handler can't strip the ?signature — see the controller.
Route::get('files/serve', LocalSignedFileController::class)->name('local-files.serve');

Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');