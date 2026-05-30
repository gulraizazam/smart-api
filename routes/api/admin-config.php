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
use App\Http\Controllers\Api\AppointmentStatusesController as ApiAppointmentStatusesController;
use App\Http\Controllers\Api\CentresController as ApiCentresController;
use App\Http\Controllers\Api\CitiesController as ApiCitiesController;
use App\Http\Controllers\Api\LeadSourcesController as ApiLeadSourcesController;
use App\Http\Controllers\Api\LeadStatusesController as ApiLeadStatusesController;
use App\Http\Controllers\Api\MachineTypesController as ApiMachineTypesController;
use App\Http\Controllers\Api\OperatorSettingsController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RegionsController as ApiRegionsController;
use App\Http\Controllers\Api\TownsController as ApiTownsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\TreatmentController;
use App\Http\Controllers\Api\UserTypeController;
use Illuminate\Support\Facades\Route;

// Treatments API Routes (Unified → TreatmentController)
Route::prefix('treatments')->name('treatments.')->group(function () {
    Route::post('datatable', [TreatmentController::class, 'datatable'])->name('datatable');
    Route::post('store', [TreatmentController::class, 'store'])->name('store');
    Route::post('drag-drop-reschedule', [TreatmentController::class, 'dragDropReschedule'])->name('drag_drop_reschedule');
    Route::get('check-patient-last-treatment', [TreatmentController::class, 'checkPatientLastTreatment'])->name('check_patient_last_treatment');
    Route::post('clear-cache', [TreatmentController::class, 'clearCache'])->name('clear_cache');
    Route::get('{id}/edit', [TreatmentController::class, 'edit'])->name('edit');
});

// Permissions API Routes (Optimized)
Route::prefix('permissions')->name('permissions.')->middleware('permission:permissions_manage')->group(function () {
    Route::post('datatable', [PermissionController::class, 'datatable'])->name('datatable');
    // Literal paths first — `tree` would otherwise bind to the
    // {permission} wildcard below and 404 with
    // "No query results for model [Permission] tree".
    Route::get('tree', [PermissionController::class, 'tree'])->name('tree');
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

    // Path-bound JSON password update — obsoletes the GET-then-PATCH
    // dance above (the legacy SPA had to GET the Blade modal, scrape
    // an `encrypt(id)` hidden input, and POST it back). With the user
    // id pinned in the path the encrypt-id obfuscation is unnecessary;
    // tenant scoping happens server-side via findByAccountId. The
    // legacy pair stays live until Blade cutover for legacy admin's
    // edit form.
    Route::patch('{id}/password', [ApplicationUserController::class, 'updatePassword'])
        ->name('update_password')
        ->whereNumber('id');
});

// User Types API Routes (Optimized)
Route::prefix('user_types')->name('user_types.')->group(function () {
    Route::post('datatable', [UserTypeController::class, 'index'])->name('datatable');
    Route::get('create', [UserTypeController::class, 'create'])->name('create');
    Route::post('/', [UserTypeController::class, 'store'])->name('store');
    Route::get('{id}', [UserTypeController::class, 'show'])->name('show');
    Route::get('{id}/edit', [UserTypeController::class, 'edit'])->name('edit');
    Route::put('{id}', [UserTypeController::class, 'update'])->name('update');
    Route::delete('{id}', [UserTypeController::class, 'destroy'])->name('destroy');
    Route::patch('active/{id}', [UserTypeController::class, 'activate'])->name('active');
    Route::patch('inactive/{id}', [UserTypeController::class, 'inactivate'])->name('inactive');
    Route::get('dropdown/list', [UserTypeController::class, 'dropdown'])->name('dropdown');
    Route::get('doctor/list', [UserTypeController::class, 'forDoctor'])->name('for_doctor');
});

