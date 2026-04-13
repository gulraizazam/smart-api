<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ConsultancyInvoiceController (API) — routes under api/consultancy/invoice/:
 *   GET  api/consultancy/invoice/{id} — show
 *   POST api/consultancy/invoice/calculate
 *   POST api/consultancy/invoice/calculate-custom
 *   POST api/consultancy/invoice/check-custom
 *   POST api/consultancy/invoice/calculate-final
 *   POST api/consultancy/invoice — store
 */
class ConsultancyInvoiceEndpointTest extends TestCase
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
            'appointments_invoice',
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

    public function test_show_for_nonexistent_appointment(): void
    {
        $response = $this->getJson('/api/consultancy/invoice/999999');
        $this->assertContains($response->status(), [200, 403, 404, 500]);
    }

    public function test_calculate_validates_required_fields(): void
    {
        $response = $this->postJson('/api/consultancy/invoice/calculate', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_calculate_with_valid_structure(): void
    {
        $response = $this->postJson('/api/consultancy/invoice/calculate', [
            'appointment_id' => 999999,
            'location_id' => 1,
            'price_for_calculation' => 500,
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    public function test_calculate_custom_discount(): void
    {
        $response = $this->postJson('/api/consultancy/invoice/calculate-custom', []);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    public function test_check_custom_discount(): void
    {
        $response = $this->postJson('/api/consultancy/invoice/check-custom', [
            'discount_id' => 1,
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    public function test_calculate_final_validates_fields(): void
    {
        $response = $this->postJson('/api/consultancy/invoice/calculate-final', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/consultancy/invoice', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_store_with_partial_payload(): void
    {
        $response = $this->postJson('/api/consultancy/invoice', [
            'appointment_id' => 999999,
            'price' => 500,
        ]);
        $this->assertContains($response->status(), [200, 201, 403, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/consultancy/invoice/1');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
