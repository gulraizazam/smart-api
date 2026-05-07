<?php

// HRM Module API Routes — Datatable endpoints, AJAX data

use App\Http\Controllers\Admin\HR\EmployeeDocumentController;
use App\Http\Controllers\Admin\HR\HrNotificationController;
use App\Http\Controllers\Admin\HR\LeaveApplicationController as AdminLeaveApplicationController;
use App\Http\Controllers\Api\HR\DashboardController;
use App\Http\Controllers\Api\HR\DepartmentController;
use App\Http\Controllers\Api\HR\DesignationController;
use App\Http\Controllers\Api\HR\EmployeeController;
use App\Http\Controllers\Api\HR\EmployeeDatatableController;
use App\Http\Controllers\Api\HR\LeaveApplicationController;
use App\Http\Controllers\Api\HR\LeaveApplicationDatatableController;
use App\Http\Controllers\Api\HR\LeaveBalanceController;
use App\Http\Controllers\Api\HR\LeaveBalanceDatatableController;
use App\Http\Controllers\Api\HR\LeaveTypeController;
use App\Http\Controllers\Api\HR\MyHrmController;
use App\Http\Controllers\Api\HR\RecruitmentController;
use App\Http\Controllers\Api\HR\RecruitmentDatatableController;
use App\Http\Controllers\Api\HR\RecruitmentInterviewController;
use App\Http\Controllers\Api\Reports\HR\CelebrationsApiController;
use Illuminate\Support\Facades\Route;

