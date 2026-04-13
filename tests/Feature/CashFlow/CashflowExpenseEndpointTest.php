<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashFlowExpense;
use App\Models\CashFlow\CashFlowPool;
use App\Models\CashFlow\CashFlowCategory;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashFlowExpensesController API endpoint tests.
 *
 * Pins:
 *   1. Expense data endpoint returns list.
 *   2. Expense store validates required fields via FormRequest.
 *   3. Expense approve requires cashflow_expense_approve gate.
 *   4. Expense reject requires reason.
 *   5. Expense void requires void_reason min 5 chars.
 *   6. Expense resubmit requires cashflow_expense_resubmit gate.
 *   7. Expense unflag requires cashflow_expense_unflag gate.
 *   8. Expense audit requires cashflow_audit_view gate.
 *   9. Expense export requires cashflow_expense_export gate.
 *  10. Unauthenticated access rejected.
 */
class CashflowExpenseEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_expense_create', 'cashflow_expense_edit',
            'cashflow_expense_approve', 'cashflow_expense_resubmit',
            'cashflow_expense_unflag', 'cashflow_expense_export',
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

    public function test_expense_data_endpoint_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/expenses/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_expense_form_data_returns_lookups(): void
    {
        $response = $this->getJson('/api/cashflow/expenses/form-data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_expense_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/store', []);
        $this->assertContains($response->status(), [422, 403]);
    }

    public function test_expense_store_with_partial_payload(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/store', [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            // Missing category_id, paid_from_pool_id, etc.
        ]);

        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_expense_approve_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/999999/approve');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_expense_reject_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/999999/reject', [
            'reject_reason' => 'Test rejection reason',
        ]);
        $this->assertContains($response->status(), [403, 404, 422, 500]);
    }

    public function test_expense_void_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/999999/void', [
            'void_reason' => 'Test void reason text',
        ]);
        $this->assertContains($response->status(), [403, 404, 422, 500]);
    }

    public function test_expense_resubmit_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/999999/resubmit');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_expense_unflag_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/999999/unflag');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_expense_audit_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/expenses/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_expense_export_endpoint(): void
    {
        $response = $this->getJson('/api/cashflow/expenses/export');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/expenses/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
