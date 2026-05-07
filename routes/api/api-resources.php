<?php

// Optimised REST API: Appointments, Consultancy, Treatment prefix groups, legacy appointment routes, Appointmentplans, Memberships

use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\Appointments\AppointmentExportController;
use App\Http\Controllers\Admin\Appointments\AppointmentInvoiceController;
use App\Http\Controllers\Admin\Appointments\AppointmentLookupController;
use App\Http\Controllers\Admin\Appointments\AppointmentScheduleController;
use App\Http\Controllers\Admin\AppointmentsPlansController;
use App\Http\Controllers\Api\ConsultancyInvoiceController;
use App\Http\Controllers\Api\MembershipsController;
use App\Http\Controllers\Api\MembershipTypesController;
use Illuminate\Support\Facades\Route;

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

        // Appointment Comments
        Route::get('{appointment}/comments', [\App\Http\Controllers\Api\AppointmentCommentController::class, 'index'])->name('comments.index');
        Route::post('comments', [\App\Http\Controllers\Api\AppointmentCommentController::class, 'store'])->name('comments.store');
    });

    // Active application-user dropdown — feeds the SPA's
    // Created/Updated/Rescheduled-by filters on the consultations
    // screen. Light auth (auth.api.dual on the parent group) is
    // sufficient: no PII, just `{id, name}` rows scoped to the caller's
    // account, matching the legacy admin's filter dropdown behaviour.
    Route::get('users/dropdown/application-users', [\App\Http\Controllers\Api\ApplicationUserController::class, 'applicationUsersDropdown'])->name('users.dropdown.application_users');

    // Consultancy API Routes (Optimized)
    Route::prefix('consultancy')->name('consultancy.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ConsultancyController::class, 'index'])->name('api_index');
        Route::post('/', [\App\Http\Controllers\Api\ConsultancyController::class, 'store'])->name('store');
        Route::get('{id}', [\App\Http\Controllers\Api\ConsultancyController::class, 'show'])->name('show');
        Route::put('{id}', [\App\Http\Controllers\Api\ConsultancyController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\Api\ConsultancyController::class, 'destroy'])->name('destroy');
        Route::put('{id}/status', [\App\Http\Controllers\Api\ConsultancyController::class, 'updateStatus'])->name('update_status');
        Route::post('{id}/schedule', [\App\Http\Controllers\Api\ConsultancyController::class, 'schedule'])->name('schedule');
        Route::get('scheduled/list', [\App\Http\Controllers\Api\ConsultancyController::class, 'scheduled'])->name('scheduled');
        Route::get('non-scheduled/list', [\App\Http\Controllers\Api\ConsultancyController::class, 'nonScheduled'])->name('non_scheduled');
        Route::get('statistics/data', [\App\Http\Controllers\Api\ConsultancyController::class, 'statistics'])->name('statistics');

        // Page extras: WhatsApp prefill data + Excel export of filtered list.
        // Send-WhatsApp itself stays client-side (window.open to wa.me).
        Route::get('{id}/whatsapp-data', [\App\Http\Controllers\Api\ConsultancyController::class, 'whatsappData'])->name('whatsapp_data');
        Route::get('{id}/activities', [\App\Http\Controllers\Api\ConsultancyController::class, 'activities'])->name('activities');
        Route::post('export', [\App\Http\Controllers\Api\ConsultancyController::class, 'export'])->name('export');

        // Consultancy Invoice API Routes
        Route::prefix('invoice')->name('invoice.')->group(function () {
            Route::get('{id}', [\App\Http\Controllers\Api\ConsultancyInvoiceController::class, 'show'])->name('show');
            Route::post('/', [\App\Http\Controllers\Api\ConsultancyInvoiceController::class, 'store'])->name('store');
            Route::post('calculate', [\App\Http\Controllers\Api\ConsultancyInvoiceController::class, 'calculate'])->name('calculate');
            Route::post('calculate-custom', [\App\Http\Controllers\Api\ConsultancyInvoiceController::class, 'calculateCustomDiscount'])->name('calculate_custom');
            Route::post('check-custom', [\App\Http\Controllers\Api\ConsultancyInvoiceController::class, 'checkCustomDiscount'])->name('check_custom');
            Route::post('calculate-final', [\App\Http\Controllers\Api\ConsultancyInvoiceController::class, 'calculateFinal'])->name('calculate_final');
        });
    });

    // Treatment API Routes (Optimized)
    Route::prefix('treatment')->name('treatment.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TreatmentController::class, 'index'])->name('api_index');
        Route::post('/', [\App\Http\Controllers\Api\TreatmentController::class, 'store'])->name('store');
        Route::get('{id}', [\App\Http\Controllers\Api\TreatmentController::class, 'show'])->name('show');
        Route::put('{id}', [\App\Http\Controllers\Api\TreatmentController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\Api\TreatmentController::class, 'destroy'])->name('destroy');
        Route::put('{id}/status', [\App\Http\Controllers\Api\TreatmentController::class, 'updateStatus'])->name('update_status');
        Route::post('{id}/schedule', [\App\Http\Controllers\Api\TreatmentController::class, 'schedule'])->name('schedule');
        Route::get('scheduled/list', [\App\Http\Controllers\Api\TreatmentController::class, 'scheduled'])->name('scheduled');
        Route::get('non-scheduled/list', [\App\Http\Controllers\Api\TreatmentController::class, 'nonScheduled'])->name('non_scheduled');
        Route::get('statistics/data', [\App\Http\Controllers\Api\TreatmentController::class, 'statistics'])->name('statistics');
        Route::get('resources/available', [\App\Http\Controllers\Api\TreatmentController::class, 'availableResources'])->name('available_resources');
        Route::get('services/by-location', [\App\Http\Controllers\Api\TreatmentController::class, 'servicesByLocation'])->name('services_by_location');

        // Page extras: WhatsApp prefill + xlsx export — same shape as
        // the consultancy equivalents so the SPA can reuse its primitives.
        Route::get('{id}/whatsapp-data', [\App\Http\Controllers\Api\TreatmentController::class, 'whatsappData'])->name('whatsapp_data');
        Route::post('export', [\App\Http\Controllers\Api\TreatmentController::class, 'export'])->name('export');

        // Treatment invoice load — JSON parallel to the legacy
        // `appointments/invoice/{id}` Blade route. Save still flows
        // through the consultancy invoice subsystem (which is
        // appointment_id-keyed, so it works for treatments too).
        Route::get('invoice/{id}', [\App\Http\Controllers\Api\TreatmentController::class, 'invoice'])->name('invoice.show');
        // Plan-tree (bundles + services + lock data) for a chosen
        // package — JSON parallel to legacy `getplansinformation`.
        Route::get('invoice/{id}/plan/{packageId}', [\App\Http\Controllers\Api\TreatmentController::class, 'invoicePlanInfo'])->name('invoice.plan');
        // Per-service price + outstanding/settle/remaining — JSON
        // parallel to legacy `getpackageprice`.
        Route::get('invoice/{id}/package-price', [\App\Http\Controllers\Api\TreatmentController::class, 'invoicePackagePrice'])->name('invoice.package_price');
        // Save the consumed-from-plan invoice. Routes directly at the
        // legacy `AppointmentInvoiceController::saveinvoice` because
        // that method already returns proper JSON envelopes
        // (`successResponse` / `errorResponse`) and contains all the
        // package-advances writes, SMS triggers, status transitions
        // and audit-log code we need. Re-implementing it here would
        // duplicate ~400 lines of business logic and silently drift.
        // The method reads `appointment_id` from the request body, so
        // POSTing it from the SPA works unchanged.
        Route::post('invoice', [\App\Http\Controllers\Admin\Appointments\AppointmentInvoiceController::class, 'saveinvoice'])->name('invoice.save');
    });

    // Appointment Routes - Using API Controller with Service Layer
    Route::get('appointments/load/scheduled-appointments', [\App\Http\Controllers\Api\AppointmentsController::class, 'scheduled'])->name('appointments.load_scheduled_appointments');
    Route::post('appointments/check-and-save-appointment', [\App\Http\Controllers\Api\AppointmentsController::class, 'schedule'])->name('appointments.check_and_save_appointment');
    
    // Legacy routes still using Admin controller (to be migrated)
    Route::get('appointments/detail/{id}', [AppointmentsController::class, 'detail'])->name('appointments.detail');
    Route::get('appointments/consulting/create', [AppointmentsController::class, 'createConsultingAppointment'])->name('appointments.consulting.create');
    Route::get('appointments/center_machines/{location_id}', [AppointmentLookupController::class, 'center_machines'])->name('appointments.center_machines');
    Route::get('appointments/treatment/create', [AppointmentsController::class, 'createTreatmentAppointment'])->name('appointments.treatment.create');
    Route::post('appointments/load-node-services', [AppointmentLookupController::class, 'loadEndServiceByBaseService'])->name('appointments.load_node_service');
    Route::post('appointments/load-all-child-services', [AppointmentLookupController::class, 'loadAllChildServices'])->name('appointments.load_all_child_services');
    // MIGRATED TO: Route::post('treatments/store') - admin.treatments.store
    Route::get('appointments/load/scheduled-serivce-appointments', [AppointmentScheduleController::class, 'getScheduledServiceAppointments'])->name('appointments.load_scheduled_service_appointments');
    Route::post('appointments/check-and-save-service-appointment', [AppointmentScheduleController::class, 'serviceSchedule'])->name('appointments.check_service_schedule_and_save_appointment');
    // MIGRATED TO: Route::post('treatments/drag-drop-reschedule') - admin.treatments.drag_drop_reschedule
    Route::get('appointments/{appointment}/edit-service', [AppointmentsController::class, 'editAppointmentService'])->name('appointments.edit_service');
    Route::get('appointments/{appointment}/feedback', [AppointmentsController::class, 'editFeedback'])->name('appointments.feedback.index');
    Route::get('appointments/invoice/{id}', [AppointmentInvoiceController::class, 'invoice'])->name('appointments.invoicecreate');
    Route::get('appointments/displayInvoice/{id}', [AppointmentInvoiceController::class, 'displayInvoiceAppointment'])->name('appointments.InvoiceDisplay');
    Route::get('appointments/invoice-consultancy/{id}/{type?}', [\App\Http\Controllers\Admin\ConsultancyInvoiceController::class, 'invoiceconsultancy'])->name('appointments.invoice-create-consultancy');
    Route::any('appointments/viewlog/{id}/{type}', [AppointmentExportController::class, 'viewLog'])->name('appointments.viewlog');

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
