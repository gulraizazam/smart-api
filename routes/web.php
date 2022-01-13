<?php

use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\UserTypesController;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\ServicesController;

    /*
    |--------------------------------------------------------------------------
    | Web Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register web routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | contains the "web" middleware group. Now create something great!
    |
    */

    Route::get('/', function () {
        return redirect()->route('login');
    });

    Auth::routes();
// Authentication Routes...
    Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('auth.admin.login');

// Check Session
    Route::get('check-session', [App\Http\Controllers\Auth\LoginController::class, 'checkSession'])->name('check_session');

// Password Reset Routes...
    Route::get('password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('auth.password.reset');
    Route::post('password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('auth.password.resetemail');
    Route::get('password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('auth.password.resettoken');



    /*After authentication*/
    Route::group(['middleware' => ['auth.common','checkAccount'], 'prefix' => 'admin', 'as' => 'admin.'], function () {

        Route::get('error-logs', [LogViewerController::class, 'index']);

        Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

        Route::get('change_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'showChangePasswordForm'])->name('change_password');
        Route::post('update_password',  [App\Http\Controllers\Auth\ChangePasswordController::class, 'changePassword'])->name('update_password');

        Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


        Route::get('settings', [SettingsController::class,'index'])->name('settings.index');

        Route::post('permissions/datatable', [PermissionsController::class, 'datatable'])->name('permissions.datatable');

        Route::resource('permissions', PermissionsController::class);

        Route::post('roles/datatable', [RolesController::class, 'datatable'])->name('roles.datatable');
        Route::resource('roles', RolesController::class);
       // Route::post('roles_mass_destroy', ['uses' => 'Admin\RolesController@massDestroy', 'as' => 'roles.mass_destroy']);

        Route::post('users/datatable', [UsersController::class, 'datatable'])->name('users.datatable');

        Route::get('users/password/{id}', [UsersController::class, 'changePassword'])->name('users.change_password');
        Route::patch('users/password', [UsersController::class, 'savePassword'])->name('users.save_password');

        Route::post('users/status', [UsersController::class, 'status'])->name('users.status');


        Route::resource('users', UsersController::class);

        Route::post('user_types/datatable', [UserTypesController::class, 'datatable'])->name('user_types.datatable');
        Route::patch('user_types/active/{id}', [UserTypesController::class, 'active']) ->name('user_types.active');
        Route::patch('user_types/inactive/{id}', [UserTypesController::class, 'inactive'])->name('user_types.inactive');
        Route::resource('user_types', UserTypesController::class);
        // User Operator Settings
        Route::get('user_operator_settings', [UserOperatorSettingsController::class,'index'])->name('user_operator_settings.index');



         // Towns Routes

        Route::get('towns/import', [TownController::class, 'importTowns'])->name('towns.import');

        Route::get('towns/upload', [TownController::class, 'uploadLeads'])->name('towns.upload');

        Route::resource('towns', TownController::class)->only('index');

        // Locations
        Route::get('locations/sort_get', [LocationsController::class, 'getSortOrder'])->name('locations.sort_get');
        Route::resource('locations', LocationsController::class)->only('index');


        // Payment Modes
        Route::get('payment_modes',[PaymentModesController::class,'index'])->name('payment_modes.index');

        // Regions
        Route::get('regions',[RegionsController::class,'index'])->name('regions.index');
        Route::get('regions/sort',[RegionsController::class,'sortOrder'])->name('regions.sort');

        // Cities
        Route::get('cities',[CitiesController::class,'index'])->name('cities.index');
        Route::get('cities/sort',[CitiesController::class,'sortOrder'])->name('cities.sort');

        // Lead Sources
        Route::get('lead_sources',[LeadSourcesController::class,'index'])->name('lead_sources.index');
        Route::get('lead_sources/sort',[LeadSourcesController::class,'sortOrder'])->name('lead_sources.sort');

        // Lead Statuses
        Route::get('lead_statuses',[LeadStatusesController::class,'index'])->name('lead_statuses.index');
        Route::get('lead_statuses/sort',[LeadStatusesController::class,'sortOrder'])->name('lead_statuses.sort');

        // Services
        Route::resource('services', ServicesController::class)->only('index');

        // Appointment Statuses
        Route::get('appointment_statuses',[AppointmentStatusesController::class,'index'])->name('appointment_statuses.index');

        // Machine Types
        Route::get('machine_types',[MachineTypeController::class,'index'])->name('machine_types.index');

    });
