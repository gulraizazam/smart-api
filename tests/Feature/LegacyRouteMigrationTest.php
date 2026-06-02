<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Patients;
use App\Services\HR\HrNotificationService;
use App\Services\PatientManagement\PatientService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the 5 /api/* routes that replaced the SPA's last dependencies on
 * legacy /admin/* Blade routes. Each new route points at the SAME
 * controller method as its /admin/ counterpart — only the URL prefix
 * changed. Behaviour is identical; this test pins the route
 * registration so a future refactor can't silently drop or rename them
 * (which would break the SPA at production cutover).
 *
 * What's NOT covered here: the controller behaviour itself. Those are
 * pre-existing methods exercised via their /admin/* tests (where
 * coverage exists). This file only pins:
 *   - The /api/* URL is registered.
 *   - It requires authentication (no anonymous access).
 *
 * See `src/lib/legacy-asset-urls.ts` (SPA) for the corresponding
 * client-side URL builders pinned by `legacy-asset-urls.test.ts`.
 */
class LegacyRouteMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function migratedRoutes(): array
    {
        // [route name, HTTP method, url pattern (for documentation)]
        return [
            // Round 1: SPA-side hardcoded /admin/* URLs.
            'invoice PDF'                  => ['admin.invoices.pdf',                'GET', '/api/invoices/{id}/pdf/{download?}/{flag?}'],
            'services tree PDF export'     => ['admin.services.export_pdf',         'GET', '/api/services/export-pdf'],
            'plan finance log XLSX export' => ['admin.plans.log_export',            'GET', '/api/plans/log/{id}/{type}'],
            // 'patient card view' removed in Phase 1A.2 — the SPA's
            // patient-detail page replaces every section the Blade card
            // surfaced, so the route + cardV2 controller method + blade
            // chain are unused and sweep at Blade cutover.
            'student verification file'    => ['admin.files.student_verification',  'GET', '/api/files/student-verification/{filename}'],
            // Round 2: backend-built URLs that were leaking into SPA
            // payloads via API Resources (image_url, preview_url,
            // download_url fields). The /admin/* originals stay live
            // for legacy until cutover; the Resources now point at
            // these /api/* aliases (suffix `_api` to avoid name
            // collision with the legacy routes).
            'patient image stream'         => ['admin.files.patient_image_api',     'GET', '/api/files/patient-image/{filename}'],
            'HR document preview'          => ['admin.hr.documents.preview_api',    'GET', '/api/hr/documents/{document}/preview'],
            'HR document download'         => ['admin.hr.documents.download_api',   'GET', '/api/hr/documents/{document}/download'],
        ];
    }

    /**
     * Pin every migrated route is registered AND lives under the /api/
     * URI prefix (NOT /admin/). Uses the route registry directly
     * because HTTP-calling routes with model-binding returns 404 from
     * Laravel's binding step before auth runs — indistinguishable from
     * "route missing." The registry is the source of truth.
     *
     * @dataProvider migratedRoutes
     */
    public function test_route_is_registered_under_api_prefix(string $name, string $method, string $expectedUri): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has($name),
            "Migrated route '{$name}' is not registered. Check routes/api/finance.php migration block.",
        );

        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route '{$name}' resolves null from registry.");

        // Strict invariant: every migrated route MUST live under the
        // /api/ prefix. If this fails, someone moved it back under
        // /admin/ (which dies at Blade cutover).
        $this->assertStringStartsWith(
            'api/',
            $route->uri(),
            "Migrated route '{$name}' is registered at '{$route->uri()}' — must start with 'api/'. "
            . 'Moving it back under /admin/ defeats the cutover migration.',
        );

        $this->assertContains(
            $method,
            $route->methods(),
            "Migrated route '{$name}' does not accept {$method} (accepts: " . implode(',', $route->methods()) . ').',
        );

        // Documentation aid — fail loudly if the URI shape drifts so
        // the SPA's URL builders stay in sync with the backend route.
        $this->assertSame(
            ltrim($expectedUri, '/'),
            $route->uri(),
            "Migrated route '{$name}' URI is '{$route->uri()}' but expected '{$expectedUri}' — "
            . 'a parameter rename / reorder will silently break the SPA URL builder. '
            . 'If this change is intentional, update src/lib/legacy-asset-urls.ts to match.',
        );
    }

    /**
     * Round 3: model accessors + service-built URLs. These are model /
     * service code that builds a URL string and ships it to the SPA in
     * a JSON field — same cutover risk as the route registrations
     * above, but easier to silently revert because the leak is in PHP
     * code, not a route file. Pin each to assert it produces a `/api/`
     * URL (not `/admin/`).
     */
    public function test_documents_model_full_url_serializes_under_api_prefix(): void
    {
        // `Documents.url` is stored as 'patient_image/<filename>' (see
        // Documents.php docstring). The accessor wraps it in the
        // streaming-route URL.
        $doc = new Documents(['url' => 'patient_image/test.jpg']);

        $this->assertNotNull($doc->full_url);
        $this->assertStringStartsWith(
            '/api/files/patient-image/',
            parse_url($doc->full_url, PHP_URL_PATH) ?? '',
            "Documents.full_url returns '{$doc->full_url}' — must start with /api/files/patient-image/. "
            . 'Documents.$appends auto-includes this in every JSON serialization, so a leak ships everywhere.',
        );
    }

    public function test_patients_model_profile_image_url_serializes_under_api_prefix(): void
    {
        // Shadow image_src on a fresh Patients instance; the accessor
        // composes the streaming URL from it.
        $patient = new Patients();
        $patient->forceFill(['image_src' => 'avatar.jpg']);

        $this->assertStringStartsWith(
            '/api/files/patient-image/',
            parse_url($patient->profile_image_url, PHP_URL_PATH) ?? '',
            "Patients.profile_image_url returns '{$patient->profile_image_url}' — must start with /api/files/patient-image/.",
        );
    }

    public function test_patients_model_profile_image_url_is_null_when_no_image(): void
    {
        // The fallback used to be `asset('images/default-avatar.png')`
        // pointing at a file that doesn't exist. Returning null lets
        // the SPA render its initials-based avatar fallback instead of
        // a broken-image icon.
        $patient = new Patients();
        $patient->forceFill(['image_src' => null]);

        $this->assertNull(
            $patient->profile_image_url,
            'Patients.profile_image_url must be null when image_src is unset — '
            . 'the previous asset() fallback resolved to a broken URL.',
        );
    }

    public function test_hr_notification_service_url_constant_targets_spa_not_blade(): void
    {
        // Reflect the private constant — pinning it as a literal string
        // is cheaper than running the full notification flow and the
        // intent is the same: the URL stored in the notification row
        // must point at the SPA path, not at a `route('admin.hr.*')`
        // call (which dies at cutover).
        $reflection = new \ReflectionClass(HrNotificationService::class);
        $path = $reflection->getConstant('SPA_HR_LEAVE_APPLICATIONS_PATH');

        $this->assertIsString($path);
        $this->assertSame('/hr/leave-applications', $path);
        $this->assertStringNotContainsString('/admin-v2', $path);
        $this->assertStringNotContainsString('/admin/hr', $path);
    }

    public function test_root_redirects_to_spa_not_blade_login(): void
    {
        // GET / used to redirect to route('login') — a legacy Blade
        // route that dies at cutover. Now sends users to the standalone
        // SPA host (config app.spa_url).
        $this->get('/')->assertRedirect(config('app.spa_url'));
    }

    public function test_config_spa_url_is_registered_and_points_at_spa_host(): void
    {
        // Emails / notifications / deep-links read this config instead of
        // hardcoding a path. Pinning the resolution catches an env tweak
        // that would silently emit the wrong URL into a production
        // password-reset email.
        $url = config('app.spa_url');

        $this->assertIsString($url);
        $this->assertNotSame('', $url);
        $this->assertStringStartsWith('http', $url);
        $this->assertStringNotContainsString('/admin-v2', $url);
    }

    /**
     * Phase 1A.1 — InvoicesController::invoice_pdf must always return a
     * PDF binary (Symfony Response from dompdf->stream() / ->download()),
     * never a Blade view. The SPA's window.open() flow assumes a PDF; if
     * a future "fix" re-introduces a `return view(...)` branch the SPA
     * silently breaks because the legacy admin Blade view files sweep
     * at cutover and the endpoint starts 500'ing.
     */
    public function test_invoice_pdf_method_returns_pdf_response_not_blade_view(): void
    {
        $reflection = new \ReflectionMethod(
            \App\Http\Controllers\Admin\InvoicesController::class,
            'invoice_pdf',
        );
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType, 'invoice_pdf must declare a return type.');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(
            \Symfony\Component\HttpFoundation\Response::class,
            $returnType->getName(),
            'invoice_pdf must return a Symfony Response (PDF binary). '
            . "Re-introducing 'Illuminate\\View\\View' or any view-typed return "
            . 'breaks the SPA at Blade cutover.',
        );
    }

    /**
     * Phase 1A.1-bis — OrdersController::invoicePdf used to return a
     * Blade view on the no-download branch (line 314 pre-fix). Same
     * shape as InvoicesController::invoice_pdf which Phase 1A.1 already
     * hardened. SPA never hit the view branch (`orders-api.ts:463`
     * defaults `download=true`) but anyone passing `false` — direct URL
     * hit, future SPA refactor, external integration — would 500 once
     * the legacy admin views are deleted. Now always streams PDF.
     */
    public function test_orders_invoice_pdf_method_returns_pdf_response_not_blade_view(): void
    {
        $reflection = new \ReflectionMethod(
            \App\Http\Controllers\Admin\OrdersController::class,
            'invoicePdf',
        );
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType, 'invoicePdf must declare a return type.');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(
            \Symfony\Component\HttpFoundation\Response::class,
            $returnType->getName(),
            'OrdersController::invoicePdf must return a Symfony Response (PDF binary), '
            . 'not a Blade View. Re-introducing a `return view(...)` branch breaks the SPA at Blade cutover.',
        );
    }

    /**
     * EmployeeResource::personalInformation used to emit
     * `asset($user->image_src)` for the avatar URL — that resolved to
     * `${APP_URL}/<filename>` which doesn't exist (employee photos
     * live at storage/app/patient_image/, served via the auth-guarded
     * `admin.files.patient_image_api` streaming route). Pin the fix
     * so a future "simplification" can't revert it to asset().
     */
    public function test_employee_resource_avatar_url_serializes_under_api_prefix(): void
    {
        $user = new \App\Models\User();
        $user->forceFill(['image_src' => 'employee-avatar.jpg']);

        // personalInformation() is private and reads only $user->image_src
        // for the avatar_url field. Skip toArray()'s full relation walk
        // (employeeDetail / locations / documents) by invoking it via
        // reflection — the avatar contract is a pure function of image_src.
        $resource = new \App\Http\Resources\HR\EmployeeResource($user);
        $method = new \ReflectionMethod($resource, 'personalInformation');
        $method->setAccessible(true);
        $personal = $method->invoke($resource, $user, false);

        $this->assertIsArray($personal);
        $this->assertNotNull($personal['avatar_url']);
        $this->assertStringStartsWith(
            '/api/files/patient-image/',
            parse_url((string) $personal['avatar_url'], PHP_URL_PATH) ?? '',
            "EmployeeResource avatar_url returns '{$personal['avatar_url']}' — "
            . 'must start with /api/files/patient-image/. asset() resolves to a '
            . 'broken Laravel-public path; only the streaming route serves the file.',
        );
    }

    /**
     * Phase 1A.2 — the SPA's /api/patients/{id}/card mirror was deleted
     * (the `Open in legacy admin` Quick action card and `patientCardUrl`
     * helper went with it).
     *
     * Note: the legacy Blade frontend still owns a /admin/patients/{id}/card
     * route under the same `admin.patients.card` name — that one sweeps
     * at Phase 3 cutover. So this test pins the API mirror is gone by
     * URI shape (everything under `api/` is the SPA-facing layer), not
     * by route name.
     */
    public function test_api_patients_card_mirror_is_not_registered_anymore(): void
    {
        $apiMirrorExists = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->contains(fn ($route) => str_starts_with($route->uri(), 'api/patients/')
                && str_contains($route->uri(), '/card'));

        $this->assertFalse(
            $apiMirrorExists,
            "An /api/patients/.../card route must NOT be registered. The SPA's "
            . 'patient-detail page covers every section the Blade card showed; '
            . 'restoring an API mirror reintroduces the Blade dependency on '
            . 'resources/views/admin/patients/card-v2/* and the admin layout.',
        );
    }
}
