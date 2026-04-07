<?php

// Error logs, Invoices calculation, Auth pages, Home, Settings, Permissions, Roles, Users, User Types, Towns, Locations, Payment Modes, Regions, Cities, Lead Sources, Lead Statuses

use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\InvoiceGenerationController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Admin\UserTypesController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;


        Route::get('error-logs', [LogViewerController::class, 'index']);
        Route::get('updateleads', [LeadsController::class, 'leadupdate']);
        Route::get('updatestatusleads', [LeadsController::class, 'leadstatusupdate']);
        Route::prefix('invoices')->name('invoices.')->group(function () {
            // Calculate amounts and get JSON response
            Route::post('/calculate-amounts', [InvoiceGenerationController::class, 'calculateAmounts'])->name('calculate-amounts');
            
            // Export exempt invoices to Excel
            Route::post('/export-exempt', [InvoiceGenerationController::class, 'exportExemptInvoices'])->name('export-exempt');
            
            // Download all invoices as ZIP of PDFs
            Route::post('/download-invoices-zip', [InvoiceGenerationController::class, 'downloadInvoicesZip'])->name('download-invoices-zip');
        });
        Route::get('change_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'showChangePasswordForm'])->name('change_password');
        Route::post('update_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'changePassword'])->name('update_password');
        Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
        Route::get('/doctor-dashboard', [App\Http\Controllers\DoctorDashboardController::class, 'index'])->name('doctor_dashboard');

        // Home dashboard chart endpoints (my-* variants for personal data)
        Route::controller(App\Http\Controllers\HomeController::class)->prefix('home')->name('home.')->group(function () {
            Route::get('my-collection-by-centre', 'myCollectionByCentre')->name('myCollectionByCentre');
            Route::get('my-revenue-by-centre', 'myRevenueByCentre')->name('myRevenueByCentre');
            Route::get('my-revenue-by-service', 'myRevenueByService')->name('myRevenueByService');
        });

        //  ----------------- Dashboard and  Home Routes ----------------- //

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

        // Permissions - using API controller for all routes
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('permission:permissions_manage');

        // Roles - using API controller for all routes
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:roles_manage');
        Route::get('roles/create', [RoleController::class, 'createView'])->name('roles.create.view')->middleware('permission:roles_manage');
        Route::get('roles/{role}/edit', [RoleController::class, 'editView'])->name('roles.edit.view')->middleware('permission:roles_manage');
        // Route::post('roles_mass_destroy', ['uses' => 'Admin\RolesController@massDestroy', 'as' => 'roles.mass_destroy']);

        // Application Users - using API controller for all routes
        Route::get('users', [ApplicationUserController::class, 'index'])->name('users.index')->middleware('permission:users_manage');

        // User Types - using API controller for all routes except index view
        Route::get('user_types', [UserTypesController::class, 'index'])->name('user_types.index')->middleware('permission:user_types_manage');
        // User Operator Settings
        Route::get('user_operator_settings', [UserOperatorSettingsController::class, 'index'])->name('user_operator_settings.index');

        // Towns Routes

        Route::get('towns/import', [TownController::class, 'importTowns'])->name('towns.import');

        Route::get('towns/upload', [TownController::class, 'uploadLeads'])->name('towns.upload');

        Route::resource('towns', TownController::class)->only('index');

        // Locations
        Route::get('locations/sort_get', [LocationsController::class, 'getSortOrder'])->name('locations.sort_get');
        Route::get('services/sort_get', [ServicesController::class, 'getSortOrder'])->name('services.sort_get');
        Route::resource('locations', LocationsController::class)->only('index');
        Route::get('locations/getservices', [LocationsController::class, 'getServices'])->name('locations.getservices');
        // Payment Modes
        Route::get('payment_modes', [PaymentModesController::class, 'index'])->name('payment_modes.index');
        Route::get('payment_modes/sort', [PaymentModesController::class, 'sortOrder'])->name('payment_modes.sort');

        // Regions
        Route::get('regions', [RegionsController::class, 'index'])->name('regions.index');
        Route::get('regions/sort', [RegionsController::class, 'sortOrder'])->name('regions.sort');

        // Cities
        Route::get('cities', [CitiesController::class, 'index'])->name('cities.index');
        Route::get('cities/sort', [CitiesController::class, 'sortOrder'])->name('cities.sort');

        // Lead Sources
        Route::get('lead_sources', [LeadSourcesController::class, 'index'])->name('lead_sources.index');
        Route::get('lead_sources/sort', [LeadSourcesController::class, 'sortOrder'])->name('lead_sources.sort');

        // Lead Statuses
