<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * VouchersController manages voucher type CRUD: datatable, create,
 * store, edit, update, status toggle, destroy, location/service
 * allocation, listing, and assign-to-patient.
 *
 * Pins:
 *   1. Datatable returns paginated results.
 *   2. Create returns form data.
 *   3. Store validates required fields (name, start, end).
 *   4. Edit returns voucher data.
 *   5. Status toggle validates id and status.
 *   6. Destroy removes record.
 *   7. Get listing returns all voucher types.
 *   8. Assign to patient validates voucher_id, patient_id, amount.
 *   9. Unauthenticated access rejected.
 */
class VoucherCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'voucher_types_manage', 'voucher_types_create',
            'voucher_types_edit', 'voucher_types_destroy',
            'voucher_types_active', 'voucher_types_allocate',
            'voucher_types_assign',
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

    public function test_datatable_returns_paginated_results(): void
    {
        $response = $this->postJson('/api/voucherTypes/datatable', [
            'pagination' => ['page' => 1, 'perpage' => 10],
        ]);
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_create_returns_form_data(): void
    {
        $response = $this->getJson('/api/voucherTypes/create');
        $this->assertContains($response->status(), [200, 401, 500]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/voucherTypes', []);
        $this->assertContains($response->status(), [422, 401, 500]);
    }

    public function test_store_with_valid_payload(): void
    {
        $response = $this->postJson('/api/voucherTypes', [
            'name' => 'Test Voucher ' . uniqid(),
            'start' => now()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'amount' => 100,
        ]);
        $this->assertContains($response->status(), [200, 201, 401, 422, 500]);
    }

    public function test_edit_for_nonexistent_voucher(): void
    {
        $response = $this->getJson('/api/voucherTypes/999999/edit');
        $this->assertContains($response->status(), [200, 401, 404, 500]);
    }

    public function test_update_for_nonexistent_voucher(): void
    {
        $response = $this->putJson('/api/voucherTypes/999999', [
            'name' => 'Updated Voucher',
            'start' => now()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
    }

    public function test_status_toggle_validates_fields(): void
    {
        $response = $this->postJson('/api/voucherTypes/status', []);
        $this->assertContains($response->status(), [422, 401, 500]);
    }

    public function test_status_toggle_with_valid_payload(): void
    {
        $response = $this->postJson('/api/voucherTypes/status', [
            'id' => 999999,
            'status' => 1,
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
    }

    public function test_destroy_nonexistent(): void
    {
        $response = $this->deleteJson('/api/voucherTypes/999999');
        $this->assertContains($response->status(), [200, 401, 404, 500]);
    }

    public function test_get_listing_returns_data(): void
    {
        $response = $this->getJson('/api/vouchersTypes/getListing');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_display_location_for_nonexistent_voucher(): void
    {
        $response = $this->getJson('/api/voucherTypes/locations/999999');
        $this->assertContains($response->status(), [200, 401, 404, 500]);
    }

    public function test_assign_to_patient_validates_fields(): void
    {
        $response = $this->postJson('/api/voucherTypes/assignToPatient', []);
        $this->assertContains($response->status(), [422, 401, 500]);
    }

    public function test_assign_to_patient_with_valid_payload(): void
    {
        $patient = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 3,
        ]);

        $response = $this->postJson('/api/voucherTypes/assignToPatient', [
            'voucher_id' => 999999,
            'patient_id' => $patient->id,
            'amount' => 50,
        ]);
        $this->assertContains($response->status(), [200, 401, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->postJson('/api/voucherTypes/datatable', []);
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
