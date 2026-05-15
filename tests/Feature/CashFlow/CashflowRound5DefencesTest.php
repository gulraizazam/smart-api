<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseAttachment;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\Vendor;
use App\Models\Locations;
use App\Services\CashFlow\ExpenseService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the four defences added in round 5:
 *
 *   - Soft-deleted FK rejection on pool / vendor / category — bare
 *     `Rule::exists()` would accept rows after a soft-delete. Now
 *     filtered with `whereNull('deleted_at')`.
 *   - Reject separation-of-duties — creator can't reject their own row.
 *   - Attachment `expense_id` scoped to caller's tenant.
 *
 * The transfer / advance concurrent-edit locks aren't probed here —
 * `lockForUpdate()` is enforced by the DB engine and the surrounding
 * transaction; testing it requires concurrent processes which the
 * Pest/RefreshDatabase model doesn't supply. The code review is the
 * test for that one.
 */
class CashflowRound5DefencesTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        \App\Models\CashFlow\CashflowSetting::setValue(
            'go_live_date',
            now()->subMonth()->format('Y-m-d'),
            accountId: 1,
        );
    }

    /* ------------------------------------------------------------- */
    /* A9 — soft-deleted FK rejection                                */
    /* ------------------------------------------------------------- */

    public function test_expense_create_rejects_a_soft_deleted_pool(): void
    {
        $pool = CashPool::factory()->create([
            'account_id' => 1,
            'opening_balance' => 1000,
            'cached_balance' => 1000,
        ]);
        $poolId = $pool->id;
        $pool->delete(); // soft-delete

        $this->assertNotNull(
            DB::table('cash_pools')->where('id', $poolId)->whereNotNull('deleted_at')->first(),
            'Sanity: the pool is soft-deleted, not hard-deleted.',
        );

        $response = $this->postJson('/api/cashflow/expenses/store', [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $poolId,
            'payment_method_id' => 2,
            'is_for_general' => 1,
            'description' => 'soft-deleted pool probe',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('paid_from_pool_id', $response->json('errors', []));
    }

    public function test_expense_create_rejects_a_soft_deleted_vendor(): void
    {
        $vendor = Vendor::factory()->create(['account_id' => 1]);
        $vendorId = $vendor->id;
        $vendor->delete();

        $response = $this->postJson('/api/cashflow/expenses/store', [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 2,
            'is_for_general' => 1,
            'description' => 'soft-deleted vendor probe',
            'vendor_id' => $vendorId,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('vendor_id', $response->json('errors', []));
    }

    public function test_expense_create_rejects_a_soft_deleted_category(): void
    {
        $category = ExpenseCategory::factory()->create(['account_id' => 1]);
        $categoryId = $category->id;
        $category->delete();

        $response = $this->postJson('/api/cashflow/expenses/store', [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => $categoryId,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 2,
            'is_for_general' => 1,
            'description' => 'soft-deleted category probe',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('category_id', $response->json('errors', []));
    }

    /* ------------------------------------------------------------- */
    /* A2 — reject SoD                                               */
    /* ------------------------------------------------------------- */

    public function test_creator_cannot_reject_their_own_expense(): void
    {
        $creator = $this->actingAsAdmin();

        $expense = Expense::create([
            'account_id' => 1,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'reject SoD probe',
            'status' => ExpenseStatus::Approved,
            'verified_by' => $creator->id,
            'is_flagged' => 0,
            'created_by' => $creator->id,
            'is_for_general' => 1,
        ]);

        $this->expectException(CashflowException::class);
        $this->expectExceptionMessageMatches('/cannot reject an expense you created/i');

        app(ExpenseService::class)
            ->reject($expense->id, 'attempting self-reject', accountId: 1);
    }

    public function test_a_different_admin_can_reject_the_expense(): void
    {
        $creator = $this->actingAsAdmin();

        $expense = Expense::create([
            'account_id' => 1,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'reject SoD positive probe',
            'status' => ExpenseStatus::Approved,
            'verified_by' => $creator->id,
            'is_flagged' => 0,
            'created_by' => $creator->id,
            'is_for_general' => 1,
        ]);

        $reviewer = $this->actingAsAdmin();
        $this->assertNotSame($creator->id, $reviewer->id, 'Sanity: distinct admins.');

        $rejected = app(ExpenseService::class)
            ->reject($expense->id, 'reviewer rejecting', accountId: 1);

        $this->assertSame(ExpenseStatus::Rejected->value, $rejected->status->value);
    }

    /* ------------------------------------------------------------- */
    /* A4 — attachment expense_id cross-tenant                       */
    /* ------------------------------------------------------------- */

    public function test_attachment_upload_rejects_an_expense_from_another_tenant(): void
    {
        // Build a Tenant B expense the attacker shouldn't reach.
        DB::table('accounts')->insertOrIgnore([
            'id' => 2,
            'name' => 'Tenant B',
            'email' => 'b@x.test',
            'contact' => '0',
            'suspended' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tenantBLocation = Locations::factory()->create(['account_id' => 2]);
        $tenantBPool = CashPool::factory()->create([
            'account_id' => 2,
            'location_id' => $tenantBLocation->id,
        ]);
        $tenantBCategory = ExpenseCategory::factory()->create(['account_id' => 2]);
        $tenantBExpense = Expense::create([
            'account_id' => 2,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 50,
            'category_id' => $tenantBCategory->id,
            'paid_from_pool_id' => $tenantBPool->id,
            'payment_method_id' => 1,
            'description' => 'tenant B expense',
            'status' => ExpenseStatus::Approved,
            'is_flagged' => 0,
            'created_by' => 1,
            'is_for_general' => 1,
        ]);

        $fixture = $this->makeUploadedPdf();

        // Multipart upload — use post() not postJson() since the body is
        // form-data. Files go in the data array; Accept header makes the
        // response come back as JSON.
        $response = $this->post(
            '/api/cashflow/expenses/attachments',
            [
                'expense_id' => $tenantBExpense->id, // ← cross-tenant
                'file' => $fixture,
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('expense_id', $response->json('errors', []));
        $this->assertEmpty(
            ExpenseAttachment::query()->where('expense_id', $tenantBExpense->id)->get(),
            'No attachment row may persist against another tenant\'s expense.',
        );
    }

    /* ------------------------------------------------------------- */

    private function makeUploadedPdf(): \Illuminate\Http\UploadedFile
    {
        // A real PDF header so the magic-byte sniffer accepts the bytes.
        // The body doesn't matter, just the leading `%PDF-1.4` signature.
        $pdfBytes = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
        $tmp = tempnam(sys_get_temp_dir(), 'pdfprobe').'.pdf';
        file_put_contents($tmp, $pdfBytes);

        return new \Illuminate\Http\UploadedFile(
            $tmp,
            'probe.pdf',
            'application/pdf',
            null,
            true, // test mode
        );
    }
}
