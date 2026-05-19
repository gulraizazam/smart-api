<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Enums\VendorTransactionType;
use App\Exceptions\CashflowException;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\Vendor;
use App\Models\Locations;
use App\Models\User;
use App\Services\CashFlow\ExpenseService;
use App\Services\CashFlow\VendorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Third-pass probe: gaps the first two security rounds missed.
 *
 * - staff_id / user_id type confusion — patient user_id should NOT be
 *   acceptable as a staff reference on expense / advance / return /
 *   movement endpoints.
 * - Self-void — creator must not be able to void their own row (the
 *   void refunds the pool; without the SoD gate a "shell + void"
 *   pattern could churn balances).
 * - VendorTransaction.expense_id cross-tenant — the column has only
 *   a bare FK to expenses.id; the service must verify the referenced
 *   expense belongs to the caller's account.
 */
class CashflowDeeperSecurityProbeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const TENANT_A = 1;

    private const TENANT_B = 2;

    private const PATIENT_USER_TYPE = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        \App\Models\CashFlow\CashflowSetting::setValue(
            'go_live_date',
            now()->subMonth()->format('Y-m-d'),
            self::TENANT_A,
        );
    }

    /* -------- staff_id type confusion -------- */

    public function test_expense_create_rejects_a_patient_user_as_staff_id(): void
    {
        $patient = User::factory()->create([
            'account_id' => self::TENANT_A,
            'user_type_id' => self::PATIENT_USER_TYPE,
        ]);

        $response = $this->postJson('/api/cashflow/expenses/store', [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'payment_method_id' => 2,
            'is_for_general' => 1,
            'description' => 'patient-as-staff probe',
            'staff_id' => $patient->id,
            // No pool because staff_id selected.
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey(
            'staff_id',
            $response->json('errors', []),
            'Validation must flag the patient user — staff_id is meant for staff/doctor users only.',
        );
    }

    public function test_staff_advance_rejects_a_patient_user_as_user_id(): void
    {
        $patient = User::factory()->create([
            'account_id' => self::TENANT_A,
            'user_type_id' => self::PATIENT_USER_TYPE,
        ]);

        $response = $this->postJson('/api/cashflow/staff/advance/store', [
            'user_id' => $patient->id,
            'pool_id' => $this->defaultCashPool->id,
            'amount' => 200,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('user_id', $response->json('errors', []));
    }

    public function test_staff_return_rejects_a_patient_user_as_user_id(): void
    {
        $patient = User::factory()->create([
            'account_id' => self::TENANT_A,
            'user_type_id' => self::PATIENT_USER_TYPE,
        ]);

        $response = $this->postJson('/api/cashflow/staff/return/store', [
            'user_id' => $patient->id,
            'pool_id' => $this->defaultCashPool->id,
            'amount' => 200,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('user_id', $response->json('errors', []));
    }

    /* -------- Self-void block -------- */

    public function test_creator_cannot_void_their_own_expense(): void
    {
        $creator = $this->actingAsAdmin();

        $expense = Expense::create([
            'account_id' => self::TENANT_A,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 1000,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'self-void probe',
            'status' => ExpenseStatus::Approved,
            'verified_by' => $creator->id,
            'is_flagged' => 0,
            'created_by' => $creator->id,
            'is_for_general' => 1,
        ]);

        $this->expectException(CashflowException::class);
        $this->expectExceptionMessageMatches('/cannot void an expense you created/i');

        app(ExpenseService::class)
            ->void($expense->id, 'attempting self-void', accountId: self::TENANT_A);
    }

    public function test_a_different_admin_can_void_the_expense(): void
    {
        $creator = $this->actingAsAdmin();

        $expense = Expense::create([
            'account_id' => self::TENANT_A,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 1000,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'other-admin-void probe',
            'status' => ExpenseStatus::Approved,
            'verified_by' => $creator->id,
            'is_flagged' => 0,
            'created_by' => $creator->id,
            'is_for_general' => 1,
        ]);

        $voider = $this->actingAsAdmin();
        $this->assertNotSame($creator->id, $voider->id, 'Sanity: distinct admins.');

        $voided = app(ExpenseService::class)
            ->void($expense->id, 'second admin voiding', accountId: self::TENANT_A);

        $this->assertNotNull($voided->voided_at);
        $this->assertSame($voider->id, (int) $voided->voided_by);
    }

    /* -------- VendorTransaction expense_id cross-tenant -------- */

    public function test_vendor_transaction_rejects_an_expense_from_another_tenant(): void
    {
        // Build a tenant B expense the attacker shouldn't be able to link to.
        DB::table('accounts')->insertOrIgnore([
            'id' => self::TENANT_B,
            'name' => 'Tenant B',
            'email' => 'b@x.test',
            'contact' => '0',
            'suspended' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tenantBLocation = Locations::factory()->create([
            'account_id' => self::TENANT_B,
            'name' => 'B branch',
        ]);
        // GuardsTenantBoundary now forces account_id to the auth
        // account on create, so the malicious foreign-tenant fixture
        // must be fabricated with model events suppressed (the
        // production validator it feeds is unchanged).
        [$tenantBPool, $tenantBCategory, $tenantBExpense] = \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($tenantBLocation) {
            $pool = \App\Models\CashFlow\CashPool::factory()->create([
                'account_id' => self::TENANT_B,
                'location_id' => $tenantBLocation->id,
            ]);
            $cat = ExpenseCategory::factory()->create([
                'account_id' => self::TENANT_B,
            ]);
            $exp = Expense::create([
                'account_id' => self::TENANT_B,
                'expense_date' => now()->format('Y-m-d'),
                'amount' => 50,
                'category_id' => $cat->id,
                'paid_from_pool_id' => $pool->id,
                'payment_method_id' => 1,
                'description' => 'tenant B expense',
                'status' => ExpenseStatus::Approved,
                'is_flagged' => 0,
                'created_by' => 1,
                'is_for_general' => 1,
            ]);
            return [$pool, $cat, $exp];
        });

        $vendorA = Vendor::factory()->create(['account_id' => self::TENANT_A]);

        $this->expectException(CashflowException::class);
        $this->expectExceptionMessageMatches('/invalid expense reference/i');

        app(VendorService::class)->recordTransaction([
            'vendor_id' => $vendorA->id,
            'type' => VendorTransactionType::Payment,
            'amount' => 50,
            'expense_id' => $tenantBExpense->id,   // ← cross-tenant injection
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => 1,
        ], accountId: self::TENANT_A);
    }
}
