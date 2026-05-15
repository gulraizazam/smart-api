<?php

// Admin Appointment routes, Patients, Custom Forms Patient, Medical/Measurement, Patient Documents, Appointment Images, Plans (optimised + legacy), Finances, Invoice/Refund Patient

use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\Appointments\AppointmentInvoiceController;
use App\Http\Controllers\Admin\Appointments\AppointmentLookupController;
use App\Http\Controllers\Admin\Appointments\AppointmentScheduleController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\UserVouchersController;
use App\Http\Controllers\Api\Patient\CustomFormFeedbackController as ApiCustomFormFeedbackController;
use App\Http\Controllers\Api\Patient\MeasurementHistoryController as ApiMeasurementHistoryController;
use App\Http\Controllers\Api\Patient\MedicalHistoryController as ApiMedicalHistoryController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PlansController as ApiPlansController;
use App\Http\Controllers\Api\RefundsController as ApiRefundsController;
use Illuminate\Support\Facades\Route;

/* Appointment routes */
Route::post('appointments/load-locations', [AppointmentLookupController::class, 'loadLocationsByCity'])->name('appointments.load_locations');
Route::post('appointments/load-doctors', [AppointmentLookupController::class, 'loadDoctorsByLocation'])->name('appointments.load_doctors');
Route::post('appointments/load-consultant-doctors', [AppointmentLookupController::class, 'loadConsultantDoctorsByLocation'])->name('appointments.load_consultant_doctors');
Route::post('appointments/update/schedule', [AppointmentScheduleController::class, 'updateSchedule'])->name('appointments.updateSchedule');
Route::get('appointments/schedule/get', [AppointmentScheduleController::class, 'getSchedule'])->name('appointments.get_schedule');
Route::get('appointments/getpackageprice', [AppointmentInvoiceController::class, 'getpackageprice'])->name('appointments.getpackageprice');
Route::resource('appointments', AppointmentsController::class);
Route::post('appointments/load/lead', [AppointmentLookupController::class, 'loadLeadData'])->name('appointments.load_lead');

// Patients API Routes (Optimized)
Route::prefix('patients')->name('patients.')->group(function () {
    Route::post('datatable', [PatientController::class, 'index'])->name('datatable');
    Route::get('search', [PatientController::class, 'search'])->name('search');
    // Direct patient creation removed — patients are created only as a
    // side-effect of booking a consultation/appointment/treatment.
    Route::post('status', [PatientController::class, 'status'])->name('status');
    Route::post('image', [PatientController::class, 'storeImage'])->name('storeimage');
    Route::post('assignmembership', [PatientController::class, 'assignMembership'])->name('assignmembership');
    Route::post('assignvoucher', [PatientController::class, 'assignVoucher'])->name('assignvoucher');
    Route::get('getPatient/{id}', [PatientController::class, 'getPatient'])->name('getPatient');
    Route::get('{id}', [PatientController::class, 'show'])->name('show');
    Route::get('{id}/edit', [PatientController::class, 'edit'])->name('edit');
    Route::put('{id}', [PatientController::class, 'update'])->name('update');
    Route::delete('{id}', [PatientController::class, 'destroy'])->name('destroy');
    Route::post('{id}/addreferral', [PatientController::class, 'addReferral'])->name('addreferral');
    // Optimized datatables for patient preview tabs (using match for GET/POST since KTDatatable uses POST by default)
    Route::match(['get', 'post'], '{id}/appointments-datatable', [PatientController::class, 'appointmentsDatatable'])->name('appointmentsDatatable');
    // Dedicated endpoints for consultations and treatments filtered by patient
    Route::match(['get', 'post'], '{id}/consultations-datatable', [PatientController::class, 'consultationsDatatable'])->name('consultationsDatatable');
    Route::match(['get', 'post'], '{id}/treatments-datatable', [PatientController::class, 'treatmentsDatatable'])->name('treatmentsDatatable');
    Route::match(['get', 'post'], '{id}/vouchers-datatable', [UserVouchersController::class, 'datatable'])->name('vouchersDatatable');
    // Optimized document upload
    Route::post('{id}/upload-document', [PatientController::class, 'uploadDocument'])->name('uploadDocument');
    Route::post('{id}/update-document/{documentId}', [PatientController::class, 'updateDocument'])->name('updateDocument');
    // Activity history
    Route::get('{id}/activity-history', [PatientController::class, 'getActivityHistory'])->name('activityHistory');
    // Voucher history
    Route::get('{id}/voucher-history/{userVoucherId}', [PatientController::class, 'getVoucherHistory'])->name('voucherHistory');
    // Tab counts
    Route::get('{id}/tab-counts', [PatientController::class, 'getTabCounts'])->name('tabCounts');
    // Last appointment location (mirrors web /admin/patients/{id}/last-appointment-location)
    Route::get('{id}/last-appointment-location', [PatientController::class, 'lastAppointmentLocation'])->name('lastAppointmentLocation');
    // Patient notes
    Route::get('{id}/notes', [PatientController::class, 'getNotes'])->name('notes');
    Route::post('{id}/notes', [PatientController::class, 'addNote'])->name('addNote');
    Route::put('{id}/notes/{noteId}', [PatientController::class, 'updateNote'])->name('updateNote');
    Route::delete('{id}/notes/{noteId}', [PatientController::class, 'deleteNote'])->name('deleteNote');
    Route::post('{id}/notes/{noteId}/toggle-pin', [PatientController::class, 'togglePinNote'])->name('togglePinNote');
});
Route::post('customformfeedbackspatient/datatable/&{id}', [PatientCustomFormController::class, 'datatable'])->name('customformfeedbackspatient.datatable');
Route::get('customformfeedbackspatient/addnewform/{id}', [PatientCustomFormController::class, 'AddNewForm'])->name('customformfeedbackspatient.addnew');
// Fill a custom form for a patient (mirrors web customformfeedbackspatient/fill_form)
Route::post('customformfeedbackspatient/fill', [ApiCustomFormFeedbackController::class, 'fill'])->name('customformfeedbackspatient.fill');

