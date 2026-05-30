<?php

use App\Http\Controllers\Admin\InventoryReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\LeadsController as AdminLeadsController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\Api\PlansController as ApiPlansController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\BundlesController as AdminBundlesController;
use App\Http\Controllers\Api\BundlesController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Admin\RefundsController;
use App\Http\Controllers\Api\RefundsController as ApiRefundsController;
use App\Http\Controllers\Admin\RegionsController;
use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\PatientsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\DiscountsController;
use App\Http\Controllers\Api\DiscountsController as ApiDiscountsController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\Admin\UserVouchersController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\CustomFormsController;
use App\Http\Controllers\Admin\LeadSourcesController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ApplicationUserController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\LeadStatusesController;
use App\Http\Controllers\Admin\PaymentModesController;
use App\Http\Controllers\Admin\SMSTemplatesController;
use App\Http\Controllers\Admin\CentreTargetsController;
use App\Http\Controllers\Admin\ResourceRotasController;
use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Http\Controllers\Admin\TransferProductController;
use App\Http\Controllers\Admin\AppointmentimageController;
use App\Http\Controllers\Admin\TransferProductsController;
use App\Http\Controllers\Admin\AppointmentsPlansController;
use App\Http\Controllers\Admin\AppointmentMedicalController;
use App\Http\Controllers\Admin\ConsultancyInvoiceController;
use App\Http\Controllers\Admin\AppointmentStatusesController;
use App\Http\Controllers\Admin\CustomFormFeedbacksController;
use App\Http\Controllers\Admin\UserOperatorSettingsController;
use App\Http\Controllers\Admin\AppointmentMeasurementController;
use App\Http\Controllers\Api\MembershipsController;
use App\Http\Controllers\Api\MembershipTypesController;
use App\Http\Controllers\Admin\Patients\MedicalHistoryController;
use App\Http\Controllers\Admin\Patients\MeasurementHistoryController;
use App\Http\Controllers\Admin\Patients\CustomFormFeedbacksController as PatientCustomFormController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Api\BusinessClosureController;
use App\Http\Controllers\Api\ScheduleController;

/*
|-----------------------------------------viewDetail---------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded within a group which is assigned the "api" middleware
| group. Enjoy building your API!
|
*/

Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->middleware('throttle:60,1');

// SPA cookie/bearer-mode logout + password reset. These were always part of
// the SPA contract (src/lib/auth.tsx, src/routes/forgot-password.tsx,
// src/routes/reset-password.tsx) but the routes were dropped in a refactor,
// leaving the SPA POST-ing at 404s. Restored ahead of the Blade cutover so the
// SPA stops depending on the legacy web auth routes (Auth::routes() in web.php,
// deleted at cutover). Public by design: logout must be idempotent for stale
// tabs; password reset runs while the user is logged out.
Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
Route::post('password/email', [\App\Http\Controllers\Api\ForgotPasswordController::class, 'send'])
    ->middleware('throttle:5,60');
Route::post('password/reset', [\App\Http\Controllers\Api\ForgotPasswordController::class, 'reset'])
    ->middleware('throttle:6,1');

// v2 auth — Passport password grant. Runs alongside the Sanctum /api/login
// during the staged migration. See app/Http/Controllers/Api/V2/AuthController.
Route::prefix('v2/auth')->name('api.v2.auth.')->group(function (): void {
    Route::post('login', [\App\Http\Controllers\Api\V2\AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');
    Route::post('refresh', [\App\Http\Controllers\Api\V2\AuthController::class, 'refresh'])
        ->middleware('throttle:10,1')
        ->name('refresh');
    Route::post('logout', [\App\Http\Controllers\Api\V2\AuthController::class, 'logout'])
        ->middleware('auth:api_passport')
        ->name('logout');
});

// Staged Sanctum → Passport migration: auth.api.dual accepts session,
// Passport, and Sanctum tokens. Strict superset of auth.common — every
// existing caller keeps working; new Passport tokens from /api/v2/auth/login
// also authenticate here.
Route::middleware('auth.api.dual')->name('admin.')->group(function () {
    require __DIR__ . '/api/dashboard.php';
    require __DIR__ . '/api/admin-config.php';
    require __DIR__ . '/api/catalogue.php';
    require __DIR__ . '/api/finance.php';
    require __DIR__ . '/api/appointments-admin.php';
    require __DIR__ . '/api/inventory.php';
    require __DIR__ . '/api/api-resources.php';
    require __DIR__ . '/cashflow.php';
    require __DIR__ . '/api/hr.php';
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    /** @var \App\Models\User|null $user */
    $user = $request->user();
    if (! $user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], 401);
    }

    return response()->json([
        'success' => true,
        'message' => 'Success',
        'data' => $user->toAuthPayload(),
    ]);
});

// Meta Conversion API Routes
Route::middleware('auth.api.dual')->prefix('meta')->name('meta.')->group(function () {
    Route::post('test-connection', [\App\Http\Controllers\Admin\MetaConversionController::class, 'testConnection'])->name('test');
    Route::post('send-lead-status', [\App\Http\Controllers\Admin\MetaConversionController::class, 'sendLeadStatus'])->name('lead-status');
});
