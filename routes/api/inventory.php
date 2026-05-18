<?php

// Consultancy Datatable, Appointment Misc, Inventory (Warehouse, Brands, Products, Transfer, Orders), Reports API

use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\Appointments\AppointmentStatusController;
use App\Http\Controllers\Admin\Appointments\AppointmentCommunicationController;
use App\Http\Controllers\Admin\Appointments\AppointmentScheduleController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\TransferProductsController;
use App\Http\Controllers\Admin\WarehouseController;
use Illuminate\Support\Facades\Route;

    /*Appointment routes*/
    Route::post('consultancy/datatable', [AppointmentsController::class, 'datatable'])->name('consultancy.datatable');
    // MIGRATED TO: Route::post('treatments/datatable') - admin.treatments.datatable
    Route::get('appointments/show/status', [AppointmentStatusController::class, 'showAppointmentStatuses'])->name('appointments.showappointmentstatus');
    Route::post('appointments/load-child-appointment-statuses', [AppointmentStatusController::class, 'loadAppointmentStatuses'])->name('appointments.load_child_appointment_statuses');
    Route::put('appointments/store/appointmentstatus', [AppointmentStatusController::class, 'storeAppointmentStatuses'])->name('appointments.storeappointmentstatus');
    Route::post('appointments/load-child-appointment/status-data', [AppointmentStatusController::class, 'loadAppointmentStatusData'])->name('appointments.load_child_appointment_status_data');
    /*Appointment routes*/

    Route::get('appointments/sms_logs/{id}', [AppointmentCommunicationController::class, 'showSMSLogs'])->name('appointments.sms_logs');

    Route::put('appointments/send/logged_sms', [AppointmentCommunicationController::class, 'sendLogSMS'])->name('appointments.resend_sms');

    // Legacy consultancy resource route - DEPRECATED, use /api/consultancy/* routes instead
    // Route::resource('consultancy', AppointmentsController::class)->except('index');

    Route::post('appointments/load-doctor-rota', [AppointmentScheduleController::class, 'loadRotaByDoctor'])->name('appointments.load_doctor_rota');

    /*Inventory Routes Start*/

    /*Warehouseroutes*/
    Route::post('warehouse/datatable', [WarehouseController::class, 'datatable'])->name('warehouse.datatable');
    Route::get('warehouse/create', [WarehouseController::class, 'create'])->name('warehouse.create');
    Route::post('warehouse', [WarehouseController::class, 'store'])->name('warehouse.store');
    Route::get('warehouse/{id}/edit', [WarehouseController::class, 'edit'])->name('warehouse.edit');
    Route::put('warehouse/{id}', [WarehouseController::class, 'update'])->name('warehouse.update');
    Route::delete('warehouse/{id}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy');
    Route::patch('warehouse/active/{id}', [WarehouseController::class, 'active'])->name('warehouse.active');
    Route::post('warehouse/status', [WarehouseController::class, 'status'])->name('warehouse.status');
    Route::get('warehouse/sort', [WarehouseController::class, 'sortorder'])->name('warehouse.sort');
    Route::post('reports/inventory_reports/result', [InventoryReportController::class, 'reportResult'])->name('reports.inventory_report_result');
    Route::post('reports/inventory_reports/stock', [InventoryReportController::class, 'stockReport'])->name('reports.inventory_report_stock');
    // SPA inventory reports — stock / sales / doctor-sales / addition + exports
    Route::get('reports/inventory/filters', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'filters'])->name('api.reports.inventory.filters');
    Route::get('reports/inventory/doctors', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'doctorsForCentres'])->name('api.reports.inventory.doctors');
    Route::post('reports/inventory/stock', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'stock'])->name('api.reports.inventory.stock');
    Route::post('reports/inventory/stock/export', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'stockExport'])->name('api.reports.inventory.stock.export');
    Route::post('reports/inventory/sales', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'sales'])->name('api.reports.inventory.sales');
    Route::post('reports/inventory/sales/export', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'salesExport'])->name('api.reports.inventory.sales.export');
    Route::post('reports/inventory/doctor-sales', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'doctorSales'])->name('api.reports.inventory.doctor-sales');
    Route::post('reports/inventory/doctor-sales/export', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'doctorSalesExport'])->name('api.reports.inventory.doctor-sales.export');
    Route::post('reports/inventory/addition', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'addition'])->name('api.reports.inventory.addition');
    Route::post('reports/inventory/addition/export', [\App\Http\Controllers\Api\Reports\InventoryReportsApiController::class, 'additionExport'])->name('api.reports.inventory.addition.export');
    Route::post('reports/general-sales', [\App\Http\Controllers\Api\Reports\GeneralSalesReportController::class, '__invoke'])->name('api.reports.general-sales');
    Route::post('reports/general-sales/export', [\App\Http\Controllers\Api\Reports\GeneralSalesReportController::class, 'export'])->name('api.reports.general-sales.export');
    Route::get('reports/general-sales/filters', [\App\Http\Controllers\Api\Reports\GeneralSalesFiltersApiController::class, '__invoke'])->name('api.reports.general-sales.filters');
    Route::get('reports/general-sales/doctors', [\App\Http\Controllers\Api\Reports\GeneralSalesFiltersApiController::class, 'doctorsForCentres'])->name('api.reports.general-sales.doctors');
    Route::post('reports/operations', [\App\Http\Controllers\Api\Reports\OperationsReportController::class, '__invoke'])->name('api.reports.operations');
    Route::post('reports/memberships', [\App\Http\Controllers\Api\Reports\MembershipReportController::class, '__invoke'])->name('api.reports.memberships');
    Route::get('reports/memberships/filters', [\App\Http\Controllers\Api\Reports\MembershipReportFiltersApiController::class, '__invoke'])->name('api.reports.memberships.filters');
    Route::get('reports/memberships/export', [\App\Http\Controllers\Api\Reports\MembershipReportController::class, 'export'])->name('api.reports.memberships.export');
    Route::post('reports/appointments', [\App\Http\Controllers\Api\Reports\AppointmentsReportController::class, '__invoke'])->name('api.reports.appointments');
    Route::post('reports/staff-wise-arrival', [\App\Http\Controllers\Api\Reports\StaffWiseArrivalController::class, '__invoke'])->name('api.reports.staff-wise-arrival');
    Route::get('reports/staff-wise-arrival/filters', [\App\Http\Controllers\Api\Reports\StaffWiseArrivalController::class, 'filters'])->name('api.reports.staff-wise-arrival.filters');
    Route::get('reports/doctor-revenue/filters', [\App\Http\Controllers\Api\Reports\DoctorRevenueReportController::class, 'filters'])->name('api.reports.doctor-revenue.filters');
    Route::post('reports/doctor-revenue', [\App\Http\Controllers\Api\Reports\DoctorRevenueReportController::class, '__invoke'])->name('api.reports.doctor-revenue');
    Route::post('reports/doctor-revenue/detail', [\App\Http\Controllers\Api\Reports\DoctorRevenueReportController::class, 'detail'])->name('api.reports.doctor-revenue.detail');
    Route::post('reports/doctor-revenue/export', [\App\Http\Controllers\Api\Reports\DoctorRevenueReportController::class, 'export'])->name('api.reports.doctor-revenue.export');
    Route::get('reports/csr-dashboard', [\App\Http\Controllers\Api\Reports\CsrDashboardController::class, '__invoke'])->name('api.reports.csr-dashboard');
    Route::post('reports/arrived-not-converted', [\App\Http\Controllers\Api\Reports\ArrivedNotConvertedController::class, '__invoke'])->name('api.reports.arrived-not-converted');
    Route::post('reports/arrived-not-converted/export', [\App\Http\Controllers\Api\Reports\ArrivedNotConvertedController::class, 'export'])->name('api.reports.arrived-not-converted.export');
    Route::get('reports/doctor-ratings/filters', [\App\Http\Controllers\Api\Reports\DoctorRatingsApiController::class, 'filters'])->name('api.reports.doctor-ratings.filters');
    Route::get('reports/doctor-ratings/doctors', [\App\Http\Controllers\Api\Reports\DoctorRatingsApiController::class, 'doctorsForCentres'])->name('api.reports.doctor-ratings.doctors');
    Route::post('reports/doctor-ratings/data', [\App\Http\Controllers\Api\Reports\DoctorRatingsApiController::class, 'data'])->name('api.reports.doctor-ratings.data');
    Route::get('reports/doctor-ratings/export', [\App\Http\Controllers\Api\Reports\DoctorRatingsApiController::class, 'export'])->name('api.reports.doctor-ratings.export');
    Route::get('reports/doctor-ratings/{doctor}/by-service', [\App\Http\Controllers\Api\Reports\DoctorRatingsApiController::class, 'byService'])->whereNumber('doctor')->name('api.reports.doctor-ratings.by-service');
    Route::get('reports/doctor-incentive/filters', [\App\Http\Controllers\Api\Reports\DoctorIncentiveReportController::class, 'filters'])->name('api.reports.doctor-incentive.filters');
    Route::get('reports/doctor-incentive/doctors', [\App\Http\Controllers\Api\Reports\DoctorIncentiveReportController::class, 'doctorsForCentres'])->name('api.reports.doctor-incentive.doctors');
    Route::post('reports/doctor-incentive', [\App\Http\Controllers\Api\Reports\DoctorIncentiveReportController::class, '__invoke'])->name('api.reports.doctor-incentive');
    Route::post('reports/doctor-incentive/export', [\App\Http\Controllers\Api\Reports\DoctorIncentiveReportController::class, 'export'])->name('api.reports.doctor-incentive.export');
    Route::get('reports/activity-logs/filters', [\App\Http\Controllers\Api\Reports\ActivityLogsApiController::class, 'filters'])->name('api.reports.activity-logs.filters');
    Route::post('reports/activity-logs', [\App\Http\Controllers\Api\Reports\ActivityLogsApiController::class, '__invoke'])->name('api.reports.activity-logs');
    Route::post('reports/activity-logs/export', [\App\Http\Controllers\Api\Reports\ActivityLogsApiController::class, 'export'])->name('api.reports.activity-logs.export');
    Route::get('reports/conversion/filters', [\App\Http\Controllers\Api\Reports\ConversionReportApiController::class, 'filters'])->name('api.reports.conversion.filters');
    Route::get('reports/conversion/doctors', [\App\Http\Controllers\Api\Reports\ConversionReportApiController::class, 'doctorsForCentres'])->name('api.reports.conversion.doctors');
    Route::post('reports/conversion', [\App\Http\Controllers\Api\Reports\ConversionReportApiController::class, '__invoke'])->name('api.reports.conversion');
    Route::post('reports/conversion/export', [\App\Http\Controllers\Api\Reports\ConversionReportApiController::class, 'export'])->name('api.reports.conversion.export');
    Route::get('reports/follow-up/filters', [\App\Http\Controllers\Api\Reports\FollowUpReportApiController::class, 'filters'])->name('api.reports.follow-up.filters');
    Route::post('reports/follow-up', [\App\Http\Controllers\Api\Reports\FollowUpReportApiController::class, '__invoke'])->name('api.reports.follow-up');
    Route::post('reports/follow-up/export', [\App\Http\Controllers\Api\Reports\FollowUpReportApiController::class, 'export'])->name('api.reports.follow-up.export');
    Route::get('reports/future-treatments/filters', [\App\Http\Controllers\Api\Reports\FutureTreatmentsReportApiController::class, 'filters'])->name('api.reports.future-treatments.filters');
    Route::post('reports/future-treatments', [\App\Http\Controllers\Api\Reports\FutureTreatmentsReportApiController::class, '__invoke'])->name('api.reports.future-treatments');
    Route::post('reports/future-treatments/export', [\App\Http\Controllers\Api\Reports\FutureTreatmentsReportApiController::class, 'export'])->name('api.reports.future-treatments.export');
    Route::get('reports/upselling/filters', [\App\Http\Controllers\Api\Reports\UpsellingApiController::class, 'filters'])->name('api.reports.upselling.filters');
    Route::post('reports/upselling/doctor', [\App\Http\Controllers\Api\Reports\UpsellingApiController::class, 'doctorUpselling'])->name('api.reports.upselling.doctor');
    Route::post('reports/upselling/doctor/export', [\App\Http\Controllers\Api\Reports\UpsellingApiController::class, 'doctorUpsellingExport'])->name('api.reports.upselling.doctor.export');
    Route::post('reports/upselling/doctor/{doctor}/detail', [\App\Http\Controllers\Api\Reports\UpsellingApiController::class, 'doctorUpsellingDetail'])->whereNumber('doctor')->name('api.reports.upselling.doctor.detail');
    Route::post('reports/upselling/consultant-revenue', [\App\Http\Controllers\Api\Reports\UpsellingApiController::class, 'consultantRevenue'])->name('api.reports.upselling.consultant-revenue');
    Route::post('reports/upselling/consultant-revenue/export', [\App\Http\Controllers\Api\Reports\UpsellingApiController::class, 'consultantRevenueExport'])->name('api.reports.upselling.consultant-revenue.export');
    Route::get('reports/doctor-ratings-detail/filters', [\App\Http\Controllers\Api\Reports\DoctorRatingsDetailApiController::class, 'filters'])->name('api.reports.doctor-ratings-detail.filters');
    Route::get('reports/doctor-ratings-detail/doctors', [\App\Http\Controllers\Api\Reports\DoctorRatingsDetailApiController::class, 'doctorsForCentres'])->name('api.reports.doctor-ratings-detail.doctors');
    Route::post('reports/doctor-ratings-detail', [\App\Http\Controllers\Api\Reports\DoctorRatingsDetailApiController::class, '__invoke'])->name('api.reports.doctor-ratings-detail');
    Route::post('reports/doctor-ratings-detail/export', [\App\Http\Controllers\Api\Reports\DoctorRatingsDetailApiController::class, 'export'])->name('api.reports.doctor-ratings-detail.export');
    /*Warehouseroutes*/

    /*Brand routes*/
    Route::post('brands/datatable', [BrandsController::class, 'datatable'])->name('brands.datatable');
    Route::post('brands', [BrandsController::class, 'store'])->name('brands.store');
    Route::get('brands/{id}/edit', [BrandsController::class, 'edit'])->name('brands.edit');
    Route::put('brands/{id}', [BrandsController::class, 'update'])->name('brands.update');
    Route::delete('brands/{id}', [BrandsController::class, 'destroy'])->name('brands.destroy');
    Route::post('brands/status', [BrandsController::class, 'status'])->name('brands.status');
    /*Brand routes*/

    /*Product routes*/
    Route::post('products/datatable', [ProductsController::class, 'datatable'])->name('products.datatable');
    Route::get('products/create', [ProductsController::class, 'create'])->name('products.create');
    Route::post('products', [ProductsController::class, 'store'])->name('products.store');
    Route::post('products/status', [ProductsController::class, 'status'])->name('products.status');
    Route::get('products/{id}/edit', [ProductsController::class, 'edit'])->name('products.edit');
    Route::post('products/save_allocate', [ProductsController::class, 'saveAllocate'])->name('products.save_allocate');
    Route::get('products/products/{id}', [ProductsController::class, 'displaylocation'])->name('products.location_manage');
    Route::get('products/{id}/saleprice', [ProductsController::class, 'editSalePrice'])->name('products.edit-sale-price');
    Route::post('products/stock-detail/{id}', [ProductsController::class, 'productStockDetail'])->name('products.stock-detail');
    Route::post('products/inventory-detail/{id}', [ProductsController::class, 'productInventoryDetail'])->name('products.inventories');
    Route::get('products/{id}/batches', [ProductsController::class, 'productBatches'])->name('products.batches');
    Route::put('products/{id}/{detail}', [ProductsController::class, 'update'])->name('products.update');
    Route::post('products/{id}/updatesaleprice', [ProductsController::class, 'updateSalePrice'])->name('products.update-sale-price');
    Route::post('products/{id}/addstock', [ProductsController::class, 'addStock'])->name('products.add-stock');
    Route::delete('products/{id}', [ProductsController::class, 'destroy'])->name('products.destroy');
    Route::get('products/transfer-product/{id}', [ProductsController::class, 'transferProductGetData'])->name('products.transfer_product.get');
    Route::post('products/transfer-product', [ProductsController::class, 'transferProduct'])->name('products.transfer_product');
    /*Product routes*/

    /* Transfer Product */
    Route::post('transfer-product/datatable', [TransferProductsController::class, 'datatable'])->name('transfer_products.datatable');
    Route::get('transfer-product/get-products', [TransferProductsController::class, 'getTransferProducts'])->name('transfer_products.get_products');
    Route::get('transfer-product/fetch-products', [TransferProductsController::class, 'getProducts'])->name('transfer_products.fetch_products');
    Route::resource('transfer_product', TransferProductsController::class)->except('index');
    /* Transfer Product Route */

    /*Order routes*/
    Route::post('orders/datatable', [OrdersController::class, 'datatable'])->name('orders.datatable');
    Route::post('orders/refund/datatable', [OrdersController::class, 'refunddatatable'])->name('orders.refund.datatable');
    Route::get('orders/getproducts', [OrdersController::class, 'getProducts'])->name('orders.getproducts');
    Route::get('orders/getdiscounts', [OrdersController::class, 'getDiscounts'])->name('orders.getdiscounts');
    Route::get('orders/displayInvoice/{id}', [OrdersController::class, 'displayInvoiceAppointment'])->name('orders.invoiceDisplay');
    Route::get('orders/{id}/invoice/json', [OrdersController::class, 'invoiceJson'])->name('orders.invoiceJson');
    Route::get('invoices/pdf/{id}/{download?}', [OrdersController::class, 'invoicePdf'])->name('orders.invoice_pdf');
    Route::post('get-employees', [OrdersController::class, 'getEmployees'])->name('get-employees');
    Route::post('get-doctors', [OrdersController::class, 'getDoctors'])->name('get-doctors');
     Route::post('get-doctors-for-sales', [OrdersController::class, 'getDoctorsForSales'])->name('get-doctors-for-sales');
    Route::post('get-centre-doctors', [OrdersController::class, 'getCentreDoctors'])->name('get-centre-doctors');
    Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('orders/{id}/edit', [OrdersController::class, 'edit'])->name('orders.edit');
    Route::post('orders/update/{id}', [OrdersController::class, 'update'])->name('orders.update');


    Route::get('orders/refund/{id}/detail', [OrdersController::class, 'orderRefundDetail'])->name('orders.refund.detail');
    Route::post('orders/{id}/refund', [OrdersController::class, 'orderRefund'])->name('orders.refund');
    //Route::delete('orders/refund/{id}', [OrdersController::class, 'orderRefundDestroy'])->name('orders.refund.destroy');
    Route::delete('orders/{id}', [OrdersController::class, 'destroy'])->name('orders.destroy');
