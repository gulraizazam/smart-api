<?php

// Reports (Finance, Operations, Membership, Appointments, Feedback, Upselling, CSR), Dashboard, Membership Types, Memberships, Voucher Reports, Wrong Conversions, Cashflow views

use App\Http\Controllers\Admin\CashFlowController;
use App\Http\Controllers\Admin\JenpharmVoucherReportController;
use App\Http\Controllers\Admin\MembershipsController as AdminMembershipsController;
use App\Http\Controllers\Admin\MembershipTypesController as AdminMembershipTypesController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\Reports\ActivitylogsReportController;
use App\Http\Controllers\Admin\Reports\AppointmentsController as ReportAppointmentsController;
use App\Http\Controllers\Admin\Reports\AppointmentsReportController;
use App\Http\Controllers\Admin\Reports\ArrivedNotConvertedController;
use App\Http\Controllers\Admin\Reports\CsrDashboardController;
use App\Http\Controllers\Admin\Reports\Finance\FinanceArrivalReportController;
use App\Http\Controllers\Admin\Reports\Finance\FinanceRevenueReportController;
use App\Http\Controllers\Admin\Reports\FinanceReportController;
use App\Http\Controllers\Admin\Reports\GeneralSalesReportController;
use App\Http\Controllers\Admin\Reports\OperationsReportController;
use App\Http\Controllers\Admin\Reports\OperationsReportNewController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\WrongConversionsController;
use App\Http\Controllers\ConversionReportController;
use App\Http\Controllers\DashboardReportsController;
use App\Http\Controllers\FeedbacksReportController;
use App\Http\Controllers\InventoryReportsController;
use App\Http\Controllers\MembershipReportsController;
use App\Http\Controllers\PatientFollowupController;
use App\Http\Controllers\UpsellingReportController;
use Illuminate\Support\Facades\Route;

Route::get('inventory/edit/{id}', [ProductsController::class, 'editInventory'])->name('inventory.edit');
// Route::get('reports/inventory_reports', [InventoryReportController::class, 'report'])->name('reports.inventory_report');

Route::get('reports/revenue_reports', [FinanceRevenueReportController::class, 'report'])->name('reports.finance_reports')->middleware('permission:finance_general_revenue_reports_manage');
Route::get('reports/tax_calculation_report', [FinanceArrivalReportController::class, 'taxCalculationReport'])->name('reports.tax_calculation_report');
Route::post('reports/tax_calculation_report_load', [FinanceArrivalReportController::class, 'taxCalculationReportLoad'])->name('reports.tax_calculation_report_load');
Route::get('service-report/{service_id}', [FinanceRevenueReportController::class, 'serviceBarChart'])->name('service.barchart');
Route::get('reports/load_revenue_reports', [FinanceReportController::class, 'revenue_reports'])->name('reports.revenue_reports')->middleware('permission:finance_general_revenue_reports_manage');
Route::get('reports/arrived_not_converted', [ArrivedNotConvertedController::class, 'index'])->name('reports.arrived_not_converted')->middleware('permission:non_converted_customers_manage');
Route::post('reports/account_sales_report_load', [GeneralSalesReportController::class, 'reportLoad'])->name('reports.account_sales_report_load');
Route::post('reports/account_revenue_report_load', [FinanceReportController::class, 'revenueReportLoad'])->name('reports.account_revenue_report_load'); // Note: revenueReportLoad not yet implemented in any controller
Route::post('appointmentreports/appointments-general-load', [ReportAppointmentsController::class, 'reportLoad'])->name('reports.appointments_general_load');

// Route start for Operations reports
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
Route::post('operation_reports/operations-report-load', [OperationsReportNewController::class, 'reportLoad'])->name('reports.operations_report_load');
Route::post('operation_reports/converted-report-load', [ArrivedNotConvertedController::class, 'reportLoad'])->name('reports.converted_report_load');
Route::get('reports/dailyarrival', [FinanceArrivalReportController::class, 'DailyArrival'])->name('reports.dailyarrival');
Route::post('reports/load_dailyarrival_report', [FinanceArrivalReportController::class, 'LoadDailyArrival'])->name('reports.load_dailyarrival_report');
Route::post('reports/load_membership_report', [MembershipReportsController::class, 'loadMembershipReport'])->name('reports.load_membership_report');
Route::get('memberships/export', [MembershipReportsController::class, 'export'])->name('memberships.export.excel');
Route::get('/admin/doctor/consultant/breakdown/{sellerId}', [UpsellingReportController::class, 'doctorConsultantBreakdown'])->name('doctor.consultant.breakdown');
Route::get('/admin/consultant/seller/detail/{consultantId}/{sellerId}', [UpsellingReportController::class, 'doctorConsultantBreakdown'])->name('consultant.seller.detail');
Route::get('reports/conversion', [ConversionReportController::class, 'index'])->name('reports.conversion')->middleware('permission:conversion_report_manage');
Route::get('reports/activity_logs', [ActivitylogsReportController::class, 'index'])->name('reports.activity_logs')->middleware('permission:activity_logs_manage');
Route::post('reports/activity_logs', [ActivitylogsReportController::class, 'fetchActivityReport'])->name('reports.load_activity_report')->middleware('permission:activity_logs_manage');
Route::post('reports/activity_logs/export', [ActivitylogsReportController::class, 'exportCsv'])->name('reports.activity_logs_export')->middleware('permission:activity_logs_manage');
Route::post('reports/load_conversion_report', [ConversionReportController::class, 'LoadConversionReport'])->name('reports.load_conversion_report');
Route::get('reports/staff_wise_arrival', [FinanceArrivalReportController::class, 'staffWiseArrival'])->name('reports.staff_wise_arrival')->middleware('permission:staff_wise_arrival_manage');
Route::post('reports/staff_wise_arrival_report', [FinanceArrivalReportController::class, 'staffWiseArrivalReport'])->name('reports.staff_wise_arrival_report');
Route::get('reports/doctor_wise_conversion', [FinanceArrivalReportController::class, 'doctorWiseConversion'])->name('reports.doctorWiseConversion')->middleware('permission:staff_wise_arrival_manage');

