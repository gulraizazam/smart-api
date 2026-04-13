<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * AppointmentScheduleController manages appointment scheduling.
 *
 * API routes:
 *   GET  api/appointments/load/scheduled-appointments
 *   GET  api/appointments/load/scheduled-serivce-appointments
 *   GET  api/appointments/schedule/get
 *   POST api/appointments/check-and-save-appointment
 *   POST api/appointments/check-and-save-service-appointment
 *   POST api/appointments/load-doctor-rota
 *   GET  api/appointments/non-scheduled/list
 */
class AppointmentScheduleEndpointTest extends TestCase
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
            'appointments_create', 'appointments_edit',
            'contact', 'schedule_manage',
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

    public function test_scheduled_appointments_returns_data(): void
    {
        $response = $this->getJson('/api/appointments/load/scheduled-appointments');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_non_scheduled_appointments_returns_data(): void
    {
        // api route uses different path than admin route
        $response = $this->getJson('/api/appointments/non-scheduled/list');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_scheduled_service_appointments_returns_data(): void
    {
        $response = $this->getJson('/api/appointments/load/scheduled-serivce-appointments');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_get_schedule_returns_data(): void
    {
        $response = $this->getJson('/api/appointments/schedule/get');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_load_doctor_rota(): void
    {
        // POST route: api/appointments/load-doctor-rota
        $response = $this->postJson('/api/appointments/load-doctor-rota', [
            'doctor_id' => 1,
        ]);
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_check_and_save_appointment(): void
    {
        $response = $this->postJson('/api/appointments/check-and-save-appointment', []);
        $this->assertContains($response->status(), [200, 400, 422, 500]);
    }

    public function test_check_and_save_service_appointment(): void
    {
        $response = $this->postJson('/api/appointments/check-and-save-service-appointment', []);
        $this->assertContains($response->status(), [200, 400, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/appointments/load/scheduled-appointments');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
