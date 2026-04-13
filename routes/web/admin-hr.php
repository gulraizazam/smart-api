<?php

// HRM Module Routes — Employee Management, Leave Management, Recruitment, Dashboard

use App\Http\Controllers\Admin\HR\DepartmentController;
use App\Http\Controllers\Admin\HR\DesignationController;
use App\Http\Controllers\Admin\HR\EmployeeController;
use App\Http\Controllers\Admin\HR\EmployeeDocumentController;
use App\Http\Controllers\Admin\HR\HRDashboardController;
use App\Http\Controllers\Admin\HR\InterviewController;
use App\Http\Controllers\Admin\HR\LeaveApplicationController;
use App\Http\Controllers\Admin\HR\LeaveBalanceController;
use App\Http\Controllers\Admin\HR\LeaveTypeController;
use App\Http\Controllers\Admin\HR\MyHrmController;
use App\Http\Controllers\Admin\HR\RecruitmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr')->name('hr.')->group(function () {

    // ── Dashboard ──
    Route::get('dashboard', [HRDashboardController::class, 'index'])
        ->middleware('permission:hr_dashboard_view')
        ->name('dashboard');

    // ── Departments ──
    Route::prefix('departments')->name('departments.')->middleware('permission:hr_departments_manage')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::put('{department}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('{department}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // ── Designations ──
    Route::prefix('designations')->name('designations.')->middleware('permission:hr_designations_manage')->group(function () {
        Route::get('/', [DesignationController::class, 'index'])->name('index');
        Route::post('/', [DesignationController::class, 'store'])->name('store');
        Route::put('{designation}', [DesignationController::class, 'update'])->name('update');
        Route::delete('{designation}', [DesignationController::class, 'destroy'])->name('destroy');
    });

    // ── Employees ──
    Route::prefix('employees')->name('employees.')->middleware('permission:hr_employees_view')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('{user}', [EmployeeController::class, 'show'])->name('show');
        Route::put('{user}', [EmployeeController::class, 'update'])->name('update')->middleware('permission:hr_employees_edit');
    });

    // ── Employee Documents ──
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::post('{user}', [EmployeeDocumentController::class, 'store'])->name('store')->middleware('permission:hr_documents_manage');
        Route::get('{document}/preview', [EmployeeDocumentController::class, 'preview'])->name('preview');
        Route::get('{document}/download', [EmployeeDocumentController::class, 'download'])->name('download');
        Route::delete('{document}', [EmployeeDocumentController::class, 'destroy'])->name('destroy')->middleware('permission:hr_documents_manage');
    });

    // ── Leave Types ──
    Route::prefix('leave-types')->name('leave-types.')->middleware('permission:hr_leave_manage')->group(function () {
        Route::get('/', [LeaveTypeController::class, 'index'])->name('index');
        Route::post('/', [LeaveTypeController::class, 'store'])->name('store');
        Route::put('{leaveType}', [LeaveTypeController::class, 'update'])->name('update');
        Route::delete('{leaveType}', [LeaveTypeController::class, 'destroy'])->name('destroy');
    });

    // ── Leave Balances ──
    Route::prefix('leave-balances')->name('leave-balances.')->middleware('permission:hr_leave_manage')->group(function () {
        Route::get('/', [LeaveBalanceController::class, 'index'])->name('index');
        Route::post('allocate', [LeaveBalanceController::class, 'allocate'])->name('allocate');
        Route::get('bulk-allocate', [LeaveBalanceController::class, 'bulkAllocateForm'])->name('bulk-allocate');
        Route::post('bulk-allocate', [LeaveBalanceController::class, 'bulkAllocate'])->name('bulk-allocate.store');
        Route::get('export', [LeaveBalanceController::class, 'export'])->name('export');
    });

    // ── Leave Applications ──
    Route::prefix('leave-applications')->name('leave-applications.')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index'])->name('index')->middleware('permission:hr_leave_view');
        Route::post('/', [LeaveApplicationController::class, 'store'])->name('store')->middleware('permission:hr_leave_apply');
        Route::post('{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('approve')->middleware('permission:hr_leave_approve');
        Route::post('{leaveApplication}/reject', [LeaveApplicationController::class, 'reject'])->name('reject')->middleware('permission:hr_leave_approve');
        Route::post('{leaveApplication}/cancel', [LeaveApplicationController::class, 'cancel'])->name('cancel')->middleware('permission:hr_leave_apply|hr_leave_approve');
        Route::get('calendar', [LeaveApplicationController::class, 'calendar'])->name('calendar')->middleware('permission:hr_leave_view');
    });

    // ── Recruitment ──
    Route::prefix('recruitment')->name('recruitment.')->middleware('permission:hr_recruitment_view')->group(function () {
        Route::get('/', [RecruitmentController::class, 'index'])->name('index');
        Route::post('/', [RecruitmentController::class, 'store'])->name('store')->middleware('permission:hr_recruitment_create');
        Route::get('{candidate}', [RecruitmentController::class, 'show'])->name('show');
        Route::put('{candidate}', [RecruitmentController::class, 'update'])->name('update')->middleware('permission:hr_recruitment_edit');
        Route::get('{candidate}/preview-cv', [RecruitmentController::class, 'previewCv'])->name('preview-cv');
        Route::get('{candidate}/download-cv', [RecruitmentController::class, 'downloadCv'])->name('download-cv');
        Route::put('{candidate}/status', [RecruitmentController::class, 'updateStatus'])->name('update-status')->middleware('permission:hr_recruitment_edit');
        Route::get('{candidate}/convert', [RecruitmentController::class, 'convert'])->name('convert')->middleware('permission:hr_recruitment_edit');
        Route::post('{candidate}/convert', [RecruitmentController::class, 'storeConversion'])->name('convert.store')->middleware('permission:hr_recruitment_edit');
        Route::delete('{candidate}', [RecruitmentController::class, 'destroy'])->name('destroy')->middleware('permission:hr_recruitment_delete');
    });

    // ── Interviews ──
    Route::post('interviews', [InterviewController::class, 'store'])
        ->middleware('permission:hr_recruitment_create')
        ->name('interviews.store');

    // ── Self-Service (My HR) ──
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('profile', [MyHrmController::class, 'profile'])->name('profile');
        Route::get('leaves', [MyHrmController::class, 'leaves'])->name('leaves');
        Route::post('leaves/apply', [MyHrmController::class, 'applyLeave'])->name('leaves.apply');
        Route::put('leaves/{leaveApplication}', [MyHrmController::class, 'updateLeave'])->name('leaves.update');
        Route::post('leaves/{leaveApplication}/cancel', [MyHrmController::class, 'cancelLeave'])->name('leaves.cancel');
    });
});
