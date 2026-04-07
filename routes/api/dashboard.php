<?php

// Dashboard, Doctor Dashboard, Google Reviews, System Targets

use Illuminate\Support\Facades\Route;


    // Dashboard API Routes
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('config', [\App\Http\Controllers\Api\DashboardController::class, 'getConfig'])->name('config');
        Route::get('stats', [\App\Http\Controllers\Api\DashboardController::class, 'getStats'])->name('stats');
        Route::get('activities', [\App\Http\Controllers\Api\DashboardController::class, 'getActivities'])->name('activities');
        Route::get('collection-by-centre', [\App\Http\Controllers\Api\DashboardController::class, 'collectionByCentre'])->name('collection_by_centre');
        Route::get('revenue-by-centre', [\App\Http\Controllers\Api\DashboardController::class, 'revenueByCentre'])->name('revenue_by_centre');
        Route::get('collection-by-service-category', [\App\Http\Controllers\Api\DashboardController::class, 'collectionByServiceCategory'])->name('collection_by_service_category');
        Route::get('revenue-by-service-category', [\App\Http\Controllers\Api\DashboardController::class, 'revenueByServiceCategory'])->name('revenue_by_service_category');
        Route::get('revenue-by-service', [\App\Http\Controllers\Api\DashboardController::class, 'revenueByService'])->name('revenue_by_service');
        Route::get('appointment-by-status', [\App\Http\Controllers\Api\DashboardController::class, 'appointmentByStatus'])->name('appointment_by_status');
        Route::get('appointment-by-type', [\App\Http\Controllers\Api\DashboardController::class, 'appointmentByType'])->name('appointment_by_type');
        Route::get('centre-wise-arrival', [\App\Http\Controllers\Api\DashboardController::class, 'centreWiseArrival'])->name('centre_wise_arrival');
        Route::get('csr-wise-arrival', [\App\Http\Controllers\Api\DashboardController::class, 'csrWiseArrival'])->name('csr_wise_arrival');
        Route::get('call-wise-arrival', [\App\Http\Controllers\Api\DashboardController::class, 'callWiseArrival'])->name('call_wise_arrival');
        Route::get('doctor-wise-conversion', [\App\Http\Controllers\Api\DashboardController::class, 'doctorWiseConversion'])->name('doctor_wise_conversion');
        Route::get('doctor-wise-feedback', [\App\Http\Controllers\Api\DashboardController::class, 'doctorWiseFeedback'])->name('doctor_wise_feedback');
        Route::get('unattended-payments', [\App\Http\Controllers\Api\DashboardController::class, 'unattendedPayments'])->name('unattended_payments');
        Route::get('overdue-treatments', [\App\Http\Controllers\Api\DashboardController::class, 'overdueTreatments'])->name('overdue_treatments');
        Route::get('doctor-upselling-data', [\App\Http\Controllers\Api\DashboardController::class, 'doctorUpsellingData'])->name('doctor_upselling_data');
    });

    // Doctor Dashboard API Routes
    Route::prefix('doctor-dashboard')->name('doctor_dashboard.')->group(function () {
        Route::get('kpis', [\App\Http\Controllers\DoctorDashboardController::class, 'getKpis'])->name('kpis');
        Route::get('hero', [\App\Http\Controllers\DoctorDashboardController::class, 'getHeroData'])->name('hero');
        Route::get('appointments', [\App\Http\Controllers\DoctorDashboardController::class, 'getTodaysAppointments'])->name('appointments');
        Route::get('benchmarks', [\App\Http\Controllers\DoctorDashboardController::class, 'getBenchmarks'])->name('benchmarks');
    });

    // Google Reviews API Routes
    Route::prefix('google-reviews')->name('google_reviews.')->group(function () {
        Route::get('data', [\App\Http\Controllers\Admin\GoogleReviewsController::class, 'getData'])->name('data');
        Route::post('save', [\App\Http\Controllers\Admin\GoogleReviewsController::class, 'save'])->name('save');
    });

    // System Targets API Routes
    Route::prefix('system-targets')->name('system_targets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CentreTargetsController::class, 'getSystemTargets'])->name('index');
        Route::post('save', [\App\Http\Controllers\Admin\CentreTargetsController::class, 'saveSystemTarget'])->name('save');
    });
