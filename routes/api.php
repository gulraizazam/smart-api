<?php

use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\AppointmentsPlansController;
use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\BundlesController;
use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\ConsultancyInvoiceController;
use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Admin\DoctorsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Patients\RefundsController as PatientRefundController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\ResourceRotasController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    Route::post('permissions/datatable', [PermissionsController::class, 'datatable'])->name('permissions.datatable');

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
    Route::resource('doctors', DoctorsController::class)->except(['index', 'show']);
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

    //Resource Rota Management
    Route::get('resourcerotas/load_location', [ResourceRotasController::class, 'load_location'])->name('resourcerotas.load_location');
    Route::get('resourcerotas/load_doctor_and_Machine', [ResourceRotasController::class, 'load_doctor_and_Machine'])->name('resourcerotas.load_doctor_and_Machine');
    Route::post('resourcerotas/datatable', [ResourceRotasController::class, 'datatable'])->name('resourcerotas.datatable');
    Route::post('resourcerotas/status', [ResourceRotasController::class, 'status'])->name('resourcerotas.status');
    Route::get('resourcerotas/calender/{id}', [ResourceRotasController::class, 'getcalenderinfo'])->name('resourcerotas.calender');
    Route::get('resourcerotas/calender/events/{id}', [ResourceRotasController::class, 'getcalenderinfoevents'])->name('resourcerotas.events');
    Route::post('resourcerotas/store_Calender_edit', [ResourceRotasController::class, 'store_calender_edit'])->name('resourcerotas.store_Calender_edit');
    Route::resource('resourcerotas', ResourceRotasController::class)->except('index');

    //Invoice Management route start
    Route::post('invoices/datatable/&{id?}', [InvoicesController::class, 'datatable'])->name('invoices.datatable');

    Route::post('invoices/cancel/{id}', [InvoicesController::class, 'cancel'])->name('invoices.cancel');

    Route::get('invoices/displayInvoice/{id}', [InvoicesController::class, 'displayInvoice'])->name('invoices.displayInvoice');
    Route::post('invoices/invoiceDatatable/{id}', [InvoicesController::class, 'invoiceDatatable'])->name('invoices.invoiceDatatable');

    Route::get('invoices/sms_logs/{id}', [InvoicesController::class, 'showSMSLogs'])->name('invoices.sms_logs');

    Route::post('invoices/send_logged_sms', [InvoicesController::class, 'sendLogSMS']);

    Route::resource('invoices', InvoicesController::class)->except('index');
    //Invoice Management route end

    Route::get('users/getpatientid', [UsersController::class, 'getpatientid'])->name('users.getpatient.id');
    Route::get('users/phone/search', [UsersController::class, 'phoneSearch'])->name('users.phone.search');
    Route::get('users/get_patient_number', [UsersController::class, 'getpatientnumber'])->name('users.get_patient_number');
    Route::get('users/get_cities', [UsersController::class, 'getUserCities'])->name('users.get_cities');
    Route::get('users/get_centers', [UsersController::class, 'getUserCenters'])->name('users.get_centers');

    /*packages*/
    Route::post('plans/planDatatable/{id}', [PackagesController::class, 'planDatatable'])->name('packages.planDatatable');
    Route::post('packages/datatable', [PackagesController::class, 'datatable'])->name('packages.datatable');

    Route::post('packages/status', [PackagesController::class, 'status'])->name('packages.status');

    Route::get('packages/getdiscountinfo', [PackagesController::class, 'getdiscountinfo'])->name('packages.getdiscountinfo');

    Route::get('packages/getdiscountinfo_custom', [PackagesController::class, 'getdiscountinfocustom'])->name('packages.getdiscountinfo_custom');

    Route::get('packages/savepackagesservice', [PackagesController::class, 'savepackages_service'])->name('packages.savepackages_service');

    Route::post('packages/deletepackagesservice', [PackagesController::class, 'deletepackagesservice'])->name('packages.deletepackages_service');

    Route::get('packages/deletepackagesexclusive', [PackagesController::class, 'deletepackagesexclusive'])->name('packages.deletepackages_exclusive');

    Route::get('packages/getgrandtotal', [PackagesController::class, 'getgrandtotal'])->name('packages.getgrandtotal');

    Route::get('packages/getgrandtotal_update', [PackagesController::class, 'getgrandtotal_update'])->name('packages.getgrandtotal_update');

    Route::get('packages/savepackages', [PackagesController::class, 'savepackages'])->name('packages.savepackages');

    Route::get('packages/updatepackages', [PackagesController::class, 'updatepackages'])->name('packages.updatepackages');

    Route::get('packages/getserviceinfo', [PackagesController::class, 'getserviceinfo'])->name('packages.getserviceinfo');

    Route::get('packages/display/{id}', [PackagesController::class, 'display'])->name('packages.display');

    Route::get('packages/getservice', [PackagesController::class, 'getservices'])->name('packages.getservice');

    Route::get('packages/pdf/{id}', [PackagesController::class, 'package_pdf'])->name('packages.package_pdf');

    Route::get('packages/getpackage', [PackagesController::class, 'getpackage'])->name('packages.getpackage');

    Route::get('packages/getservice_for_discount_zero', [PackagesController::class, 'getservices_for_zero'])->name('packages.getserviceinfo_discount_zero');

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
    Route::resource('packages', PackagesController::class)->except('index');

    //Non Refunds Route start
    Route::post('nonplansrefunds/datatable', [RefundsController::class, 'nonplansdatatable'])->name('nonplansrefunds.datatable');
    Route::get('nonplansrefunds/refund_create/{id}', [RefundsController::class, 'nonplans_refund_create'])->name('nonprefunds.refund_create');
    Route::post('nonplansrefunds/store', [RefundsController::class, 'nonplans_refund_store'])->name('nonplansrefunds.store');

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

    Route::post('leads/junk_datatable', [LeadsController::class, 'junkDatatable'])->name('leads.junk_datatable');

    Route::get('leads/edit/service/{id}/{service_id}', [LeadsController::class, 'editService'])->name('leads.edit.service');
    Route::get('leads/showleadstatus', [LeadsController::class, 'showLeadStatuses'])->name('leads.showleadstatus');
    Route::put('leads/storeleadstatus', [LeadsController::class, 'storeLeadStatuses'])->name('leads.storeleadstatus');
    Route::get('leads/detail/{id}', [LeadsController::class, 'detail'])->name('leads.detail');
    Route::get('leads/getleadid', [LeadsController::class, 'getleadid'])->name('leads.getlead.id');
    Route::get('leads/get_lead_number', [LeadsController::class, 'getleadnumber'])->name('leads.get_lead_number');
    Route::get('leads/phone/search', [LeadsController::class, 'phoneSearch'])->name('leads.phone.search');
    Route::resource('leads', LeadsController::class)->except('index');
    Route::post('leads/datatable', [LeadsController::class, 'datatable'])->name('leads.datatable');
    // Convert Lead
    Route::get('leads/convert/{id}', [LeadsController::class, 'convert'])->name('leads.convert');
    Route::get('lead_Create_popup', [LeadsController::class, 'make_pop'])->name('leads.create_popup');

    /*Appointment routes*/
    Route::post('appointments/load-locations', [AppointmentsController::class, 'loadLocationsByCity'])->name('appointments.load_locations');
    Route::post('appointments/load-doctors', [AppointmentsController::class, 'loadDoctorsByLocation'])->name('appointments.load_doctors');
    Route::post('appointments/update/schedule', [AppointmentsController::class, 'updateSchedule'])->name('appointments.updateSchedule');
    Route::get('appointments/schedule/get', [AppointmentsController::class, 'getSchedule'])->name('appointments.get_schedule');
    Route::resource('appointments', AppointmentsController::class);
    Route::post('appointments/load/lead', [AppointmentsController::class, 'loadLeadData'])->name('appointments.load_lead');

    // Patients routes start
    Route::post('patients/{id}/appointments-datatable', [PatientsController::class, 'appointmentsDatatable'])->name('patients.appointmentsDatatable');

    Route::post('patients/datatable', [PatientsController::class, 'datatable'])->name('patients.datatable');

    Route::post('patients/status', [PatientsController::class, 'status'])->name('patients.status');
    Route::get('patients/getPatient/{id}', [PatientsController::class, 'getPatient'])->name('patients.getPatient');
    Route::post('patients/image', [PatientsController::class, 'imagestore'])->name('patients.storeimage');
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

    /*Route start for patient pakcage*/
    Route::post('plans/datatable/{id?}', [PackagesController::class, 'datatable'])->name('plans.datatable');

    Route::get('plans/getserviceinfo', [PackagesController::class, 'getserviceinfo'])->name('plans.getserviceinfo');

    Route::get('plans/getdiscountinfo', [PackagesController::class, 'getdiscountinfo'])->name('plans.getdiscountinfo');

    Route::get('plans/savepackagesservice', [PackagesController::class, 'savepackages_service'])->name('plans.savepackages_service');

    Route::get('plans/savepackages', [PackagesController::class, 'savepackages'])->name('plans.savepackages');

    Route::get('plans/getdiscountinfo_custom', [PackagesController::class, 'getdiscountinfocustom'])->name('plans.getdiscountinfo_custom');

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

    Route::post('refundpatient/datatable/&{id}', [RefundsController::class, 'datatable'])->name('refundpatient.datatable');
    Route::get('refundpatient/refund_create/{id}', [RefundsController::class, 'refund_create'])->name('refundpatient.refund_create');

    Route::post('nonplansrefundspatient/datatable/{id}', [PatientRefundController::class, 'nonplansdatatable'])->name('nonplansrefundpatient.datatable');
    Route::get('nonplansrefundspatient/refund_create/{id}', [PatientRefundController::class, 'nonplansrefundscreate'])->name('nonplansrefundpatient.refund_create');

    /*Appointment routes*/
    Route::post('consultancy/datatable', [AppointmentsController::class, 'datatable'])->name('consultancy.datatable');
    Route::post('treatment/datatable', [AppointmentsController::class, 'treatmentDatatable'])->name('treatment.datatable');
    Route::get('appointments/show/status', [AppointmentsController::class, 'showAppointmentStatuses'])->name('appointments.showappointmentstatus');
    Route::post('appointments/load-child-appointment-statuses', [AppointmentsController::class, 'loadAppointmentStatuses'])->name('appointments.load_child_appointment_statuses');
    Route::put('appointments/store/appointmentstatus', [AppointmentsController::class, 'storeAppointmentStatuses'])->name('appointments.storeappointmentstatus');
    Route::post('appointments/load-child-appointment/status-data', [AppointmentsController::class, 'loadAppointmentStatusData'])->name('appointments.load_child_appointment_status_data');
    /*Appointment routes*/

    Route::get('appointments/sms_logs/{id}', [AppointmentsController::class, 'showSMSLogs'])->name('appointments.sms_logs');

    Route::put('appointments/send/logged_sms', [AppointmentsController::class, 'sendLogSMS'])->name('appointments.resend_sms');

    Route::resource('consultancy', AppointmentsController::class)->except('index');

    Route::post('appointments/load-doctor-rota', [AppointmentsController::class, 'loadRotaByDoctor'])->name('appointments.load_doctor_rota');

    /*Inventory Routes Start*/

    /*Brand routes*/
    Route::post('brands/datatable', [BrandsController::class, 'datatable'])->name('brands.datatable');
    Route::post('brands', [BrandsController::class, 'store'])->name('brands.store');
    Route::get('brands/{id}/edit', [BrandsController::class, 'edit'])->name('brands.edit');
    Route::put('brands/{id}', [BrandsController::class, 'update'])->name('brands.update');
    Route::delete('brands/{id}', [BrandsController::class, 'destroy'])->name('brands.destroy');
    /*Brand routes*/

    /*Product routes*/
    Route::post('products/datatable', [ProductsController::class, 'datatable'])->name('products.datatable');
    Route::post('products', [ProductsController::class, 'store'])->name('products.store');
    Route::post('products/status', [ProductsController::class, 'status'])->name('products.status');
    Route::get('products/{id}/edit', [ProductsController::class, 'edit'])->name('products.edit');
    Route::get('products/{id}/saleprice', [ProductsController::class, 'editSalePrice'])->name('products.edit-sale-price');
    Route::post('products/stock-detail/{id}', [ProductsController::class, 'productStockDetail'])->name('products.stock-detail');
    Route::put('products/{id}/{detail}', [ProductsController::class, 'update'])->name('products.update');
    Route::post('products/{id}/updatesaleprice', [ProductsController::class, 'updateSalePrice'])->name('products.update-sale-price');
    Route::post('products/{id}/addstock', [ProductsController::class, 'addStock'])->name('products.add-stock');
    Route::delete('products/{id}', [ProductsController::class, 'destroy'])->name('products.destroy');
    /*Product routes*/

    /*Order routes*/
    Route::post('orders/datatable', [OrdersController::class, 'datatable'])->name('orders.datatable');
    Route::post('orders/refund/datatable', [OrdersController::class, 'refunddatatable'])->name('orders.refund.datatable');
    Route::get('orders/getproducts', [OrdersController::class, 'getProducts'])->name('orders.getproducts');
    Route::get('orders/getdiscounts', [OrdersController::class, 'getDiscounts'])->name('orders.getdiscounts');
    Route::get('orders/refund/{id}/detail', [OrdersController::class, 'orderRefundDetail'])->name('orders.refund.detail');
    Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::post('orders/{id}/cancel', [OrdersController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{id}/refund', [OrdersController::class, 'orderRefund'])->name('orders.refund');
    Route::delete('orders/{id}', [OrdersController::class, 'destroy'])->name('orders.destroy');

    /*Order routes*/
    Route::get('appointments/load/scheduled-appointments', [AppointmentsController::class, 'getScheduledAppointments'])->name('appointments.load_scheduled_appointments');

    Route::get('appointments/detail/{id}', [AppointmentsController::class, 'detail'])->name('appointments.detail');

    Route::get('appointments/consulting/create', [AppointmentsController::class, 'createConsultingAppointment'])->name('appointments.consulting.create');

    Route::post('appointments/check-and-save-appointment', [AppointmentsController::class, 'checkAndSaveAppointments'])->name('appointments.check_and_save_appointment');

    Route::get('appointments/center_machines/{location_id}', [AppointmentsController::class, 'center_machines'])->name('appointments.center_machines');
    Route::get('appointments/treatment/create', [AppointmentsController::class, 'createTreatmentAppointment'])->name('appointments.treatment.create');

    Route::post('appointments/load-node-services', [AppointmentsController::class, 'loadEndServiceByBaseService'])->name('appointments.load_node_service');
    Route::post('appointments/store-service', [AppointmentsController::class, 'storeService'])->name('appointments.store_service');

    Route::get('appointments/load/scheduled-serivce-appointments', [AppointmentsController::class, 'getScheduledServiceAppointments'])->name('appointments.load_scheduled_service_appointments');

    Route::post('appointments/check-and-save-service-appointment', [AppointmentsController::class, 'serviceSchedule'])->name('appointments.check_service_schedule_and_save_appointment');
    // Edit Service
    Route::get('appointments/{appointment}/edit-service', [AppointmentsController::class, 'editAppointmentService'])->name('appointments.edit_service');

    Route::get('appointments/invoice/{id}', [AppointmentsController::class, 'invoice'])->name('appointments.invoicecreate');

    Route::get('appointments/displayInvoice/{id}', [AppointmentsController::class, 'displayInvoiceAppointment'])->name('appointments.InvoiceDisplay');

    Route::get('appointments/invoice-consultancy/{id}/{type?}', [ConsultancyInvoiceController::class, 'invoiceconsultancy'])->name('appointments.invoice-create-consultancy');

    Route::any('appointments/viewlog/{id}/{type}', [AppointmentsController::class, 'viewLog'])->name('appointments.viewlog');

    Route::post('appointmentsmedical/datatable/{id}', [AppointmentMedicalController::class, 'datatable'])->name('appointmentsmedical.datatable');

    Route::get('appointmentsmedical/medicalcreate/{id}', [AppointmentMedicalController::class, 'create'])->name('appointmentsmedical.create');

    Route::post('appointmentsmedical/{form_id}/{appointment_id}/submit_form', [AppointmentMedicalController::class, 'submit_form'])->name('appointmentsmedical.submit_form');

    /*Route start for plans in appointment module*/
    Route::get('appointmentplans/{appointment_id}', [AppointmentsPlansController::class, 'create'])->name('appointmentplans.create');
    /*Route end for plans in appointment module*/

});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
