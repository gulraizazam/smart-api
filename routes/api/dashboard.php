<?php

// Dashboard, Doctor Dashboard, Google Reviews, System Targets

use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\GoogleReviewsController;
use App\Http\Controllers\Admin\UserBranchesController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\V1\ManagementDashboardApiController;
use App\Http\Controllers\DoctorDashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard API Routes
Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('config', [DashboardController::class, 'getConfig'])->name('config');
    Route::get('stats', [DashboardController::class, 'getStats'])->name('stats');
    Route::get('activities', [DashboardController::class, 'getActivities'])->name('activities');
    Route::get('collection-by-centre', [DashboardController::class, 'collectionByCentre'])->name('collection_by_centre');
    Route::get('revenue-by-centre', [DashboardController::class, 'revenueByCentre'])->name('revenue_by_centre');
    Route::get('collection-by-service-category', [DashboardController::class, 'collectionByServiceCategory'])->name('collection_by_service_category');
    Route::get('revenue-by-service-category', [DashboardController::class, 'revenueByServiceCategory'])->name('revenue_by_service_category');
    Route::get('revenue-by-service', [DashboardController::class, 'revenueByService'])->name('revenue_by_service');
    Route::get('appointment-by-status', [DashboardController::class, 'appointmentByStatus'])->name('appointment_by_status');
    Route::get('appointment-by-type', [DashboardController::class, 'appointmentByType'])->name('appointment_by_type');
    Route::get('centre-wise-arrival', [DashboardController::class, 'centreWiseArrival'])->name('centre_wise_arrival');
    Route::get('csr-wise-arrival', [DashboardController::class, 'csrWiseArrival'])->name('csr_wise_arrival');
    Route::get('call-wise-arrival', [DashboardController::class, 'callWiseArrival'])->name('call_wise_arrival');
    Route::get('doctor-wise-conversion', [DashboardController::class, 'doctorWiseConversion'])->name('doctor_wise_conversion');
    Route::get('doctor-wise-feedback', [DashboardController::class, 'doctorWiseFeedback'])->name('doctor_wise_feedback');
    Route::get('unattended-payments', [DashboardController::class, 'unattendedPayments'])->name('unattended_payments');
    Route::get('overdue-treatments', [DashboardController::class, 'overdueTreatments'])->name('overdue_treatments');
    Route::get('doctor-upselling-data', [DashboardController::class, 'doctorUpsellingData'])->name('doctor_upselling_data');
});

// Doctor Dashboard API Routes
Route::prefix('doctor-dashboard')->name('doctor_dashboard.')->group(function () {
    Route::get('kpis', [DoctorDashboardController::class, 'getKpis'])->name('kpis');
    Route::get('hero', [DoctorDashboardController::class, 'getHeroData'])->name('hero');
    Route::get('appointments', [DoctorDashboardController::class, 'getTodaysAppointments'])->name('appointments');
    Route::get('benchmarks', [DoctorDashboardController::class, 'getBenchmarks'])->name('benchmarks');
});

// User-branch assignment API (management dashboard scoping).
Route::match(['put', 'post'], 'user-branches/{id}', [UserBranchesController::class, 'update'])
    ->middleware(['permission:users_manage', 'throttle:30,1'])
    ->name('user_branches.update');

// Management Dashboard API Routes — one composite endpoint per section + cross-cutting.
// throttle is per-authenticated-user; sections fan out to ~10 endpoints on load,
// so the ceiling is sized above a cold-load burst but below abuse levels.
Route::prefix('management-dashboard')
    ->name('management_dashboard_api.')
    ->middleware(['can:management_dashboard.view', 'throttle:120,1'])
    ->group(function () {
        Route::get('overview', [ManagementDashboardApiController::class, 'overview'])->name('overview');
        Route::get('branches', [ManagementDashboardApiController::class, 'branches'])->name('branches');
        Route::get('people', [ManagementDashboardApiController::class, 'people'])->name('people');
        Route::get('patients', [ManagementDashboardApiController::class, 'patients'])->name('patients');
        Route::get('new-returning', [ManagementDashboardApiController::class, 'newReturning'])->name('new_returning');
        Route::get('service-category-trend', [ManagementDashboardApiController::class, 'serviceCategoryTrend'])->name('service_category_trend');
        Route::get('avg-transaction-value', [ManagementDashboardApiController::class, 'avgTransactionValue'])->name('avg_transaction_value');
        Route::get('avg-conversion-value', [ManagementDashboardApiController::class, 'avgConversionValue'])->name('avg_conversion_value');
        Route::get('lead-gender-funnel', [ManagementDashboardApiController::class, 'leadGenderFunnel'])->name('lead_gender_funnel');
        Route::get('lead-service-interest', [ManagementDashboardApiController::class, 'leadServiceInterest'])->name('lead_service_interest');
        Route::get('gender-revenue', [ManagementDashboardApiController::class, 'genderRevenue'])->name('gender_revenue');
        Route::get('today-activities', [ManagementDashboardApiController::class, 'todayActivities'])->name('today_activities');
    });

// Google Reviews API Routes
Route::prefix('google-reviews')->name('google_reviews.')->group(function () {
    Route::get('data', [GoogleReviewsController::class, 'getData'])->name('data');
    Route::post('save', [GoogleReviewsController::class, 'save'])->name('save');
});

// System Targets API Routes
Route::prefix('system-targets')->name('system_targets.')->group(function () {
    Route::get('/', [CentreTargetsController::class, 'getSystemTargets'])->name('index');
    Route::post('save', [CentreTargetsController::class, 'saveSystemTarget'])->name('save');
});
