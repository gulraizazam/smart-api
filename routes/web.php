<?php

use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\AppointmentsController;
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
use App\Http\Controllers\Admin\Patients\PackagesController as PatientPackageController;
use App\Http\Controllers\Admin\Patients\RefundsController as PatientRefundController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\Reports\AppointmentsController as ReportAppointmentsController;
use App\Http\Controllers\Admin\Reports\FinanceReportController;
use App\Http\Controllers\Admin\Reports\OperationsReportController;
use App\Http\Controllers\Admin\ResourceRotasController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\UserTypesController;
use App\Http\Controllers\ConversionReportController;
use App\Http\Controllers\PatientFollowupController;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\PackageAdvances;
use Facade\Ignition\Support\Packagist\Package;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;
use App\Http\Controllers\DashboardReportsController;

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
Route::get('/unathorized', function () {
    return view('unathorized');
})->name('unauthorized');
Auth::routes();
// Authentication Routes...
Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('auth.admin.login');

Route::get('/deliver-on-appointment-book', function () {
    \Artisan::call('appointment:deliver-on-appointment-book');
});
Route::get('/2nd-message-on-appointment-day', function () {
    \Artisan::call('appointment:2nd-message-on-appointment-day');
});
Route::get('/3rd-message-before-appointment', function () {
    \Artisan::call('appointment:3rd-message-before-appointment');
});
Route::get('/check-expired-records', function () {
    \Artisan::call('check:expired');
});
Route::get('/daily-stats', function () {
    \Artisan::call('appointments:daily-stats');
});
Route::get('/get_deleted', function () {
    $appointments = Appointments::where('deleted_by', 4)
        ->where('deleted_at','!=',null)
        
        ->get();
    return view('deleted',get_defined_vars());
});
Route::get('followup', [DashboardReportsController::class, 'FollowUp'])->name('dashboard.followup');

// Check Session
Route::get('check-session', [App\Http\Controllers\Auth\LoginController::class, 'checkSession'])->name('check_session');

