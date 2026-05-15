<?php

// Invoices, User Helpers, Packages/Plans, Membership Codes, Custom Form Feedbacks, Custom Forms, Leads, Feedbacks Datatable

use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\InvoiceGenerationController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Api\InvoicesController as ApiInvoicesController;
use App\Http\Controllers\Admin\LeadsController as AdminLeadsController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Api\PackagesController as ApiPackagesController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Api\WrongConversionsController;
use App\Http\Controllers\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\Admin\PatientsController as AdminPatientsController;
use App\Http\Controllers\Admin\HR\EmployeeDocumentController;
use App\Http\Controllers\PatientFileController;
use Illuminate\Support\Facades\Route;

    //Invoice Management route start

    // Invoices — REST API additions (registered first so literal paths
    // like `/invoices/{id}/cancel` beat the legacy Route::resource
    // wildcard `{invoice}` below)
    Route::prefix('invoices')->name('invoices.api.')->group(function () {
        Route::get('/', [ApiInvoicesController::class, 'index'])->name('index');
        Route::get('{invoice}', [ApiInvoicesController::class, 'show'])->name('show')->whereNumber('invoice');
        Route::post('{invoice}/cancel', [ApiInvoicesController::class, 'cancel'])->name('cancel')->whereNumber('invoice');
        Route::get('{invoice}/sms-logs', [ApiInvoicesController::class, 'smsLogs'])->name('sms_logs')->whereNumber('invoice');
        Route::post('{invoice}/resend-sms', [ApiInvoicesController::class, 'resendSMS'])->name('resend_sms')->whereNumber('invoice');

        // Tax Calculation Report — SPA aliases for the legacy
        // /admin/invoices/{calculate-amounts,export-exempt,download-invoices-zip}
        // endpoints. Same controller methods, just an /api/ prefix so the
        // SPA can call them through the standard API client.
        Route::post('calculate-amounts', [InvoiceGenerationController::class, 'calculateAmounts'])->name('calculate_amounts');
        Route::post('export-exempt', [InvoiceGenerationController::class, 'exportExemptInvoices'])->name('export_exempt');
        Route::post('download-invoices-zip', [InvoiceGenerationController::class, 'downloadInvoicesZip'])->name('download_invoices_zip');
    });

    Route::post('invoices/datatable/&{id?}', [InvoicesController::class, 'datatable'])->name('invoices.datatable');

    Route::post('invoices/cancel/{id}', [InvoicesController::class, 'cancel'])->name('invoices.cancel');

    Route::get('invoices/displayInvoice/{id}', [InvoicesController::class, 'displayInvoice'])->name('invoices.displayInvoice');
    Route::post('invoices/invoiceDatatable/{id}', [InvoicesController::class, 'invoiceDatatable'])->name('invoices.invoiceDatatable');

    Route::get('invoices/sms_logs/{id}', [InvoicesController::class, 'showSMSLogs'])->name('invoices.sms_logs');

    Route::post('invoices/send_logged_sms', [InvoicesController::class, 'sendLogSMS']);

    // Legacy admin resource — every verb it would register points to
    // methods that don't exist on the admin InvoicesController, so they
    // would surface as BadMethodCallException 500s (same latent bug we
    // fixed on Resources / Centre Targets). Invoices are read + cancelled
    // only via the REST API above; the legacy explicit routes handle the
    // admin datatable/display/sms flows.
    Route::resource('invoices', InvoicesController::class)->except([
        'index', 'show', 'store', 'update', 'destroy', 'create', 'edit',
    ]);
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

    /*Routes for editing the cash in treatment plan — the edit-payment
      surface. Idempotent middleware on the two write routes catches
      double-click + network-retry double-submits the same way it does
      on the create flow. */
    Route::get('packages/edit_cash/{id}/{package_id}', [PackagesController::class, 'editpackageadvancescashindex'])->name('packages.edit_cash');

    Route::post('packages/delete/cash', [PackagesController::class, 'deletepackageadvancescash'])
        ->middleware(['throttle:60,1', 'idempotent'])
        ->name('packages.delete_cash');

    Route::put('packages/edit_cash/store', [PackagesController::class, 'storepackageadvancescash'])
        ->middleware(['throttle:60,1', 'idempotent'])
        ->name('packages.edit_cash.store');
    /*End*/

    // Route for Sms log start
    Route::get('packages/sms_logs/{id}', [PackagesController::class, 'showSMSLogs'])->name('packages.sms_logs');
    Route::post('packages/send/logged_sms', [PackagesController::class, 'sendLogSMS'])->name('packages.resend_sms');
    // End

    Route::get('packages/getappointmentinfo', [PackagesController::class, 'getappointmentinfo'])->name('packages.getappointmentinfo');
    Route::get('packages/getsoldbydata', [PackagesController::class, 'getSoldByData'])->name('packages.getsoldbydata');
    Route::get('packages/checkDuplicateServiceForSoldBy', [PackagesController::class, 'checkDuplicateServiceForSoldBy'])->name('packages.checkDuplicateServiceForSoldBy');
    Route::post('packages/updatesoldby', [PackagesController::class, 'updateSoldBy'])->name('packages.updatesoldby');
    // Packages — REST API (registered first so literal paths beat the
    // legacy `Route::resource` wildcard `{package}`). In this system the
    // UI label "Packages" maps to the `bundles` table (the literal
    // `packages` table holds patient plans, served elsewhere). This
    // controller delegates to `BundleService`, so it shares behaviour
    // with `/api/bundles/*` verbatim — response shape matches the
    // `/admin/bundles` datatable.
    Route::prefix('packages')->name('packages.api.')->group(function () {
        Route::get('/', [ApiPackagesController::class, 'index'])->name('index');
        Route::post('datatable', [ApiPackagesController::class, 'datatable'])->name('datatable');
        Route::post('status', [ApiPackagesController::class, 'status'])->name('status');
        Route::get('sort/get', [ApiPackagesController::class, 'sortOrderGet'])->name('sort_get');
        Route::post('sort/save', [ApiPackagesController::class, 'sortOrderSave'])->name('sort_save');
        Route::post('create', [ApiPackagesController::class, 'store'])->name('create');
        Route::get('{id}', [ApiPackagesController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('{id}/edit', [ApiPackagesController::class, 'edit'])->name('edit')->whereNumber('id');
        Route::patch('{id}', [ApiPackagesController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('{id}', [ApiPackagesController::class, 'destroy'])->name('destroy')->whereNumber('id');
    });

    // Legacy admin resource — `store`/`show` are admin-side `abort(404)`
    // stubs (the real flow uses the staged savepackages/updatepackages
    // endpoints above). `destroy` is excluded because the REST API above
    // owns `DELETE /packages/{id}`. `edit` + `update` stay for the
    // legacy admin edit form.
    Route::resource('packages', PackagesController::class)->except(['index', 'store', 'show', 'create', 'destroy']);

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

    // Wrong-conversions safety net (SPA replacement for the legacy
    // /admin/wrong-conversions Blade page). Auto-revert via
    // ConversionStateService::revertIfNeeded covers the canonical
    // refund/payment-delete/plan-delete paths; this list catches the
    // rest (historical rows, direct DB edits, validation criteria
    // beyond net cash).
    Route::prefix('wrong-conversions')->name('wrong_conversions.')->group(function (): void {
        Route::get('/', [WrongConversionsController::class, 'index'])->name('index');
        Route::post('reset-all', [WrongConversionsController::class, 'resetAll'])->name('reset_all');
        Route::post('{id}/reset', [WrongConversionsController::class, 'reset'])->name('reset')->whereNumber('id');
    });

    // Migration of the last 5 SPA → Blade-route dependencies. Each
    // points at the SAME controller method as the legacy /admin/* route
    // so behaviour is identical; only the URL changes. The legacy
    // /admin/* routes stay live until cutover (legacy frontend still
    // uses them); this group is what the SPA hits going forward.
    //   #1 invoice PDF (returns View — the legacy printable invoice)
    Route::get('invoices/{id}/pdf/{download?}/{flag?}', [InvoicesController::class, 'invoice_pdf'])
        ->name('invoices.pdf')->whereNumber('id');
    //   #2 services tree PDF export (returns BinaryFileResponse)
    Route::get('services/export-pdf', [AdminServicesController::class, 'exportPdf'])
        ->name('services.export_pdf');
    //   #3 plan finance log Excel export (streams XLSX via packagelog)
    Route::get('plans/log/{id}/{type}', [PackagesController::class, 'packagelog'])
        ->name('plans.log_export')->whereNumber('id');
    // (#4 patient card Blade view removed — SPA's patient-detail page
    //  covers every section the legacy card showed; the "Open in legacy
    //  admin" Quick action that hit this route was deleted at the same
    //  time. The cardV2 method on AdminPatientsController and the
    //  resources/views/admin/patients/card-v2/ blade folder are
    //  unreferenced now and sweep with the legacy admin tree at cutover.)
    //   #5 student-verification document stream (returns StreamedResponse)
    Route::get('files/student-verification/{filename}', [PatientFileController::class, 'studentVerification'])
        ->name('files.student_verification');

    // Round-2 migration: backend serializers (PatientDetailResource,
    // EmployeeResource) build URLs via `route('admin.*')` against
    // routes registered under `routes/web/*`. The SPA reads these as
    // payload fields (`image_url`, `preview_url`, `download_url`) and
    // opens them via <a href> / <img src> — same cutover risk as the
    // raw /admin/* hardcoded URLs. Distinct route names (suffix `_api`)
    // so the Resource updates target the API URLs explicitly without
    // colliding with the legacy `route()` callers.
    //   #6 patient image stream (avatar / profile pic)
    Route::get('files/patient-image/{filename}', [PatientFileController::class, 'patientImage'])
        ->name('files.patient_image_api');
    //   #7 HR employee document preview (image inline / PDF inline)
    Route::get('hr/documents/{document}/preview', [EmployeeDocumentController::class, 'preview'])
        ->name('hr.documents.preview_api');
    //   #8 HR employee document download (force-download)
    Route::get('hr/documents/{document}/download', [EmployeeDocumentController::class, 'download'])
        ->name('hr.documents.download_api');
