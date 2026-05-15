<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the soft-delete safety net added 2026-05-15.
 *
 * Pre-fix: `ExpenseObserver` had no `deleted()` hook. If any code path
 * (admin tool, background job, accidental ->delete()) soft-deleted an
 * Expense row, the pool stayed debited forever — there was no way to
 * recover the missing amount without manually voiding (which checks
 * voided_at, not deleted_at, so a soft-deleted-but-not-voided row was
 * invisible to recalc).
 *
 * Post-fix: the observer mirrors void()'s pool + vendor refund logic
 * on soft-delete, and `restored()` re-debits if the row is brought
 * back. void() and soft-delete remain SEPARATE workflows — void is
 * the user-facing "this didn't happen, undo it" path; deleted() is a
 * defence-in-depth safety net for any other path that might call
 * ->delete().
 */
class ExpenseSoftDeleteRefundTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private CashPool $pool;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->pool = CashPool::factory()->create(['opening_balance' => 10000, 'cached_balance' => 10000]);
        $this->category = ExpenseCategory::query()->first();
    }

    public function test_soft_deleting_an_approved_expense_refunds_the_pool(): void
    {
        $balanceBefore = $this->poolBalance();

        $expense = $this->makeExpense(amount: 1500);
        $this->assertSame($balanceBefore - 1500, $this->poolBalance(),
            'Sanity: creating the expense debits the pool.');

        $expense->delete();

        $this->assertSame($balanceBefore, $this->poolBalance(),
            'Soft-deleting an approved expense MUST credit the pool back — otherwise the amount is silently stuck.');
    }

    public function test_soft_deleting_a_voided_expense_does_not_double_refund(): void
    {
        // Void already refunded; deleted() must be a no-op on voided rows
        // or the pool gets the same amount twice.
        $balanceBefore = $this->poolBalance();

        $expense = $this->makeExpense(amount: 2000);
        app(\App\Services\CashFlow\ExpenseService::class)
            ->void($expense->id, 'voided for test', accountId: 1);

        $this->assertSame($balanceBefore, $this->poolBalance(),
            'Sanity: void refunded the pool.');

        $expense->fresh()->delete();

        $this->assertSame($balanceBefore, $this->poolBalance(),
            'A voided + soft-deleted expense must NOT double-refund the pool.');
    }

    public function test_restoring_a_soft_deleted_expense_re_debits_the_pool(): void
    {
        $balanceBefore = $this->poolBalance();

        $expense = $this->makeExpense(amount: 800);
        $expense->delete();
        $this->assertSame($balanceBefore, $this->poolBalance(),
            'Sanity: soft-delete refunded.');

        $expense->restore();

        $this->assertSame($balanceBefore - 800, $this->poolBalance(),
            'Restoring a soft-deleted expense must re-debit the pool to keep the ledger consistent.');
    }

    public function test_soft_deleting_an_expense_with_a_vendor_credits_the_vendor_ledger(): void
    {
        $vendor = Vendor::factory()->create(['cached_balance' => 0]);
        $expense = $this->makeExpense(amount: 600, vendorId: $vendor->id);

        // Sanity: vendor balance reflects the payment.
        $this->assertSame(-600.0, (float) $vendor->fresh()->cached_balance,
            'Sanity: creating the expense debited the vendor (Payment lowers their balance).');

        $expense->delete();

        $this->assertSame(0.0, (float) $vendor->fresh()->cached_balance,
            'Soft-delete must reverse the vendor transaction too — otherwise vendor balance lies.');

        $this->assertNull(
            VendorTransaction::query()->where('expense_id', $expense->id)->first(),
            'The vendor transaction row must be soft-deleted alongside the expense.',
        );
    }

    public function test_soft_deleting_busts_the_pool_balance_cache(): void
    {
        // Seed a stale cache value, soft-delete, then read.
        Cache::put('cashflow_pools_1', 'stale-snapshot', 600);

        $expense = $this->makeExpense(amount: 250);
        $this->assertNull(Cache::get('cashflow_pools_1'),
            'Sanity: create() busts the cache.');

        Cache::put('cashflow_pools_1', 'stale-snapshot-2', 600);
        $expense->delete();

        $this->assertNull(Cache::get('cashflow_pools_1'),
            'Soft-delete must bust the pool cache — otherwise the dashboard shows the wrong balance.');
    }

    /* --------------------------------------------------------------- */

    private function poolBalance(): float
    {
        return (float) $this->pool->fresh()->cached_balance;
    }

    private function makeExpense(int $amount, ?int $vendorId = null): Expense
    {
        $expense = Expense::create([
            'account_id' => 1,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => $amount,
            'category_id' => $this->category->id,
            'paid_from_pool_id' => $this->pool->id,
            'payment_method_id' => 1,
            'description' => "test {$amount}",
            'vendor_id' => $vendorId,
            'status' => ExpenseStatus::Approved,
            'verified_by' => 1,
            'is_flagged' => 0,
            'created_by' => 1,
            'is_for_general' => 1,
        ]);

        if ($vendorId !== null) {
            VendorTransaction::create([
                'account_id' => 1,
                'vendor_id' => $vendorId,
                'type' => \App\Enums\VendorTransactionType::Payment,
                'amount' => $amount,
                'expense_id' => $expense->id,
                'description' => "test {$amount}",
                'transaction_date' => now()->format('Y-m-d'),
                'is_for_general' => 1,
                'created_by' => 1,
            ]);
        }

        return $expense;
    }
}
