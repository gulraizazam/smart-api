<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->middleware('throttle:30,1');

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
        ->middleware('throttle:30,1')
        ->name('login');
    Route::post('refresh', [\App\Http\Controllers\Api\V2\AuthController::class, 'refresh'])
        ->middleware('throttle:30,1')
        ->name('refresh');
    Route::post('logout', [\App\Http\Controllers\Api\V2\AuthController::class, 'logout'])
        ->middleware('auth:api_passport')
        ->name('logout');
});

// Build provenance. Public + deliberately minimal (just the short commit SHA
// and build time — no env, versions, or secrets) so "what's deployed on api?"
// is a one-second check and scripts/deploy-api.sh can verify the upload. The
// file is stamped on the server by the deploy script; absent (e.g. locally or
// pre-first-deploy) it reports 'unknown' rather than erroring. Additive — crm2
// does not use it.
Route::get('version', function () {
    $file = storage_path('app/deploy-version.json');
    $data = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

    return response()->json([
        'commit' => $data['commit'] ?? 'unknown',
        'builtAt' => $data['builtAt'] ?? null,
    ]);
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
    require __DIR__ . '/api/whatsapp.php';
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

// WhatsApp Cloud API webhook (Meta). Public by design — Meta's servers can't
// log in: GET is the verify-token handshake, POST is authenticated by the
// X-Hub-Signature-256 HMAC (app secret) inside the controller. CSRF is
// exempted for this URI in App\Http\Middleware\VerifyCsrfToken (Meta sends
// no session cookie, so the no-cookie skip would clear it anyway).
Route::get('whatsapp/webhook', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'verify'])
    ->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'receive'])
    ->name('whatsapp.webhook.receive');
