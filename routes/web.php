<?php

use App\Models\Leads;
use App\Models\Services;
use App\Models\Appointments;
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
use App\Http\Controllers\Admin\VoucherController;
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

Route::get('/package-advances-sum', function () {
    $sum = PackageAdvances::where('cash_flow', 'in')
        ->where('payment_mode_id', 4)
        ->whereNull('deleted_at')
        ->whereBetween('created_at', ['2024-07-01 00:00:00', '2025-06-30 23:59:59'])
        ->sum('cash_amount');
    
    return response()->json(['sum' => $sum]);
});
Route::get('/services/export-pdf', [ServicesController::class, 'exportPdf'])->name('services.export.pdf');
Route::get('/download-student-membership-patients', [ApiMembershipsController::class, 'downloadStudentMembershipPatients'])
    ->name('download.student.membership.patients');
Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');
Route::get('/download-doctor-upselling-excel', [UpsellingReportController::class, 'downloadDoctorUpsellingExcel'])->name('download.doctor.upselling');
Auth::routes();
// Authentication Routes...

    Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');

    Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('auth.admin.login');

Route::get('/deliver-on-appointment-book', function () {
    \Artisan::call('appointment:deliver-on-appointment-book');
});
Route::get('/2nd-message-on-appointment-day', function () {
    \Artisan::call('appointment:2nd-message-on-appointment-day');
});
Route::get('/3rd-message-before-appointment', function () {
    \Artisan::call('appointment:3rd-message-before-appointment');
});
Route::get('/check-expired-records', function () {
    \Artisan::call('check:expired');
});
Route::get('/daily-stats', function () {
    \Artisan::call('appointments:daily-stats');
});
Route::get('/check-memberships', function () {
    \Artisan::call('memberships:expire');
});
Route::get('/get_deleted', function () {
    $appointments = Appointments::onlyTrashed()->where('deleted_by', 4)->get();
    return view('deleted', get_defined_vars());
});
Route::get('getservices', function () {

    $services = Services::where('slug', '!=', 'all')
        ->where(['parent_id' => 0])

        ->orderBy('id', 'asc')
        ->get();

    $mergedServices = [];
    foreach ($services as $service) {

        $children = Services::where(['parent_id' => $service->id])

            ->orderBy('id', 'asc')->get()->toArray();

        $mergedServices[] = $service->toArray();
        foreach ($children as $child) {
            $mergedServices[] = $child;
        }
    }
    return view('deleted', compact('mergedServices'));
});
Route::get('testupdate', function () {
    $packageService = PackageService::find(198286);
    $packageService->sold_by = auth()->id(); // or any value
    $packageService->save();

\Log::info('Sold by updated to: ' . $packageService->sold_by);
});
Route::get('followup', [DashboardReportsController::class, 'FollowUp'])->name('dashboard.followup');

// Check Session
Route::get('check-session', [App\Http\Controllers\Auth\LoginController::class, 'checkSession'])->name('check_session');

// Password Reset Routes...
Route::get('password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('auth.password.reset');
Route::post('password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('auth.password.resetemail');
Route::get('password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('auth.password.resettoken');
Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::group(['middleware' => ['auth.common', 'checkAccount'], 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::middleware(['auth', 'check.ip.restriction'])->group(function () {
        require __DIR__ . '/web/admin-core.php';
        require __DIR__ . '/web/admin-catalogue.php';
        require __DIR__ . '/web/admin-appointments.php';
        require __DIR__ . '/web/admin-reports.php';
    });
});
