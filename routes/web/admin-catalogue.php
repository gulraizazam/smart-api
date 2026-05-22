<?php

// Services, Resources, Machine Types, Logs, SMS Templates, Doctors, Refunds, Feedbacks, Discounts, Vouchers, Bundles, Centre Targets, Business Closures, Package Advances, Invoices, Packages/Plans, Custom Forms, Leads, Memberships, Patients, Medical/Measurement, Finances, Invoice/Refund Patient

use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\BundlesController as AdminBundlesController;
use App\Http\Controllers\Admin\ServiceBundlesController as AdminServiceBundlesController;
use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Patients\PackagesController as PatientPackageController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Admin\ResourceRotasController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\UserVouchersController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MembershipsController as ApiMembershipsController;
use App\Http\Controllers\Api\RefundsController as ApiRefundsController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

        Route::get('lead_statuses', [LeadStatusesController::class, 'index'])->name('lead_statuses.index');
        Route::get('lead_statuses/sort', [LeadStatusesController::class, 'sortOrder'])->name('lead_statuses.sort');

        // Services
        Route::resource('services', ServicesController::class)->only(['index', 'show'])->middleware('permission:services.list.view');

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
        Route::resource('discounts', DiscountsController::class)->only('index')->middleware('permission:discounts.list.view');
        Route::resource('voucherTypes', VouchersController::class)->only('index')->middleware('permission:voucher_types.list.view');
         Route::resource('vouchers', UserVouchersController::class)->only('index')->middleware('permission:discounts_manage');
        Route::get('vouchersTypes/getListing', [VouchersController::class, 'getListing'])->name('vouchersTypes.getListing')->middleware('permission:voucher_types.list.view');

        //User Vouchers route Start
        Route::resource('user-vouchers', UserVouchersController::class)->only(['index', 'show'])->middleware('permission:vouchers.list.view');
        Route::post('user-vouchers/datatable', [UserVouchersController::class, 'datatable'])->name('user-vouchers.datatable')->middleware('permission:vouchers.list.view');
        //User Vouchers route end
        //Discount route end

        //Packages route Start
        Route::get('bundles', [AdminBundlesController::class, 'index'])->name('bundles.index')->middleware('permission:packages.list.view');
        Route::get('bundles/sort', [AdminBundlesController::class, 'sort'])->name('bundles.sort')->middleware('permission:packages.sort');
        //Packages route end

        // Service Bundles (same service × N sessions) — uses the Bundles
        // module catalog (bundles.*), introduced in 2026_05_29.
        Route::get('service-bundles', [AdminServiceBundlesController::class, 'index'])->name('service-bundles.index')->middleware('permission:bundles.list.view');
        Route::get('service-bundles/sort', [AdminServiceBundlesController::class, 'sort'])->name('service-bundles.sort')->middleware('permission:bundles.sort');
        // Service Bundles route end

        //Centre Target
        Route::resource('centre_targets', CentreTargetsController::class)->only('index');

        //Google Reviews
        Route::get('google_reviews', [\App\Http\Controllers\Admin\GoogleReviewsController::class, 'index'])->name('google_reviews.index');

        //Package Advance route start
        Route::resource('packagesadvances', PackageAdvancesController::class)->only('index');

        //Resource Rota Management (Schedule Calendar)
        Route::get('resourcerotas/schedule', [ResourceRotasController::class, 'scheduleCalendar'])->name('resourcerotas.schedule')->middleware('permission:scheduling_shifts.list.view');
        Route::get('resourcerotas/repeating-shifts', [ResourceRotasController::class, 'repeatingShifts'])->name('resourcerotas.repeating-shifts')->middleware('permission:scheduling_shifts.list.view');

        //Business Closures Management
        Route::get('business-closures', [App\Http\Controllers\Admin\BusinessClosureController::class, 'index'])->name('business-closures.index')->middleware('permission:business_closures.list.view');

        //Invoice Management route start
        Route::get('invoices/log/{id}/{type}/{patient_id?}', [InvoicesController::class, 'invoicelog'])->name('invoices.invoice_log');
        Route::resource('invoices', InvoicesController::class)->only('index')->middleware('permission:invoices.list.view');
        //Invoice Management route end
        Route::get('invoices/pdf/{id}/{download?}/{flag?}', [InvoicesController::class, 'invoice_pdf'])->name('invoices.invoice_pdf');

        // Package route start
        Route::get('plans/log/{id}/{type}', [PackagesController::class, 'packagelog'])->name('packages.log');
        Route::get('view-package/{id}', [PackagesController::class, 'viewPackage'])->name('packages.view.package');

        Route::resource('packages', PackagesController::class)->only(['index', 'create'])->middleware('permission:plans.list.view');
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
        Route::post('memberships/upload', [ApiMembershipsController::class, 'uploadMemberships'])->name('memberships.upload');
        Route::get('memberships/export/pdf', [ApiMembershipsController::class, 'exportPdf'])->name('memberships.export.pdf');
        Route::get('memberships/export/excel', [ApiMembershipsController::class, 'exportDocs'])->name('membership.export.excel');
        // Patients - using API controller for CRUD operations, keeping view routes
        Route::get('patients', [PatientsController::class, 'index'])->name('patients.index')->middleware('permission:patients.list.view');
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

        Route::post('refundpatient/store', [ApiRefundsController::class, 'store'])->name('refundpatient.store');

        Route::get('refundpatient/detail/{id}', [RefundsController::class, 'detail'])->name('refundpatient.detail');

        Route::get('refundpatient/{id}', [RefundsController::class, 'index'])->name('refundpatient.index');

        Route::get('refundpatient/{id}/create', [RefundsController::class, 'create'])->name('refundpatient.create');
        /*Route end for patient refunds*/

        // Patient non-plans refunds routes removed — functionality not in use

