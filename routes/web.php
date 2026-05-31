<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Land at the SPA.
    return redirect('/admin-v2/');
});

Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');
