<?php

// Services, Logs, SMS Templates, Resources, Doctors, Refunds, Discounts, Vouchers, Bundles, Centre Targets, Package Advances, Business Closures, Schedule Calendar

use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\PackagesController as AdminPackagesController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\UserVouchersController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\Api\BundlesController;
use App\Http\Controllers\Api\CentreTargetsController as ApiCentreTargetsController;
use App\Http\Controllers\Api\ResourcesController as ApiResourcesController;
use App\Http\Controllers\Api\SMSTemplatesController as ApiSMSTemplatesController;
use App\Http\Controllers\Api\BusinessClosureController;
use App\Http\Controllers\Api\BusinessWorkingDaysController;
use App\Http\Controllers\Api\DiscountsController as ApiDiscountsController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\RefundsController as ApiRefundsController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ServiceBundlesController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

// Services (Optimized - API Controller + Service Layer + API Resources)
Route::prefix('services')->name('services.')->group(function () {
    Route::post('datatable', [ServicesController::class, 'datatable'])->name('datatable');
    Route::post('status', [ServicesController::class, 'status'])->name('status');
    Route::get('create', [ServicesController::class, 'create'])->name('create');
    Route::post('/', [ServicesController::class, 'store'])->name('store');
    Route::post('duplicate', [ServicesController::class, 'storeDuplicate'])->name('duplicate.store');
    Route::get('sort/get', [ServicesController::class, 'sortOrderGet'])->name('get_sort');
    Route::post('sort/save', [ServicesController::class, 'sortOrderSave'])->name('sort_save');
    Route::get('color', [ServicesController::class, 'getColor'])->name('get_color');

    Route::get('{id}/edit', [ServicesController::class, 'edit'])->name('edit')->whereNumber('id');
    Route::get('{id}/bundle-impact', [ServicesController::class, 'bundleImpact'])->name('bundle_impact')->whereNumber('id');
    Route::get('{id}/duplicate', [ServicesController::class, 'duplicate'])->name('duplicate')->whereNumber('id');
    Route::get('{id}', [ServicesController::class, 'show'])->name('show')->whereNumber('id');
    Route::put('{id}', [ServicesController::class, 'update'])->name('update')->whereNumber('id');
    Route::delete('{id}', [ServicesController::class, 'destroy'])->name('destroy')->whereNumber('id');
});

// Logs Routes Start
Route::post('logs/datatable', [LogsController::class, 'datatable'])->name('logs.datatable');
// Logs Routes End

// Sms Templates Start
Route::post('sms_templates/datatable', [SMSTemplatesController::class, 'datatable'])->name('sms_templates.datatable');
Route::get('sms_templates/{id}/edit', [SMSTemplatesController::class, 'edit'])->name('sms_templates.edit');
Route::put('sms_templates/{id}', [SMSTemplatesController::class, 'update'])->name('sms_templates.update');
Route::post('sms_templates/status', [SMSTemplatesController::class, 'status'])->name('sms_templates.status');

// SMS Templates — REST API additions (non-conflicting methods/URIs only;
// legacy endpoints above keep the admin UI working unchanged)
Route::prefix('sms_templates')->name('sms_templates.api.')->group(function () {
    Route::get('/', [ApiSMSTemplatesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiSMSTemplatesController::class, 'dropdown'])->name('dropdown');
    Route::post('create', [ApiSMSTemplatesController::class, 'store'])->name('create');
    Route::get('{smsTemplate}', [ApiSMSTemplatesController::class, 'show'])->name('show')->whereNumber('smsTemplate');
    Route::get('{smsTemplate}/variables', [ApiSMSTemplatesController::class, 'variables'])->name('variables')->whereNumber('smsTemplate');
    Route::patch('{smsTemplate}', [ApiSMSTemplatesController::class, 'update'])->name('update')->whereNumber('smsTemplate');
    Route::patch('{smsTemplate}/status', [ApiSMSTemplatesController::class, 'status'])->name('status')->whereNumber('smsTemplate');
    Route::delete('{smsTemplate}', [ApiSMSTemplatesController::class, 'destroy'])->name('destroy')->whereNumber('smsTemplate');
});
// Sms Templates End

// Resource Route start

