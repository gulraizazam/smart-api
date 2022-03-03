<?php

use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\BundlesController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\DoctorsController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
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
use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Patients\PackagesController as PatientPackageController;

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
        Route::get('plans/log/{id}/{type}', [PackagesController::class, 'packagelog'])->name('packages.log');
        Route::resource('packages', PackagesController::class)->only('index');
        // Package Route end

       // Non Refunds Route start
        Route::get('nonplansrefunds/index', [RefundsController::class, 'nonplansindex'])->name('nonplansrefunds.index');
        //Non Refunds Route end

         // Custom User Form Feedbacks Routes
        Route::get('custom_form_feedbacks/{id}/print', [CustomFormFeedbacksController::class, 'filledPrint'])->name("custom_form_feedbacks.filled_print");
        Route::resource('custom_form_feedbacks', CustomFormFeedbacksController::class);
        Route::get('custom_form_feedbacks/{form_id}/preview_form', [CustomFormFeedbacksController::class, 'preview_form'])->name("custom_form_feedbacks.preview_form");

        Route::get('custom_form_feedbacks/{id}/preview', [CustomFormFeedbacksController::class, 'filled_preview'])->name("custom_form_feedbacks.filled_preview");

        /*Route start for patient Pre define Custome Forms*/
        Route::get('customformfeedbackspatient/editcustomform/{id}',[PatientCustomFormController::class, 'edit'])->name('customformfeedbackspatient.edit');
        Route::get('customformfeedbackspatient/previewcustomform/{id}',[PatientCustomFormController::class, 'filled_preview'])->name('customformfeedbackspatient.previewform');
        Route::get('customformfeedbackspatient/fill_form/{id}/{patient_id}',[PatientCustomFormController::class, 'fill_form'])->name('customformfeedbackspatient.fill_form');
        Route::get('customformfeedbackspatient/{id}',[PatientCustomFormController::class, 'index'])->name('customformfeedbackspatient.index');
        Route::get('customformfeedbackspatient/{id}/print', [CustomFormFeedbacksController::class, 'filledPrint'])->name("patient_custom_form_feedbacks.filled_print");
        Route::get('customformfeedbackspatient/{id}/export_pdf', [CustomFormFeedbacksController::class, 'exportPdf'])->name("patient_custom_form_feedbacks.export_pdf");
        Route::get('custom_form_feedbacks/{form_id}/fill_form', [CustomFormFeedbacksController::class, 'fill_form'])->name("custom_form_feedbacks.fill_form");
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


        Route::patch('leads/send_sms/{id}', [LeadsController::class, 'send_sms'])->name('leads.send_sms');

        Route::post('leads/status', [LeadsController::class, 'status'])->name('leads.status');


        Route::get('LeadCommentStore',[LeadsController::class, 'LeadStoreComment'])->name('leads.storecomment');

        Route::get('LeadEditDetail',[LeadsController::class, 'LeadEditDetailAjax'])->name('leads.LeadEditDetail');

        //Lead Import
        Route::get('leads/import', [LeadsController::class, 'importLeads'])->name('leads.import');

        Route::post('leads/upload', [LeadsController::class, 'uploadLeads'])->name('leads.upload');


        Route::resource('leads', LeadsController::class);

        Route::post('leads/comment_store', [LeadsController:: class, 'comment_store'])->name('leads.comment_store');
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

        Route::get('patients/{id}/preview', [PatientsController::class, 'preview'])->name("patients.preview");

        Route::get('plans/log/{id}/{patient_id}/{type}', [PatientPackageController::class, 'planlog'])->name('plans.log');

        Route::get('patients/{id}/leads', [PatientsController::class, 'leads'])->name("patients.leads");

        Route::post('patients/{id}/leads-datatable', [PatientsController::class, 'leadsDatatable'])->name('patients.leadsDatatable');

        Route::get('patients/{id}/appointments', [PatientsController::class, 'appointments'])->name("patients.appointments");

        Route::post('patients/{id}/appointments-datatable', [PatientsController::class, 'appointmentsDatatable'])->name('patients.appointmentsDatatable');

        Route::get('patients/{id}/image', [PatientsController::class, 'imageindex'])->name("patients.imageurl");


        Route::get('patients/{id}/document', [PatientsController::class, 'documentindex'])->name("patients.document");

        Route::get('patients/edit/{id}', [PatientsController::class, 'documentedit'])->name('patients.documentedit');

        Route::resource('patients', PatientsController::class);


        /*Route start for patient medical history Forms*/
        Route::get('medicalhistoryform/editcustomform/{id}', [MedicalHistoryController::class, 'edit'])->name('medicalhistoryform.edit');

        Route::get('medicalhistoryform/previewcustomform/{id}',[MedicalHistoryController::class, 'filled_preview'])->name('medicalhistoryform.previewform');

        Route::get('medicalhistoryform/{id}',[MedicalHistoryController::class, 'index'])->name('medicalhistoryform.index');


        /*Route start for patient measurement history Forms*/
        Route::get('measurementhistoryform/editcustomform/{id}',[MeasurementHistoryController::class, 'edit'])->name('measurementhistoryform.edit');

        Route::get('measurementhistoryform/previewcustomform/{id}',[MeasurementHistoryController::class, 'filled_preview'])->name('measurementhistoryform.previewform');

        Route::get('measurementhistoryform/{id}',[MeasurementHistoryController::class, 'index'])->name('measurementhistoryform.index');

        Route::post('measurementhistoryform/{custom_form_id}', [MeasurementHistoryController::class, 'update_measurement_field'])->name('measurementhistoryform.update');

        Route::get('appointmentsmeasurement/{id}/export_pdf', [AppointmentMeasurementController::class, 'exportPdf'])->name("appointment_measurement_custom_form_feedbacks.export_pdf");


        /*Route start for patient Package advances*/

        Route::get('finances/savepackagesadvances',[PackageAdvancesController::class, 'savepackagesadvances'])->name('finances.savepackagesadvances');

        Route::get('finances/getpackagesinfo',[PackageAdvancesController::class, 'getpackagesinfo'])->name('finances.getpackagesinfo');

        Route::get('finances/{id}',[PackageAdvancesController::class, 'index'])->name('finances.index');

        /*Route end for patient package advances */


    });