// Password Reset Routes...
Route::get('password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('auth.password.reset');
Route::post('password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('auth.password.resetemail');
Route::get('password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('auth.password.resettoken');

/*After authentication*/
Route::group(['middleware' => ['auth.common', 'checkAccount'], 'prefix' => 'admin', 'as' => 'admin.'], function () {

    Route::get('attchrole', function () {
        dd(date_default_timezone_get());
        $user = \App\Models\User::whereEmail('amjad@redsignal.biz')->first();
        dd($user->assignRole(1));
    });
    Route::get('getrecords', function () {
        $rr = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->select('leads.lead_status_id', 'leads.active', 'leads.city_id', 'leads.service_id', 'leads.active', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId', 'users.*')
            ->get();

        return view('admin.records', compact('rr'));
    });
    Route::get('error-logs', [LogViewerController::class, 'index']);
    Route::get('updateleads', [LeadsController::class, 'leadupdate']);
    Route::get('updatestatusleads', [LeadsController::class, 'leadstatusupdate']);
    Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
    Route::get('change_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'showChangePasswordForm'])->name('change_password');
    Route::post('update_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'changePassword'])->name('update_password');
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::post('/home/datatable', [App\Http\Controllers\HomeController::class, 'datatable'])->name('home.datatable');
    Route::get('/home/collection-by-centre', [App\Http\Controllers\HomeController::class, 'collectionByCentre'])->name('home.collectionByCentre');
    Route::get('/home/my-collection-by-centre', [App\Http\Controllers\HomeController::class, 'myCollectionByCentre'])->name('home.myCollectionByCentre');
    Route::get('/home/revenue-by-service-category', [App\Http\Controllers\HomeController::class, 'RevenueByServiceCategory'])->name('home.RevenueByServiceCategory');
    Route::get('/home/revenue-by-centre', [App\Http\Controllers\HomeController::class, 'revenueByCentre'])->name('home.revenueByCentre');
    Route::get('/home/collection-by-service-category', [App\Http\Controllers\HomeController::class, 'CollectionByServiceCategory'])->name('home.CollectionByServiceCategory');
    Route::get('/home/my-revenue-by-centre', [App\Http\Controllers\HomeController::class, 'myRevenueByCentre'])->name('home.myRevenueByCentre');
    Route::get('/home/revenue-by-service', [App\Http\Controllers\HomeController::class, 'revenueByService'])->name('home.revenueByService');
    Route::get('/home/my-revenue-by-service', [App\Http\Controllers\HomeController::class, 'myRevenueByService'])->name('home.myRevenueByService');
    Route::get('/home/getstats', [App\Http\Controllers\HomeController::class, 'getStats'])->name('home.getstats');
    Route::get('/home/getactivity', [App\Http\Controllers\HomeController::class, 'getActivity'])->name('home.getactivity');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

    Route::resource('permissions', PermissionsController::class)->middleware('permission:permissions_manage');

    Route::post('roles/datatable', [RolesController::class, 'datatable'])->name('roles.datatable');
    Route::resource('roles', RolesController::class)->middleware('permission:roles_manage');
    // Route::post('roles_mass_destroy', ['uses' => 'Admin\RolesController@massDestroy', 'as' => 'roles.mass_destroy']);

    Route::post('users/datatable', [UsersController::class, 'datatable'])->name('users.datatable');

    Route::get('users/password/{id}', [UsersController::class, 'changePassword'])->name('users.change_password');
    Route::patch('users/password', [UsersController::class, 'savePassword'])->name('users.save_password');

    Route::post('users/status', [UsersController::class, 'status'])->name('users.status');

    Route::resource('users', UsersController::class)->middleware('permission:users_manage');

    Route::post('user_types/datatable', [UserTypesController::class, 'datatable'])->name('user_types.datatable');
    Route::patch('user_types/active/{id}', [UserTypesController::class, 'active'])->name('user_types.active');
    Route::patch('user_types/inactive/{id}', [UserTypesController::class, 'inactive'])->name('user_types.inactive');
    Route::resource('user_types', UserTypesController::class)->middleware('permission:user_types_manage');
    // User Operator Settings
    Route::get('user_operator_settings', [UserOperatorSettingsController::class, 'index'])->name('user_operator_settings.index');

    // Towns Routes

    Route::get('towns/import', [TownController::class, 'importTowns'])->name('towns.import');

    Route::get('towns/upload', [TownController::class, 'uploadLeads'])->name('towns.upload');

    Route::resource('towns', TownController::class)->only('index');

    // Locations
    Route::get('locations/sort_get', [LocationsController::class, 'getSortOrder'])->name('locations.sort_get');
    Route::resource('locations', LocationsController::class)->only('index');

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
    Route::get('lead_statuses', [LeadStatusesController::class, 'index'])->name('lead_statuses.index');
    Route::get('lead_statuses/sort', [LeadStatusesController::class, 'sortOrder'])->name('lead_statuses.sort');

    // Services
    Route::resource('services', ServicesController::class)->only('index')->middleware('permission:services_manage');

    // Appointment Statuses
    Route::get('appointment_statuses', [AppointmentStatusesController::class, 'index'])->name('appointment_statuses.index');

    //Resource Route start
    Route::resource('resources', ResourcesController::class)->only('index');
    //Resource Route end

    // Machine Types
    Route::get('machine_types', [MachineTypeController::class, 'index'])->name('machine_types.index');

    // Logs Types
    Route::get('logs', [LogsController::class, 'index'])->name('logs.index');

    // Sms Templates
    Route::get('sms_templates', [SMSTemplatesController::class, 'index'])->name('sms_templates.index');

    // Doctors Templates
    Route::get('doctors', [DoctorsController::class, 'index'])->name('doctors.index');

    // Refund Route
    Route::resource('refunds', RefundsController::class)->only('index');
    Route::post('refunds/getplans', [PackagesController::class, 'getPlans'])->name('refunds.getplans');
    Route::get('refunds/edit/{id}', [PackagesController::class, 'editRefund'])->name('refunds.edit');
    Route::post('refunds/update', [PackagesController::class, 'updateRefund'])->name('refunds.update');
    //Refunds route end

    //Discount route Start
    Route::resource('discounts', DiscountsController::class)->only('index')->middleware('permission:discounts_manage');
    //Discount route end

    //Packages route Start
    Route::get('bundles', [BundlesController::class, 'index'])->name('bundles.index')->middleware('permission:packages_manage');
    //Packages route end

    //Centre Target
    Route::resource('centre_targets', CentreTargetsController::class)->only('index');

    //Package Advance route start
    Route::resource('packagesadvances', PackageAdvancesController::class)->only('index');

    //Resource Rota Management
    Route::resource('resourcerotas', ResourceRotasController::class)->only('index')->middleware('permission:resourcerotas_manage');
    Route::get('resourcerotas/calender/view/{id}', [ResourceRotasController::class, 'viewCalender'])->name('resourcerotas.calender-view');

    //Invoice Management route start
    Route::get('invoices/log/{id}/{type}/{patient_id?}', [InvoicesController::class, 'invoicelog'])->name('invoices.invoice_log');
    Route::resource('invoices', InvoicesController::class)->only('index')->middleware('permission:invoices_manage');
    //Invoice Management route end
    Route::get('invoices/pdf/{id}/{download?}/{flag?}', [InvoicesController::class, 'invoice_pdf'])->name('invoices.invoice_pdf');

    // Package route start
    Route::get('plans/log/{id}/{type}', [PackagesController::class, 'packagelog'])->name('packages.log');
    Route::resource('packages', PackagesController::class)->only('index')->middleware('permission:plans_manage');
    // Package Route end

    // Non Refunds Route start
    Route::get('nonplansrefunds/index', [RefundsController::class, 'nonplansindex'])->name('nonplansrefunds.index');
    //Non Refunds Route end

    // Custom User Form Feedbacks Routes
    Route::get('custom_form_feedbacks/{id}/print', [CustomFormFeedbacksController::class, 'filledPrint'])->name('custom_form_feedbacks.filled_print');
    Route::resource('custom_form_feedbacks', CustomFormFeedbacksController::class);
    Route::get('custom_form_feedbacks/{form_id}/preview_form', [CustomFormFeedbacksController::class, 'preview_form'])->name('custom_form_feedbacks.preview_form');

    Route::get('custom_form_feedbacks/{id}/preview', [CustomFormFeedbacksController::class, 'filled_preview'])->name('custom_form_feedbacks.filled_preview');

    /*Route start for patient Pre define Custome Forms*/
    Route::get('customformfeedbackspatient/editcustomform/{id}', [PatientCustomFormController::class, 'edit'])->name('customformfeedbackspatient.edit');
    Route::get('customformfeedbackspatient/previewcustomform/{id}', [PatientCustomFormController::class, 'filled_preview'])->name('customformfeedbackspatient.previewform');
    Route::get('customformfeedbackspatient/fill_form/{id}/{patient_id}', [PatientCustomFormController::class, 'fill_form'])->name('customformfeedbackspatient.fill_form');
    Route::get('customformfeedbackspatient/{id}', [PatientCustomFormController::class, 'index'])->name('customformfeedbackspatient.index');
    Route::get('customformfeedbackspatient/{id}/print', [CustomFormFeedbacksController::class, 'filledPrint'])->name('patient_custom_form_feedbacks.filled_print');
    Route::get('customformfeedbackspatient/{id}/export_pdf', [CustomFormFeedbacksController::class, 'exportPdf'])->name('patient_custom_form_feedbacks.export_pdf');
    Route::get('custom_form_feedbacks/{form_id}/fill_form', [CustomFormFeedbacksController::class, 'fill_form'])->name('custom_form_feedbacks.fill_form');
    /*Route End for patient pre define Custome Forms*/
    /*Route Start for patient card measurement*/
    /*Route End for patient card measurement */
    // Patients routes end

    // Custom User Form Routes
    Route::resource('custom_forms', CustomFormsController::class);

    // Leads
    Route::get('leadstatus_popup_checks', [LeadsController::class, 'LeadStatusespopcheck'])->name('leads.leadstatus_popup_checks');

    Route::get('leadstatuschild_popup_checks', [LeadsController::class, 'LeadStatusChildpopcheck'])->name('leads.leadstatuschild_popup_checks');

    Route::post('leads/loadlead', [LeadsController::class, 'loadLeadData'])->name('leads.load_lead');

    Route::get('leads/junk', [LeadsController::class, 'junk'])->name('leads.junk');

    Route::post('leads/load_child_services', [LeadsController::class, 'LoadChildServices'])->name('leads.load_child_services');
    Route::patch('leads/send_sms/{id}', [LeadsController::class, 'send_sms'])->name('leads.send_sms');

    Route::post('leads/status', [LeadsController::class, 'status'])->name('leads.status');

    Route::get('LeadCommentStore', [LeadsController::class, 'LeadStoreComment'])->name('leads.storecomment');

    Route::get('LeadCommentStore', [LeadsController::class, 'LeadStoreComment'])->name('leads.storecomment');
    Route::get('LeadEditDetail', [LeadsController::class, 'LeadEditDetailAjax'])->name('leads.LeadEditDetail');

    //Lead Import
    Route::get('leads/import', [LeadsController::class, 'importLeads'])->name('leads.import');
    Route::post('leads/upload', [LeadsController::class, 'uploadLeads'])->name('leads.upload');

    Route::resource('leads', LeadsController::class)->only('index');
    Route::post('leads/comment_store', [LeadsController::class, 'comment_store'])->name('leads.comment_store');
    // Load and Save Lead Statuses
    Route::get('leads_lead_statuses', [LeadsController::class, 'loadLeadStatuses'])->name('leads.lead_statuses');

    Route::put('leads_save_status', [LeadsController::class, 'saveLeadStatus'])->name('leads.save_status');
    // Load and Save Treatments
    Route::get('leads_treatments', [LeadsController::class, 'loadTreatments'])->name('leads.treatments');

    Route::put('leads_save_treatment', [LeadsController::class, 'saveTreatment'])->name('leads.save_treatment');
    // Load and Save Lead Sources

    Route::get('leads_lead_sources', [LeadsController::class, 'loadLeadSources'])->name('leads.lead_sources');

    Route::put('leads_save_source', [LeadsController::class, 'saveLeadSource'])->name('leads.save_source');
    // Load and Save Cities
    Route::get('leads_cities', [LeadsController::class, 'loadCities'])->name('leads.cities');

    Route::put('leads_save_city', [LeadsController::class, 'saveCity'])->name('leads.save_city');
    Route::get('leads/export/pdf', [LeadsController::class, 'exportPdf'])->name('leads.export.pdf');
    Route::get('leads/export/excel', [LeadsController::class, 'exportDocs'])->name('leads.export.excel');

    Route::get('patients/{id}/preview', [PatientsController::class, 'preview'])->name('patients.preview');

    Route::get('plans/log/{id}/{patient_id}/{type}', [PatientPackageController::class, 'planlog'])->name('plans.log');

    Route::get('patients/{id}/leads', [PatientsController::class, 'leads'])->name('patients.leads');

    Route::post('patients/{id}/leads-datatable', [PatientsController::class, 'leadsDatatable'])->name('patients.leadsDatatable');

    Route::get('patients/{id}/appointments', [PatientsController::class, 'appointments'])->name('patients.appointments');

    Route::get('patients/{id}/image', [PatientsController::class, 'imageindex'])->name('patients.imageurl');

    Route::get('patients/{id}/document', [PatientsController::class, 'documentindex'])->name('patients.document');

    Route::get('patients/edit/{id}', [PatientsController::class, 'documentedit'])->name('patients.documentedit');

    Route::resource('patients', PatientsController::class)->middleware('permission:patients_manage');

    /*Route start for patient medical history Forms*/
    Route::get('medicalhistoryform/editcustomform/{id}', [MedicalHistoryController::class, 'edit'])->name('medicalhistoryform.edit');

    Route::get('medicalhistoryform/previewcustomform/{id}', [MedicalHistoryController::class, 'filled_preview'])->name('medicalhistoryform.previewform');

    Route::get('medicalhistoryform/{id}', [MedicalHistoryController::class, 'index'])->name('medicalhistoryform.index');

    /*Route start for patient measurement history Forms*/
    Route::get('measurementhistoryform/editcustomform/{id}', [MeasurementHistoryController::class, 'edit'])->name('measurementhistoryform.edit');

    Route::get('measurementhistoryform/previewcustomform/{id}', [MeasurementHistoryController::class, 'filled_preview'])->name('measurementhistoryform.previewform');

    Route::get('measurementhistoryform/{id}', [MeasurementHistoryController::class, 'index'])->name('measurementhistoryform.index');

    Route::post('measurementhistoryform/{custom_form_id}', [MeasurementHistoryController::class, 'update_measurement_field'])->name('measurementhistoryform.update');

    Route::get('appointmentsmeasurement/{id}/export_pdf', [AppointmentMeasurementController::class, 'exportPdf'])->name('appointment_measurement_custom_form_feedbacks.export_pdf');

    /*Route start for patient Package advances*/

    Route::get('finances/getpackagesinfo', [PackageAdvancesController::class, 'getpackagesinfo'])->name('finances.getpackagesinfo');

    Route::get('finances/{id}', [PackageAdvancesController::class, 'index'])->name('finances.index');

    /*Route end for patient package advances */

    /*Route start for patient invoices*/

    Route::get('invoicepatient/pdf/{id}', [InvoicesController::class, 'invoice_pdf'])->name('invoicepatient.invoice_pdf');

    Route::get('invoicepatient/log/{id}/{type}/{patient_id?}', [InvoicesController::class, 'invoicelog'])->name('invoicepatient.invoice_log');

    Route::get('invoicepatient/{id}', [InvoicesController::class, 'index'])->name('invoicepatient.index');
    /*Route end for patient invoices*/

    /*Route start for Patient refunds*/

    Route::post('refundpatient/store', [RefundsController::class, 'store'])->name('refundpatient.store');

    Route::get('refundpatient/detail/{id}', [RefundsController::class, 'detail'])->name('refundpatient.detail');

    Route::get('refundpatient/{id}', [RefundsController::class, 'index'])->name('refundpatient.index');

    Route::get('refundpatient/{id}/create', [RefundsController::class, 'create'])->name('refundpatient.create');
    /*Route end for patient refunds*/

    /*Route start for patient non plans refunds*/
    Route::get('nonplansrefundspatient/{id}', [PatientRefundController::class, 'nonplansrefundsindex'])->name('nonplansrefundpatient.index');
    Route::post('nonplansrefundspatient/store', [PatientRefundController::class, 'nonplansrefundsstore'])->name('nonplansrefundpatient.store');
    /*Route end for patient non plans refunds*/

    /*Appointment routes*/

    Route::get('appointments/doctors', [AppointmentsController::class, 'loadDoctors'])->name('appointments.doctors');

    Route::put('appointments/save_doctor', [AppointmentsController::class, 'saveDoctor'])->name('appointments.save_doctor');

    Route::post('appointments/status', [AppointmentsController::class, 'status'])->name('appointments.status');

    /*Route::resource('appointments', AppointmentsController::class)->only('index');*/
    Route::resource('consultancy', AppointmentsController::class)->only('index')->middleware('permission:appointments_manage');
    Route::get('treatment', [AppointmentsController::class, 'treatment'])->name('treatment.index')->middleware('permission:appointments_services');

    /*service routes*/

    Route::get('appointments/manage-services', [AppointmentsController::class, 'createService'])->name('appointments.manage_services');

    Route::get('appointments/load-non-scheduled-service-appointments', [AppointmentsController::class, 'getNonScheduledServiceAppointments'])->name('appointments.load_nonscheduled_service_appointments');

    Route::get('appointments/get_room_resources', [AppointmentsController::class, 'getRoomResources'])->name('appointments.get_room_resources');

    Route::get('appointments/get_room_resources_with_specific_date', [AppointmentsController::class, 'getRoomResourcesWithDate'])->name('appointments.get_room_resources_with_specific_date');

    //Appointment Route section for treatment invoice start

    Route::get('appointments/getplansinformation', [AppointmentsController::class, 'getplansinformation'])->name('appointments.getplansinformation');

    Route::get('appointments/getpackageprice', [AppointmentsController::class, 'getpackageprice'])->name('appointments.getpackageprice');

    Route::get('appointments/getinvoicecalculation', [AppointmentsController::class, 'getinvoicecalculation'])->name('appointments.getinvoicecalculation');

    Route::get('appointments/getcalculatedPriceExclusicecheck', [AppointmentsController::class, 'getcalculatedPriceExclusicecheck'])->name('appointments.getcalculatedPriceExclusicecheck');

    Route::get('appointments/saveinvoice', [AppointmentsController::class, 'saveinvoice'])->name('appointments.saveinvoice');
    //Appointment Route section for treatment invoice end

    /*Appointment route section for consultancy invoice start*/

    Route::get('appointments/getconsultancycalculation', [ConsultancyInvoiceController::class, 'getconsultancycalculation'])->name('appointments.getconsultancycalculation');

    Route::get('appointments/getcustomcalculation', [ConsultancyInvoiceController::class, 'getcustomcalculation'])->name('appointments.getcustomcalculation');

    Route::get('appointments/checkedcustom', [ConsultancyInvoiceController::class, 'checkedcustom'])->name('appointments.checkedcustom');

    Route::get('appointments/getfinalcalculation', [ConsultancyInvoiceController::class, 'getfinalcalculation'])->name('appointments.getfinalcalculation');

    Route::get('appointments/saveconsultancyinvoice', [ConsultancyInvoiceController::class, 'saveinvoice'])->name('appointments.saveconsultancyinvoice');
    /*Appointment route section for consultancy invoice end*/

    Route::get('appointmentsimage/imageindex/{id}', [AppointmentimageController::class, 'index'])->name('appointmentsimage.imageindex');

    Route::post('appointmentsimage/imagestore_before/{id}', [AppointmentimageController::class, 'imagestore_before'])->name('appointmentsimage.imagestore_before');

    Route::resource('appointmentsimage', AppointmentimageController::class);

    //Appointment route start for measurement

    Route::get('appointmentsmeasurement/measurementindex/{id}', [AppointmentMeasurementController::class, 'index'])->name('appointmentsmeasurement.measurements');

    Route::get('appointmentsmeasurement/measurementcreate/{id}', [AppointmentMeasurementController::class, 'create'])->name('appointmentsmeasurement.create');

    Route::get('appointmentsmeasurement/fill_form/{id}/{appointment_id}', [AppointmentMeasurementController::class, 'fill_form'])->name('appointmentmeasurement.fill_form');

    Route::post('appointmentsmeasurement/{form_id}/{appointment_id}/submit_form', [AppointmentMeasurementController::class, 'submit_form'])->name('appointmentmeasurement.submit_form');

    Route::get('appointmentsmeasurement/edit/{id}', [AppointmentMeasurementController::class, 'edit'])->name('appointmentmeasurement.edit');

    Route::post('appointmentsmeasurement/{custom_form_id}', [AppointmentMeasurementController::class, 'update_measurement_field'])->name('appointmentmeasurement.update');

    Route::get('appointmentsmeasurement/previewform/{id}', [AppointmentMeasurementController::class, 'filled_preview'])->name('appointmentmeasurement.previewform');

    Route::get('appointmentsmeasurement/{id}/print', [AppointmentMeasurementController::class, 'filledPrint'])->name('appointment_measurement_custom_form_feedbacks.filled_print');

    Route::get('appointmentsmeasurement/{id}/export_pdf', [AppointmentMeasurementController::class, 'exportPdf'])->name('appointment_measurement_custom_form_feedbacks.export_pdf');
    //Appointment route end for measurement

    /*Appointment Route start for medical history form*/

    Route::get('appointmentsmedical/medicalindex/{id}', [AppointmentMedicalController::class, 'index'])->name('appointmentsmedical.medicals');

    Route::get('appointmentsmedical/edit/{id}', [AppointmentMedicalController::class, 'edit'])->name('appointmentsmedical.edit');

    Route::post('appointmentsmedical/{custom_form_id}', [AppointmentMedicalController::class, 'update_medical_field'])->name('appointmentsmedical.update');

    Route::get('appointmentsmedical/previewform/{id}', [AppointmentMedicalController::class, 'filled_preview'])->name('appointmentsmedical.previewform');

    Route::get('appointmentsmedical/fill_form/{id}/{appointment_id}', [AppointmentMedicalController::class, 'fill_form'])->name('appointmentsmedical.fill_form');

    Route::get('appointmentsmedical/{id}/print', [AppointmentMedicalController::class, 'filledPrint'])->name('appointmentsmedical.custom_form_feedbacks.filled_print');

    Route::get('appointmentsmedical/{id}/export_pdf', [AppointmentMedicalController::class, 'exportPdf'])->name('appointmentsmedical.custom_form_feedbacks.export_pdf');
    /*Appointment Route end for medical history form*/

    Route::get('dashboard/getdoctors', [DashboardReportsController::class, 'GetCentreDoctors'])->name('getdoctors');

    // Appointment Comments
    Route::get('appointments/comment-save', [AppointmentsController::class, 'AppointmentStoreComment'])->name('appointments.storecomment');
    //Appointment Route end for images

    /*Consultancy Routes*/
    Route::post('appointments/load-locations', [AppointmentsController::class, 'loadLocationsByCity'])->name('appointments.load_locations');
    Route::post('appointments/load_child_services', [AppointmentsController::class, 'LoadChildServices'])->name('appointments.load_child_services');
    Route::get('appointments/load-non-scheduled-appointments', [AppointmentsController::class, 'getNonScheduledAppointments'])->name('appointments.load_nonscheduled_appointments');
    Route::post('appointments/check-phone-exist', [AppointmentsController::class, 'checkPhoneExist'])->name('appointments.check_phone_exist');
    Route::get('appointments/export/{limit}/{offset}', [AppointmentsController::class, 'export'])->name('appointments.export');
    Route::get('download-today-consultancies', [AppointmentsController::class, 'todayexport']);
    Route::get('download-today-treatments', [AppointmentsController::class, 'todaytreatments']);
    Route::get('appointments/view/log/{id}/{type}', [AppointmentsController::class, 'logPage'])->name('appointments.loadPage');
    Route::post('download-filter-data', [AppointmentsController::class, 'downloadExportdata']);
    /*Inventory Routes*/

    Route::get('brands', [BrandsController::class, 'index'])->name('brands.index');

    Route::get('products', [ProductsController::class, 'index'])->name('products.index');

    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');

    Route::get('stocks', [OrdersController::class, 'refund'])->name('refund.index');

    Route::get('order/refunds', [OrdersController::class, 'refund'])->name('order.refunds.index');

    Route::get('products/stock/{id}', [ProductsController::class, 'productStock'])->name('products.stock');
    Route::get('reports/revenue_reports', [FinanceReportController::class, 'report'])->name('reports.finance_reports')->middleware('permission:finance_general_revenue_reports_manage');
    Route::get('reports/load_revenue_reports', [FinanceReportController::class, 'revenue_reports'])->name('reports.revenue_reports')->middleware('permission:finance_general_revenue_reports_manage');
    Route::get('reports/arrived_not_converted', [FinanceReportController::class, 'ArrivedNotConverted'])->name('reports.arrived_not_converted')->middleware('permission:non_converted_customers_manage');
    Route::post('reports/account_sales_report_load', [FinanceReportController::class, 'reportLoad'])->name('reports.account_sales_report_load');
    Route::post('reports/account_revenue_report_load', [FinanceReportController::class, 'revenueReportLoad'])->name('reports.account_revenue_report_load');
    Route::post('appointmentreports/appointments-general-load', [ReportAppointmentsController::class, 'reportLoad'])->name('reports.appointments_general_load');

    //Route start for Operations reports
    Route::get('operation_reports/loaddayarray', [OperationsReportController::class, 'loaddayarray'])->name('reports.operations_report_loadday');
    Route::get('operation_reports/operations-report', [OperationsReportController::class, 'report'])->name('reports.operations_report')->middleware('permission:operations_reports_manage');
    Route::post('operation_reports/operations-report-load', [OperationsReportController::class, 'reportLoad'])->name('reports.operations_report_load');
    Route::post('operation_reports/converted-report-load', [OperationsReportController::class, 'reportLoadConverted'])->name('reports.converted_report_load');
    Route::get('reports/dailyarrival', [FinanceReportController::class, 'Dailyarrival'])->name('reports.dailyarrival');
    Route::post('reports/load_dailyarrival_report', [FinanceReportController::class, 'LoadDailyArrival'])->name('reports.load_dailyarrival_report');
    Route::get('reports/conversion', [ConversionReportController::class, 'index'])->name('reports.conversion')->middleware('permission:conversion_report_manage');
    Route::post('reports/load_conversion_report', [ConversionReportController::class, 'LoadConversionReport'])->name('reports.load_conversion_report');
    Route::get('reports/staff_wise_arrival', [FinanceReportController::class, 'staffWiseArrival'])->name('reports.staff_wise_arrival')->middleware('permission:staff_wise_arrival_manage');
    Route::post('reports/staff_wise_arrival_report', [FinanceReportController::class, 'staffWiseArrivalReport'])->name('reports.staff_wise_arrival_report');
    //Route end for Operations reports

    /////////////////Dashboard Stats//////

    Route::get('dashboard/collection-by-centre', [DashboardReportsController::class, 'collectionByCentre'])->name('dashboard.collection_by_centre');
    Route::get('dashboard/my-collection-by-centre', [DashboardReportsController::class, 'myCollectionByCentre'])->name('dashboard.myCollectionByCentre');
    Route::get('dashboard/revenue-by-centre', [DashboardReportsController::class, 'revenueByCentre'])->name('dashboard.revenueByCentre');
    Route::get('dashboard/revenue-by-service-category', [DashboardReportsController::class, 'RevenueByServiceCategory'])->name('dashboard.revenueByServiceCategory');
    Route::get('dashboard/collection-by-service-category', [DashboardReportsController::class, 'CollectionByServiceCategory'])->name('dashboard.CollectionByServiceCategory');
    Route::get('dashboard/my-revenue-by-centre', [DashboardReportsController::class, 'myRevenueByCentre'])->name('dashboard.myRevenueByCentre');
    Route::get('dashboard/revenue-by-service', [DashboardReportsController::class, 'revenueByService'])->name('dashboard.revenueByService');
    Route::get('dashboard/my-revenue-by-service', [DashboardReportsController::class, 'myRevenueByService'])->name('dashboard.myRevenueByService');
    Route::get('dashboard/appointment-by-status', [DashboardReportsController::class, 'AppointmentByStatus'])->name('dashboard.appointment_by_status');
    Route::get('dashboard/appointment-by-type', [DashboardReportsController::class, 'AppointmentByType'])->name('dashboard.appointment_by_type');
    // Dashboard CENTRE WISE ARRIVAL
    Route::get('dashboard/centre_wise_arrival', [DashboardReportsController::class, 'CentreWiseArrival'])->name('dashboard.centre_wise_arrival');

    Route::get('dashboard/location_wise_arrival', [DashboardReportsController::class, 'LocationWiseArrival'])->name('dashboard.location_wise_arrival');
    Route::get('dashboard/user_wise_arrival', [DashboardReportsController::class, 'UserWiseArrival'])->name('dashboard.user_wise_arrival');
    // Dashboard CSR WISE ARRIVAL
    Route::get('dashboard/csr_wise_arrival', [DashboardReportsController::class, 'CSRWiseArrival'])->name('dashboard.csr_wise_arrival');
    Route::get('dashboard/patient-follow-up', [PatientFollowupController::class, 'patientFollowUp'])->name('dashboard.patient_follow_up');
    Route::get('dashboard/patient-follow-up-one-month', [PatientFollowupController::class, 'patientFollowUpOneMonth'])->name('dashboard.patient_follow_up_one_month');
    Route::get('dashboard/revenue-by-centre/{period}/{medium_type}/{performance?}', [DashboardReportsController::class, 'getRevenueByCenterReport'])->name('dashboardreport.revenue_by_centre');
    Route::get('getcolor', [ServicesController::class, 'GetColor'])->name('dashboard.getcolor');
    Route::get('dashboard/getchild', [DashboardReportsController::class, 'getChild'])->name('dashboard.getchild');
    Route::get('dashboard/agent_wise_arrival', [DashboardReportsController::class, 'AgentWiseArrival'])->name('dashboard.agent_wise_arrival');

        Route::get('dashboard/csr_user_wise_arrival', [DashboardReportsController::class, 'CsrUserWiseArrival'])->name('dashboard.csr_user_wise_arrival');
        Route::get('dashboard/doctore_user_wise_conversion', [DashboardReportsController::class, 'DoctoreWiseConversion'])->name('dashboard.doctor_wise_conversion');
        Route::get('dashboard/all_doctor_user_wise_conversion', [DashboardReportsController::class, 'AllDoctorsWiseConversion'])->name('dashboard.all_doctor_wise_conversion');
        Route::get('dashboard/patient-follow-up', [PatientFollowupController::class, 'patientFollowUp'])->name('dashboard.patient_follow_up');
        Route::get('dashboard/follow-up-report', [DashboardReportsController::class, 'FollowUpReport'])->name('reports.follow_up')->middleware('permission:follow_up_manage');
        Route::get('dashboard/follow-up-report-monthly', [DashboardReportsController::class, 'FollowUpReportMonthly'])->name('reports.follow_up_month');
        Route::post('dashboard/patient_follow_up_report', [DashboardReportsController::class, 'loadFollowUpReport'])->name('reports.patient_follow_up_report');
        Route::post('dashboard/patient_follow_up_report_monthly', [all_doctor_wise_conversion::class, 'LoadPatientFollowUpReportMonthly'])->name('reports.patient_follow_up_report_monthly');
        Route::get('dashboard/patient-follow-up-one-month', [PatientFollowupController::class, 'patientFollowUpOneMonth'])->name('dashboard.patient_follow_up_one_month');
        Route::get('dashboard/patient-follow-up/download', [PatientFollowupController::class, 'patientFollowUpDownload'])->name('follow_up.download');
        Route::get('dashboard/patient-monthly-follow-up/download', [PatientFollowupController::class, 'patientMonthlyFollowUpDownload'])->name('monthly_follow_up.download');
    
    });