// Resources — REST API additions (registered first so literal paths win
// over the legacy `Route::resource` wildcard `{resource}`; legacy PUT/
// DELETE continue to serve the admin UI untouched)
Route::prefix('resources')->name('resources.api.')->group(function () {
    Route::get('/', [ApiResourcesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiResourcesController::class, 'dropdown'])->name('dropdown');
    Route::get('form-data', [ApiResourcesController::class, 'formData'])->name('form_data');
    Route::get('machine-types', [ApiResourcesController::class, 'machineTypesByLocation'])->name('machine_types');
    Route::post('create', [ApiResourcesController::class, 'store'])->name('create');
    Route::get('{resource}', [ApiResourcesController::class, 'show'])->name('show')->whereNumber('resource');
    Route::patch('{resource}', [ApiResourcesController::class, 'update'])->name('update')->whereNumber('resource');
    Route::patch('{resource}/status', [ApiResourcesController::class, 'status'])->name('status')->whereNumber('resource');
});

Route::post('resources/datatable', [ResourcesController::class, 'datatable'])->name('resources.datatable');
Route::post('resources/status', [ResourcesController::class, 'status'])->name('resources.status');
Route::get('resources/get_machinetype', [ResourcesController::class, 'get_machinetype'])->name('resources.get_machinetype');

// Legacy admin resource — `show` and `update` are excluded because the
// REST API above already owns `GET /resources/{id}` and
// `PATCH /resources/{id}`. Admin UI posts updates as `PUT` (Blade
// `@method('put')` form-spoof), so we re-register PUT-only for legacy.
Route::resource('resources', ResourcesController::class)->except(['index', 'show', 'update']);
Route::put('resources/{id}', [ResourcesController::class, 'update'])->name('resources.update')->whereNumber('id');

// Doctors API Routes (Optimized)
Route::prefix('doctors')->name('doctors.')->middleware('permission:doctors_manage')->group(function () {
    Route::post('datatable', [DoctorController::class, 'datatable'])->name('datatable');
    Route::get('create', [DoctorController::class, 'create'])->name('create');
    Route::post('/', [DoctorController::class, 'store'])->name('store');
    Route::get('{doctor}/edit', [DoctorController::class, 'edit'])->name('edit');
    Route::put('{doctor}', [DoctorController::class, 'update'])->name('update');
    Route::delete('{doctor}', [DoctorController::class, 'destroy'])->name('destroy');
    Route::post('status', [DoctorController::class, 'status'])->name('status');
    Route::get('password/{id}', [DoctorController::class, 'changePassword'])->name('change_password');
    Route::patch('password', [DoctorController::class, 'savePassword'])->name('save_password');
    Route::get('locations/{id}', [DoctorController::class, 'displayLocation'])->name('location_manage');
    Route::get('get-service', [DoctorController::class, 'getServices'])->name('get_service');
    Route::post('save_service', [DoctorController::class, 'saveServices'])->name('save_service');
    Route::post('delete_service', [DoctorController::class, 'deleteServices'])->name('delete_service');
});
// Doctors Route End

// Refunds Routes (Refactored — using API controller)
Route::post('refunds/datatable', [ApiRefundsController::class, 'datatable'])->name('refunds.datatable');
Route::get('refunds/refund_create/{id}', [ApiRefundsController::class, 'calculate'])->name('refunds.refund_create');
Route::get('refunds/detail/{id}', [ApiRefundsController::class, 'detail'])->name('refunds.detail');
Route::get('refunds/plans-for-patient/{patientId}', [ApiRefundsController::class, 'plansForPatient'])->name('refunds.plans_for_patient');
Route::get('refunds/history/{packageId}', [ApiRefundsController::class, 'history'])->name('refunds.history');
Route::get('refunds/edit/{id}', [AdminPackagesController::class, 'editRefund'])->name('refunds.edit_api');
Route::post('refunds/update', [AdminPackagesController::class, 'updateRefund'])->name('refunds.update_api');
Route::post('refunds', [ApiRefundsController::class, 'store'])
    ->middleware('idempotent')
    ->name('refunds.store');

Route::resource('feedbacks', FeedbackController::class)->only(['store', 'edit', 'update', 'destroy']);
// Discount Routes (Refactored — using API controller)
Route::post('discounts/datatable', [ApiDiscountsController::class, 'datatable'])->name('discounts.datatable');
Route::post('discounts/status', [ApiDiscountsController::class, 'status'])->name('discounts.status');
Route::get('discounts/locations/{id}', [ApiDiscountsController::class, 'displayLocation'])->name('discounts.location_manage');
Route::get('discounts/getDservice', [ApiDiscountsController::class, 'getServices'])->name('discounts.get_Dservice');
Route::get('getDiscountServices', [ApiDiscountsController::class, 'getDiscountServices'])->name('discounts.getDiscountServices');
Route::get('discounts/services-for-configurable', [ApiDiscountsController::class, 'getServicesForConfigurable'])->name('discounts.servicesForConfigurable');
Route::post('discounts/allocate-configurable', [ApiDiscountsController::class, 'allocateConfigurable'])->name('discounts.allocateConfigurable');
Route::post('discounts/saveDervice', [ApiDiscountsController::class, 'saveAllocations'])->name('discounts.save_Dervice');
Route::post('discounts/deleteDservice', [ApiDiscountsController::class, 'deleteAllocation'])->name('discounts.delete_service');
Route::post('discounts/deleteDserviceGroup', [ApiDiscountsController::class, 'deleteAllocationGroup'])->name('discounts.delete_service_group');

