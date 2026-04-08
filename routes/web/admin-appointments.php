<?php

// Appointment management views, Services routes, Inventory (Warehouse, Brands, Products, Transfer Products)

use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\Appointments\AppointmentExportController;
use App\Http\Controllers\Admin\Appointments\AppointmentInvoiceController;
use App\Http\Controllers\Admin\Appointments\AppointmentLookupController;
use App\Http\Controllers\Admin\Appointments\AppointmentScheduleController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\ConsultancyInvoiceController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\TransferProductsController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\DashboardReportsController;
use Illuminate\Support\Facades\Route;

        /*Appointment routes*/

        Route::get('appointments/doctors', [AppointmentsController::class, 'loadDoctors'])->name('appointments.doctors');

        Route::put('appointments/save_doctor', [AppointmentsController::class, 'saveDoctor'])->name('appointments.save_doctor');

        Route::post('appointments/status', [AppointmentsController::class, 'status'])->name('appointments.status');

        /*Route::resource('appointments', AppointmentsController::class)->only('index');*/
        Route::resource('consultancy', AppointmentsController::class)->only('index')->middleware('permission:appointments_manage');
        Route::get('treatment', [AppointmentsController::class, 'treatment'])->name('treatment.index')->middleware('permission:treatments_manage');

        /*service routes*/

        Route::get('appointments/manage-services', [AppointmentsController::class, 'createService'])->name('appointments.manage_services');

        Route::get('appointments/load-non-scheduled-service-appointments', [AppointmentScheduleController::class, 'getNonScheduledServiceAppointments'])->name('appointments.load_nonscheduled_service_appointments');

        Route::get('appointments/get_room_resources', [AppointmentLookupController::class, 'getRoomResources'])->name('appointments.get_room_resources');

        Route::get('appointments/get_room_resources_with_specific_date', [AppointmentLookupController::class, 'getRoomResourcesWithDate'])->name('appointments.get_room_resources_with_specific_date');

        //Appointment Route section for treatment invoice start

        Route::get('appointments/getplansinformation', [AppointmentInvoiceController::class, 'getplansinformation'])->name('appointments.getplansinformation');

        Route::get('appointments/getpackageprice', [AppointmentInvoiceController::class, 'getpackageprice'])->name('appointments.getpackageprice');

        Route::get('appointments/getinvoicecalculation', [AppointmentInvoiceController::class, 'getinvoicecalculation'])->name('appointments.getinvoicecalculation');

        Route::get('appointments/getcalculatedPriceExclusicecheck', [AppointmentInvoiceController::class, 'getcalculatedPriceExclusicecheck'])->name('appointments.getcalculatedPriceExclusicecheck');

        Route::get('appointments/saveinvoice', [AppointmentInvoiceController::class, 'saveinvoice'])->name('appointments.saveinvoice');
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

        Route::get('dashboard/getdoctors', [DashboardReportsController::class, 'getCentreDoctors'])->name('getdoctors');

        // Appointment Comments
        Route::get('appointments/comment-save', [\App\Http\Controllers\Admin\Appointments\AppointmentCommunicationController::class, 'AppointmentStoreComment'])->name('appointments.storecomment');
        //Appointment Route end for images

        /*Consultancy Routes*/
        Route::post('appointments/load-locations', [AppointmentLookupController::class, 'loadLocationsByCity'])->name('appointments.load_locations');
        Route::post('appointments/load_child_services', [AppointmentLookupController::class, 'LoadChildServices'])->name('appointments.load_child_services');
        Route::get('appointments/load-non-scheduled-appointments', [AppointmentScheduleController::class, 'getNonScheduledAppointments'])->name('appointments.load_nonscheduled_appointments');
        Route::post('appointments/check-phone-exist', [AppointmentLookupController::class, 'checkPhoneExist'])->name('appointments.check_phone_exist');
        // Migrated to API route - using API controller with Service layer
        // Route::post('appointments/check-and-save-appointment', [AppointmentsController::class, 'checkAndSaveAppointments'])->name('appointments.check_and_save_appointment');
        Route::get('appointments/export/{limit}/{offset}', [AppointmentExportController::class, 'export'])->name('appointments.export');
        Route::get('download-today-consultancies', [AppointmentExportController::class, 'todayexport']);
        Route::get('download-today-treatments', [AppointmentExportController::class, 'todaytreatments']);
        Route::get('appointments/view/log/{id}/{type}', [AppointmentExportController::class, 'logPage'])->name('appointments.loadPage');
        Route::post('download-filter-data', [AppointmentExportController::class, 'downloadExportdata']);
        Route::get('appointments/get-whatsapp-data', [\App\Http\Controllers\Admin\Appointments\AppointmentCommunicationController::class, 'getWhatsAppData'])->name('appointments.get_whatsapp_data');
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
