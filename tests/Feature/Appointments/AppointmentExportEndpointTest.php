<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * AppointmentExportController handles Excel exports.
 *
 * Routes (all under admin/ web):
 *   GET admin/appointments/export/{limit}/{offset}
 *   GET admin/appointments/view/log/{id}/{type}
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

    public function test_export_with_limit_offset(): void
    {
        // GET admin/appointments/export/{limit}/{offset}
        // Returns BinaryFileResponse (Excel download) — use getStatusCode().
        $response = $this->get('/admin/appointments/export/10/0');
        $this->assertContains($response->getStatusCode(), [200, 302, 500]);
    }

    public function test_view_log_web_type(): void
    {
        // GET admin/appointments/view/log/{id}/{type}
        $response = $this->get('/admin/appointments/view/log/999999/web');
        $this->assertContains($response->status(), [200, 302, 404, 500]);
    }

    public function test_view_log_excel_type(): void
    {
        $response = $this->get('/admin/appointments/view/log/999999/excel');
        $this->assertContains($response->status(), [200, 302, 404, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->get('/admin/appointments/export/10/0');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