Route::get('discounts/create', [ApiDiscountsController::class, 'create'])->name('discounts.create');
Route::post('discounts', [ApiDiscountsController::class, 'store'])->name('discounts.store');
Route::get('discounts/{id}/edit', [ApiDiscountsController::class, 'edit'])->name('discounts.edit');
Route::put('discounts/{id}', [ApiDiscountsController::class, 'update'])->name('discounts.update');
Route::delete('discounts/{id}', [ApiDiscountsController::class, 'destroy'])->name('discounts.destroy');

// //Vouchers

Route::post('voucherTypes/datatable', [VouchersController::class, 'datatable'])->name('voucherTypes.datatable');
Route::post('vouchers/datatable', [UserVouchersController::class, 'datatable'])->name('vouchers.datatable');
Route::post('vouchers', [UserVouchersController::class, 'store'])->name('vouchers.store');
Route::get('vouchers/{id}/edit', [UserVouchersController::class, 'edit'])->name('vouchers.edit');
Route::put('vouchers/{id}', [UserVouchersController::class, 'update'])->name('vouchers.update');
Route::delete('vouchers/{id}', [UserVouchersController::class, 'destroy'])->name('vouchers.destroy');
Route::post('voucherTypes/status', [VouchersController::class, 'status'])->name('voucherTypes.status');
Route::get('voucherTypes/locations/{id}', [VouchersController::class, 'displayDlocation'])->name('voucherTypes.location_manage');
Route::get('vouchersTypes/getListing', [VouchersController::class, 'getListing'])->name('vouchersTypes.getListing');
Route::get('getDservice', [VouchersController::class, 'getDservices'])->name('voucherTypes.get_Dservice');
Route::get('getDiscountServices', [VouchersController::class, 'getDiscountServices'])->name('voucherTypes.getDiscountServices');
Route::post('saveDervice', [VouchersController::class, 'saveDservices'])->name('voucherTypes.save_Dervice');
Route::post('deleteDservice', [VouchersController::class, 'deleteDservice'])->name('voucherTypes.delete_service');
Route::post('voucherTypes/assignToPatient', [VouchersController::class, 'assignToPatient'])->name('voucherTypes.assignToPatient');

Route::resource('voucherTypes', VouchersController::class)->except('index');

// Bundles Route start (API Controller)
Route::prefix('bundles')->name('bundles.')->group(function () {
    Route::post('datatable', [BundlesController::class, 'datatable'])->name('datatable');
    Route::post('status', [BundlesController::class, 'status'])->name('status');
    Route::get('sort/get', [BundlesController::class, 'sortOrderGet'])->name('get_sort');
    Route::post('sort/save', [BundlesController::class, 'sortOrderSave'])->name('sort_save');
    Route::get('detail/{id}', [BundlesController::class, 'detail'])->name('detail')->whereNumber('id');
    Route::get('{id}/edit', [BundlesController::class, 'edit'])->name('edit')->whereNumber('id');
    Route::post('/', [BundlesController::class, 'store'])->name('store');
    Route::put('{id}', [BundlesController::class, 'update'])->name('update')->whereNumber('id');
    Route::delete('{id}', [BundlesController::class, 'destroy'])->name('destroy')->whereNumber('id');
});
// Bundles Route End

// Service Bundles (same service × N sessions)
Route::prefix('service-bundles')->name('service-bundles.')->group(function () {
    Route::post('datatable', [ServiceBundlesController::class, 'datatable'])->name('datatable');
    Route::post('status', [ServiceBundlesController::class, 'status'])->name('status');
    Route::get('sort/get', [ServiceBundlesController::class, 'sortOrderGet'])->name('get_sort');
    Route::post('sort/save', [ServiceBundlesController::class, 'sortOrderSave'])->name('sort_save');
    Route::post('bulk', [ServiceBundlesController::class, 'bulkStore'])->name('bulk_store');
    Route::get('detail/{id}', [ServiceBundlesController::class, 'detail'])->name('detail')->whereNumber('id');
    Route::post('/', [ServiceBundlesController::class, 'store'])->name('store');
    Route::get('{id}/edit', [ServiceBundlesController::class, 'edit'])->name('edit')->whereNumber('id');
    Route::put('{id}', [ServiceBundlesController::class, 'update'])->name('update')->whereNumber('id');
    Route::delete('{id}', [ServiceBundlesController::class, 'destroy'])->name('destroy')->whereNumber('id');
});
// Service Bundles Route End

