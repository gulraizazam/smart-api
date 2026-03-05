<?php

use App\Http\Controllers\Admin\InventoryReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\LeadsController as AdminLeadsController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\Api\PlansController as ApiPlansController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\BundlesController as AdminBundlesController;
use App\Http\Controllers\Api\BundlesController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\Admin\UserVouchersController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\ResourceRotasController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\TransferProductController;
use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\TransferProductsController;
use App\Http\Controllers\Admin\AppointmentsPlansController;
use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\ConsultancyInvoiceController;
use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\MembershipsController;
use App\Http\Controllers\Admin\MembershipTypesController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Api\BusinessClosureController;
use App\Http\Controllers\Api\ScheduleController;

/*
|-----------------------------------------viewDetail---------------------------------
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

    // Dashboard API Routes
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('config', [\App\Http\Controllers\Api\DashboardController::class, 'getConfig'])->name('config');
        Route::get('stats', [\App\Http\Controllers\Api\DashboardController::class, 'getStats'])->name('stats');
        Route::get('activities', [\App\Http\Controllers\Api\DashboardController::class, 'getActivities'])->name('activities');
        Route::get('collection-by-centre', [\App\Http\Controllers\Api\DashboardController::class, 'collectionByCentre'])->name('collection_by_centre');
        Route::get('revenue-by-centre', [\App\Http\Controllers\Api\DashboardController::class, 'revenueByCentre'])->name('revenue_by_centre');
        Route::get('collection-by-service-category', [\App\Http\Controllers\Api\DashboardController::class, 'collectionByServiceCategory'])->name('collection_by_service_category');
        Route::get('revenue-by-service-category', [\App\Http\Controllers\Api\DashboardController::class, 'revenueByServiceCategory'])->name('revenue_by_service_category');
        Route::get('revenue-by-service', [\App\Http\Controllers\Api\DashboardController::class, 'revenueByService'])->name('revenue_by_service');
        Route::get('appointment-by-status', [\App\Http\Controllers\Api\DashboardController::class, 'appointmentByStatus'])->name('appointment_by_status');
        Route::get('appointment-by-type', [\App\Http\Controllers\Api\DashboardController::class, 'appointmentByType'])->name('appointment_by_type');
        Route::get('centre-wise-arrival', [\App\Http\Controllers\Api\DashboardController::class, 'centreWiseArrival'])->name('centre_wise_arrival');
        Route::get('csr-wise-arrival', [\App\Http\Controllers\Api\DashboardController::class, 'csrWiseArrival'])->name('csr_wise_arrival');
        Route::get('call-wise-arrival', [\App\Http\Controllers\Api\DashboardController::class, 'callWiseArrival'])->name('call_wise_arrival');
        Route::get('doctor-wise-conversion', [\App\Http\Controllers\Api\DashboardController::class, 'doctorWiseConversion'])->name('doctor_wise_conversion');
        Route::get('doctor-wise-feedback', [\App\Http\Controllers\Api\DashboardController::class, 'doctorWiseFeedback'])->name('doctor_wise_feedback');
        Route::get('unattended-payments', [\App\Http\Controllers\Api\DashboardController::class, 'unattendedPayments'])->name('unattended_payments');
        Route::get('overdue-treatments', [\App\Http\Controllers\Api\DashboardController::class, 'overdueTreatments'])->name('overdue_treatments');
        Route::get('doctor-upselling-data', [\App\Http\Controllers\Api\DashboardController::class, 'doctorUpsellingData'])->name('doctor_upselling_data');
    });

    // Treatments API Routes (Optimized)
    Route::prefix('treatments')->name('treatments.')->group(function () {
        Route::post('datatable', [\App\Http\Controllers\Api\TreatmentsController::class, 'datatable'])->name('datatable');
        Route::post('store', [\App\Http\Controllers\Api\TreatmentsController::class, 'store'])->name('store');
        Route::post('drag-drop-reschedule', [\App\Http\Controllers\Api\TreatmentsController::class, 'dragDropReschedule'])->name('drag_drop_reschedule');
        Route::get('check-patient-last-treatment', [\App\Http\Controllers\Api\TreatmentsController::class, 'checkPatientLastTreatment'])->name('check_patient_last_treatment');
        Route::post('clear-cache', [\App\Http\Controllers\Api\TreatmentsController::class, 'clearCache'])->name('clear_cache');
        Route::get('{id}/edit', [\App\Http\Controllers\Api\TreatmentsController::class, 'edit'])->name('edit');
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

    // Services (Optimized - using API Controller with Service Layer)
    Route::prefix('services')->name('services.')->group(function () {
        // Static routes first (before dynamic {id} routes)
        Route::post('datatable', [ServicesController::class, 'datatable'])->name('datatable');
        Route::post('status', [ServicesController::class, 'status'])->name('status');
        Route::get('create', [ServicesController::class, 'create'])->name('create');
        Route::post('/', [ServicesController::class, 'store'])->name('store');
        Route::post('duplicate', [ServicesController::class, 'storeDuplicate'])->name('duplicate.store');
        Route::get('sort/get', [ServicesController::class, 'sortOrderGet'])->name('get_sort');
        Route::post('sort/save', [ServicesController::class, 'sortOrderSave'])->name('sort_save');
        Route::get('color', [ServicesController::class, 'getColor'])->name('get_color');
        // Dynamic routes with {id} parameter
        Route::get('{id}/edit', [ServicesController::class, 'edit'])->name('edit');
        Route::get('{id}/duplicate', [ServicesController::class, 'duplicate'])->name('duplicate');
        Route::get('{id}', [ServicesController::class, 'show'])->name('show');
        Route::put('{id}', [ServicesController::class, 'update'])->name('update');
        Route::delete('{id}', [ServicesController::class, 'destroy'])->name('destroy');
    });

    // Logs Routes Start
    Route::post('logs/datatable', [LogsController::class, 'datatable'])->name('logs.datatable');
    // Logs Routes End

    // Sms Templates Start
    Route::post('sms_templates/datatable', [SMSTemplatesController::class, 'datatable'])->name('sms_templates.datatable');
    Route::get('sms_templates/{id}/edit', [SMSTemplatesController::class, 'edit'])->name('sms_templates.edit');
    Route::put('sms_templates/{id}', [SMSTemplatesController::class, 'update'])->name('sms_templates.update');
    Route::post('sms_templates/status', [SMSTemplatesController::class, 'status'])->name('sms_templates.status');
    // Sms Templates End

    //Resource Route start
    Route::post('resources/datatable', [ResourcesController::class, 'datatable'])->name('resources.datatable');
    Route::post('resources/status', [ResourcesController::class, 'status'])->name('resources.status');
    Route::get('resources/get_machinetype', [ResourcesController::class, 'get_machinetype'])->name('resources.get_machinetype');
    Route::resource('resources', ResourcesController::class)->except('index');

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

    //Refunds route start
    Route::post('refunds/datatable', [RefundsController::class, 'datatable'])->name('refunds.datatable');
    Route::get('refunds/refund_create/{id}', [RefundsController::class, 'refund_create'])->name('refunds.refund_create');
    Route::get('refunds/detail/{id}', [RefundsController::class, 'detail'])->name('refunds.detail');
    Route::resource('refunds', RefundsController::class)->except('index');
    Route::resource('feedbacks', FeedbackController::class)->except('index');
    //Discount route Start
    Route::post('discounts/datatable', [DiscountsController::class, 'datatable'])->name('discounts.datatable');
    Route::post('discounts/status', [DiscountsController::class, 'status'])->name('discounts.status');
    Route::get('discounts/locations/{id}', [DiscountsController::class, 'displayDlocation'])->name('discounts.location_manage');
    Route::get('discounts/getDservice', [DiscountsController::class, 'getDservices'])->name('discounts.get_Dservice');
    Route::get('getDiscountServices', [DiscountsController::class, 'getDiscountServices'])->name('discounts.getDiscountServices');
    Route::get('discounts/services-for-configurable', [DiscountsController::class, 'getServicesForConfigurable'])->name('discounts.servicesForConfigurable');
    Route::post('discounts/allocate-configurable', [DiscountsController::class, 'allocateConfigurable'])->name('discounts.allocateConfigurable');
    Route::post('discounts/saveDervice', [DiscountsController::class, 'saveDservices'])->name('discounts.save_Dervice');
    Route::post('discounts/deleteDservice', [DiscountsController::class, 'deleteDservice'])->name('discounts.delete_service');
    Route::post('discounts/deleteDserviceGroup', [DiscountsController::class, 'deleteDserviceGroup'])->name('discounts.delete_service_group');

    Route::resource('discounts', DiscountsController::class)->except('index');

    ////Vouchers

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

    //Bundles Route start (API Controller)
    Route::prefix('bundles')->name('bundles.')->group(function () {
        Route::post('datatable', [BundlesController::class, 'datatable'])->name('datatable');
        Route::post('status', [BundlesController::class, 'status'])->name('status');
        Route::get('detail/{id}', [BundlesController::class, 'detail'])->name('detail');
        Route::get('{id}/edit', [BundlesController::class, 'edit'])->name('edit');
        Route::post('/', [BundlesController::class, 'store'])->name('store');
        Route::put('{id}', [BundlesController::class, 'update'])->name('update');
        Route::delete('{id}', [BundlesController::class, 'destroy'])->name('destroy');
    });
    //Bundles Route End

    //Centre Target
    Route::post('centre_targets/load-centres', [CentreTargetsController::class, 'leadtargetcentre'])->name('centre_targets.load_target_centre');
    Route::get('centre_targets/diplay/{id}', [CentreTargetsController::class, 'display'])->name('centre_targets.display');
    Route::post('centre_targets/datatable', [CentreTargetsController::class, 'datatable'])->name('centre_targets.datatable');
    Route::resource('centre_targets', CentreTargetsController::class)->except('index');

    //Package Advance route start
    Route::post('packagesadvances/datatable', [PackageAdvancesController::class, 'datatable'])->name('packagesadvances.datatable');
    Route::post('packagesadvances/status', [PackageAdvancesController::class, 'status'])->name('packagesadvances.status');
    Route::post('packagesadvances/cancel/{id}', [PackageAdvancesController::class, 'cancel'])->name('packagesadvances.cancel');
    Route::get('packagesadvances/getpackages', [PackageAdvancesController::class, 'getpackages'])->name('packagesadvances.getpackages');
    Route::get('packagesadvances/getpackagesinfo', [PackageAdvancesController::class, 'getpackagesinfo'])->name('packagesadvances.getpackagesinfo');
    Route::get('packagesadvances/getpackagesinfo_update', [PackageAdvancesController::class, 'getpackagesinfo_update'])->name('packagesadvances.getpackagesinfo_update');
    Route::get('packagesadvances/savepackagesadvances', [PackageAdvancesController::class, 'savepackagesadvances'])->name('packagesadvances.savepackagesadvances');
    Route::get('packagesadvances/updatepackagesadvances', [PackageAdvancesController::class, 'updatepackagesadvances'])->name('packagesadvances.updatepackagesadvances');
    Route::get('packagesadvances/update_record_final', [PackageAdvancesController::class, 'update_record_final'])->name('packagesadvances.update_record_final');
    Route::resource('packagesadvances', PackageAdvancesController::class)->except('index');


    //Business Closures Management
    Route::post('business-closures/datatable', [BusinessClosureController::class, 'datatable'])->name('business-closures.datatable');
    Route::get('business-closures/create', [BusinessClosureController::class, 'create'])->name('business-closures.create');
    Route::post('business-closures', [BusinessClosureController::class, 'store'])->name('business-closures.store');
    Route::get('business-closures/{id}/edit', [BusinessClosureController::class, 'edit'])->name('business-closures.edit');
    Route::put('business-closures/{id}', [BusinessClosureController::class, 'update'])->name('business-closures.update');
    Route::delete('business-closures/{id}', [BusinessClosureController::class, 'destroy'])->name('business-closures.destroy');

    //Schedule Calendar API
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
    Route::post('schedule/bulk-delete-shifts', [ScheduleController::class, 'bulkDeleteShifts'])->name('schedule.bulk-delete-shifts');

    //Invoice Management route start
    Route::post('invoices/datatable/&{id?}', [InvoicesController::class, 'datatable'])->name('invoices.datatable');

    Route::post('invoices/cancel/{id}', [InvoicesController::class, 'cancel'])->name('invoices.cancel');

    Route::get('invoices/displayInvoice/{id}', [InvoicesController::class, 'displayInvoice'])->name('invoices.displayInvoice');
    Route::post('invoices/invoiceDatatable/{id}', [InvoicesController::class, 'invoiceDatatable'])->name('invoices.invoiceDatatable');

    Route::get('invoices/sms_logs/{id}', [InvoicesController::class, 'showSMSLogs'])->name('invoices.sms_logs');

    Route::post('invoices/send_logged_sms', [InvoicesController::class, 'sendLogSMS']);

    Route::resource('invoices', InvoicesController::class)->except('index');
    //Invoice Management route end

    Route::get('users/getpatientid', [ApplicationUserController::class, 'getpatientid'])->name('users.getpatient.id');
    Route::get('users/getpatient-optimized', [ApplicationUserController::class, 'getpatientOptimized'])->name('users.getpatient.optimized');
    Route::get('users/getpatientorder', [ApplicationUserController::class, 'getpatientidOrder'])->name('users.getpatient.order');
    Route::get('orders/check_membership', [OrdersController::class, 'checkMembership'])->name('orders.check_membership');
    Route::get('users/phone/search', [ApplicationUserController::class, 'phoneSearch'])->name('users.phone.search');
    Route::get('users/get_patient_number', [ApplicationUserController::class, 'getpatientnumber'])->name('users.get_patient_number');
    Route::get('users/get_cities', [ApplicationUserController::class, 'getUserCities'])->name('users.get_cities');
    Route::get('users/get_centers', [ApplicationUserController::class, 'getUserCenters'])->name('users.get_centers');

    /*packages*/
    Route::post('plans/planDatatable/{id}', [PackagesController::class, 'planDatatable'])->name('packages.planDatatable');

    Route::post('packages/status', [PackagesController::class, 'status'])->name('packages.status');

    Route::get('packages/getdiscountinfo', [PackagesController::class, 'getdiscountinfo'])->name('packages.getdiscountinfo');