/* Route start for patient medical history Forms */
Route::post('medicalhistoryform/datatable/&{id}', [MedicalHistoryController::class, 'datatable'])->name('medicalhistoryform.datatable');
// Fill a medical history form (mirrors web appointmentsmedical submit_form)
Route::post('medicalhistoryform/{id}', [ApiMedicalHistoryController::class, 'fill'])->name('medicalhistoryform.fill');

/* Route start for patient measurement history Forms */
Route::post('measurementhistoryform/datatable/&{id}', [MeasurementHistoryController::class, 'datatable'])->name('measurementhistoryform.datatable');
// Fill a measurement history form (mirrors web appointmentsmeasurement submit_form)
Route::post('measurementhistoryform/{id}', [ApiMeasurementHistoryController::class, 'fill'])->name('measurementhistoryform.fill');

Route::post('patients/documentdatatable/{id}', [PatientsController::class, 'documentdatatable'])->name('patients.documentdatatable');

Route::get('patients/createdocument/{id}', [PatientsController::class, 'documentCreate'])->name('patients.createdocument');

Route::post('patients/storedocument', [PatientsController::class, 'documentstore'])->name('patients.storedocument');

Route::delete('patients/deletedocuments/{id}', [PatientsController::class, 'documentdelete'])->name('patients.documentsdestroy');

Route::post('patients/updatedocuments/{id}', [PatientsController::class, 'documentupdate'])->name('patients.updatedocuments');

// Appointment Route start for images
Route::post('appointmentsimage/datatable/{id}', [AppointmentimageController::class, 'datatable'])->name('appointmentsimage.datatable');

Route::post('appointmentsmeasurement/datatable/{id}', [AppointmentMeasurementController::class, 'datatable'])->name('appointmentsmeasurement.datatable');

/* Route start for patient package - NEW OPTIMIZED ROUTES */
Route::prefix('plans-optimized')->group(function () {
    // Patient-specific plans (patient card)
    Route::post('datatable/{patient_id}', [ApiPlansController::class, 'datatable'])->name('plans.optimized.datatable');
    Route::get('lookup-data/{patient_id}', [ApiPlansController::class, 'getLookupData'])->name('plans.optimized.lookup');
    Route::get('statistics/{patient_id}', [ApiPlansController::class, 'getStatistics'])->name('plans.optimized.statistics');

    // Global plans (admin packages page)
    Route::post('global/datatable', [ApiPlansController::class, 'globalDatatable'])->name('plans.optimized.global.datatable');
    Route::get('global/lookup-data', [ApiPlansController::class, 'getGlobalLookupData'])->name('plans.optimized.global.lookup');
});
/* Route end for patient package - NEW OPTIMIZED ROUTES */

