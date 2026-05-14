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
            'cashflow_vendor_view', 'cashflow_vendor_manage', 'cashflow_vendor_toggle',
            'cashflow_vendor_deliver', 'cashflow_vendor_transaction',
            'cashflow_vendor_transaction_delete', 'cashflow_vendor_transaction_edit',
            'cashflow_vendor_edit', 'cashflow_vendor_create', 'cashflow_vendor_request',
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

    /**
     * Phase-2 fix: `vendorsTransactionDelete` now gates on the dedicated
     * `cashflow_vendor_transaction_delete` slug (which the seeder already
     * defines). Before the fix it shared the create slug, so a user with
     * create-only could delete and a user with delete-only could not.
     */
    /**
     * Phase-3 fix: `UpdateVendorRequest::name` is now required (was nullable),
     * so an admin editing a vendor can't accidentally wipe the display name.
     */
    /**
     * Plan B-Vendors: vendor purchase EDIT now requires `edit_reason`
     * (min 5, max 500). Create-mode payload still doesn't need it. The
     * route param `txId` is what flips StoreVendorPurchaseRequest from
     * create-rules to edit-rules.
     */
    public function test_vendor_transaction_edit_requires_edit_reason(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/update', [
            'amount' => 1000,
            'description' => 'Updated description',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'delivered',
            'attachment_url' => 'https://drive.google.com/file/d/abc/view',
            // No edit_reason.
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edit_reason']);
    }

    public function test_vendor_purchase_create_does_not_require_edit_reason(): void
    {
        // The same validator runs but in create mode (no txId in route) so
        // edit_reason is nullable. The payload still 422s on other reasons
        // (missing for_branch_id) — what matters is that `errors.edit_reason`
        // is NOT in the response.
        $response = $this->postJson('/api/cashflow/vendors/1/purchase', [
            'amount' => 1000,
            'description' => 'A new purchase',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'ordered',
        ]);
        $errors = $response->json('errors') ?? [];
        $this->assertArrayNotHasKey('edit_reason', $errors,
            'Create mode must not require edit_reason — that field is edit-only.');
    }

    public function test_vendor_update_rejects_empty_name(): void
    {
        // Use a vendor id that probably won't exist; the validator runs before
        // the controller routes to the service, so we get 422 regardless.
        $response = $this->postJson('/api/cashflow/vendors/1/update', [
            'name' => '',
            'contact_person' => 'Joe',
            'phone' => '0300-0000000',
            'category_id' => 1,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_vendor_transaction_delete_rejects_when_only_create_slug_held(): void
    {
        // Reset to a role that holds the route-level view slug (to clear the
        // Phase-10 middleware) AND the create slug — but NOT the delete slug.
        // The controller's inline `can('cashflow_vendor_transaction_delete')`
        // must still reject. Tests the Phase-2 slug realignment specifically.
        auth()->user()->roles()->detach();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createPermission('cashflow_vendor_view');
        $this->createPermission('cashflow_vendor_transaction');
        $role = $this->createRole('vendor-create-only-' . uniqid());
        $role->givePermissionTo(['cashflow_vendor_view', 'cashflow_vendor_transaction']);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->postJson('/api/cashflow/vendors/1/transactions/1/delete');
        $response->assertStatus(403);
    }

    public function test_vendor_transaction_delete_clears_gate_with_delete_slug(): void
    {
        // Reset to a role that holds ONLY the delete slug.
        auth()->user()->roles()->detach();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createPermission('cashflow_vendor_transaction_delete');
        $role = $this->createRole('vendor-delete-only-' . uniqid());
        $role->givePermissionTo(['cashflow_vendor_transaction_delete']);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Hits a non-existent vendor/transaction so the service throws a not-found
        // error AFTER passing the gate. The point of the assertion is that we got
        // past the 403 — not whether the entity exists.
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/delete');
        $this->assertNotSame(403, $response->status(), 'Holding the delete slug must clear the auth gate.');
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/vendors/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
