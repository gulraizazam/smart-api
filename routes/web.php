<?php

use App\Models\Leads;
use App\Models\Services;
use App\Models\Appointments;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\TownController;
use Facade\Ignition\Support\Packagist\Package;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\BundlesController as AdminBundlesController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\UserVouchersController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\UserTypesController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\PatientFollowupController;
use App\Http\Controllers\ConversionReportController;
use App\Http\Controllers\DashboardReportsController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Api\PermissionController;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\ResourceRotasController;

use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\TransferProductsController;
use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\ConsultancyInvoiceController;
use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Admin\Reports\FinanceReportController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\MembershipsController;
use App\Http\Controllers\Admin\MembershipTypesController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Reports\OperationsReportController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\PackagesController as PatientPackageController;
use App\Http\Controllers\Admin\Reports\AppointmentsController as ReportAppointmentsController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\Admin\Reports\ActivitylogsReportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FeedbacksReportController;
use App\Http\Controllers\InventoryReportsController;
use App\Http\Controllers\MembershipReportsController;
use App\Http\Controllers\UpsellingReportController;
use App\Http\Controllers\Admin\InvoiceGenerationController;
use App\Models\PackageService;

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

Route::get('/package-advances-sum', function () {
    $sum = PackageAdvances::where('cash_flow', 'in')
        ->where('payment_mode_id', 4)
        ->whereNull('deleted_at')
        ->whereBetween('created_at', ['2024-07-01 00:00:00', '2025-06-30 23:59:59'])
        ->sum('cash_amount');
    
    return response()->json(['sum' => $sum]);
});
Route::get('/services/export-pdf', [ServicesController::class, 'exportPdf'])->name('services.export.pdf');
Route::get('/download-student-membership-patients', [MembershipsController::class, 'downloadStudentMembershipPatients'])
    ->name('download.student.membership.patients');
Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');
Route::get('/download-doctor-upselling-excel', [UpsellingReportController::class, 'downloadDoctorUpsellingExcel'])->name('download.doctor.upselling');
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
Route::get('/check-memberships', function () {
    \Artisan::call('memberships:expire');
});
Route::get('/get_deleted', function () {
    $appointments = Appointments::onlyTrashed()->where('deleted_by', 4)->get();
    return view('deleted', get_defined_vars());
});
Route::get('getservices', function () {

    $services = Services::where('slug', '!=', 'all')
        ->where(['parent_id' => 0])

        ->orderBy('id', 'asc')
        ->get();

    $mergedServices = [];
    foreach ($services as $service) {

        $children = Services::where(['parent_id' => $service->id])

            ->orderBy('id', 'asc')->get()->toArray();

        $mergedServices[] = $service->toArray();
        foreach ($children as $child) {
            $mergedServices[] = $child;
        }
    }
    return view('deleted', compact('mergedServices'));
});
Route::get('testupdate', function () {
    $packageService = PackageService::find(198286);
    $packageService->sold_by = auth()->id(); // or any value
    $packageService->save();

\Log::info('Sold by updated to: ' . $packageService->sold_by);
});
Route::get('followup', [DashboardReportsController::class, 'FollowUp'])->name('dashboard.followup');

// Check Session
Route::get('check-session', [App\Http\Controllers\Auth\LoginController::class, 'checkSession'])->name('check_session');

