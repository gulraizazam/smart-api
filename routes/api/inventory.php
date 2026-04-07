<?php

// Consultancy Datatable, Appointment Misc, Inventory (Warehouse, Brands, Products, Transfer, Orders), Reports API

use App\Http\Controllers\Admin\AppointmentsController;
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
    Route::get('appointments/show/status', [AppointmentsController::class, 'showAppointmentStatuses'])->name('appointments.showappointmentstatus');
    Route::post('appointments/load-child-appointment-statuses', [AppointmentsController::class, 'loadAppointmentStatuses'])->name('appointments.load_child_appointment_statuses');
    Route::put('appointments/store/appointmentstatus', [AppointmentsController::class, 'storeAppointmentStatuses'])->name('appointments.storeappointmentstatus');
    Route::post('appointments/load-child-appointment/status-data', [AppointmentsController::class, 'loadAppointmentStatusData'])->name('appointments.load_child_appointment_status_data');
    /*Appointment routes*/

    Route::get('appointments/sms_logs/{id}', [AppointmentsController::class, 'showSMSLogs'])->name('appointments.sms_logs');

    Route::put('appointments/send/logged_sms', [AppointmentsController::class, 'sendLogSMS'])->name('appointments.resend_sms');

    // Legacy consultancy resource route - DEPRECATED, use /api/consultancy/* routes instead
    // Route::resource('consultancy', AppointmentsController::class)->except('index');

    Route::post('appointments/load-doctor-rota', [AppointmentsController::class, 'loadRotaByDoctor'])->name('appointments.load_doctor_rota');

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
    Route::post('reports/general-sales', [\App\Http\Controllers\Api\Reports\GeneralSalesReportController::class, '__invoke'])->name('api.reports.general-sales');
    Route::post('reports/operations', [\App\Http\Controllers\Api\Reports\OperationsReportController::class, '__invoke'])->name('api.reports.operations');
    Route::post('reports/memberships', [\App\Http\Controllers\Api\Reports\MembershipReportController::class, '__invoke'])->name('api.reports.memberships');
    Route::post('reports/appointments', [\App\Http\Controllers\Api\Reports\AppointmentsReportController::class, '__invoke'])->name('api.reports.appointments');
    Route::get('reports/csr-dashboard', [\App\Http\Controllers\Api\Reports\CsrDashboardController::class, '__invoke'])->name('api.reports.csr-dashboard');
    Route::post('reports/arrived-not-converted', [\App\Http\Controllers\Api\Reports\ArrivedNotConvertedController::class, '__invoke'])->name('api.reports.arrived-not-converted');
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
