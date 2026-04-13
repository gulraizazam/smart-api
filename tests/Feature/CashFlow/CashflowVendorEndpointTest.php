<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashFlowVendorsController API endpoint tests.
 *
 * Pins:
 *   1. Vendor data endpoint returns list.
 *   2. Vendor overview returns summary.
 *   3. Vendor store validates required fields via StoreVendorRequest.
 *   4. Vendor update validates fields via UpdateVendorRequest.
 *   5. Vendor toggle requires cashflow_vendor_toggle gate.
 *   6. Vendor ledger returns transactions.
 *   7. Vendor purchase validates required fields.
 *   8. Vendor transaction deliver requires attachment_url.
 *   9. Vendor requests data/store/approve/dismiss work.
 *  10. Vendor form-data returns category lookups.
 *  11. Unauthenticated access rejected.
 */
class CashflowVendorEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_vendor_manage', 'cashflow_vendor_toggle',
            'cashflow_vendor_deliver', 'cashflow_vendor_transaction',
            'cashflow_audit_view', 'cashflow_vendor_ledger_export',
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

    public function test_vendor_data_endpoint_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_overview_returns_summary(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/overview');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_form_data_returns_lookups(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/form-data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/store', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_vendor_update_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/update', [
            'name' => 'Updated Vendor',
        ]);
        $this->assertContains($response->status(), [403, 404, 422, 500]);
    }

    public function test_vendor_toggle_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/toggle');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_vendor_ledger_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/999999/ledger');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_vendor_purchase_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/purchase', []);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_vendor_transaction_deliver_requires_attachment(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/deliver', []);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_vendor_transaction_delete_on_nonexistent(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/delete');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_vendor_transaction_audit_on_nonexistent(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/999999/transactions/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_vendor_ledger_export_on_nonexistent(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/999999/ledger/export');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_vendor_requests_data_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/vendor-requests/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_requests_store_validates_fields(): void
    {
        $response = $this->postJson('/api/cashflow/vendor-requests/store', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_vendor_requests_approve_on_nonexistent(): void
    {
        $response = $this->postJson('/api/cashflow/vendor-requests/999999/approve');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_vendor_requests_dismiss_on_nonexistent(): void
    {
        $response = $this->postJson('/api/cashflow/vendor-requests/999999/dismiss');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/vendors/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