// Name prefix is `api.hr.` (full names become `admin.api.hr.*`) to avoid
// collisions with the web-side `admin.hr.*` routes. Laravel's `route()`
// helper resolves a duplicated name to the last-registered route — and
// since routes/api.php loads after routes/web.php, identical names here
// would silently hijack web-facing links (view/edit pages rendered as
// raw JSON). Keep API route names under `api.hr.` so both sides resolve
// to the right URL.
Route::prefix('hr')->name('api.hr.')->group(function () {

    // ── Dashboard (gate applied in-controller) ──
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('kpis', [DashboardController::class, 'kpis'])->name('kpis');
        Route::get('pending-leaves', [DashboardController::class, 'pendingLeaves'])->name('pending-leaves');
        Route::get('upcoming-leaves', [DashboardController::class, 'upcomingLeaves'])->name('upcoming-leaves');
        Route::get('recent-hires', [DashboardController::class, 'recentHiresEndpoint'])->name('recent-hires');
    });

    // ── Datatable Endpoints ──
    Route::post('employees/datatable', [EmployeeDatatableController::class, 'datatable'])->name('employees.datatable');
    Route::post('leave-applications/datatable', [LeaveApplicationDatatableController::class, 'datatable'])->name('leave-applications.datatable');
    Route::post('leave-balances/datatable', [LeaveBalanceDatatableController::class, 'datatable'])->name('leave-balances.datatable');
    Route::post('recruitment/datatable', [RecruitmentDatatableController::class, 'datatable'])->name('recruitment.datatable');

    // ── Leave Calendar Data ──
    Route::get('leave-applications/calendar-data', [AdminLeaveApplicationController::class, 'calendarData'])->name('leave-applications.calendar-data');

    // ── Employees (REST) ──
    // Gates are applied in-controller to match the rest of /api/* HR.
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        // Literal paths before {user} — otherwise `form-options` /
        // `documents/...` would bind to the user wildcard and 404 because
        // no user exists with that id.
        Route::get('form-options', [EmployeeController::class, 'formOptions'])->name('form-options');
        // Document delete — employee-scoped path so the SPA can use the
        // same prefix it uses for upload. Reuses the legacy controller
        // (route-model-bound EmployeeDocument), gates inside the method.
        Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])
            ->name('documents.destroy')
            ->whereNumber('document');
        Route::get('{user}', [EmployeeController::class, 'show'])->name('show')->whereNumber('user');
        Route::patch('{user}', [EmployeeController::class, 'update'])->name('update')->whereNumber('user');
        Route::delete('{user}', [EmployeeController::class, 'destroy'])->name('destroy')->whereNumber('user');
        Route::patch('{user}/status', [EmployeeController::class, 'status'])->name('status')->whereNumber('user');
        // Document upload — same controller as /admin/hr/documents/{user};
        // `documents` is a literal segment so it doesn't collide with the
        // numeric {user} bindings above.
        Route::post('{user}/documents', [EmployeeDocumentController::class, 'store'])
            ->name('documents.store')
            ->whereNumber('user');
        // Avatar upload / remove — multipart `avatar` field. Returns the
        // fresh EmployeeResource so the SPA can swap the cached profile
        // payload in one round-trip.
        Route::post('{user}/avatar', [EmployeeController::class, 'avatarStore'])
            ->name('avatar.store')
            ->whereNumber('user');
        Route::delete('{user}/avatar', [EmployeeController::class, 'avatarDestroy'])
            ->name('avatar.destroy')
            ->whereNumber('user');
    });

    // ── Leave Applications (REST actions) ──
    Route::prefix('leave-applications')->name('leave-applications.')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index'])->name('index');
        Route::post('/', [LeaveApplicationController::class, 'store'])->name('store');
        Route::get('summary', [LeaveApplicationController::class, 'summary'])->name('summary');
        Route::get('calendar', [LeaveApplicationController::class, 'calendar'])->name('calendar');
        Route::get('export', [LeaveApplicationController::class, 'export'])->name('export');
        Route::get('{leaveApplication}', [LeaveApplicationController::class, 'show'])->name('show');
        Route::post('{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('approve');
        Route::post('{leaveApplication}/reject', [LeaveApplicationController::class, 'reject'])->name('reject');
        Route::post('{leaveApplication}/cancel', [LeaveApplicationController::class, 'cancel'])->name('cancel');
    });

    // ── HR Notifications ──
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [HrNotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [HrNotificationController::class, 'markRead'])->name('read');
        Route::post('mark-all-read', [HrNotificationController::class, 'markAllRead'])->name('mark-all-read');
    });

    // ── Self-service (no hr_* permission required — ownership enforced in-controller via Auth::id()) ──
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('profile', [MyHrmController::class, 'profile'])->name('profile.show');
        Route::patch('profile', [MyHrmController::class, 'updateProfile'])->name('profile.update');
        Route::get('documents', [MyHrmController::class, 'documents'])->name('documents.index');
        Route::get('leaves', [MyHrmController::class, 'leaves'])->name('leaves.index');
        Route::post('leaves/apply', [MyHrmController::class, 'applyLeave'])->name('leaves.apply');
        Route::put('leaves/{leaveApplication}', [MyHrmController::class, 'updateLeave'])->name('leaves.update');
        Route::post('leaves/{leaveApplication}/cancel', [MyHrmController::class, 'cancelLeave'])->name('leaves.cancel');
    });

    // ── Departments (gates applied in-controller to match the rest of /api/* HR) ──
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::get('{department}', [DepartmentController::class, 'show'])->name('show');
        Route::patch('{department}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('{department}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // ── Designations (gates applied in-controller to match the rest of /api/* HR) ──
    Route::prefix('designations')->name('designations.')->group(function () {
        Route::get('/', [DesignationController::class, 'index'])->name('index');
        Route::post('/', [DesignationController::class, 'store'])->name('store');
        Route::get('{designation}', [DesignationController::class, 'show'])->name('show');
        Route::patch('{designation}', [DesignationController::class, 'update'])->name('update');
        Route::delete('{designation}', [DesignationController::class, 'destroy'])->name('destroy');
    });

    // ── Leave Balances (gates applied in-controller to match the rest of /api/* HR) ──
    Route::prefix('leave-balances')->name('leave-balances.')->group(function () {
        Route::get('/', [LeaveBalanceController::class, 'index'])->name('index');
        Route::get('matrix', [LeaveBalanceController::class, 'matrix'])->name('matrix');
        Route::get('fiscal-year', [LeaveBalanceController::class, 'fiscalYear'])->name('fiscal-year');
        Route::get('export', [LeaveBalanceController::class, 'export'])->name('export');
        Route::get('employee/{user}', [LeaveBalanceController::class, 'forEmployee'])->name('for-employee');
        Route::post('allocate', [LeaveBalanceController::class, 'allocate'])->name('allocate');
        Route::post('bulk-allocate', [LeaveBalanceController::class, 'bulkAllocate'])->name('bulk-allocate');
        Route::delete('{leaveBalance}', [LeaveBalanceController::class, 'destroy'])->name('destroy');
    });

    // ── Recruitment (gates applied in-controller to match the rest of /api/* HR) ──
    Route::prefix('recruitment')->name('recruitment.')->group(function () {
        Route::get('/', [RecruitmentController::class, 'index'])->name('index');
        Route::post('/', [RecruitmentController::class, 'store'])->name('store');
        Route::get('summary', [RecruitmentController::class, 'summary'])->name('summary');

        // Flat interview endpoints (legacy-compatible + by-id ops)
        Route::post('interviews', [RecruitmentInterviewController::class, 'store'])->name('interviews.store');
        Route::patch('interviews/{interview}', [RecruitmentInterviewController::class, 'update'])->name('interviews.update');
        Route::delete('interviews/{interview}', [RecruitmentInterviewController::class, 'destroy'])->name('interviews.destroy');

        // Candidate resource
        Route::get('{candidate}', [RecruitmentController::class, 'show'])->name('show');
        Route::patch('{candidate}', [RecruitmentController::class, 'update'])->name('update');
        Route::delete('{candidate}', [RecruitmentController::class, 'destroy'])->name('destroy');
        Route::patch('{candidate}/status', [RecruitmentController::class, 'updateStatus'])->name('status');
        Route::get('{candidate}/cv', [RecruitmentController::class, 'previewCv'])->name('cv.preview');
        Route::get('{candidate}/cv/download', [RecruitmentController::class, 'downloadCv'])->name('cv.download');
        Route::post('{candidate}/convert', [RecruitmentController::class, 'convert'])->name('convert');

        // Nested interview endpoints (under candidate)
        Route::get('{candidate}/interviews', [RecruitmentInterviewController::class, 'index'])->name('interviews.index');
        Route::post('{candidate}/interviews', [RecruitmentInterviewController::class, 'store'])->name('interviews.create');
    });

    // ── Leave Types (gates applied in-controller to match the rest of /api/* HR) ──
    Route::prefix('leave-types')->name('leave-types.')->group(function () {
        Route::get('/', [LeaveTypeController::class, 'index'])->name('index');
        Route::post('/', [LeaveTypeController::class, 'store'])->name('store');
        Route::patch('{leaveType}', [LeaveTypeController::class, 'update'])->name('update');
        Route::delete('{leaveType}', [LeaveTypeController::class, 'destroy'])->name('destroy');
    });

    // ── Reports (read-only) ──
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('celebrations', CelebrationsApiController::class)->name('celebrations');
        Route::get('celebrations/birthdays/export', [CelebrationsApiController::class, 'exportBirthdays'])
            ->name('celebrations.birthdays.export');
        Route::get('celebrations/anniversaries/export', [CelebrationsApiController::class, 'exportAnniversaries'])
            ->name('celebrations.anniversaries.export');
    });
});
