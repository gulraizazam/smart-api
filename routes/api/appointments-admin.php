<?php

// Admin Appointment routes, Patients, Custom Forms Patient, Medical/Measurement, Patient Documents, Appointment Images, Plans (optimised + legacy), Finances, Invoice/Refund Patient

use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\Appointments\AppointmentInvoiceController;
use App\Http\Controllers\Admin\Appointments\AppointmentLookupController;
use App\Http\Controllers\Admin\Appointments\AppointmentScheduleController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Api\PlansController as ApiPlansController;
use App\Http\Controllers\Api\RefundsController as ApiRefundsController;
use Illuminate\Support\Facades\Route;

    /*Appointment routes*/
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

    Route::delete('plans/destroy/{id}', [PackagesController::class, 'destroy'])->name('plans.destroy');

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

    Route::post('refundpatient/datatable/&{id}', [ApiRefundsController::class, 'patientDatatable'])->name('refundpatient.datatable');
    Route::get('refundpatient/refund_create/{id}', [ApiRefundsController::class, 'calculate'])->name('refundpatient.refund_create');

    // Patient non-plans refunds API routes removed — functionality not in use
