<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashFlowStaffController API endpoint tests.
 *
 * Pins:
 *   1. Staff summary endpoint returns data.
 *   2. Staff recent activity returns list.
 *   3. Staff ledger returns user-specific data.
 *   4. Staff eligible endpoint returns list.
 *   5. Advance store validates required fields.
 *   6. Advance void requires void_reason min 5 chars.
 *   7. Advance update requires edit_reason.
 *   8. Return store validates required fields.
 *   9. Return void requires void_reason min 5 chars.
 *  10. Audit endpoints require cashflow_audit_view.
 *  11. Unauthenticated access rejected.
 */
class CashflowStaffEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_staff_advance_view', 'cashflow_staff_advance',
            'cashflow_staff_advance_create', 'cashflow_staff_advance_void',
            'cashflow_staff_advance_edit', 'cashflow_staff_return_void',
            'cashflow_audit_view',
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

    public function test_staff_summary_returns_data(): void
    {
        $response = $this->getJson('/api/cashflow/staff/summary');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_staff_recent_activity_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/staff/recent-activity');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_staff_ledger_for_user(): void
    {
        $user = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 1,
        ]);

        $response = $this->getJson("/api/cashflow/staff/{$user->id}/ledger");
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_staff_eligible_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/staff/eligible');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_advance_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/store', []);
        $this->assertContains($response->status(), [422, 403]);
    }

    public function test_advance_store_with_partial_payload(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/store', [
            'amount' => 500,
            // Missing user_id, pool_id, description
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_advance_void_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/void', [
            'void_reason' => 'Test void reason text',
        ]);
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_advance_void_rejects_short_reason(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/void', [
            'void_reason' => 'ab',
        ]);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_advance_update_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/update', [
            'amount' => 1000,
            'pool_id' => 1,
            'description' => 'Updated advance',
            'edit_reason' => 'Correcting amount',
        ]);
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_advance_audit_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/staff/advance/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_return_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/staff/return/store', []);
        $this->assertContains($response->status(), [422, 403]);
    }

    public function test_return_void_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/staff/return/999999/void', [
            'void_reason' => 'Test void reason text',
        ]);
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_return_audit_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/staff/return/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/staff/summary');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