// Centre Target
// Centre Targets — REST API additions (registered first so literal paths
// win over the legacy `Route::resource` wildcard `{centre_target}`)
Route::prefix('centre_targets')->name('centre_targets.api.')->group(function () {
    Route::get('/', [ApiCentreTargetsController::class, 'index'])->name('index');
    Route::get('form-data', [ApiCentreTargetsController::class, 'formData'])->name('form_data');
    Route::post('centres', [ApiCentreTargetsController::class, 'loadCentres'])->name('centres');
    Route::post('create', [ApiCentreTargetsController::class, 'store'])->name('create');
    Route::get('{centreTarget}', [ApiCentreTargetsController::class, 'show'])->name('show')->whereNumber('centreTarget');
    Route::patch('{centreTarget}', [ApiCentreTargetsController::class, 'update'])->name('update')->whereNumber('centreTarget');
    Route::delete('{centreTarget}', [ApiCentreTargetsController::class, 'destroy'])->name('destroy')->whereNumber('centreTarget');
});

Route::post('centre_targets/load-centres', [CentreTargetsController::class, 'leadtargetcentre'])->name('centre_targets.load_target_centre');
Route::get('centre_targets/diplay/{id}', [CentreTargetsController::class, 'display'])->name('centre_targets.display');
Route::post('centre_targets/datatable', [CentreTargetsController::class, 'datatable'])->name('centre_targets.datatable');

// Legacy admin resource — `show` and `update` are excluded because the
// REST API above already owns `GET /centre_targets/{id}` (legacy had no
// `show` method, so this also fixes that latent bug) and
// `PATCH /centre_targets/{id}`. Admin UI posts updates as `PUT` via
// `@method('put')` form-spoof, so we re-register PUT-only for legacy.
// DELETE also left to API (legacy deleteRecord ends with a flash+redirect
// on failure which doesn't fit a JSON response).
Route::resource('centre_targets', CentreTargetsController::class)->except(['index', 'show', 'update', 'destroy']);
Route::put('centre_targets/{id}', [CentreTargetsController::class, 'update'])->name('centre_targets.update')->whereNumber('id');

// Package Advance route start
Route::post('packagesadvances/datatable', [PackageAdvancesController::class, 'datatable'])->name('packagesadvances.datatable');
Route::post('packagesadvances/status', [PackageAdvancesController::class, 'status'])->name('packagesadvances.status');
Route::post('packagesadvances/cancel/{id}', [PackageAdvancesController::class, 'cancel'])
    ->middleware(['throttle:60,1', 'idempotent'])
    ->name('packagesadvances.cancel');
Route::get('packagesadvances/getpackages', [PackageAdvancesController::class, 'getpackages'])->name('packagesadvances.getpackages');
Route::get('packagesadvances/getpackagesinfo', [PackageAdvancesController::class, 'getpackagesinfo'])->name('packagesadvances.getpackagesinfo');
Route::get('packagesadvances/getpackagesinfo_update', [PackageAdvancesController::class, 'getpackagesinfo_update'])->name('packagesadvances.getpackagesinfo_update');
// CSRF-bypass cleanup (2026-05-15): the three GET routes that used to
// register here mutated `package_advances` via the controller's
// `savepackagesadvances` / `updatepackagesadvances` / `update_record_final`
// methods. Laravel's VerifyCsrfToken middleware excludes GET by design —
// any `<img src="...">` on an attacker page would trigger the write with
// the operator's session cookie. The orphaned routes had no SPA caller;
// the live POST path is in routes/api/appointments-admin.php
// (finances.savepackagesadvances). The standalone POST routes on
// Route::resource below cover the remaining REST surface.
Route::resource('packagesadvances', PackageAdvancesController::class)->except('index');

// Business Closures Management
Route::post('business-closures/datatable', [BusinessClosureController::class, 'datatable'])->name('business-closures.datatable');
Route::get('business-closures/create', [BusinessClosureController::class, 'create'])->name('business-closures.create');
Route::post('business-closures', [BusinessClosureController::class, 'store'])->name('business-closures.store');

