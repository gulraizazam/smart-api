<?php

use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

Route::middleware('auth.common')->name('admin.')->group(function () {

    // Setting Routes
    Route::get('settings/{id}/edit', [SettingsController::class,'edit'])->name('settings.edit');
    Route::put('settings/{id}', [SettingsController::class,'update'])->name('settings.update');
    Route::post('settings/datatable', [SettingsController::class, 'datatable'])->name('settings.datatable');


});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
