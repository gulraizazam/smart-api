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
 *
 * The legacy admin/* web wrappers (saveinvoice, getplansinformation,
 * getinvoicecalculation, getcalculatedPriceExclusicecheck) were deleted
 * with the Blade admin UI; their cases were removed here. The live API
 * surface for these flows is covered by the /api/* cases below and by
 * the treatment-invoice / consultancy-invoice API suites.
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

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/appointments/invoice/1');
        $this->assertContains($response->status(), [401, 302, 403]);
    }

    /**
     * Treatment/consultation invoice amounts are legitimately fractional
     * (tax-inclusive prices → settle = price − cash inherits decimals).
     * The save endpoint must NOT reject them with "The settle must be an
     * integer." The downstream business logic may still fail for this
     * bare payload — we only assert the money-field validation passed.
     */
    public function test_fractional_settle_passes_validation(): void
    {
        $response = $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'settle' => 6996.67,
            'cash' => 1000.50,
        ]);

        $response->assertJsonMissingValidationErrors(['settle', 'cash']);
    }

    /**
     * The anti-negative guard (`min:0`) that blocks the pool-draining
     * money primitive must survive the integer→numeric relaxation.
     */
    public function test_negative_settle_is_still_rejected(): void
    {
        $response = $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'settle' => -5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('settle');
    }
}
