<?php

use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TownController;

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
    Route::get('settings/{id}/edit', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings/{id}', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/datatable', [SettingsController::class, 'datatable'])->name('settings.datatable');
    // Operator Settings Routes Start
    Route::post('user_operator_settings/datatable', [UserOperatorSettingsController::class, 'datatable'])->name('user_operator_settings.datatable');
    Route::get('user_operator_settings/{id}/edit', [UserOperatorSettingsController::class, 'edit'])->name('user_operator_settings.edit');
    Route::put('user_operator_settings/{id}', [UserOperatorSettingsController::class, 'update'])->name('user_operator_settings.update');
    // Operator Settings Routes End

    // Payment Modes Routes Start
    Route::post('payment_modes/datatable', [PaymentModesController::class, 'datatable'])->name('payment_modes.datatable');
    Route::post('payment_modes', [PaymentModesController::class, 'store'])->name('payment_modes.store');
    Route::get('payment_modes/{id}/edit', [PaymentModesController::class, 'edit'])->name('payment_modes.edit');
    Route::put('payment_modes/{id}', [PaymentModesController::class, 'update'])->name('payment_modes.update');
    Route::delete('payment_modes/{id}', [PaymentModesController::class, 'destroy'])->name('payment_modes.destroy');
    Route::post('payment_modes/status', [PaymentModesController::class, 'status'])->name('payment_modes.status');
    Route::post('payment_modes_sort_save', [PaymentModesController::class, 'sortorder_save'])->name('payment_modes.sort_save');
    Route::get('payment_modes_sort', [PaymentModesController::class, 'sortOrderGet'])->name('payment_modes.sort_get');
    // Payment Modes Routes End


    //Town routes

    Route::post('towns/datatable', [TownController::class, 'datatable'])->name('towns.datatable');

    Route::post('towns/status', [TownController::class, 'status'])->name('towns.status');

    Route::resource('towns', TownController::class)->except('index');

    // Regions Routes Start
    Route::post('regions/datatable', [RegionsController::class, 'datatable'])->name('regions.datatable');
    Route::post('regions', [RegionsController::class, 'store'])->name('regions.store');
    Route::get('regions/{id}/edit', [RegionsController::class, 'edit'])->name('regions.edit');
    Route::put('regions/{id}', [RegionsController::class, 'update'])->name('regions.update');
    Route::delete('regions/{id}', [RegionsController::class, 'destroy'])->name('regions.destroy');
    Route::post('regions/status', [RegionsController::class, 'status'])->name('regions.status');
    Route::post('regions_sort_save', [RegionsController::class, 'sortOrderSave'])->name('regions.sort_save');
    Route::get('regions_sort', [RegionsController::class, 'sortOrderGet'])->name('regions.sort_get');
    // Regions Routes End

    // Cities Routes Start
    Route::post('cities/datatable', [CitiesController::class, 'datatable'])->name('cities.datatable');
    Route::post('cities', [CitiesController::class, 'store'])->name('cities.store');
    Route::get('cities/{id}/edit', [CitiesController::class, 'edit'])->name('cities.edit');
    Route::put('cities/{id}', [CitiesController::class, 'update'])->name('cities.update');
    Route::delete('cities/{id}', [CitiesController::class, 'destroy'])->name('cities.destroy');
    Route::post('cities/status', [CitiesController::class, 'status'])->name('cities.status');
    Route::post('cities_sort_save', [CitiesController::class, 'sortOrderSave'])->name('cities.sort_save');
    Route::get('cities_sort', [CitiesController::class, 'sortOrderGet'])->name('cities.sort_get');
    // Cities Routes End

});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
