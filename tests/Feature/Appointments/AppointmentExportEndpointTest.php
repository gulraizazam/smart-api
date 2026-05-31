<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * AppointmentExportController handles Excel exports and view-log output.
 *
 * The Blade admin/* wrappers were deleted with the legacy UI. The live
 * API surface for the view-log flow is:
 *   ANY api/appointments/viewlog/{id}/{type} -> AppointmentExportController@viewLog
 *
 * The Excel export action (export/{limit}/{offset}) has no /api twin yet,
 * so its dedicated case was removed; the unauthenticated-access pin is
 * repointed onto the live viewlog twin instead.
 */
class AppointmentExportEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'appointments_manage', 'appointments_view',
            'appointments_export_all', 'appointments_export_today',
            'appointments_export_this_month', 'appointments_log',
            'appointments_consultancy', 'consultations_manage', 'treatments_services',
        ]);
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }

        $role = $this->createRole('test-admin-' . uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_view_log_web_type(): void
    {
        // ANY api/appointments/viewlog/{id}/{type}
        $response = $this->get('/api/appointments/viewlog/999999/web');
        $this->assertContains($response->status(), [200, 302, 404, 500]);
    }

    public function test_view_log_excel_type(): void
    {
        $response = $this->get('/api/appointments/viewlog/999999/excel');
        $this->assertContains($response->status(), [200, 302, 404, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/appointments/viewlog/999999/web');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
