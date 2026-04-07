<?php

// Treatments API, Permissions, Roles, Users, User Types, Settings, Operator Settings, Payment Modes, Towns, Regions, Cities, Lead Sources, Locations, Lead Statuses, Appointment Statuses, Machine Types

use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServicesController;
use Illuminate\Support\Facades\Route;

    // Treatments API Routes (Unified → TreatmentController)
    Route::prefix('treatments')->name('treatments.')->group(function () {
        Route::post('datatable', [\App\Http\Controllers\Api\TreatmentController::class, 'datatable'])->name('datatable');
        Route::post('store', [\App\Http\Controllers\Api\TreatmentController::class, 'store'])->name('store');
        Route::post('drag-drop-reschedule', [\App\Http\Controllers\Api\TreatmentController::class, 'dragDropReschedule'])->name('drag_drop_reschedule');
        Route::get('check-patient-last-treatment', [\App\Http\Controllers\Api\TreatmentController::class, 'checkPatientLastTreatment'])->name('check_patient_last_treatment');
        Route::post('clear-cache', [\App\Http\Controllers\Api\TreatmentController::class, 'clearCache'])->name('clear_cache');
        Route::get('{id}/edit', [\App\Http\Controllers\Api\TreatmentController::class, 'edit'])->name('edit');
    });

    // Permissions API Routes (Optimized)
    Route::prefix('permissions')->name('permissions.')->middleware('permission:permissions_manage')->group(function () {
        Route::post('datatable', [PermissionController::class, 'datatable'])->name('datatable');
        Route::get('parent-groups', [PermissionController::class, 'parentGroups'])->name('parent_groups');
        Route::get('create', [PermissionController::class, 'create'])->name('create');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::get('{permission}', [PermissionController::class, 'show'])->name('show');
        Route::get('{permission}/edit', [PermissionController::class, 'edit'])->name('edit');
        Route::put('{permission}', [PermissionController::class, 'update'])->name('update');
        Route::delete('{permission}', [PermissionController::class, 'destroy'])->name('destroy');
    });

    // Roles API Routes (Optimized)
    Route::prefix('roles')->name('roles.')->middleware('permission:roles_manage')->group(function () {
        Route::post('datatable', [RoleController::class, 'datatable'])->name('datatable');
        Route::get('create', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
        Route::get('{role}/duplicate', [RoleController::class, 'duplicate'])->name('duplicate');
        Route::post('duplicate', [RoleController::class, 'storeDuplicate'])->name('duplicate.store');
    });

    // Application Users API Routes (Optimized)
    Route::prefix('users')->name('users.')->middleware('permission:users_manage')->group(function () {
        Route::post('datatable', [ApplicationUserController::class, 'datatable'])->name('datatable');
        Route::get('create', [ApplicationUserController::class, 'create'])->name('create');
        Route::post('/', [ApplicationUserController::class, 'store'])->name('store');
        Route::get('{user}/edit', [ApplicationUserController::class, 'edit'])->name('edit');
        Route::put('{user}', [ApplicationUserController::class, 'update'])->name('update');
        Route::delete('{user}', [ApplicationUserController::class, 'destroy'])->name('destroy');
        Route::post('status', [ApplicationUserController::class, 'status'])->name('status');
        Route::get('password/{id}', [ApplicationUserController::class, 'changePassword'])->name('change_password');
        Route::patch('password', [ApplicationUserController::class, 'savePassword'])->name('save_password');
    });

    // User Types API Routes (Optimized)
    Route::prefix('user_types')->name('user_types.')->group(function () {
        Route::post('datatable', [\App\Http\Controllers\Api\UserTypeController::class, 'index'])->name('datatable');
        Route::get('create', [\App\Http\Controllers\Api\UserTypeController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Api\UserTypeController::class, 'store'])->name('store');
        Route::get('{id}', [\App\Http\Controllers\Api\UserTypeController::class, 'show'])->name('show');
        Route::get('{id}/edit', [\App\Http\Controllers\Api\UserTypeController::class, 'edit'])->name('edit');
        Route::put('{id}', [\App\Http\Controllers\Api\UserTypeController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\Api\UserTypeController::class, 'destroy'])->name('destroy');
        Route::patch('active/{id}', [\App\Http\Controllers\Api\UserTypeController::class, 'activate'])->name('active');
        Route::patch('inactive/{id}', [\App\Http\Controllers\Api\UserTypeController::class, 'inactivate'])->name('inactive');
        Route::get('dropdown/list', [\App\Http\Controllers\Api\UserTypeController::class, 'dropdown'])->name('dropdown');
        Route::get('doctor/list', [\App\Http\Controllers\Api\UserTypeController::class, 'forDoctor'])->name('for_doctor');
    });

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
    Route::post('services_save', [ServicesController::class, 'sortOrderSave'])->name('services.sort_save');
    Route::get('cities_sort', [CitiesController::class, 'sortOrderGet'])->name('cities.sort_get');
    Route::get('services_sort', [ServicesController::class, 'sortOrderGet'])->name('services.get_sort');
    // Cities Routes End

    // Lead Sources Routes Start
    Route::post('lead_sources/datatable', [LeadSourcesController::class, 'datatable'])->name('lead_sources.datatable');
    Route::post('lead_sources', [LeadSourcesController::class, 'store'])->name('lead_sources.store');
    Route::get('lead_sources/{id}/edit', [LeadSourcesController::class, 'edit'])->name('lead_sources.edit');
    Route::put('lead_sources/{id}', [LeadSourcesController::class, 'update'])->name('lead_sources.update');
    Route::delete('lead_sources/{id}', [LeadSourcesController::class, 'destroy'])->name('lead_sources.destroy');
    Route::post('lead_sources/status', [LeadSourcesController::class, 'status'])->name('lead_sources.status');
    Route::post('lead_sources_sort_save', [LeadSourcesController::class, 'sortOrderSave'])->name('lead_sources.sort_save');
    Route::get('lead_sources_sort', [LeadSourcesController::class, 'sortOrderGet'])->name('lead_sources.sort_get');
    // Lead Sources Routes End

    // Locations
    Route::post('locations/verify', [LocationsController::class, 'verify'])->name('locations.verify');
    Route::put('locations/verify_edit', [LocationsController::class, 'verify_edit'])->name('locations.verify_edit');
    Route::post('locations/datatable', [LocationsController::class, 'datatable'])->name('locations.datatable');
    Route::patch('locations/active/{id}', [LocationsController::class, 'active'])->name('locations.active');
    Route::post('locations/status', [LocationsController::class, 'status'])->name('locations.status');
    Route::get('locations/sort', [LocationsController::class, 'sortorder'])->name('locations.sort');
    Route::put('locations/edit_update/{id}', [LocationsController::class, 'update'])->name('locations.updatelocation');
    Route::post('lcation_sort_save', [LocationsController::class, 'sortorder_save'])->name('locations.sort_save');
    Route::resource('locations', LocationsController::class)->except('index');

    // Lead Statuses Routes Start
    Route::post('lead_statuses/datatable', [LeadStatusesController::class, 'datatable'])->name('lead_statuses.datatable');
    Route::post('lead_statuses', [LeadStatusesController::class, 'store'])->name('lead_statuses.store');
    Route::get('lead_statuses/{id}/edit', [LeadStatusesController::class, 'edit'])->name('lead_statuses.edit');
    Route::put('lead_statuses/{id}', [LeadStatusesController::class, 'update'])->name('lead_statuses.update');
    Route::delete('lead_statuses/{id}', [LeadStatusesController::class, 'destroy'])->name('lead_statuses.destroy');
    Route::post('lead_statuses/status', [LeadStatusesController::class, 'status'])->name('lead_statuses.status');
    Route::post('lead_statuses_sort_save', [LeadStatusesController::class, 'sortOrderSave'])->name('lead_statuses.sort_save');
    Route::get('lead_statuses_sort', [LeadStatusesController::class, 'sortOrderGet'])->name('lead_statuses.sort_get');
    // Lead Statuses Routes End

    // Appointment Statuses Routes Start
    Route::post('appointment_statuses/datatable', [AppointmentStatusesController::class, 'datatable'])->name('appointment_statuses.datatable');
    Route::post('appointment_statuses', [AppointmentStatusesController::class, 'store'])->name('appointment_statuses.store');
    Route::get('appointment_statuses/{id}/edit', [AppointmentStatusesController::class, 'edit'])->name('appointment_statuses.edit');
    Route::put('appointment_statuses/{id}', [AppointmentStatusesController::class, 'update'])->name('appointment_statuses.update');
    Route::delete('appointment_statuses/{id}', [AppointmentStatusesController::class, 'destroy'])->name('appointment_statuses.destroy');
    Route::post('appointment_statuses/status', [AppointmentStatusesController::class, 'status'])->name('appointment_statuses.status');
    // Appointment Statuses Routes End

    // Machine Types Routes Start
    Route::post('machine_types/datatable', [MachineTypeController::class, 'datatable'])->name('machine_types.datatable');
    Route::post('machine_types', [MachineTypeController::class, 'store'])->name('machine_types.store');
    Route::get('machine_types/{id}/edit', [MachineTypeController::class, 'edit'])->name('machine_types.edit');
    Route::put('machine_types/{id}', [MachineTypeController::class, 'update'])->name('machine_types.update');
    Route::delete('machine_types/{id}', [MachineTypeController::class, 'destroy'])->name('machine_types.destroy');
    Route::post('machine_types/status', [MachineTypeController::class, 'status'])->name('machine_types.status');
    // Machine Types Routes End
