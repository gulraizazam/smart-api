<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tests the pool-balance arithmetic in ExpenseObserver.
 *
 * The observer is the load-bearing piece that keeps `cash_pools.cached_balance`
 * in sync with the expense lifecycle. The audit found a class of bugs where
 * an admin edit changed both `paid_from_pool_id` and `amount` in the same
 * save and the wrong delta was applied — these tests pin every transition
 * the observer's `updated()` method enumerates so a regression cannot land
 * without breaking one of them.
 */
class ExpenseObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_an_expense_decrements_the_paying_pool(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(10000)->create();

        Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1500,
        ]);

        $this->assertSame(8500.00, (float) $pool->fresh()->cached_balance);
    }

    public function test_creating_an_expense_with_no_pool_does_not_touch_any_balance(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(5000)->create();

        Expense::factory()->create([
            'paid_from_pool_id' => null,
            'amount' => 999,
        ]);

        $this->assertSame(5000.00, (float) $pool->fresh()->cached_balance);
    }

    public function test_rejecting_an_expense_reverses_the_pool(): void
    {
        // Model A: reject REVERSES the pool deduction (the money is given
        // back). This matches the legacy app (crm2) so both stay consistent
        // on the shared cached_balance column; the recompute likewise
        // EXCLUDES rejected rows, so a rejected expense nets to zero.
        $pool = CashPool::factory()->withOpeningBalance(10000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 2000,
            'status' => ExpenseStatus::Pending,
        ]);

        $this->assertSame(8000.00, (float) $pool->fresh()->cached_balance);

        $expense->update(['status' => ExpenseStatus::Rejected]);

        $this->assertSame(
            10000.00,
            (float) $pool->fresh()->cached_balance,
            'Reject reverses the deduction — the pool returns to its pre-expense balance.',
        );
    }

    public function test_status_round_trip_between_pending_and_rejected_reverses_and_reapplies(): void
    {
        // Model A: reject reverses the deduction, resubmit (Rejected ->
        // Pending) re-applies it, and re-reject reverses it again. Each
        // transition moves the pool; the rejected state always nets to zero.
        $pool = CashPool::factory()->withOpeningBalance(10000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 2000,
            'status' => ExpenseStatus::Pending,
        ]);

        $expense->update(['status' => ExpenseStatus::Rejected]);
        $this->assertSame(10000.00, (float) $pool->fresh()->cached_balance,
            'Reject reverses the deduction.');

        $expense->update(['status' => ExpenseStatus::Pending]);
        $this->assertSame(
            8000.00,
            (float) $pool->fresh()->cached_balance,
            'Resubmit (Rejected -> Pending) re-applies the deduction.',
        );

        $expense->update(['status' => ExpenseStatus::Rejected]);
        $this->assertSame(
            10000.00,
            (float) $pool->fresh()->cached_balance,
            'Re-reject (Pending -> Rejected) reverses the deduction again.',
        );
    }

    public function test_admin_edit_increasing_the_amount_debits_only_the_delta(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(10000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1000,
        ]);
        $this->assertSame(9000.00, (float) $pool->fresh()->cached_balance);

        $expense->update(['amount' => 1500]);

        $this->assertSame(
            8500.00,
            (float) $pool->fresh()->cached_balance,
            'An amount increase of 500 should remove only 500 more from the pool, not the full new amount.'
        );
    }

    public function test_admin_edit_decreasing_the_amount_credits_the_delta(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(10000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1500,
        ]);
        $this->assertSame(8500.00, (float) $pool->fresh()->cached_balance);

        $expense->update(['amount' => 1000]);

        $this->assertSame(
            9000.00,
            (float) $pool->fresh()->cached_balance,
            'An amount decrease of 500 should give back exactly 500 to the pool.'
        );
    }

    public function test_changing_only_the_pool_credits_the_old_pool_and_debits_the_new_one(): void
    {
        $oldPool = CashPool::factory()->withOpeningBalance(10000)->create();
        $newPool = CashPool::factory()->withOpeningBalance(10000)->create();

        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $oldPool->id,
            'amount' => 2000,
        ]);
        $this->assertSame(8000.00, (float) $oldPool->fresh()->cached_balance);
        $this->assertSame(10000.00, (float) $newPool->fresh()->cached_balance);

        $expense->update(['paid_from_pool_id' => $newPool->id]);

        $this->assertSame(
            10000.00,
            (float) $oldPool->fresh()->cached_balance,
            'Old pool must be fully credited back when an expense moves to a different pool.'
        );
        $this->assertSame(
            8000.00,
            (float) $newPool->fresh()->cached_balance,
            'New pool must be debited the full expense amount when an expense moves to it.'
        );
    }

    public function test_changing_pool_and_amount_simultaneously_uses_old_amount_for_old_pool(): void
    {
        $oldPool = CashPool::factory()->withOpeningBalance(10000)->create();
        $newPool = CashPool::factory()->withOpeningBalance(10000)->create();

        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $oldPool->id,
            'amount' => 2000,
        ]);

        $expense->update([
            'paid_from_pool_id' => $newPool->id,
            'amount' => 3000,
        ]);

        $this->assertSame(
            10000.00,
            (float) $oldPool->fresh()->cached_balance,
            'Old pool must receive its ORIGINAL amount (2000) back, not the new amount.'
        );
        $this->assertSame(
            7000.00,
            (float) $newPool->fresh()->cached_balance,
            'New pool must be debited the NEW amount (3000), not the original.'
        );
    }
}
