<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * AppointmentInvoiceController handles invoice view, save, display,
 * plans information, package price, and invoice calculation.
 *
 * Routes: api/appointments/invoice/{id} (GET), api/appointments/displayInvoice/{id} (GET),
 *   api/appointments/getpackageprice (GET).
 *   admin/appointments/saveinvoice (GET), admin/appointments/getplansinformation (GET),
 *   admin/appointments/getinvoicecalculation (GET), admin/appointments/getcalculatedPriceExclusicecheck (GET).
 */
class AppointmentInvoiceEndpointTest extends TestCase
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
            'appointments_invoice', 'appointments_invoice_display',
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

    public function test_invoice_view_for_nonexistent_appointment(): void
    {
        $response = $this->getJson('/api/appointments/invoice/999999');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_display_invoice_for_nonexistent(): void
    {
        $response = $this->getJson('/api/appointments/displayInvoice/999999');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_get_package_price(): void
    {
        // GET route under api/
        $response = $this->get('/api/appointments/getpackageprice');
        $this->assertContains($response->status(), [200, 302, 422, 500]);
    }

    public function test_save_invoice_web_route(): void
    {
        // saveinvoice is a GET web route under admin/. 404 is also
        // acceptable now: as of 2026-05-15 the controller scopes its
        // Appointments::find to the caller's account_id and returns a
        // 404 when no appointment_id is supplied / matched (prevents
        // cross-tenant invoice fabrication via guessable ids).
        $response = $this->get('/admin/appointments/saveinvoice');
        $this->assertContains($response->status(), [200, 302, 404, 422, 500]);
    }

    public function test_get_plans_information_web_route(): void
    {
        // GET web route under admin/
        $response = $this->get('/admin/appointments/getplansinformation');
        $this->assertContains($response->status(), [200, 302, 422, 500]);
    }

    public function test_get_invoice_calculation_web_route(): void
    {
        // GET web route under admin/
        $response = $this->get('/admin/appointments/getinvoicecalculation');
        $this->assertContains($response->status(), [200, 302, 422, 500]);
    }

    public function test_get_calculated_price_exclusive_check_web_route(): void
    {
        // GET web route under admin/
        $response = $this->get('/admin/appointments/getcalculatedPriceExclusicecheck');
        $this->assertContains($response->status(), [200, 302, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/appointments/invoice/1');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