// REST-style additions (static segments first so they don't get captured as ids)
// Note: `business-closures.index` is reserved for the web admin page route name
// (resources/views/admin/partials/sidebar.blade.php, schedule-calendar.js). This
// API listing keeps a distinct name to avoid Ziggy/route() collision.
Route::get('business-closures', [BusinessClosureController::class, 'index'])->name('business-closures.list');
Route::get('business-closures/upcoming', [BusinessClosureController::class, 'upcoming'])->name('business-closures.upcoming');
Route::post('business-closures/check', [BusinessClosureController::class, 'check'])->name('business-closures.check');
Route::post('business-closures/bulk-delete', [BusinessClosureController::class, 'bulkDelete'])->name('business-closures.bulk-delete');

Route::get('business-closures/{id}/edit', [BusinessClosureController::class, 'edit'])->name('business-closures.edit');
Route::get('business-closures/{businessClosure}', [BusinessClosureController::class, 'show'])->name('business-closures.show');
Route::put('business-closures/{id}', [BusinessClosureController::class, 'update'])->name('business-closures.update');
Route::delete('business-closures/{id}', [BusinessClosureController::class, 'destroy'])->name('business-closures.destroy');

// Business Working Days (REST — gates applied in-controller, legacy
// /schedule/get-business-working-days + /schedule/save-business-working-days
// kept below for backward compatibility)
Route::prefix('business-working-days')->name('business-working-days.')->group(function () {
    Route::get('/', [BusinessWorkingDaysController::class, 'show'])->name('show');
    Route::put('/', [BusinessWorkingDaysController::class, 'update'])->name('update');
    Route::get('check', [BusinessWorkingDaysController::class, 'check'])->name('check');
    Route::get('calendar', [BusinessWorkingDaysController::class, 'calendar'])->name('calendar');

    Route::prefix('exceptions')->name('exceptions.')->group(function () {
        Route::get('/', [BusinessWorkingDaysController::class, 'indexExceptions'])->name('index');
        Route::post('/', [BusinessWorkingDaysController::class, 'storeException'])->name('store');
        Route::post('bulk', [BusinessWorkingDaysController::class, 'bulkReplaceExceptions'])->name('bulk');
        Route::get('{exception}', [BusinessWorkingDaysController::class, 'showException'])->name('show');
        Route::patch('{exception}', [BusinessWorkingDaysController::class, 'updateException'])->name('update');
        Route::delete('{exception}', [BusinessWorkingDaysController::class, 'destroyException'])->name('destroy');
    });
});

// Schedule Calendar API
Route::get('schedule/get-locations', [ScheduleController::class, 'getLocations'])->name('schedule.get-locations');
Route::get('schedule/get-business-working-days', [ScheduleController::class, 'getBusinessWorkingDays'])->name('schedule.get-business-working-days');
Route::post('schedule/save-business-working-days', [ScheduleController::class, 'saveBusinessWorkingDays'])->name('schedule.save-business-working-days');
Route::post('schedule/get-shifts', [ScheduleController::class, 'getShifts'])->name('schedule.get-shifts');
Route::post('schedule/store-shifts', [ScheduleController::class, 'storeShifts'])->name('schedule.store-shifts');
Route::post('schedule/delete-shifts', [ScheduleController::class, 'deleteShifts'])->name('schedule.delete-shifts');
Route::post('schedule/delete-single-shift', [ScheduleController::class, 'deleteSingleShift'])->name('schedule.delete-single-shift');
Route::post('schedule/store-time-off', [ScheduleController::class, 'storeTimeOff'])->name('schedule.store-time-off');
Route::post('schedule/get-time-offs', [ScheduleController::class, 'getTimeOffs'])->name('schedule.get-time-offs');
Route::post('schedule/get-time-off', [ScheduleController::class, 'getTimeOff'])->name('schedule.get-time-off');
Route::post('schedule/update-time-off', [ScheduleController::class, 'updateTimeOff'])->name('schedule.update-time-off');
Route::post('schedule/delete-time-off', [ScheduleController::class, 'deleteTimeOff'])->name('schedule.delete-time-off');
Route::post('schedule/store-repeating-shifts', [ScheduleController::class, 'storeRepeatingShifts'])->name('schedule.store-repeating-shifts');
Route::post('schedule/get-resources', [ScheduleController::class, 'getResources'])->name('schedule.get-resources');
Route::post('schedule/bulk-delete-shifts', [ScheduleController::class, 'bulkDeleteShifts'])->name('schedule.bulk-delete-shifts');