/* Patient-scoped plan audit log (mirrors web /admin/plans/log/{id}/{patient_id}/{type}) */
Route::get('plans/{planId}/log/{patientId}/{type}', [ApiPlansController::class, 'planLog'])
    ->whereNumber(['planId', 'patientId'])
    ->where('type', '[A-Za-z_-]+')
    ->name('plans.log');

/* Route start for patient pakcage - OLD ROUTES (TO BE DEPRECATED) */
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
Route::post('plans/voucher/reserve', [PackagesController::class, 'reserveVoucherForPlan'])->name('plans.voucher.reserve');
Route::post('plans/voucher/refund', [PackagesController::class, 'refundVoucherForPlan'])->name('plans.voucher.refund');

Route::get('plans/getgrandtotal', [PackagesController::class, 'getgrandtotal'])->name('plans.getgrandtotal');

Route::post('plans/deletepackagesservice', [PackagesController::class, 'deletepackagesservice'])->name('plans.deletepackages_service');
// Atomic cascade delete — voucher refunds + per-row deletes inside
// one DB transaction. Used by the SPA when tearing down a
// configurable Buy/Get group so a mid-batch failure rolls back
// everything instead of leaving the operator with half-deleted state.
Route::post('plans/cascade-delete', [PackagesController::class, 'cascadeDeleteGroup'])->name('plans.cascade_delete');

Route::get('plans/updatepackages', [PackagesController::class, 'updatepackages'])->name('plans.updatepackages');

Route::get('plans/getgrandtotal_update', [PackagesController::class, 'getgrandtotal_update'])->name('plans.getgrandtotal_update');

Route::post('plans/status', [PackagesController::class, 'status'])->name('plans.status');

Route::delete('plans/destroy/{id}', [PackagesController::class, 'destroy'])->name('plans.destroy');

Route::get('plans/display/{id}', [PackagesController::class, 'display'])->name('plans.display');

Route::get('plans/{id}/print-data', [PackagesController::class, 'printData'])->name('plans.printData');

Route::get('plans/edit/{id}', [PackagesController::class, 'edit'])->name('plans.edit');

Route::get('plans/{id}', [PackagesController::class, 'index'])->name('plans.index');

Route::get('plans/{id}/createplan', [PackagesController::class, 'create'])->name('plans.createplan');

Route::get('plans/edit_cash/{id}/{package_id}', [PackagesController::class, 'editpackageadvancescashindex'])->name('plans.edit_cash');

/* Route end for patient package */

Route::post('finances/datatable/&{id}', [PackageAdvancesController::class, 'datatable'])->name('finances.datatable');

Route::get('finances/{id}/create', [PackageAdvancesController::class, 'create'])->name('finances.create');

Route::get('finances/getpackages', [PackageAdvancesController::class, 'getpackages'])->name('finances.getpackages');

Route::post('finances/savepackagesadvances', [PackageAdvancesController::class, 'savepackagesadvances'])
    ->middleware(['throttle:60,1', 'idempotent'])
    ->name('finances.savepackagesadvances');

Route::post('invoicepatient/datatable/&{id}', [InvoicesController::class, 'datatable'])->name('invoicepatient.datatable');

Route::get('invoicepatient/displayInvoice/{id}', [InvoicesController::class, 'displayInvoice'])->name('invoicepatient.displayInvoice');

Route::post('invoicepatient/cancel/{id}', [InvoicesController::class, 'cancel'])->name('invoicepatient.cancel');

Route::post('refundpatient/datatable/&{id}', [ApiRefundsController::class, 'patientDatatable'])->name('refundpatient.datatable');
Route::get('refundpatient/refund_create/{id}', [ApiRefundsController::class, 'calculate'])->name('refundpatient.refund_create');

// Patient non-plans refunds API routes removed — functionality not in use