// Setting Routes
Route::get('settings/{id}/edit', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings/{id}', [SettingsController::class, 'update'])->name('settings.update');
Route::post('settings/datatable', [SettingsController::class, 'datatable'])->name('settings.datatable');
// Operator Settings Routes Start (legacy — kept for admin UI)
Route::post('user_operator_settings/datatable', [UserOperatorSettingsController::class, 'datatable'])->name('user_operator_settings.datatable');
Route::get('user_operator_settings/{id}/edit', [UserOperatorSettingsController::class, 'edit'])->name('user_operator_settings.edit');
Route::put('user_operator_settings/{id}', [UserOperatorSettingsController::class, 'update'])->name('user_operator_settings.update');

// Operator Settings REST API (gates applied in-controller)
Route::prefix('operator-settings')->name('operator-settings.')->group(function () {
    Route::get('/', [OperatorSettingsController::class, 'index'])->name('index');
    Route::get('global', [OperatorSettingsController::class, 'globalIndex'])->name('global.index');
    Route::get('global/{globalOperator}', [OperatorSettingsController::class, 'globalShow'])->name('global.show');
    Route::get('{operatorSetting}', [OperatorSettingsController::class, 'show'])->name('show');
    Route::patch('{operatorSetting}', [OperatorSettingsController::class, 'update'])->name('update');
    Route::patch('{operatorSetting}/test-mode', [OperatorSettingsController::class, 'toggleTestMode'])->name('test-mode');
});
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

// Town routes

// Towns — REST API additions (registered first so literal paths win over
// the legacy `Route::resource` wildcard `{town}`; legacy PUT/DELETE continue
// to serve the admin UI untouched)
Route::prefix('towns')->name('towns.api.')->group(function () {
    Route::get('/', [ApiTownsController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiTownsController::class, 'dropdown'])->name('dropdown');
    Route::post('create', [ApiTownsController::class, 'store'])->name('create');
    Route::get('{town}', [ApiTownsController::class, 'show'])->name('show')->whereNumber('town');
    Route::patch('{town}', [ApiTownsController::class, 'update'])->name('update')->whereNumber('town');
    Route::patch('{town}/status', [ApiTownsController::class, 'status'])->name('status')->whereNumber('town');
});

Route::post('towns/datatable', [TownController::class, 'datatable'])->name('towns.datatable');

Route::post('towns/status', [TownController::class, 'status'])->name('towns.status');

Route::resource('towns', TownController::class)->except('index');

// Regions Routes Start
// Regions — legacy (kept intact for admin UI; shares URIs with REST where safe)
Route::post('regions/datatable', [RegionsController::class, 'datatable'])->name('regions.datatable');
Route::post('regions', [RegionsController::class, 'store'])->name('regions.store');
Route::get('regions/{id}/edit', [RegionsController::class, 'edit'])->name('regions.edit')->whereNumber('id');
Route::put('regions/{id}', [RegionsController::class, 'update'])->name('regions.update')->whereNumber('id');
Route::delete('regions/{id}', [RegionsController::class, 'destroy'])->name('regions.destroy')->whereNumber('id');
Route::post('regions/status', [RegionsController::class, 'status'])->name('regions.status');
Route::post('regions_sort_save', [RegionsController::class, 'sortOrderSave'])->name('regions.sort_save');
Route::get('regions_sort', [RegionsController::class, 'sortOrderGet'])->name('regions.sort_get');

// Regions — REST API additions (non-conflicting methods/URIs only; legacy
// endpoints above keep the admin UI working unchanged)
Route::prefix('regions')->name('regions.api.')->group(function () {
    Route::get('/', [ApiRegionsController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiRegionsController::class, 'dropdown'])->name('dropdown');
    Route::get('sorted', [ApiRegionsController::class, 'sorted'])->name('sorted');
    Route::post('sort', [ApiRegionsController::class, 'sort'])->name('sort');
    Route::get('{region}', [ApiRegionsController::class, 'show'])->name('show')->whereNumber('region');
    Route::patch('{region}', [ApiRegionsController::class, 'update'])->name('update')->whereNumber('region');
    Route::patch('{region}/status', [ApiRegionsController::class, 'status'])->name('status')->whereNumber('region');
});
// Regions Routes End

// Cities Routes Start
Route::post('cities/datatable', [CitiesController::class, 'datatable'])->name('cities.datatable');
Route::post('cities', [CitiesController::class, 'store'])->name('cities.store');
Route::get('cities/{id}/edit', [CitiesController::class, 'edit'])->name('cities.edit');
Route::put('cities/{id}', [CitiesController::class, 'update'])->name('cities.update');
Route::delete('cities/{id}', [CitiesController::class, 'destroy'])->name('cities.destroy');
Route::post('cities/status', [CitiesController::class, 'status'])->name('cities.status');
Route::post('cities_sort_save', [CitiesController::class, 'sortOrderSave'])->name('cities.sort_save');
// AUTH-5 dedup: these flat legacy endpoints (kept for the Blade admin UI — see
// note below) shared their route NAMES with the SPA-canonical grouped routes in
// catalogue.php (`services/sort/{get,save}`, names `services.sort_save` /
// `services.get_sort`), which broke `route:cache` (duplicate-name LogicException).
// The SPA uses ONLY the grouped routes (src/components/services/service-sort-dialog.tsx);
// the names now belong to those. Paths here are unchanged, so the Blade UI is
// unaffected. The flat routes themselves are swept (with SPA/Blade verification)
// at the Blade cutover.
Route::post('services_save', [ServicesController::class, 'sortOrderSave'])->name('services.sort_save_legacy');
Route::post('services_category_sort_save', [ServicesController::class, 'categorySortOrderSave'])->name('services.category_sort_save');
Route::get('cities_sort', [CitiesController::class, 'sortOrderGet'])->name('cities.sort_get');
Route::get('services_sort', [ServicesController::class, 'sortOrderGet'])->name('services.get_sort_legacy');

// Cities — REST API additions (non-conflicting methods/URIs only; legacy
// endpoints above keep the admin UI working unchanged)
Route::prefix('cities')->name('cities.api.')->group(function () {
    Route::get('/', [ApiCitiesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiCitiesController::class, 'dropdown'])->name('dropdown');
    Route::get('sorted', [ApiCitiesController::class, 'sorted'])->name('sorted');
    Route::post('sort', [ApiCitiesController::class, 'sort'])->name('sort');
    Route::post('create', [ApiCitiesController::class, 'store'])->name('create');
    Route::get('{city}', [ApiCitiesController::class, 'show'])->name('show')->whereNumber('city');
    Route::patch('{city}', [ApiCitiesController::class, 'update'])->name('update')->whereNumber('city');
    Route::patch('{city}/status', [ApiCitiesController::class, 'status'])->name('status')->whereNumber('city');
});
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

// Lead Sources — REST API additions (non-conflicting methods/URIs only;
// legacy endpoints above keep the admin UI working unchanged)
Route::prefix('lead_sources')->name('lead_sources.api.')->group(function () {
    Route::get('/', [ApiLeadSourcesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiLeadSourcesController::class, 'dropdown'])->name('dropdown');
    Route::get('sorted', [ApiLeadSourcesController::class, 'sorted'])->name('sorted');
    Route::post('sort', [ApiLeadSourcesController::class, 'sort'])->name('sort');
    Route::post('create', [ApiLeadSourcesController::class, 'store'])->name('create');
    Route::get('{leadSource}', [ApiLeadSourcesController::class, 'show'])->name('show')->whereNumber('leadSource');
    Route::patch('{leadSource}', [ApiLeadSourcesController::class, 'update'])->name('update')->whereNumber('leadSource');
    Route::patch('{leadSource}/status', [ApiLeadSourcesController::class, 'status'])->name('status')->whereNumber('leadSource');
});
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

// Centres — REST API (backed by the Locations model/service). Uses a
// distinct `/centres` URL prefix so there is zero overlap with the legacy
// `/locations/*` routes above — the admin UI continues to work unchanged.
Route::prefix('centres')->name('centres.api.')->group(function () {
    Route::get('/', [ApiCentresController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiCentresController::class, 'dropdown'])->name('dropdown');
    Route::get('sorted', [ApiCentresController::class, 'sorted'])->name('sorted');
    Route::post('sort', [ApiCentresController::class, 'sort'])->name('sort');
    Route::post('create', [ApiCentresController::class, 'store'])->name('create');
    Route::get('{centre}', [ApiCentresController::class, 'show'])->name('show')->whereNumber('centre');
    Route::patch('{centre}', [ApiCentresController::class, 'update'])->name('update')->whereNumber('centre');
    Route::patch('{centre}/status', [ApiCentresController::class, 'status'])->name('status')->whereNumber('centre');
    Route::get('{centre}/services', [ApiCentresController::class, 'services'])->name('services')->whereNumber('centre');
    Route::delete('{centre}', [ApiCentresController::class, 'destroy'])->name('destroy')->whereNumber('centre');
});

// Lead Statuses Routes Start
Route::post('lead_statuses/datatable', [LeadStatusesController::class, 'datatable'])->name('lead_statuses.datatable');
Route::get('lead_statuses/create', [LeadStatusesController::class, 'create'])->name('lead_statuses.create');
Route::post('lead_statuses', [LeadStatusesController::class, 'store'])->name('lead_statuses.store');
Route::get('lead_statuses/{id}/edit', [LeadStatusesController::class, 'edit'])->name('lead_statuses.edit');
Route::put('lead_statuses/{id}', [LeadStatusesController::class, 'update'])->name('lead_statuses.update');
Route::delete('lead_statuses/{id}', [LeadStatusesController::class, 'destroy'])->name('lead_statuses.destroy');
Route::post('lead_statuses/status', [LeadStatusesController::class, 'status'])->name('lead_statuses.status');
Route::post('lead_statuses_sort_save', [LeadStatusesController::class, 'sortOrderSave'])->name('lead_statuses.sort_save');
Route::get('lead_statuses_sort', [LeadStatusesController::class, 'sortOrderGet'])->name('lead_statuses.sort_get');

// Lead Statuses — REST API additions (non-conflicting methods/URIs only;
// legacy endpoints above keep the admin UI working unchanged)
Route::prefix('lead_statuses')->name('lead_statuses.api.')->group(function () {
    Route::get('/', [ApiLeadStatusesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiLeadStatusesController::class, 'dropdown'])->name('dropdown');
    Route::get('parents', [ApiLeadStatusesController::class, 'parents'])->name('parents');
    Route::get('sorted', [ApiLeadStatusesController::class, 'sorted'])->name('sorted');
    Route::post('sort', [ApiLeadStatusesController::class, 'sort'])->name('sort');
    Route::post('create', [ApiLeadStatusesController::class, 'store'])->name('create');
    Route::get('{leadStatus}', [ApiLeadStatusesController::class, 'show'])->name('show')->whereNumber('leadStatus');
    Route::patch('{leadStatus}', [ApiLeadStatusesController::class, 'update'])->name('update')->whereNumber('leadStatus');
    Route::patch('{leadStatus}/status', [ApiLeadStatusesController::class, 'status'])->name('status')->whereNumber('leadStatus');
});
// Lead Statuses Routes End

// Appointment Statuses Routes Start
Route::post('appointment_statuses/datatable', [AppointmentStatusesController::class, 'datatable'])->name('appointment_statuses.datatable');
Route::post('appointment_statuses', [AppointmentStatusesController::class, 'store'])->name('appointment_statuses.store');
Route::get('appointment_statuses/{id}/edit', [AppointmentStatusesController::class, 'edit'])->name('appointment_statuses.edit');
Route::put('appointment_statuses/{id}', [AppointmentStatusesController::class, 'update'])->name('appointment_statuses.update');
Route::delete('appointment_statuses/{id}', [AppointmentStatusesController::class, 'destroy'])->name('appointment_statuses.destroy');
Route::post('appointment_statuses/status', [AppointmentStatusesController::class, 'status'])->name('appointment_statuses.status');

// Appointment Statuses — REST API additions (non-conflicting methods/URIs
// only; legacy endpoints above keep the admin UI working unchanged)
Route::prefix('appointment_statuses')->name('appointment_statuses.api.')->group(function () {
    Route::get('/', [ApiAppointmentStatusesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiAppointmentStatusesController::class, 'dropdown'])->name('dropdown');
    Route::get('parents', [ApiAppointmentStatusesController::class, 'parents'])->name('parents');
    Route::post('create', [ApiAppointmentStatusesController::class, 'store'])->name('create');
    Route::get('{appointmentStatus}', [ApiAppointmentStatusesController::class, 'show'])->name('show')->whereNumber('appointmentStatus');
    Route::patch('{appointmentStatus}', [ApiAppointmentStatusesController::class, 'update'])->name('update')->whereNumber('appointmentStatus');
    Route::patch('{appointmentStatus}/status', [ApiAppointmentStatusesController::class, 'status'])->name('status')->whereNumber('appointmentStatus');
});
// Appointment Statuses Routes End

// Cancellation Reasons (read-only — used by status-update dialogs)
Route::get('cancellation-reasons', [\App\Http\Controllers\Api\CancellationReasonsController::class, 'index'])
    ->name('cancellation_reasons.index');

// Machine Types Routes Start
Route::post('machine_types/datatable', [MachineTypeController::class, 'datatable'])->name('machine_types.datatable');
Route::post('machine_types', [MachineTypeController::class, 'store'])->name('machine_types.store');
Route::get('machine_types/{id}/edit', [MachineTypeController::class, 'edit'])->name('machine_types.edit');
Route::put('machine_types/{id}', [MachineTypeController::class, 'update'])->name('machine_types.update');
Route::delete('machine_types/{id}', [MachineTypeController::class, 'destroy'])->name('machine_types.destroy');
Route::post('machine_types/status', [MachineTypeController::class, 'status'])->name('machine_types.status');

// Machine Types — REST API additions (non-conflicting methods/URIs only;
// legacy endpoints above keep the admin UI working unchanged)
Route::prefix('machine_types')->name('machine_types.api.')->group(function () {
    Route::get('/', [ApiMachineTypesController::class, 'index'])->name('index');
    Route::get('dropdown', [ApiMachineTypesController::class, 'dropdown'])->name('dropdown');
    Route::post('create', [ApiMachineTypesController::class, 'store'])->name('create');
    Route::get('{machineType}', [ApiMachineTypesController::class, 'show'])->name('show')->whereNumber('machineType');
    Route::patch('{machineType}', [ApiMachineTypesController::class, 'update'])->name('update')->whereNumber('machineType');
    Route::patch('{machineType}/status', [ApiMachineTypesController::class, 'status'])->name('status')->whereNumber('machineType');
});
// Machine Types Routes End
