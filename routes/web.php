<?php

use App\Http\Controllers\Admin\ServicesController;
use App\Http\Controllers\Api\MembershipsController as ApiMembershipsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\UpsellingReportController;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Land at the SPA. Was `redirect()->route('login')` which targeted
    // the legacy Blade login page; that route disappears at cutover and
    // the SPA owns its own /login screen anyway.
    return redirect('/admin-v2/');
});

Route::get('/unauthorized', function () {
    return view('unathorized');
})->name('unauthorized');

Auth::routes();
// Authentication Routes...

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');

Route::post('login', [LoginController::class, 'login'])->middleware('throttle:60,1')->name('auth.admin.login');

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
});

// Artisan command routes — protected and rate-limited (these are also handled by scheduler)
Route::middleware(['auth.common', 'throttle:5,1'])->group(function () {
    Route::get('/deliver-on-appointment-book', function () {
        Artisan::call('appointment:deliver-on-appointment-book');

        return response()->json(['status' => 'ok']);
    });
    Route::get('/2nd-message-on-appointment-day', function () {
        Artisan::call('appointment:2nd-message-on-appointment-day');

        return response()->json(['status' => 'ok']);
    });
    Route::get('/3rd-message-before-appointment', function () {
        Artisan::call('appointment:3rd-message-before-appointment');

        return response()->json(['status' => 'ok']);
    });
    Route::get('/check-expired-records', function () {
        Artisan::call('discounts:inactive');
        Artisan::call('packages:expire');

        return response()->json(['status' => 'ok']);
    });
    Route::get('/daily-stats', function () {
        Artisan::call('appointments:daily-stats');

        return response()->json(['status' => 'ok']);
    });
    Route::get('/check-memberships', function () {
        Artisan::call('memberships:expire');

        return response()->json(['status' => 'ok']);
    });
});

// Check Session
Route::get('check-session', [LoginController::class, 'checkSession'])->name('check_session');

// Password Reset Routes...
Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('auth.password.reset');
// Round 4 Auth-C2 — throttle password reset link requests. Without
// this, an attacker can enumerate accounts by timing/response and
// flood any address with reset emails. 5 attempts per IP per hour
// is enough for legitimate users (typo, second device) and far below
// any abuse threshold.
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:5,60')
    ->name('auth.password.resetemail');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])
    ->middleware('throttle:10,60')
    ->name('auth.password.resettoken');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth.common', 'checkAccount'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware(['auth', 'check.ip.restriction'])->group(function () {
            require __DIR__.'/web/admin-core.php';
            require __DIR__.'/web/admin-catalogue.php';
            require __DIR__.'/web/admin-appointments.php';
            require __DIR__.'/web/admin-reports.php';
            require __DIR__.'/web/admin-hr.php';
        });
    });
