<?php

use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\BundlesController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\DoctorsController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\SMSTemplatesController;
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
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\ResourceRotasController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackagesController;

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
        Route::get('payment_modes/sort',[PaymentModesController::class,'sortOrder'])->name('payment_modes.sort');

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


        //Resource Route start
        Route::resource('resources', ResourcesController::class)->only('index');
        //Resource Route end

        // Machine Types
        Route::get('machine_types',[MachineTypeController::class,'index'])->name('machine_types.index');

        // Logs Types
        Route::get('logs',[LogsController::class,'index'])->name('logs.index');

        // Sms Templates
        Route::get('sms_templates',[SMSTemplatesController::class,'index'])->name('sms_templates.index');

        // Doctors Templates
        Route::get('doctors',[DoctorsController::class,'index'])->name('doctors.index');

       // Refund Route
        Route::resource('refunds',RefundsController::class)->only('index');
        //Refunds route end

        //Discount route Start
        Route::resource('discounts', DiscountsController::class)->only('index');
        //Discount route end

        //Packages route Start
        Route::get('bundles',[BundlesController::class,'index'])->name('bundles.index');
        //Packages route end

        //Centre Target
        Route::resource('centre_targets', CentreTargetsController::class)->only('index');


        //Package Advance route start
        Route::resource('packagesadvances', PackageAdvancesController::class)->only('index');

        //Resource Rota Management
        Route::resource('resourcerotas', ResourceRotasController::class)->only('index');
        Route::get('resourcerotas/calender/view/{id}', [ResourceRotasController::class, 'viewCalender'])->name('resourcerotas.calender-view');

        //Invoice Management route start
        Route::get('invoices/log/{id}/{type}', [InvoicesController::class, 'invoicelog'])->name('invoices.invoice_log');
        Route::resource('invoices', InvoicesController::class)->only('index');
        //Invoice Management route end

        // Package route start
        Route::post('packages/datatable', [PackagesController::class, 'datatable'])->name('packages.datatable');

        Route::post('packages/status', [PackagesController::class, 'status'])->name('packages.status');

        Route::get('packages/getdiscountinfo',[PackagesController::class, 'getdiscountinfo'])->name('packages.getdiscountinfo');

        Route::get('packages/getdiscountinfo_custom',[PackagesController::class, 'getdiscountinfocustom'])->name('packages.getdiscountinfo_custom');

        Route::get('packages/savepackagesservice',[PackagesController::class, 'savepackages_service'])->name('packages.savepackages_service');

        Route::post('packages/deletepackagesservice',[PackagesController::class, 'deletepackagesservice'])->name('packages.deletepackages_service');

        Route::get('packages/deletepackagesexclusive',[PackagesController::class, 'deletepackagesexclusive'])->name('packages.deletepackages_exclusive');

        Route::get('packages/getgrandtotal',[PackagesController::class, 'getgrandtotal'])->name('packages.getgrandtotal');

        Route::get('packages/getgrandtotal_update',[PackagesController::class, 'getgrandtotal_update'])->name('packages.getgrandtotal_update');

        Route::get('packages/savepackages',[PackagesController::class, 'savepackages'])->name('packages.savepackages');

        Route::get('packages/updatepackages',[PackagesController::class, 'updatepackages'])->name('packages.updatepackages');

        Route::get('packages/getserviceinfo',[PackagesController::class, 'getserviceinfo'])->name('packages.getserviceinfo');

        Route::get('packages/display/{id}',[PackagesController::class, 'display'])->name('packages.display');

        Route::get('packages/getservice',[PackagesController::class, 'getservices'])->name('packages.getservice');

        Route::get('packages/getservice_for_discount_zero', [PackagesController::class, 'getservices_for_zero'])->name('packages.getserviceinfo_discount_zero');

        Route::get('packages/pdf/{id}', [PackagesController::class, 'package_pdf'])->name('packages.package_pdf');

        Route::get('packages/getpackage', [PackagesController::class, 'getpackage'])->name('packages.getpackage');

        /*Routes for editing the cash in treatment plan*/
        Route::get('packages/edit_cash/{id}/{package_id}',[PackagesController::class, 'editpackageadvancescashindex'])->name('packages.edit_cash');

        Route::post('packages/delete_cash',[PackagesController::class, 'deletepackageadvancescash'])->name('packages.delete_cash');

        Route::put('packages/edit_cash/store',[PackagesController::class, 'storepackageadvancescash'])->name('packages.edit_cash.store');
        /*End*/

        // Route for Sms log start
        Route::get('plans/log/{id}/{type}', [PackagesController::class, 'packagelog'])->name('packages.log');
        Route::get('packages/sms_logs/{id}', [PackagesController::class, 'showSMSLogs'])->name('packages.sms_logs');
        Route::post('packages/send_logged_sms', [PackagesController::class, 'sendLogSMS'])->name('packages.resend_sms');
        // End

        Route::get('packages/getappointmentinfo',[PackagesController::class, 'getappointmentinfo'])->name('packages.getappointmentinfo');
        Route::resource('packages', PackagesController::class);
        // Package Route end

    });
