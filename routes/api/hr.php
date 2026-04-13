<?php

// HRM Module API Routes — Datatable endpoints, AJAX data

use App\Http\Controllers\Api\HR\EmployeeDatatableController;
use App\Http\Controllers\Api\HR\LeaveApplicationDatatableController;
use App\Http\Controllers\Api\HR\LeaveBalanceDatatableController;
use App\Http\Controllers\Api\HR\RecruitmentDatatableController;
use App\Http\Controllers\Admin\HR\HrNotificationController;
use App\Http\Controllers\Admin\HR\LeaveApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr')->name('hr.')->group(function () {

    // ── Datatable Endpoints ──
    Route::post('employees/datatable', [EmployeeDatatableController::class, 'datatable'])->name('employees.datatable');
    Route::post('leave-applications/datatable', [LeaveApplicationDatatableController::class, 'datatable'])->name('leave-applications.datatable');
    Route::post('leave-balances/datatable', [LeaveBalanceDatatableController::class, 'datatable'])->name('leave-balances.datatable');
    Route::post('recruitment/datatable', [RecruitmentDatatableController::class, 'datatable'])->name('recruitment.datatable');

    // ── Leave Calendar Data ──
    Route::get('leave-applications/calendar-data', [LeaveApplicationController::class, 'calendarData'])->name('leave-applications.calendar-data');

    // ── HR Notifications ──
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [HrNotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [HrNotificationController::class, 'markRead'])->name('read');
        Route::post('mark-all-read', [HrNotificationController::class, 'markAllRead'])->name('mark-all-read');
    });
});