Route::get('packages/deleteplanrowtem', [PackagesController::class, 'deleteplanrowtem'])->name('packages.deleteplanrowtem');
    Route::get('packages/getdiscountinfo_custom', [PackagesController::class, 'getdiscountinfocustom'])->name('packages.getdiscountinfo_custom');
    Route::post('packages/savepackagesservice', [PackagesController::class, 'makePackagesServicesData'])->name('packages.savepackages_service');
    //Route::get('packages/savepackagesservice', [PackagesController::class, 'savepackages_service'])->name('packages.savepackages_service');

    Route::post('packages/deletepackagesservice', [PackagesController::class, 'deletepackagesservice'])->name('packages.deletepackages_service');
    Route::post('packages/deleteconfpackagesservice', [PackagesController::class, 'deleteconfpackagesservice'])->name('packages.deleteconfpackages_service');
    Route::get('packages/deletepackagesexclusive', [PackagesController::class, 'deletepackagesexclusive'])->name('packages.deletepackages_exclusive');

    Route::get('packages/getgrandtotal', [PackagesController::class, 'getgrandtotal'])->name('packages.getgrandtotal');

    Route::get('packages/getgrandtotal_update', [PackagesController::class, 'getgrandtotal_update'])->name('packages.getgrandtotal_update');

    Route::post('packages/savepackages', [PackagesController::class, 'savepackages'])->name('packages.savepackages');

    Route::get('packages/updatepackages', [PackagesController::class, 'updatepackages'])->name('packages.updatepackages');

    Route::get('packages/getserviceinfo', [PackagesController::class, 'getserviceinfo'])->name('packages.getserviceinfo');
    Route::get('packages/getvoucherinfo', [PackagesController::class, 'getvoucherinfo'])->name('packages.getvoucherinfo');
    Route::get('packages/display/{id}', [PackagesController::class, 'display'])->name('packages.display');

    Route::get('packages/getservice', [PackagesController::class, 'getservices'])->name('packages.getservice');
    Route::get('packages/getbundles', [PackagesController::class, 'getbundles'])->name('packages.getbundles');
    Route::post('packages/savebundle_service', [PackagesController::class, 'savebundle_service'])->name('packages.savebundle_service');
    Route::post('packages/updatebundle', [PackagesController::class, 'updatebundle'])->name('packages.updatebundle');

    Route::get('packages/getmemberships', [PackagesController::class, 'getmemberships'])->name('packages.getmemberships');
    Route::get('packages/getmembershipinfo', [PackagesController::class, 'getmembershipinfo'])->name('packages.getmembershipinfo');
    Route::get('packages/searchmembershipcodes', [PackagesController::class, 'searchMembershipCodes'])->name('packages.searchmembershipcodes');
    Route::post('packages/savemembership_service', [PackagesController::class, 'savemembership_service'])->name('packages.savemembership_service');
    Route::post('packages/update_membership_plan', [PackagesController::class, 'updateMembershipPlan'])->name('packages.update_membership_plan');

    // Membership Code Generation Routes
    Route::post('membership-codes/generate', [\App\Http\Controllers\Api\MembershipCodeController::class, 'generateCodes'])->name('membership-codes.generate');
    Route::post('membership-codes/preview', [\App\Http\Controllers\Api\MembershipCodeController::class, 'previewCodes'])->name('membership-codes.preview');
    Route::get('membership-codes/available', [\App\Http\Controllers\Api\MembershipCodeController::class, 'getAvailableCodes'])->name('membership-codes.available');
    Route::get('membership-codes/search', [\App\Http\Controllers\Api\MembershipCodeController::class, 'searchCodes'])->name('membership-codes.search');

    Route::get('packages/pdf/{id}', [PackagesController::class, 'package_pdf'])->name('packages.package_pdf');

    Route::get('packages/getpackage', [PackagesController::class, 'getpackage'])->name('packages.getpackage');

    Route::get('packages/getservice_for_discount_zero', [PackagesController::class, 'getservices_for_zero'])->name('packages.getserviceinfo_discount_zero');

    Route::get('packages/getserviceinfo_for_plan', [PackagesController::class, 'getserviceinfo_for_plan'])->name('packages.getserviceinfo_for_plan');
    Route::get('packages/getdiscountinfo_for_plan', [PackagesController::class, 'getdiscountinfo_for_plan'])->name('packages.getdiscountinfo_for_plan');
    Route::get('packages/getdiscountinfocustom_for_plan', [PackagesController::class, 'getdiscountinfocustom_for_plan'])->name('packages.getdiscountinfocustom_for_plan');
    Route::get('packages/savepackages_service_for_plan', [PackagesController::class, 'savepackages_service_for_plan'])->name('packages.savepackages_service_for_plan');

    /*Routes for editing the cash in treatment plan*/
    Route::get('packages/edit_cash/{id}/{package_id}', [PackagesController::class, 'editpackageadvancescashindex'])->name('packages.edit_cash');

    Route::post('packages/delete/cash', [PackagesController::class, 'deletepackageadvancescash'])->name('packages.delete_cash');

    Route::put('packages/edit_cash/store', [PackagesController::class, 'storepackageadvancescash'])->name('packages.edit_cash.store');
    /*End*/

    // Route for Sms log start
    Route::get('packages/sms_logs/{id}', [PackagesController::class, 'showSMSLogs'])->name('packages.sms_logs');
    Route::post('packages/send/logged_sms', [PackagesController::class, 'sendLogSMS'])->name('packages.resend_sms');
    // End

    Route::get('packages/getappointmentinfo', [PackagesController::class, 'getappointmentinfo'])->name('packages.getappointmentinfo');
    Route::get('packages/getsoldbydata', [PackagesController::class, 'getSoldByData'])->name('packages.getsoldbydata');
    Route::get('packages/checkDuplicateServiceForSoldBy', [PackagesController::class, 'checkDuplicateServiceForSoldBy'])->name('packages.checkDuplicateServiceForSoldBy');
    Route::post('packages/updatesoldby', [PackagesController::class, 'updateSoldBy'])->name('packages.updatesoldby');
    Route::resource('packages', PackagesController::class)->except('index');

    // Non Plans Refunds API routes removed — functionality not in use

    // Custom User Form Feedbacks Routes
    Route::post('custom_form_feedbacks/datatable', [CustomFormFeedbacksController::class, 'datatable'])->name('custom_form_feedbacks.datatable');
    Route::post('custom_form_feedbacks/status', [CustomFormFeedbacksController::class, 'status'])->name('custom_form_feedbacks.status');

    Route::get('custom_form_feedbacks/{id}/export_pdf', [CustomFormFeedbacksController::class, 'exportPdf'])->name('custom_form_feedbacks.export_pdf');
    Route::post('custom_form_feedbacks/{form_id}/submit_form', [CustomFormFeedbacksController::class, 'submit_form'])->name('custom_form_feedbacks.submit_form');
    Route::post('custom_form_feedbacks/{feedback_id}/update_field/{feedback_field_id}', [CustomFormFeedbacksController::class, 'update_field'])->name('custom_form_feedbacks.update_field');
    Route::resource('custom_form_feedbacks', CustomFormFeedbacksController::class)->except('index');

    //Custom user form
    Route::post('custom_forms/datatable', [CustomFormsController::class, 'datatable'])->name('custom_forms.datatable');
    Route::post('custom_forms/status', [CustomFormsController::class, 'status'])->name('custom_forms.status');
    Route::resource('custom_forms', CustomFormsController::class);

    Route::post('custom_forms_mass_destroy', [CustomFormsController::class, 'massDestroy'])->name('custom_forms.mass_destroy');
    Route::post('custom_forms/form_update/{id}', [CustomFormsController::class, 'form_update'])->name('custom_forms.form_update');
    Route::post('custom_forms/{form_id}/update_field/{field_id}', [CustomFormsController::class, 'update_field'])->name('custom_forms.update_field');
    Route::post('custom_forms/{id}/create_field/', [CustomFormsController::class, 'create_field'])->name('custom_forms.create_field');
    Route::get('custom_forms/{id}/sort_fields/', [CustomFormsController::class, 'sort_fields'])->name('custom_forms.sort_fields');
    Route::post('custom_forms/{form_id}/delete_field/{field_id}', [CustomFormsController::class, 'delete_field'])->name('custom_forms.delete_field');
    Route::get('custom_forms_sort', [CustomFormsController::class, 'sortorder'])->name('custom_forms.sort');
    Route::get('custom_forms_sort_save', [CustomFormsController::class, 'sortorder_save'])->name('custom_forms.sort_save');
    Route::get('custom_forms_medical', [CustomFormsController::class, 'create_medical'])->name('custom_forms.create_medical');
    Route::get('custom_forms_measurement', [CustomFormsController::class, 'create_measurement'])->name('custom_forms.create_measurement');

    // Leads API Routes (Optimized)
    // IMPORTANT: Specific routes MUST come before wildcard {id} routes
    Route::prefix('leads')->name('leads.')->group(function () {
        // POST routes
        Route::post('datatable', [LeadsController::class, 'datatable'])->name('datatable');
        Route::post('junk-datatable', [LeadsController::class, 'datatable'])->name('junk_datatable');
        Route::post('/', [LeadsController::class, 'store'])->name('store');
        Route::post('status', [LeadsController::class, 'status'])->name('status');
        Route::post('load_child_services', [LeadsController::class, 'loadChildServices'])->name('load_child_services');
        Route::post('upload', [LeadsController::class, 'uploadLeads'])->name('upload');
        Route::post('comment', [LeadsController::class, 'storeComment'])->name('storecomment');
        Route::post('loadlead', [LeadsController::class, 'loadLeadData'])->name('load_lead');
        
        // GET routes - specific paths (must be before {id} wildcard)
        Route::get('create', [LeadsController::class, 'create'])->name('create');
        Route::get('showleadstatus', [LeadsController::class, 'showLeadStatuses'])->name('showleadstatus');
        Route::get('getleadid', [LeadsController::class, 'getLeadId'])->name('getlead.id');
        Route::get('get_lead_number', [LeadsController::class, 'getLeadNumber'])->name('get_lead_number');
        Route::get('phone/search', [LeadsController::class, 'phoneSearch'])->name('phone.search');
        Route::get('lead_statuses', [LeadsController::class, 'loadLeadStatuses'])->name('lead_statuses');
        Route::get('treatments', [LeadsController::class, 'loadTreatments'])->name('treatments');
        Route::get('lead_sources', [LeadsController::class, 'loadLeadSources'])->name('lead_sources');
        Route::get('cities', [LeadsController::class, 'loadCities'])->name('cities');
        Route::get('leadstatus_popup_checks', [LeadsController::class, 'leadStatusesPopCheck'])->name('leadstatus_popup_checks');
        Route::get('leadstatuschild_popup_checks', [LeadsController::class, 'leadStatusChildPopCheck'])->name('leadstatuschild_popup_checks');
        Route::get('export/pdf', [LeadsController::class, 'exportPdf'])->name('export.pdf');
        Route::get('export/excel', [LeadsController::class, 'exportDocs'])->name('export.excel');
        Route::get('detail/{id}', [LeadsController::class, 'detail'])->name('detail');
        Route::get('convert/{id}', [LeadsController::class, 'convert'])->name('convert');
        Route::get('edit/service/{id}/{service_id}', [LeadsController::class, 'editService'])->name('edit.service');
        
        // PUT routes
        Route::put('storeleadstatus', [LeadsController::class, 'storeLeadStatuses'])->name('storeleadstatus');
        Route::put('save_city', [LeadsController::class, 'saveCity'])->name('save_city');
        
        // PATCH routes
        Route::patch('{id}/send-sms', [LeadsController::class, 'sendSms'])->name('send_sms');
        
        // POST routes with {id}
        Route::post('{id}/remove-from-junk', [LeadsController::class, 'removeFromJunk'])->name('remove_from_junk');
        
        // Wildcard {id} routes - MUST be last
        Route::get('{id}', [LeadsController::class, 'detail'])->name('show');
        Route::get('{id}/edit', [LeadsController::class, 'edit'])->name('edit');
        Route::put('{id}', [LeadsController::class, 'update'])->name('update');
        Route::delete('{id}', [LeadsController::class, 'destroy'])->name('destroy');
    });

    Route::post('feedbacks/datatable', [FeedbackController::class, 'datatable'])->name('feedbacks.datatable');
    // Legacy route for popup (keeping for backward compatibility)
    Route::get('lead_Create_popup', [AdminLeadsController::class, 'make_pop'])->name('leads.create_popup');

    /*Appointment routes*/
    Route::post('appointments/load-locations', [AppointmentsController::class, 'loadLocationsByCity'])->name('appointments.load_locations');
    Route::post('appointments/load-doctors', [AppointmentsController::class, 'loadDoctorsByLocation'])->name('appointments.load_doctors');
    Route::post('appointments/load-consultant-doctors', [AppointmentsController::class, 'loadConsultantDoctorsByLocation'])->name('appointments.load_consultant_doctors');
    Route::post('appointments/update/schedule', [AppointmentsController::class, 'updateSchedule'])->name('appointments.updateSchedule');
    Route::get('appointments/schedule/get', [AppointmentsController::class, 'getSchedule'])->name('appointments.get_schedule');
    Route::resource('appointments', AppointmentsController::class);
    Route::post('appointments/load/lead', [AppointmentsController::class, 'loadLeadData'])->name('appointments.load_lead');

    // Patients API Routes (Optimized)
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::post('datatable', [\App\Http\Controllers\Api\PatientController::class, 'index'])->name('datatable');
        Route::get('create', [\App\Http\Controllers\Api\PatientController::class, 'create'])->name('create');
        Route::get('search', [\App\Http\Controllers\Api\PatientController::class, 'search'])->name('search');
        Route::post('/', [\App\Http\Controllers\Api\PatientController::class, 'store'])->name('store');
        Route::post('status', [\App\Http\Controllers\Api\PatientController::class, 'status'])->name('status');
        Route::post('image', [\App\Http\Controllers\Api\PatientController::class, 'storeImage'])->name('storeimage');
        Route::post('assignmembership', [\App\Http\Controllers\Api\PatientController::class, 'assignMembership'])->name('assignmembership');
        Route::post('assignvoucher', [\App\Http\Controllers\Api\PatientController::class, 'assignVoucher'])->name('assignvoucher');
        Route::get('getPatient/{id}', [\App\Http\Controllers\Api\PatientController::class, 'getPatient'])->name('getPatient');
        Route::get('{id}', [\App\Http\Controllers\Api\PatientController::class, 'show'])->name('show');
        Route::get('{id}/edit', [\App\Http\Controllers\Api\PatientController::class, 'edit'])->name('edit');
        Route::put('{id}', [\App\Http\Controllers\Api\PatientController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\Api\PatientController::class, 'destroy'])->name('destroy');
        Route::post('{id}/addreferral', [\App\Http\Controllers\Api\PatientController::class, 'addReferral'])->name('addreferral');
        // Optimized datatables for patient preview tabs (using match for GET/POST since KTDatatable uses POST by default)
        Route::match(['get', 'post'], '{id}/appointments-datatable', [\App\Http\Controllers\Api\PatientController::class, 'appointmentsDatatable'])->name('appointmentsDatatable');
        // Dedicated endpoints for consultations and treatments filtered by patient
        Route::match(['get', 'post'], '{id}/consultations-datatable', [\App\Http\Controllers\Api\PatientController::class, 'consultationsDatatable'])->name('consultationsDatatable');
        Route::match(['get', 'post'], '{id}/treatments-datatable', [\App\Http\Controllers\Api\PatientController::class, 'treatmentsDatatable'])->name('treatmentsDatatable');
        Route::match(['get', 'post'], '{id}/vouchers-datatable', [\App\Http\Controllers\Admin\UserVouchersController::class, 'datatable'])->name('vouchersDatatable');
        // Optimized document upload
        Route::post('{id}/upload-document', [\App\Http\Controllers\Api\PatientController::class, 'uploadDocument'])->name('uploadDocument');
        Route::post('{id}/update-document/{documentId}', [\App\Http\Controllers\Api\PatientController::class, 'updateDocument'])->name('updateDocument');
        // Activity history
        Route::get('{id}/activity-history', [\App\Http\Controllers\Api\PatientController::class, 'getActivityHistory'])->name('activityHistory');
        // Voucher history
        Route::get('{id}/voucher-history/{userVoucherId}', [\App\Http\Controllers\Api\PatientController::class, 'getVoucherHistory'])->name('voucherHistory');
        // Tab counts
        Route::get('{id}/tab-counts', [\App\Http\Controllers\Api\PatientController::class, 'getTabCounts'])->name('tabCounts');
        // Patient notes
        Route::get('{id}/notes', [\App\Http\Controllers\Api\PatientController::class, 'getNotes'])->name('notes');
        Route::post('{id}/notes', [\App\Http\Controllers\Api\PatientController::class, 'addNote'])->name('addNote');
        Route::put('{id}/notes/{noteId}', [\App\Http\Controllers\Api\PatientController::class, 'updateNote'])->name('updateNote');
        Route::delete('{id}/notes/{noteId}', [\App\Http\Controllers\Api\PatientController::class, 'deleteNote'])->name('deleteNote');
        Route::post('{id}/notes/{noteId}/toggle-pin', [\App\Http\Controllers\Api\PatientController::class, 'togglePinNote'])->name('togglePinNote');
    });
    Route::post('customformfeedbackspatient/datatable/&{id}', [PatientCustomFormController::class, 'datatable'])->name('customformfeedbackspatient.datatable');
    Route::get('customformfeedbackspatient/addnewform/{id}', [PatientCustomFormController::class, 'AddNewForm'])->name('customformfeedbackspatient.addnew');

    /*Route start for patient medical history Forms*/
    Route::post('medicalhistoryform/datatable/&{id}', [MedicalHistoryController::class, 'datatable'])->name('medicalhistoryform.datatable');

    /*Route start for patient measurement history Forms*/
    Route::post('measurementhistoryform/datatable/&{id}', [MeasurementHistoryController::class, 'datatable'])->name('measurementhistoryform.datatable');

    Route::post('patients/documentdatatable/{id}', [PatientsController::class, 'documentdatatable'])->name('patients.documentdatatable');

    Route::get('patients/createdocument/{id}', [PatientsController::class, 'documentCreate'])->name('patients.createdocument');

    Route::post('patients/storedocument', [PatientsController::class, 'documentstore'])->name('patients.storedocument');

    Route::delete('patients/deletedocuments/{id}', [PatientsController::class, 'documentdelete'])->name('patients.documentsdestroy');

    Route::post('patients/updatedocuments/{id}', [PatientsController::class, 'documentupdate'])->name('patients.updatedocuments');

    // Appointment Route start for images
    Route::post('appointmentsimage/datatable/{id}', [AppointmentimageController::class, 'datatable'])->name('appointmentsimage.datatable');

    Route::post('appointmentsmeasurement/datatable/{id}', [AppointmentMeasurementController::class, 'datatable'])->name('appointmentsmeasurement.datatable');

    /*Route start for patient package - NEW OPTIMIZED ROUTES*/
    Route::prefix('plans-optimized')->group(function () {
        // Patient-specific plans (patient card)
        Route::post('datatable/{patient_id}', [ApiPlansController::class, 'datatable'])->name('plans.optimized.datatable');
        Route::get('lookup-data/{patient_id}', [ApiPlansController::class, 'getLookupData'])->name('plans.optimized.lookup');
        Route::get('statistics/{patient_id}', [ApiPlansController::class, 'getStatistics'])->name('plans.optimized.statistics');
        
        // Global plans (admin packages page)
        Route::post('global/datatable', [ApiPlansController::class, 'globalDatatable'])->name('plans.optimized.global.datatable');
        Route::get('global/lookup-data', [ApiPlansController::class, 'getGlobalLookupData'])->name('plans.optimized.global.lookup');
    });
    /*Route end for patient package - NEW OPTIMIZED ROUTES*/

    /*Route start for patient pakcage - OLD ROUTES (TO BE DEPRECATED)*/
    Route::post('plans/datatable/{id?}', [PackagesController::class, 'datatable'])->name('plans.datatable');

    Route::get('plans/getserviceinfo', [PackagesController::class, 'getserviceinfo'])->name('plans.getserviceinfo');

    Route::get('plans/getdiscountinfo', [PackagesController::class, 'getdiscountinfo'])->name('plans.getdiscountinfo');

    Route::get('plans/savepackagesservice', [PackagesController::class, 'savepackages_service'])->name('plans.savepackages_service');

    Route::get('plans/savepackages', [PackagesController::class, 'savepackages'])->name('plans.savepackages');

    Route::get('plans/getdiscountinfo_custom', [PackagesController::class, 'getdiscountinfocustom'])->name('plans.getdiscountinfo_custom');

    Route::get('plans/getserviceinfo_for_plan', [PackagesController::class, 'getserviceinfo_for_plan'])->name('plans.getserviceinfo_for_plan');
    Route::get('plans/getdiscountinfo_for_plan', [PackagesController::class, 'getdiscountinfo_for_plan'])->name('plans.getdiscountinfo_for_plan');
    Route::get('plans/getdiscountinfocustom_for_plan', [PackagesController::class, 'getdiscountinfocustom_for_plan'])->name('plans.getdiscountinfocustom_for_plan');
    Route::get('plans/savepackages_service_for_plan', [PackagesController::class, 'savepackages_service_for_plan'])->name('plans.savepackages_service_for_plan');

    Route::get('plans/getgrandtotal', [PackagesController::class, 'getgrandtotal'])->name('plans.getgrandtotal');

    Route::post('plans/deletepackagesservice', [PackagesController::class, 'deletepackagesservice'])->name('plans.deletepackages_service');

    Route::get('plans/updatepackages', [PackagesController::class, 'updatepackages'])->name('plans.updatepackages');

    Route::get('plans/getgrandtotal_update', [PackagesController::class, 'getgrandtotal_update'])->name('plans.getgrandtotal_update');

    Route::post('plans/status', [PackagesController::class, 'status'])->name('plans.status');

    Route::any('plans/destroy/{id}', [PackagesController::class, 'destroy'])->name('plans.destroy');

    Route::get('plans/display/{id}', [PackagesController::class, 'display'])->name('plans.display');

    Route::get('plans/edit/{id}', [PackagesController::class, 'edit'])->name('plans.edit');

    Route::get('plans/{id}', [PackagesController::class, 'index'])->name('plans.index');

    Route::get('plans/{id}/createplan', [PackagesController::class, 'create'])->name('plans.createplan');

    Route::get('plans/edit_cash/{id}/{package_id}', [PackagesController::class, 'editpackageadvancescashindex'])->name('plans.edit_cash');

    /*Route end for patient package*/

    Route::post('finances/datatable/&{id}', [PackageAdvancesController::class, 'datatable'])->name('finances.datatable');

    Route::get('finances/{id}/create', [PackageAdvancesController::class, 'create'])->name('finances.create');

    Route::get('finances/getpackages', [PackageAdvancesController::class, 'getpackages'])->name('finances.getpackages');

    Route::post('finances/savepackagesadvances', [PackageAdvancesController::class, 'savepackagesadvances'])->name('finances.savepackagesadvances');

    Route::post('invoicepatient/datatable/&{id}', [InvoicesController::class, 'datatable'])->name('invoicepatient.datatable');

    Route::get('invoicepatient/displayInvoice/{id}', [InvoicesController::class, 'displayInvoice'])->name('invoicepatient.displayInvoice');

    Route::post('invoicepatient/cancel/{id}', [InvoicesController::class, 'cancel'])->name('invoicepatient.cancel');

    Route::post('refundpatient/datatable/&{id}', [RefundsController::class, 'patientDatatable'])->name('refundpatient.datatable');
    Route::get('refundpatient/refund_create/{id}', [RefundsController::class, 'refund_create'])->name('refundpatient.refund_create');

    // Patient non-plans refunds API routes removed — functionality not in use

    /*Appointment routes*/
    Route::post('consultancy/datatable', [AppointmentsController::class, 'datatable'])->name('consultancy.datatable');
    // MIGRATED TO: Route::post('treatments/datatable') - admin.treatments.datatable
    Route::get('appointments/show/status', [AppointmentsController::class, 'showAppointmentStatuses'])->name('appointments.showappointmentstatus');
    Route::post('appointments/load-child-appointment-statuses', [AppointmentsController::class, 'loadAppointmentStatuses'])->name('appointments.load_child_appointment_statuses');
    Route::put('appointments/store/appointmentstatus', [AppointmentsController::class, 'storeAppointmentStatuses'])->name('appointments.storeappointmentstatus');
    Route::post('appointments/load-child-appointment/status-data', [AppointmentsController::class, 'loadAppointmentStatusData'])->name('appointments.load_child_appointment_status_data');
    /*Appointment routes*/

    Route::get('appointments/sms_logs/{id}', [AppointmentsController::class, 'showSMSLogs'])->name('appointments.sms_logs');

    Route::put('appointments/send/logged_sms', [AppointmentsController::class, 'sendLogSMS'])->name('appointments.resend_sms');

    // Legacy consultancy resource route - DEPRECATED, use /api/consultancy/* routes instead
    // Route::resource('consultancy', AppointmentsController::class)->except('index');

    Route::post('appointments/load-doctor-rota', [AppointmentsController::class, 'loadRotaByDoctor'])->name('appointments.load_doctor_rota');

    /*Inventory Routes Start*/

    /*Warehouseroutes*/
    Route::post('warehouse/datatable', [WarehouseController::class, 'datatable'])->name('warehouse.datatable');
    Route::get('warehouse/create', [WarehouseController::class, 'create'])->name('warehouse.create');
    Route::post('warehouse', [WarehouseController::class, 'store'])->name('warehouse.store');
    Route::get('warehouse/{id}/edit', [WarehouseController::class, 'edit'])->name('warehouse.edit');
    Route::put('warehouse/{id}', [WarehouseController::class, 'update'])->name('warehouse.update');
    Route::delete('warehouse/{id}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy');
    Route::patch('warehouse/active/{id}', [WarehouseController::class, 'active'])->name('warehouse.active');
    Route::post('warehouse/status', [WarehouseController::class, 'status'])->name('warehouse.status');
    Route::get('warehouse/sort', [WarehouseController::class, 'sortorder'])->name('warehouse.sort');
    Route::post('reports/inventory_reports/result', [InventoryReportController::class, 'reportResult'])->name('reports.inventory_report_result');
    Route::post('reports/inventory_reports/stock', [InventoryReportController::class, 'stockReport'])->name('reports.inventory_report_stock');
    /*Warehouseroutes*/

    /*Brand routes*/
    Route::post('brands/datatable', [BrandsController::class, 'datatable'])->name('brands.datatable');
    Route::post('brands', [BrandsController::class, 'store'])->name('brands.store');
    Route::get('brands/{id}/edit', [BrandsController::class, 'edit'])->name('brands.edit');
    Route::put('brands/{id}', [BrandsController::class, 'update'])->name('brands.update');
    Route::delete('brands/{id}', [BrandsController::class, 'destroy'])->name('brands.destroy');
    Route::post('brands/status', [BrandsController::class, 'status'])->name('brands.status');
    /*Brand routes*/

    /*Product routes*/
    Route::post('products/datatable', [ProductsController::class, 'datatable'])->name('products.datatable');
    Route::get('products/create', [ProductsController::class, 'create'])->name('products.create');
    Route::post('products', [ProductsController::class, 'store'])->name('products.store');
    Route::post('products/status', [ProductsController::class, 'status'])->name('products.status');
    Route::get('products/{id}/edit', [ProductsController::class, 'edit'])->name('products.edit');
    Route::post('products/save_allocate', [ProductsController::class, 'saveAllocate'])->name('products.save_allocate');
    Route::get('products/products/{id}', [ProductsController::class, 'displaylocation'])->name('products.location_manage');
    Route::get('products/{id}/saleprice', [ProductsController::class, 'editSalePrice'])->name('products.edit-sale-price');
    Route::post('products/stock-detail/{id}', [ProductsController::class, 'productStockDetail'])->name('products.stock-detail');
    Route::post('products/inventory-detail/{id}', [ProductsController::class, 'productInventoryDetail'])->name('products.inventories');
    Route::put('products/{id}/{detail}', [ProductsController::class, 'update'])->name('products.update');
    Route::post('products/{id}/updatesaleprice', [ProductsController::class, 'updateSalePrice'])->name('products.update-sale-price');
    Route::post('products/{id}/addstock', [ProductsController::class, 'addStock'])->name('products.add-stock');
    Route::delete('products/{id}', [ProductsController::class, 'destroy'])->name('products.destroy');
    Route::get('products/transfer-product/{id}', [ProductsController::class, 'transferProductGetData'])->name('products.transfer_product.get');
    Route::post('products/transfer-product', [ProductsController::class, 'transferProduct'])->name('products.transfer_product');
    /*Product routes*/

    /* Transfer Product */
    Route::post('transfer-product/datatable', [TransferProductsController::class, 'datatable'])->name('transfer_products.datatable');
    Route::get('transfer-product/get-products', [TransferProductsController::class, 'getTransferProducts'])->name('transfer_products.get_products');
    Route::get('transfer-product/fetch-products', [TransferProductsController::class, 'getProducts'])->name('transfer_products.fetch_products');
    Route::resource('transfer_product', TransferProductsController::class)->except('index');
    /* Transfer Product Route */

    /*Order routes*/
    Route::post('orders/datatable', [OrdersController::class, 'datatable'])->name('orders.datatable');
    Route::post('orders/refund/datatable', [OrdersController::class, 'refunddatatable'])->name('orders.refund.datatable');
    Route::get('orders/getproducts', [OrdersController::class, 'getProducts'])->name('orders.getproducts');
    Route::get('orders/getdiscounts', [OrdersController::class, 'getDiscounts'])->name('orders.getdiscounts');
    Route::get('orders/displayInvoice/{id}', [OrdersController::class, 'displayInvoiceAppointment'])->name('orders.invoiceDisplay');
    Route::get('invoices/pdf/{id}/{download?}', [OrdersController::class, 'invoicePdf'])->name('orders.invoice_pdf');
    Route::post('get-employees', [OrdersController::class, 'getEmployees'])->name('get-employees');
    Route::post('get-doctors', [OrdersController::class, 'getDoctors'])->name('get-doctors');
     Route::post('get-doctors-for-sales', [OrdersController::class, 'getDoctorsForSales'])->name('get-doctors-for-sales');
    Route::post('get-centre-doctors', [OrdersController::class, 'getCentreDoctors'])->name('get-centre-doctors');
    Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('orders/{id}/edit', [OrdersController::class, 'edit'])->name('orders.edit');
    Route::post('orders/update/{id}', [OrdersController::class, 'update'])->name('orders.update');


    Route::get('orders/refund/{id}/detail', [OrdersController::class, 'orderRefundDetail'])->name('orders.refund.detail');
    Route::post('orders/{id}/refund', [OrdersController::class, 'orderRefund'])->name('orders.refund');
    //Route::delete('orders/refund/{id}', [OrdersController::class, 'orderRefundDestroy'])->name('orders.refund.destroy');
    Route::delete('orders/{id}', [OrdersController::class, 'destroy'])->name('orders.destroy');

    // Appointments API Routes (Optimized)
    Route::prefix('appointments')->name('appointments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AppointmentsController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\AppointmentsController::class, 'store'])->name('store');
        Route::get('{id}', [\App\Http\Controllers\Api\AppointmentsController::class, 'show'])->name('show');
        Route::put('{id}', [\App\Http\Controllers\Api\AppointmentsController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\Api\AppointmentsController::class, 'destroy'])->name('destroy');
        Route::put('{id}/status', [\App\Http\Controllers\Api\AppointmentsController::class, 'updateStatus'])->name('update_status');
        Route::post('schedule', [\App\Http\Controllers\Api\AppointmentsController::class, 'schedule'])->name('schedule');
        Route::get('scheduled/list', [\App\Http\Controllers\Api\AppointmentsController::class, 'scheduled'])->name('scheduled');
        Route::get('non-scheduled/list', [\App\Http\Controllers\Api\AppointmentsController::class, 'nonScheduled'])->name('non_scheduled');
        Route::get('statistics/data', [\App\Http\Controllers\Api\AppointmentsController::class, 'statistics'])->name('statistics');
    });

    // Consultancy API Routes (Optimized)
    Route::prefix('consultancy')->name('consultancy.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ConsultancyController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\ConsultancyController::class, 'store'])->name('store');
        Route::put('{id}', [\App\Http\Controllers\Api\ConsultancyController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\Api\ConsultancyController::class, 'destroy'])->name('destroy');
        Route::post('{id}/schedule', [\App\Http\Controllers\Api\ConsultancyController::class, 'schedule'])->name('schedule');
        Route::get('scheduled/list', [\App\Http\Controllers\Api\ConsultancyController::class, 'scheduled'])->name('scheduled');
        Route::get('non-scheduled/list', [\App\Http\Controllers\Api\ConsultancyController::class, 'nonScheduled'])->name('non_scheduled');
        Route::get('statistics/data', [\App\Http\Controllers\Api\ConsultancyController::class, 'statistics'])->name('statistics');
    });

    // Treatment API Routes (Optimized)
    Route::prefix('treatment')->name('treatment.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TreatmentController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\TreatmentController::class, 'store'])->name('store');
        Route::put('{id}', [\App\Http\Controllers\Api\TreatmentController::class, 'update'])->name('update');
        Route::get('scheduled/list', [\App\Http\Controllers\Api\TreatmentController::class, 'scheduled'])->name('scheduled');
        Route::get('non-scheduled/list', [\App\Http\Controllers\Api\TreatmentController::class, 'nonScheduled'])->name('non_scheduled');
        Route::get('statistics/data', [\App\Http\Controllers\Api\TreatmentController::class, 'statistics'])->name('statistics');
        Route::get('resources/available', [\App\Http\Controllers\Api\TreatmentController::class, 'availableResources'])->name('available_resources');
        Route::get('services/by-location', [\App\Http\Controllers\Api\TreatmentController::class, 'servicesByLocation'])->name('services_by_location');
    });

    // Appointment Routes - Using API Controller with Service Layer
    Route::get('appointments/load/scheduled-appointments', [\App\Http\Controllers\Api\AppointmentsController::class, 'scheduled'])->name('appointments.load_scheduled_appointments');
    Route::post('appointments/check-and-save-appointment', [\App\Http\Controllers\Api\AppointmentsController::class, 'schedule'])->name('appointments.check_and_save_appointment');
    
    // Legacy routes still using Admin controller (to be migrated)
    Route::get('appointments/detail/{id}', [AppointmentsController::class, 'detail'])->name('appointments.detail');
    Route::get('appointments/consulting/create', [AppointmentsController::class, 'createConsultingAppointment'])->name('appointments.consulting.create');
    Route::get('appointments/center_machines/{location_id}', [AppointmentsController::class, 'center_machines'])->name('appointments.center_machines');
    Route::get('appointments/treatment/create', [AppointmentsController::class, 'createTreatmentAppointment'])->name('appointments.treatment.create');
    Route::post('appointments/load-node-services', [AppointmentsController::class, 'loadEndServiceByBaseService'])->name('appointments.load_node_service');
    Route::post('appointments/load-all-child-services', [AppointmentsController::class, 'loadAllChildServices'])->name('appointments.load_all_child_services');
    // MIGRATED TO: Route::post('treatments/store') - admin.treatments.store
    Route::get('appointments/load/scheduled-serivce-appointments', [AppointmentsController::class, 'getScheduledServiceAppointments'])->name('appointments.load_scheduled_service_appointments');
    Route::post('appointments/check-and-save-service-appointment', [AppointmentsController::class, 'serviceSchedule'])->name('appointments.check_service_schedule_and_save_appointment');
    // MIGRATED TO: Route::post('treatments/drag-drop-reschedule') - admin.treatments.drag_drop_reschedule
    Route::get('appointments/{appointment}/edit-service', [AppointmentsController::class, 'editAppointmentService'])->name('appointments.edit_service');
    Route::get('appointments/{appointment}/feedback', [AppointmentsController::class, 'editFeedback'])->name('appointments.feedback.index');
    Route::get('appointments/invoice/{id}', [AppointmentsController::class, 'invoice'])->name('appointments.invoicecreate');
    Route::get('appointments/getpackageprice', [AppointmentsController::class, 'getpackageprice'])->name('appointments.getpackageprice');
    Route::get('appointments/displayInvoice/{id}', [AppointmentsController::class, 'displayInvoiceAppointment'])->name('appointments.InvoiceDisplay');
    Route::get('appointments/invoice-consultancy/{id}/{type?}', [ConsultancyInvoiceController::class, 'invoiceconsultancy'])->name('appointments.invoice-create-consultancy');
    Route::any('appointments/viewlog/{id}/{type}', [AppointmentsController::class, 'viewLog'])->name('appointments.viewlog');

    Route::post('appointmentsmedical/datatable/{id}', [AppointmentMedicalController::class, 'datatable'])->name('appointmentsmedical.datatable');

    Route::get('appointmentsmedical/medicalcreate/{id}', [AppointmentMedicalController::class, 'create'])->name('appointmentsmedical.create');

    Route::post('appointmentsmedical/{form_id}/{appointment_id}/submit_form', [AppointmentMedicalController::class, 'submit_form'])->name('appointmentsmedical.submit_form');

    /*Route start for plans in appointment module*/
    Route::get('appointmentplans/{appointment_id}', [AppointmentsPlansController::class, 'create'])->name('appointmentplans.create');
    /*Route end for plans in appointment module*/
    Route::get('membershiptypes/getactivetypes', [MembershipTypesController::class, 'getActiveTypes'])->name('membershiptypes.getactivetypes');
    Route::post('membershiptypes/datatable', [MembershipTypesController::class, 'datatable'])->name('membershiptypes.datatable');
    Route::post('membershiptypes/status', [MembershipTypesController::class, 'status'])->name('membershiptypes.status');
    Route::resource('membershiptypes', MembershipTypesController::class)->except('index');
    Route::get('memberships/getsoldbyusers', [MembershipsController::class, 'getSoldByUsers'])->name('memberships.getsoldbyusers');
    Route::resource('memberships', MembershipsController::class)->except('index');
    Route::post('memberships/datatable', [MembershipsController::class, 'datatable'])->name('memberships.datatable');
    Route::post('memberships/status', [MembershipsController::class, 'status'])->name('memberships.status');
    Route::post('memberships/cancel', [MembershipsController::class, 'cancelMembership'])->name('memberships.cancel');
    Route::get('memberships/{id}/student-verification', [MembershipsController::class, 'getStudentVerificationDetails'])->name('memberships.student_verification');

    // Cash Flow Module Routes
    require __DIR__.'/cashflow.php';
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Meta Conversion API Routes
Route::prefix('meta')->name('meta.')->group(function () {
    Route::post('test-connection', [\App\Http\Controllers\Admin\MetaConversionController::class, 'testConnection'])->name('test');
    Route::post('send-lead-status', [\App\Http\Controllers\Admin\MetaConversionController::class, 'sendLeadStatus'])->name('lead-status');
});
