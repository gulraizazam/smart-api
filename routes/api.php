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
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\CustomFormFeedbacksController;

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

    // Services
    Route::post('services/datatable', [ServicesController::class, 'datatable'])->name('services.datatable');
    Route::post('services/status', [ServicesController::class, 'status'])->name('services.status');
    Route::resource('services', ServicesController::class)->except('index');

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

    // Doctors Route Start
    Route::post('doctors/datatable', [DoctorsController::class, 'datatable'])->name('doctors.datatable');
    Route::get('doctors/password/{id}', [DoctorsController::class, 'changePassword'])->name('doctors.change_password');
    Route::patch('doctors/password', [DoctorsController::class, 'savePassword'])->name('doctors.save_password');
    Route::post('doctors/status', [DoctorsController::class, 'status'])->name('doctors.status');
    Route::resource('doctors', DoctorsController::class)->except(['index', 'create', 'show']);
    Route::get('doctors/locations/{id}', [DoctorsController::class, 'displaylocation'])->name('doctors.location_manage');
    Route::get('doctors/get-service', [DoctorsController::class, 'getservices'])->name('doctors.get_service');
    Route::post('doctors/save_service', [DoctorsController::class, 'saveservices'])->name('doctors.save_service');
    Route::post('doctors/delete_service', [DoctorsController::class, 'deleteservices'])->name('doctors.delete_service');
    // Doctors Route End


    //Refunds route start
    Route::post('refunds/datatable', [RefundsController::class, 'datatable'])->name('refunds.datatable');
    Route::get('refunds/refund_create/{id}', [RefundsController::class, 'refund_create'])->name('refunds.refund_create');
    Route::get('refunds/detail/{id}', [RefundsController::class, 'detail'])->name('refunds.detail');
    Route::resource('refunds', RefundsController::class)->except('index');


    //Discount route Start
    Route::post('discounts/datatable', [DiscountsController::class, 'datatable'])->name('discounts.datatable');
    Route::post('discounts/status', [DiscountsController::class, 'status'])->name('discounts.status');
    Route::get('discounts/locations/{id}', [DiscountsController::class, 'displayDlocation'])->name('discounts.location_manage');
    Route::get('getDservice', [DiscountsController::class, 'getDservices'])->name('discounts.get_Dservice');
    Route::post('saveDervice', [DiscountsController::class, 'saveDservices'])->name('discounts.save_Dervice');
    Route::post('deleteDservice', [DiscountsController::class, 'deleteDservice'])->name('discounts.delete_service');

    Route::resource('discounts', DiscountsController::class)->except('index');

    //Packages Route start
    Route::post('bundles/datatable', [BundlesController::class, 'datatable'])->name('bundles.datatable');
    Route::post('bundles/status', [BundlesController::class, 'status'])->name('bundles.status');
    Route::get('bundles/detail/{id}', [BundlesController::class, 'detail'])->name('bundles.detail');
    Route::resource('bundles', BundlesController::class)->except(['index', 'create', 'show']);
    //Packages Route End


    //Centre Target
    Route::post('centre_targets/load-centres', [CentreTargetsController::class, 'leadtargetcentre'])->name( 'centre_targets.load_target_centre');
    Route::get('centre_targets/diplay/{id}', [CentreTargetsController::class, 'display'])->name( 'centre_targets.display');
    Route::post('centre_targets/datatable', [CentreTargetsController::class, 'datatable'])->name( 'centre_targets.datatable');
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

    //Resource Rota Management
    Route::get('resourcerotas/load_location',[ResourceRotasController::class, 'load_location'])->name('resourcerotas.load_location');
    Route::get('resourcerotas/load_doctor_and_Machine',[ResourceRotasController::class, 'load_doctor_and_Machine'])->name('resourcerotas.load_doctor_and_Machine');
    Route::post('resourcerotas/datatable',[ResourceRotasController::class, 'datatable'])->name('resourcerotas.datatable');
    Route::post('resourcerotas/status',[ResourceRotasController::class, 'status'])->name('resourcerotas.status');
    Route::get('resourcerotas/calender/{id}',[ResourceRotasController::class, 'getcalenderinfo'])->name('resourcerotas.calender');
    Route::get('resourcerotas/calender/events/{id}',[ResourceRotasController::class, 'getcalenderinfoevents'])->name('resourcerotas.events');
    Route::post('resourcerotas/store_Calender_edit',[ResourceRotasController::class, 'store_calender_edit'])->name('resourcerotas.store_Calender_edit');
    Route::resource('resourcerotas', ResourceRotasController::class)->except('index');

    //Invoice Management route start
    Route::post('invoices/datatable', [InvoicesController::class, 'datatable'])->name('invoices.datatable');

    Route::post('invoices/cancel/{id}', [InvoicesController::class, 'cancel'])->name('invoices.cancel');

    Route::get('invoices/displayInvoice/{id}', [InvoicesController::class, 'displayInvoice'])->name('invoices.displayInvoice');
    Route::post('invoices/invoiceDatatable/{id}', [InvoicesController::class, 'invoiceDatatable'])->name('invoices.invoiceDatatable');

    Route::get('invoices/pdf/{id}', [InvoicesController::class, 'invoice_pdf'])->name('invoices.invoice_pdf');

    Route::get('invoices/sms_logs/{id}', [InvoicesController::class, 'showSMSLogs'])->name('invoices.sms_logs');

    Route::post('invoices/send_logged_sms', [InvoicesController::class, 'sendLogSMS']);

    Route::resource('invoices', InvoicesController::class)->except('index');
    //Invoice Management route end

    Route::get('users/getpatientid', [UsersController::class, 'getpatientid'])->name('users.getpatient.id');

    /*packages*/
    Route::post('plans/planDatatable/{id}', [PackagesController::class, 'planDatatable'])->name('packages.planDatatable');
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
    Route::get('packages/sms_logs/{id}', [PackagesController::class, 'showSMSLogs'])->name('packages.sms_logs');
    Route::post('packages/send_logged_sms', [PackagesController::class, 'sendLogSMS'])->name('packages.resend_sms');
    // End

    Route::get('packages/getappointmentinfo',[PackagesController::class, 'getappointmentinfo'])->name('packages.getappointmentinfo');
    Route::resource('packages', PackagesController::class)->except('index');

     //Non Refunds Route start
     Route::post('nonplansrefunds/datatable', [RefundsController::class, 'nonplansdatatable'])->name('nonplansrefunds.datatable');
     Route::get('nonplansrefunds/refund_create/{id}', [RefundsController::class, 'nonplans_refund_create'])->name('nonprefunds.refund_create');
     Route::post('nonplansrefunds/store', [RefundsController::class, 'nonplans_refund_store'])->name('nonplansrefunds.store');


     // Custom User Form Feedbacks Routes
     Route::post('custom_form_feedbacks/datatable', [CustomFormFeedbacksController::class, 'datatable'])->name('custom_form_feedbacks.datatable');
     Route::post('custom_form_feedbacks/status', [CustomFormFeedbacksController::class, 'status'])->name('custom_form_feedbacks.status');
     Route::get('custom_form_feedbacks/{form_id}/fill_form', [CustomFormFeedbacksController::class, 'fill_form'])->name("custom_form_feedbacks.fill_form");
     Route::get('custom_form_feedbacks/{form_id}/preview_form', [CustomFormFeedbacksController::class, 'preview_form'])->name("custom_form_feedbacks.preview_form");
     Route::get('custom_form_feedbacks/{id}/preview', [CustomFormFeedbacksController::class, 'filled_preview'])->name("custom_form_feedbacks.filled_preview");
     Route::get('custom_form_feedbacks/{id}/print', [CustomFormFeedbacksController::class, 'filledPrint'])->name("custom_form_feedbacks.filled_print");
     Route::get('custom_form_feedbacks/{id}/export_pdf', [CustomFormFeedbacksController::class, 'exportPdf'])->name("custom_form_feedbacks.export_pdf");
     Route::post('custom_form_feedbacks/{form_id}/submit_form', [CustomFormFeedbacksController::class, 'submit_form'])->name('custom_form_feedbacks.submit_form');
     Route::post('custom_form_feedbacks/{feedback_id}/update_field/{feedback_field_id}', [CustomFormFeedbacksController::class, 'update_field'])->name('custom_form_feedbacks.update_field');
     Route::resource('custom_form_feedbacks', CustomFormFeedbacksController::class)->except('index');


});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