Route::post('reports/incentive_report', [FinanceArrivalReportController::class, 'loadIncentiveReport'])->name('reports.incentive_report');
Route::get('reports/appointments', [AppointmentsReportController::class, 'index'])->name('reports.appointmentsReport');
Route::post('reports/appointments_report', [AppointmentsReportController::class, 'reportLoad'])->name('reports.appointments_report');

// CSR Dashboard - Consultations scheduled in next 5 days
Route::get('reports/csr-dashboard', CsrDashboardController::class)->name('reports.csr_dashboard')->middleware('permission:csr_dashboard_report');

// ////Dashboard Stats - Now handled by API routes in api.php //////
// Kept routes that are still needed (not migrated to API or used elsewhere)
Route::get('dashboard/my-collection-by-centre', [DashboardReportsController::class, 'myCollectionByCentre'])->name('dashboard.myCollectionByCentre');
Route::get('dashboard/my-revenue-by-centre', [DashboardReportsController::class, 'myRevenueByCentre'])->name('dashboard.myRevenueByCentre');
Route::get('dashboard/my-revenue-by-service', [DashboardReportsController::class, 'myRevenueByService'])->name('dashboard.myRevenueByService');
// MIGRATED TO API: /api/dashboard/unattended-payments
// Route::get('dashboard/patient-follow-up', [PatientFollowupController::class, 'patientFollowUp'])->name('dashboard.patient_follow_up');
// MIGRATED TO API: /api/dashboard/overdue-treatments
// Route::get('dashboard/patient-follow-up-one-month', [PatientFollowupController::class, 'patientFollowUpOneMonth'])->name('dashboard.patient_follow_up_one_month');
Route::get('getcolor', [ServicesController::class, 'GetColor'])->name('dashboard.getcolor');
Route::get('dashboard/getchild', [DashboardReportsController::class, 'getChild'])->name('dashboard.getchild');
// MIGRATED TO API: /api/dashboard/doctor-upselling-data
// Route::get('dashboard/doctor/upselling/data', [UpsellingReportController::class, 'getDoctorUpsellingData'])->name('dashboard.doctor.upselling.data');
Route::get('dashboard/feedback/view/{id}', [DashboardReportsController::class, 'viewFeedback'])->name('feedback.view');
Route::get('dashboard/all_doctor_user_wise_conversion', [DashboardReportsController::class, 'allDoctorsWiseConversion'])->name('dashboard.all_doctor_wise_conversion');
Route::get('dashboard/follow-up-report', [DashboardReportsController::class, 'followUpReport'])->name('reports.follow_up')->middleware('permission:follow_up_manage');
Route::get('dashboard/follow-up-report-monthly', [DashboardReportsController::class, 'followUpReportMonthly'])->name('reports.follow_up_month');
Route::post('dashboard/patient_follow_up_report', [DashboardReportsController::class, 'loadFollowUpReport'])->name('reports.patient_follow_up_report');
Route::get('dashboard/patient-follow-up/download', [PatientFollowupController::class, 'patientFollowUpDownload'])->name('follow_up.download');
Route::get('dashboard/patient-monthly-follow-up/download', [PatientFollowupController::class, 'patientMonthlyFollowUpDownload'])->name('monthly_follow_up.download');
// ///Activity Logs Script
Route::get('activitylogs', [ActivitylogsReportController::class, 'InsertLogs']);

// /////////Memberships routes/////
Route::resource('membershiptypes', AdminMembershipTypesController::class)->only('index');
Route::resource('memberships', AdminMembershipsController::class)->only('index');

// Jenpharm Gift Voucher Report
Route::get('jenpharm-voucher-report', [JenpharmVoucherReportController::class, 'index'])->name('jenpharm-voucher-report.index');

// Wrong Conversions Report
Route::get('wrong-conversions', [WrongConversionsController::class, 'index'])->name('wrong-conversions.index');
Route::post('wrong-conversions/reset/{id}', [WrongConversionsController::class, 'reset'])->name('wrong-conversions.reset');
Route::post('wrong-conversions/reset-all', [WrongConversionsController::class, 'resetAll'])->name('wrong-conversions.reset-all');

// Cash Flow Module (view-only routes)
Route::prefix('cashflow')->name('cashflow.')->middleware('permission:cashflow.manage')->group(function () {
    Route::get('/', [CashFlowController::class, 'dashboard'])->name('dashboard');
    Route::get('expenses', [CashFlowController::class, 'expenses'])->name('expenses');
    Route::get('transfers', [CashFlowController::class, 'transfers'])->name('transfers');
    Route::get('vendors', [CashFlowController::class, 'vendors'])->name('vendors');
    Route::get('staff', [CashFlowController::class, 'staff'])->name('staff');
    Route::get('reports', [CashFlowController::class, 'reports'])->name('reports');
    Route::get('settings', [CashFlowController::class, 'settings'])->name('settings');
    Route::get('fdm', [CashFlowController::class, 'fdmView'])->name('fdm');
});
