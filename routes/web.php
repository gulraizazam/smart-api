<?php

use App\Models\PackageAdvances;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\BundlesController as AdminBundlesController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Api\RefundsController as ApiRefundsController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Admin\VouchersController;
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
use App\Http\Controllers\Admin\Reports\GeneralSalesReportController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Admin\MembershipsController as AdminMembershipsController;
use App\Http\Controllers\Admin\MembershipTypesController as AdminMembershipTypesController;
use App\Http\Controllers\Api\MembershipsController as ApiMembershipsController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Reports\OperationsReportController;
use App\Http\Controllers\Admin\Reports\OperationsReportNewController;
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
use App\Http\Controllers\Admin\Reports\AppointmentsReportController;
use App\Http\Controllers\Admin\Reports\ArrivedNotConvertedController;
use App\Http\Controllers\Admin\Reports\CsrDashboardController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');

Auth::routes();
// Authentication Routes...

    Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');

    Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->middleware('throttle:5,1')->name('auth.admin.login');

// Protected data exports and reports — require authentication
Route::middleware(['auth.common'])->group(function () {
    Route::get('/package-advances-sum', fn () => response()->json([
        'sum' => PackageAdvances::where('cash_flow', 'in')
            ->where('payment_mode_id', 4)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', ['2024-07-01 00:00:00', '2025-06-30 23:59:59'])
            ->sum('cash_amount'),
    ]))->name('package_advances.sum');
    Route::get('/services/export-pdf', [ServicesController::class, 'exportPdf'])->name('services.export.pdf');
    Route::get('/download-student-membership-patients', [ApiMembershipsController::class, 'downloadStudentMembershipPatients'])
        ->name('download.student.membership.patients');
    Route::get('/download-doctor-upselling-excel', [UpsellingReportController::class, 'downloadDoctorUpsellingExcel'])->name('download.doctor.upselling');
    Route::get('followup', [DashboardReportsController::class, 'FollowUp'])->name('dashboard.followup');
});

// Artisan command routes — protected and rate-limited (these are also handled by scheduler)
Route::middleware(['auth.common', 'throttle:5,1'])->group(function () {
    Route::get('/deliver-on-appointment-book', function () {
        \Artisan::call('appointment:deliver-on-appointment-book');
        return response()->json(['status' => 'ok']);
    });
    Route::get('/2nd-message-on-appointment-day', function () {
        \Artisan::call('appointment:2nd-message-on-appointment-day');
        return response()->json(['status' => 'ok']);
    });
    Route::get('/3rd-message-before-appointment', function () {
        \Artisan::call('appointment:3rd-message-before-appointment');
        return response()->json(['status' => 'ok']);
    });
    Route::get('/check-expired-records', function () {
        \Artisan::call('check:expired');
        return response()->json(['status' => 'ok']);
    });
    Route::get('/daily-stats', function () {
        \Artisan::call('appointments:daily-stats');
        return response()->json(['status' => 'ok']);
    });
    Route::get('/check-memberships', function () {
        \Artisan::call('memberships:expire');
        return response()->json(['status' => 'ok']);
    });
});

// Check Session
Route::get('check-session', [App\Http\Controllers\Auth\LoginController::class, 'checkSession'])->name('check_session');

// Password Reset Routes...
Route::get('password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('auth.password.reset');
// Round 4 Auth-C2 — throttle password reset link requests. Without
// this, an attacker can enumerate accounts by timing/response and
// flood any address with reset emails. 5 attempts per IP per hour
// is enough for legitimate users (typo, second device) and far below
// any abuse threshold.
Route::post('password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:5,60')
    ->name('auth.password.resetemail');
Route::get('password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])
    ->middleware('throttle:10,60')
    ->name('auth.password.resettoken');
Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth.common', 'checkAccount'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware(['auth', 'check.ip.restriction'])->group(function () {
            require __DIR__ . '/web/admin-core.php';
            require __DIR__ . '/web/admin-catalogue.php';
            require __DIR__ . '/web/admin-appointments.php';
            require __DIR__ . '/web/admin-reports.php';
        });
    });
