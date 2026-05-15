<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\Vendor;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Cross-tenant injection defence for the cashflow/payments module.
 *
 * Pre-fix audit (2026-05-15): `StoreExpenseRequest` and
 * `UpdateExpenseRequest` validated `paid_from_pool_id`, `vendor_id`,
 * `category_id`, `for_branch_id`, `payment_method_id`, `staff_id` with
 * BARE `exists:` rules — no `account_id` scoping. A malicious operator
 * from tenant A could POST `paid_from_pool_id` belonging to tenant B
 * and have the expense accepted. Foreign-key constraints at the DB
 * level reference only the row id, so the insert succeeds. The pool
 * row gets debited even though the row belongs to another tenant.
 *
 * That's a cross-tenant theft primitive. These tests pin the fix:
 * every FK in the cashflow request shapes must reject ids from other
 * tenants with a 422 validation error, never silently accept them.
 */
class CashflowTenantInjectionSecurityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const TENANT_A = 1;

    private const TENANT_B = 2;

    private CashPool $tenantBPool;

    private Vendor $tenantBVendor;

    private ExpenseCategory $tenantBCategory;

    private Locations $tenantBLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedTenantB();
        $this->actingAsAdmin();

        // Configure the cashflow module so requests reach validation
        // instead of bouncing on the module-not-configured pre-check.
        \App\Models\CashFlow\CashflowSetting::setValue(
            'go_live_date',
            now()->subMonth()->format('Y-m-d'),
            self::TENANT_A,
        );
    }

    public function test_create_rejects_pool_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/store', $this->basePayload([
            'paid_from_pool_id' => $this->tenantBPool->id,
        ]));

        $response->assertStatus(422);
        $errors = $response->json('errors', []);
        $this->assertArrayHasKey(
            'paid_from_pool_id',
            $errors,
            'The pool id from a different tenant must be rejected on its own field, not some other unrelated failure.',
        );
        $this->assertEmpty(
            Expense::query()->where('paid_from_pool_id', $this->tenantBPool->id)->get(),
            'No expense row may persist against another tenant\'s pool.',
        );
    }

    public function test_create_rejects_vendor_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/store', $this->basePayload([
            'vendor_id' => $this->tenantBVendor->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_create_rejects_category_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/store', $this->basePayload([
            'category_id' => $this->tenantBCategory->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_create_rejects_branch_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/store', $this->basePayload([
            'for_branch_id' => $this->tenantBLocation->id,
            'is_for_general' => 0,
            'paid_from_pool_id' => $this->defaultCashPool->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_transfer_rejects_pool_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/transfers/store', [
            'transfer_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'from_pool_id' => $this->defaultCashPool->id,
            'to_pool_id' => $this->tenantBPool->id,
            'method' => 'physical_cash',
            'attachment_url' => 'https://drive.google.com/file/d/abc/view',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('to_pool_id', $response->json('errors', []));
    }

    public function test_staff_advance_rejects_pool_from_another_tenant(): void
    {
        $staff = \App\Models\User::factory()->create(['account_id' => self::TENANT_A]);

        $response = $this->postJson('/api/cashflow/staff/advance/store', [
            'user_id' => $staff->id,
            'pool_id' => $this->tenantBPool->id,
            'amount' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('pool_id', $response->json('errors', []));
    }

    public function test_vendor_create_rejects_category_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/store', [
            'name' => 'inj-vendor',
            'contact_person' => 'x',
            'phone' => '0300',
            'category_id' => $this->tenantBCategory->id,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('category_id', $response->json('errors', []));
    }

    public function test_movement_pool_to_pool_rejects_dest_from_another_tenant(): void
    {
        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'pool',
            'dest_type' => 'pool',
            'transfer_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'source_id' => $this->defaultCashPool->id,
            'dest_id' => $this->tenantBPool->id,
            'method' => 'physical_cash',
            'attachment_url' => 'https://drive.google.com/file/d/abc/view',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('dest_id', $response->json('errors', []));
    }

    /* --------------------------------------------------------------- */

    private function seedTenantB(): void
    {
        DB::table('accounts')->insertOrIgnore([
            'id' => self::TENANT_B,
            'name' => 'Tenant B',
            'email' => 'b@x.test',
            'contact' => '0',
            'suspended' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantBLocation = Locations::factory()->create([
            'account_id' => self::TENANT_B,
            'name' => 'Tenant B Branch',
        ]);

        $this->tenantBPool = CashPool::factory()->create([
            'account_id' => self::TENANT_B,
            'location_id' => $this->tenantBLocation->id,
            'name' => 'Tenant B Pool',
            'opening_balance' => 5000,
            'cached_balance' => 5000,
        ]);

        $this->tenantBVendor = Vendor::factory()->create([
            'account_id' => self::TENANT_B,
            'name' => 'Tenant B Vendor',
        ]);

        $this->tenantBCategory = ExpenseCategory::factory()->create([
            'account_id' => self::TENANT_B,
            'name' => 'Tenant B Category',
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()
                ->where('account_id', self::TENANT_A)->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 2, // Card — not Cash so no attachment required
            'is_for_general' => 1,
            'description' => 'tenant injection test',
        ], $overrides);
    }
}
