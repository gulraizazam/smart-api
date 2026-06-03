<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Land at the SPA (standalone host — config app.spa_url, e.g. crm3.cutera.pk).
    return redirect(config('app.spa_url'));
});

Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');