// Password Reset Routes...
Route::get('password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('auth.password.reset');
Route::post('password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('auth.password.resetemail');
Route::get('password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('auth.password.resettoken');
Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
/*After authentication*/
Route::group(['middleware' => ['auth.common', 'checkAccount'], 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::middleware(['auth', 'check.ip.restriction'])->group(function () {

        Route::get('error-logs', [LogViewerController::class, 'index']);
        Route::get('updateleads', [LeadsController::class, 'leadupdate']);
        Route::get('updatestatusleads', [LeadsController::class, 'leadstatusupdate']);
        Route::prefix('invoices')->name('invoices.')->group(function () {
            // Calculate amounts and get JSON response
            Route::post('/calculate-amounts', [InvoiceGenerationController::class, 'calculateAmounts'])->name('calculate-amounts');
            
            // Export exempt invoices to Excel
            Route::post('/export-exempt', [InvoiceGenerationController::class, 'exportExemptInvoices'])->name('export-exempt');
            
            // Download all invoices as ZIP of PDFs
            Route::post('/download-invoices-zip', [InvoiceGenerationController::class, 'downloadInvoicesZip'])->name('download-invoices-zip');
        });
        Route::get('change_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'showChangePasswordForm'])->name('change_password');
        Route::post('update_password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'changePassword'])->name('update_password');
        Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
        Route::post('/home/datatable', [App\Http\Controllers\HomeController::class, 'datatable'])->name('home.datatable');
        // MIGRATED TO API: /api/dashboard/collection-by-centre
        // Route::get('/home/collection-by-centre', [App\Http\Controllers\HomeController::class, 'collectionByCentre'])->name('home.collectionByCentre');
        Route::get('/home/my-collection-by-centre', [App\Http\Controllers\HomeController::class, 'myCollectionByCentre'])->name('home.myCollectionByCentre');
        // MIGRATED TO API: /api/dashboard/revenue-by-service-category
        // Route::get('/home/revenue-by-service-category', [App\Http\Controllers\HomeController::class, 'RevenueByServiceCategory'])->name('home.RevenueByServiceCategory');
        // MIGRATED TO API: /api/dashboard/revenue-by-centre
        // Route::get('/home/revenue-by-centre', [App\Http\Controllers\HomeController::class, 'revenueByCentre'])->name('home.revenueByCentre');
        // MIGRATED TO API: /api/dashboard/collection-by-service-category
        // Route::get('/home/collection-by-service-category', [App\Http\Controllers\HomeController::class, 'CollectionByServiceCategory'])->name('home.CollectionByServiceCategory');
        Route::get('/home/my-revenue-by-centre', [App\Http\Controllers\HomeController::class, 'myRevenueByCentre'])->name('home.myRevenueByCentre');
        // MIGRATED TO API: /api/dashboard/revenue-by-service
        // Route::get('/home/revenue-by-service', [App\Http\Controllers\HomeController::class, 'revenueByService'])->name('home.revenueByService');
        Route::get('/home/my-revenue-by-service', [App\Http\Controllers\HomeController::class, 'myRevenueByService'])->name('home.myRevenueByService');
        // MIGRATED TO API: /api/dashboard/stats
        // Route::get('/home/getstats', [App\Http\Controllers\HomeController::class, 'getStats'])->name('home.getstats');


        //  ----------------- Dashboard and  Home Routes ----------------- //

        // MIGRATED TO API: /api/dashboard/activities
        // Route::prefix('home')->group(function () {
        //     Route::get('getactivity', [App\Http\Controllers\HomeController::class, 'getActivity'])->name('home.getactivity');
        // });

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

        // Permissions - using API controller for all routes
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('permission:permissions_manage');

        // Roles - using API controller for all routes
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:roles_manage');
        // Route::post('roles_mass_destroy', ['uses' => 'Admin\RolesController@massDestroy', 'as' => 'roles.mass_destroy']);

        // Application Users - using API controller for all routes
        Route::get('users', [ApplicationUserController::class, 'index'])->name('users.index')->middleware('permission:users_manage');

        // User Types - using API controller for all routes except index view
        Route::get('user_types', [UserTypesController::class, 'index'])->name('user_types.index')->middleware('permission:user_types_manage');
        // User Operator Settings
        Route::get('user_operator_settings', [UserOperatorSettingsController::class, 'index'])->name('user_operator_settings.index');

        // Towns Routes

        Route::get('towns/import', [TownController::class, 'importTowns'])->name('towns.import');

        Route::get('towns/upload', [TownController::class, 'uploadLeads'])->name('towns.upload');

        Route::resource('towns', TownController::class)->only('index');

        // Locations
        Route::get('locations/sort_get', [LocationsController::class, 'getSortOrder'])->name('locations.sort_get');
        Route::get('services/sort_get', [ServicesController::class, 'getSortOrder'])->name('services.sort_get');
        Route::resource('locations', LocationsController::class)->only('index');
        Route::get('locations/getservices', [LocationsController::class, 'getServices'])->name('locations.getservices');
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
        Route::resource('services', ServicesController::class)->only(['index', 'show'])->middleware('permission:services_manage');

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

        // Doctors - using API controller for index
        Route::get('doctors', [DoctorController::class, 'index'])->name('doctors.index');

        // Refund Route
        Route::resource('refunds', RefundsController::class)->only('index');
        Route::resource('feedbacks', FeedbackController::class)->only('index');
        Route::post('refunds/getplans', [PackagesController::class, 'getPlans'])->name('refunds.getplans');
        Route::post('feedbacks/gettreatments', [FeedbackController::class, 'getTreatment'])->name('feedbacks.gettreatments');
        Route::post('feedbacks/gettreatmentsinfo', [FeedbackController::class, 'getTreatmentInfo'])->name('feedbacks.gettreatmentsinfo');
        Route::get('refunds/edit/{id}', [PackagesController::class, 'editRefund'])->name('refunds.edit');
        Route::post('refunds/update', [PackagesController::class, 'updateRefund'])->name('refunds.update');
        //Refunds route end

        //Discount route Start
        Route::resource('discounts', DiscountsController::class)->only('index')->middleware('permission:discounts_manage');
        Route::resource('voucherTypes', VouchersController::class)->only('index')->middleware('permission:discounts_manage');
         Route::resource('vouchers', UserVouchersController::class)->only('index')->middleware('permission:discounts_manage');
        Route::get('vouchersTypes/getListing', [VouchersController::class, 'getListing'])->name('vouchersTypes.getListing')->middleware('permission:discounts_manage');

        //User Vouchers route Start
        Route::resource('user-vouchers', UserVouchersController::class)->only(['index', 'show'])->middleware('permission:vouchers_manage');
        Route::post('user-vouchers/datatable', [UserVouchersController::class, 'datatable'])->name('user-vouchers.datatable')->middleware('permission:vouchers_manage');
        //User Vouchers route end
        //Discount route end

        //Packages route Start
        Route::get('bundles', [AdminBundlesController::class, 'index'])->name('bundles.index')->middleware('permission:packages_manage');
        //Packages route end

        //Centre Target
        Route::resource('centre_targets', CentreTargetsController::class)->only('index');

        //Package Advance route start
        Route::resource('packagesadvances', PackageAdvancesController::class)->only('index');

        //Resource Rota Management (Schedule Calendar)
        Route::get('resourcerotas/schedule', [ResourceRotasController::class, 'scheduleCalendar'])->name('resourcerotas.schedule')->middleware('permission:resourcerotas_manage');
        Route::get('resourcerotas/repeating-shifts', [ResourceRotasController::class, 'repeatingShifts'])->name('resourcerotas.repeating-shifts')->middleware('permission:resourcerotas_manage');

        //Business Closures Management
        Route::get('business-closures', [App\Http\Controllers\Admin\BusinessClosureController::class, 'index'])->name('business-closures.index')->middleware('permission:business_closures_manage');

        //Invoice Management route start
        Route::get('invoices/log/{id}/{type}/{patient_id?}', [InvoicesController::class, 'invoicelog'])->name('invoices.invoice_log');
        Route::resource('invoices', InvoicesController::class)->only('index')->middleware('permission:invoices_manage');
        //Invoice Management route end
        Route::get('invoices/pdf/{id}/{download?}/{flag?}', [InvoicesController::class, 'invoice_pdf'])->name('invoices.invoice_pdf');

        // Package route start
        Route::get('plans/log/{id}/{type}', [PackagesController::class, 'packagelog'])->name('packages.log');
        Route::get('view-package/{id}', [PackagesController::class, 'viewPackage'])->name('packages.view.package');

        Route::resource('packages', PackagesController::class)->only('index')->middleware('permission:plans_manage');
        Route::post('packages/resetvoucherpacakgebundles', [PackagesController::class, 'resetvoucherpacakgebundles'])->name('packages.resetvoucherpacakgebundles');
        Route::get('packages/getserviceinfo_for_plan', [PackagesController::class, 'getserviceinfo_for_plan'])->name('packages.getserviceinfo_for_plan');
        Route::get('packages/getdiscountinfo_for_plan', [PackagesController::class, 'getdiscountinfo_for_plan'])->name('packages.getdiscountinfo_for_plan');
        Route::get('packages/getdiscountinfocustom_for_plan', [PackagesController::class, 'getdiscountinfocustom_for_plan'])->name('packages.getdiscountinfocustom_for_plan');
        // Package Route end

        // Non Plans Refunds routes removed — functionality not in use

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

        // Leads - View routes only (all API operations handled in api.php)
        Route::get('leads', [LeadsController::class, 'index'])->name('leads.index');
        Route::get('leads/junk', [LeadsController::class, 'junk'])->name('leads.junk');
        Route::get('leads/import', [LeadsController::class, 'importLeads'])->name('leads.import');
        
        // Memberships
        Route::post('memberships/upload', [MembershipsController::class, 'uploadMemberships'])->name('memberships.upload');
        Route::get('memberships/export/pdf', [MembershipsController::class, 'exportPdf'])->name('memberships.export.pdf');
        Route::get('memberships/export/excel', [MembershipsController::class, 'exportDocs'])->name('membership.export.excel');
        // Patients - using API controller for CRUD operations, keeping view routes
        Route::get('patients', [PatientsController::class, 'index'])->name('patients.index')->middleware('permission:patients_manage');
        Route::match(['get', 'post'], 'patients/{id}/preview', [PatientsController::class, 'preview'])->name('patients.preview');
        // New Patient Card V2 - Section-based navigation (no JS tab conflicts)
        Route::get('patients/{id}/card/{section?}', [PatientsController::class, 'cardV2'])->name('patients.card');
        Route::get('patients/{id}/last-appointment-location', [PatientsController::class, 'getLastAppointmentLocation'])->name('patients.getLastAppointmentLocation');
        Route::get('patients/{id}/leads', [PatientsController::class, 'leads'])->name('patients.leads');
        Route::post('patients/{id}/leads-datatable', [PatientsController::class, 'leadsDatatable'])->name('patients.leadsDatatable');
        Route::get('patients/{id}/appointments', [PatientsController::class, 'appointments'])->name('patients.appointments');
        Route::get('patients/{id}/image', [PatientsController::class, 'imageindex'])->name('patients.imageurl');
        Route::get('patients/{id}/document', [PatientsController::class, 'documentindex'])->name('patients.document');
        Route::get('patients/edit/{id}', [PatientsController::class, 'documentedit'])->name('patients.documentedit');

        Route::get('plans/log/{id}/{patient_id}/{type}', [PatientPackageController::class, 'planlog'])->name('plans.log');

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

        // Patient non-plans refunds routes removed — functionality not in use

        /*Appointment routes*/

        Route::get('appointments/doctors', [AppointmentsController::class, 'loadDoctors'])->name('appointments.doctors');

        Route::put('appointments/save_doctor', [AppointmentsController::class, 'saveDoctor'])->name('appointments.save_doctor');

        Route::post('appointments/status', [AppointmentsController::class, 'status'])->name('appointments.status');

        /*Route::resource('appointments', AppointmentsController::class)->only('index');*/
        Route::resource('consultancy', AppointmentsController::class)->only('index')->middleware('permission:appointments_manage');
        Route::get('treatment', [AppointmentsController::class, 'treatment'])->name('treatment.index')->middleware('permission:treatments_manage');

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
        // Migrated to API route - using API controller with Service layer
        // Route::post('appointments/check-and-save-appointment', [AppointmentsController::class, 'checkAndSaveAppointments'])->name('appointments.check_and_save_appointment');
        Route::get('appointments/export/{limit}/{offset}', [AppointmentsController::class, 'export'])->name('appointments.export');
        Route::get('download-today-consultancies', [AppointmentsController::class, 'todayexport']);
        Route::get('download-today-treatments', [AppointmentsController::class, 'todaytreatments']);
        Route::get('appointments/view/log/{id}/{type}', [AppointmentsController::class, 'logPage'])->name('appointments.loadPage');
        Route::post('download-filter-data', [AppointmentsController::class, 'downloadExportdata']);
        Route::get('appointments/get-whatsapp-data', [AppointmentsController::class, 'getWhatsAppData'])->name('appointments.get_whatsapp_data');
        /*Inventory Routes*/
        Route::get('warehouse', [WarehouseController::class, 'index'])->name('warehouse.index');

        Route::get('brands', [BrandsController::class, 'index'])->name('brands.index');

        Route::get('products', [ProductsController::class, 'index'])->name('products.index');
        Route::get('products/logs/{id}', [ProductsController::class, 'logs'])->name('products.logs');

        Route::get('transfer-product', [TransferProductsController::class, 'index'])->name('transfer_product.index');

        Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');

        Route::get('stocks', [OrdersController::class, 'refund'])->name('refund.index');

        Route::get('order/refunds', [OrdersController::class, 'refund'])->name('order.refunds.index');

        Route::get('products/stock/{id}', [ProductsController::class, 'productStock'])->name('products.stock');
        Route::get('products/inventory/{id}', [ProductsController::class, 'productInventory'])->name('products.inventory');
        Route::get('products/search', [ProductsController::class, 'searchProducts'])->name('products.search');
        Route::get('inventory/edit/{id}', [ProductsController::class, 'editInventory'])->name('inventory.edit');
        //Route::get('reports/inventory_reports', [InventoryReportController::class, 'report'])->name('reports.inventory_report');

        Route::get('reports/revenue_reports', [FinanceReportController::class, 'report'])->name('reports.finance_reports')->middleware('permission:finance_general_revenue_reports_manage');
        Route::get('reports/tax_calculation_report', [FinanceReportController::class, 'taxCalculationReport'])->name('reports.tax_calculation_report');
        Route::post('reports/tax_calculation_report_load', [FinanceReportController::class, 'taxCalculationReportLoad'])->name('reports.tax_calculation_report_load');
        Route::get('service-report/{service_id}', [FinanceReportController::class, 'serviceBarChart'])->name('service.barchart');
        Route::get('reports/load_revenue_reports', [FinanceReportController::class, 'revenue_reports'])->name('reports.revenue_reports')->middleware('permission:finance_general_revenue_reports_manage');
        Route::get('reports/arrived_not_converted', [FinanceReportController::class, 'ArrivedNotConverted'])->name('reports.arrived_not_converted')->middleware('permission:non_converted_customers_manage');
        Route::post('reports/account_sales_report_load', [FinanceReportController::class, 'reportLoad'])->name('reports.account_sales_report_load');
        Route::post('reports/account_revenue_report_load', [FinanceReportController::class, 'revenueReportLoad'])->name('reports.account_revenue_report_load');
        Route::post('appointmentreports/appointments-general-load', [ReportAppointmentsController::class, 'reportLoad'])->name('reports.appointments_general_load');

        //Route start for Operations reports
        Route::get('operation_reports/loaddayarray', [OperationsReportController::class, 'loaddayarray'])->name('reports.operations_report_loadday');
        Route::get('inventory_reports', [InventoryReportsController::class, 'inventoryReport'])->name('reports.inventory_report');
        Route::get('feedbacks_reports', [FeedbacksReportController::class, 'feedbackReport'])->name('reports.feedback_report');
        Route::get('future_treatments', [FeedbacksReportController::class, 'futureTreatmentsReport'])->name('reports.future_treatments');
        Route::get('upselling', [UpsellingReportController::class, 'index'])->name('reports.upselling');
        Route::get('consultant_revenue', [UpsellingReportController::class, 'consultantRevenueReport'])->name('reports.consultant_revenue');
        Route::get('doctor_revenue', [UpsellingReportController::class, 'doctorRevenueReport'])->name('reports.doctor_revenue');
        Route::post('load_feedback_reports', [FeedbacksReportController::class, 'loadFeedbackReport'])->name('reports.load_feedback_report');
         Route::post('load_upselling_reports', [UpsellingReportController::class, 'loadUpsellingReport'])->name('reports.load_upselling_report');
         Route::post('load_consultant_revenue_reports', [UpsellingReportController::class, 'loadConsultantRevenueReport'])->name('reports.load_consultant_revenue_report');
         Route::post('load_doctor_revenue_reports', [UpsellingReportController::class, 'loadDoctorRevenueReport'])->name('reports.load_doctor_revenue_report');
         Route::get('/doctor-upselling-detail/{doctorId}', [UpsellingReportController::class, 'doctorUpsellingDetail'])
             ->name('doctor.upselling.detail');
             Route::get('/consultant-revenue-detail/{consultantId}', [UpsellingReportController::class, 'consultantRevenueDetail'])
             ->name('consultant.revenue.detail');
             Route::get('/doctor-revenue-detail/{doctorId}', [UpsellingReportController::class, 'doctorRevenueDetail'])
             ->name('admin.doctor.revenue.detail');
             Route::get('/admin/consultant/revenue/report', [UpsellingReportController::class, 'loadConsultantRevenueReport'])->name('consultant.revenue.report');
        Route::post('load_future_treatments_report', [FeedbacksReportController::class, 'loadFutureTreatmentsReport'])->name('reports.load_future_treatments_report')->middleware('permission:followuppatient_manage');
        Route::post('load_inventory_reports', [InventoryReportsController::class, 'loadInventoryReport'])->name('reports.load_inventory_report');
        Route::get('operation_reports/operations-report', [OperationsReportController::class, 'report'])->name('reports.operations_report')->middleware('permission:operations_reports_manage');
        Route::get('membership_reports', [MembershipReportsController::class, 'index'])->name('reports.membership-reports');
        Route::post('operation_reports/operations-report-load', [OperationsReportController::class, 'reportLoad'])->name('reports.operations_report_load');
        Route::post('operation_reports/converted-report-load', [OperationsReportController::class, 'reportLoadConverted'])->name('reports.converted_report_load');
        Route::get('reports/dailyarrival', [FinanceReportController::class, 'Dailyarrival'])->name('reports.dailyarrival');
        Route::post('reports/load_dailyarrival_report', [FinanceReportController::class, 'LoadDailyArrival'])->name('reports.load_dailyarrival_report');
        Route::post('reports/load_membership_report', [MembershipReportsController::class, 'loadMembershipReport'])->name('reports.load_membership_report');
        Route::get('memberships/export', [MembershipReportsController::class, 'Export'])->name('memberships.export.excel');
        Route::get('/admin/doctor/consultant/breakdown/{sellerId}', [UpsellingReportController::class, 'doctorConsultantBreakdown'])->name('doctor.consultant.breakdown');
        Route::get('/admin/consultant/seller/detail/{consultantId}/{sellerId}', [UpsellingReportController::class, 'doctorConsultantBreakdown'])->name('consultant.seller.detail');
        Route::get('reports/conversion', [ConversionReportController::class, 'index'])->name('reports.conversion')->middleware('permission:conversion_report_manage');
        Route::get('reports/activity_logs', [ActivitylogsReportController::class, 'index'])->name('reports.activity_logs');
        Route::post('reports/activity_logs', [ActivitylogsReportController::class, 'fetchActivityReport'])->name('reports.load_activity_report');
        Route::post('reports/load_conversion_report', [ConversionReportController::class, 'LoadConversionReport'])->name('reports.load_conversion_report');
        Route::get('reports/staff_wise_arrival', [FinanceReportController::class, 'staffWiseArrival'])->name('reports.staff_wise_arrival')->middleware('permission:staff_wise_arrival_manage');
        Route::post('reports/staff_wise_arrival_report', [FinanceReportController::class, 'staffWiseArrivalReport'])->name('reports.staff_wise_arrival_report');
         Route::get('reports/doctor_wise_conversion', [FinanceReportController::class, 'doctorWiseConversion'])->name('reports.doctorWiseConversion')->middleware('permission:staff_wise_arrival_manage');

         Route::post('reports/incentive_report', [FinanceReportController::class, 'loadIncentiveReport'])->name('reports.incentive_report');
         Route::get('reports/appointments', [FinanceReportController::class, 'appointmentsReport'])->name('reports.appointmentsReport');
         Route::post('reports/appointments_report', [FinanceReportController::class, 'loadAppointmentsReport'])->name('reports.appointments_report');
         
         // CSR Dashboard - Consultations scheduled in next 5 days
         Route::get('reports/csr-dashboard', [FinanceReportController::class, 'csrDashboard'])->name('reports.csr_dashboard')->middleware('permission:csr_dashboard_report');

         //////Dashboard Stats - Now handled by API routes in api.php //////
        // Kept routes that are still needed (not migrated to API or used elsewhere)
        Route::get('dashboard/my-collection-by-centre', [DashboardReportsController::class, 'myCollectionByCentre'])->name('dashboard.myCollectionByCentre');
        Route::get('dashboard/my-revenue-by-centre', [DashboardReportsController::class, 'myRevenueByCentre'])->name('dashboard.myRevenueByCentre');
        Route::get('dashboard/my-revenue-by-service', [DashboardReportsController::class, 'myRevenueByService'])->name('dashboard.myRevenueByService');
        Route::get('dashboard/location_wise_arrival', [DashboardReportsController::class, 'LocationWiseArrival'])->name('dashboard.location_wise_arrival');
        Route::get('dashboard/user_wise_arrival', [DashboardReportsController::class, 'UserWiseArrival'])->name('dashboard.user_wise_arrival');
        // MIGRATED TO API: /api/dashboard/unattended-payments
        // Route::get('dashboard/patient-follow-up', [PatientFollowupController::class, 'patientFollowUp'])->name('dashboard.patient_follow_up');
        // MIGRATED TO API: /api/dashboard/overdue-treatments
        // Route::get('dashboard/patient-follow-up-one-month', [PatientFollowupController::class, 'patientFollowUpOneMonth'])->name('dashboard.patient_follow_up_one_month');
        Route::get('dashboard/revenue-by-centre/{period}/{medium_type}/{performance?}', [DashboardReportsController::class, 'getRevenueByCenterReport'])->name('dashboardreport.revenue_by_centre');
        Route::get('getcolor', [ServicesController::class, 'GetColor'])->name('dashboard.getcolor');
        Route::get('dashboard/getchild', [DashboardReportsController::class, 'getChild'])->name('dashboard.getchild');
        Route::get('dashboard/agent_wise_arrival', [DashboardReportsController::class, 'AgentWiseArrival'])->name('dashboard.agent_wise_arrival');
        Route::get('dashboard/csr_user_wise_arrival', [DashboardReportsController::class, 'CsrUserWiseArrival'])->name('dashboard.csr_user_wise_arrival');
        // MIGRATED TO API: /api/dashboard/doctor-upselling-data
        // Route::get('dashboard/doctor/upselling/data', [UpsellingReportController::class, 'getDoctorUpsellingData'])->name('dashboard.doctor.upselling.data');
        Route::get('dashboard/doctore_wise_upselling', [DashboardReportsController::class, 'DoctoreWiseUpselling'])->name('dashboard.doctor_wise_upselling');
        Route::get('dashboard/feedback/view/{id}', [DashboardReportsController::class, 'ViewFeedback'])->name('feedback.view');
        Route::get('dashboard/all_doctor_user_wise_conversion', [DashboardReportsController::class, 'AllDoctorsWiseConversion'])->name('dashboard.all_doctor_wise_conversion');
        Route::get('dashboard/follow-up-report', [DashboardReportsController::class, 'FollowUpReport'])->name('reports.follow_up')->middleware('permission:follow_up_manage');
        Route::get('dashboard/follow-up-report-monthly', [DashboardReportsController::class, 'FollowUpReportMonthly'])->name('reports.follow_up_month');
        Route::post('dashboard/patient_follow_up_report', [DashboardReportsController::class, 'loadFollowUpReport'])->name('reports.patient_follow_up_report');
        Route::get('dashboard/patient-follow-up/download', [PatientFollowupController::class, 'patientFollowUpDownload'])->name('follow_up.download');
        Route::get('dashboard/patient-monthly-follow-up/download', [PatientFollowupController::class, 'patientMonthlyFollowUpDownload'])->name('monthly_follow_up.download');
        /////Activity Logs Script
        Route::get('activitylogs', [ActivitylogsReportController::class, 'InsertLogs']);

        ///////////Memberships routes/////
        Route::resource('membershiptypes', MembershipTypesController::class)->only('index');
        Route::resource('memberships', MembershipsController::class)->only('index');
        
        // Wrong Conversions Report
        Route::get('wrong-conversions', [\App\Http\Controllers\Admin\WrongConversionsController::class, 'index'])->name('wrong-conversions.index');
        Route::post('wrong-conversions/reset/{id}', [\App\Http\Controllers\Admin\WrongConversionsController::class, 'reset'])->name('wrong-conversions.reset');
        Route::post('wrong-conversions/reset-all', [\App\Http\Controllers\Admin\WrongConversionsController::class, 'resetAll'])->name('wrong-conversions.reset-all');

        // Cash Flow Module (view-only routes)
        Route::prefix('cashflow')->name('cashflow.')->middleware('permission:cashflow_manage')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CashFlowController::class, 'dashboard'])->name('dashboard');
            Route::get('expenses', [\App\Http\Controllers\Admin\CashFlowController::class, 'expenses'])->name('expenses');
            Route::get('transfers', [\App\Http\Controllers\Admin\CashFlowController::class, 'transfers'])->name('transfers');
            Route::get('vendors', [\App\Http\Controllers\Admin\CashFlowController::class, 'vendors'])->name('vendors');
            Route::get('staff', [\App\Http\Controllers\Admin\CashFlowController::class, 'staff'])->name('staff');
            Route::get('reports', [\App\Http\Controllers\Admin\CashFlowController::class, 'reports'])->name('reports');
            Route::get('settings', [\App\Http\Controllers\Admin\CashFlowController::class, 'settings'])->name('settings');
            Route::get('fdm', [\App\Http\Controllers\Admin\CashFlowController::class, 'fdmView'])->name('fdm');
        });
    });
});
