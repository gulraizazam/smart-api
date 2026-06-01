<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashflowSetting;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\User;
use App\Services\CashFlow\ExpenseService;
use App\Services\CashFlow\PoolService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins expense reject = pool reversal (Model A), aligning crm3 with the
 * legacy app (crm2) so both stay consistent on the SHARED prod
 * cash_pools.cached_balance column during the coexistence window.
 *
 * Contract:
 *   - create        -> debit pool
 *   - reject         -> REVERSE the debit (credit back)
 *   - resubmit       -> re-apply the debit (with the current amount)
 *   - void-of-rejected -> NO pool change (reject already reversed it)
 *   - recompute      -> EXCLUDES rejected rows
 *
 * This reverts the short-lived "Model B" (reject is a pool no-op), which
 * diverged from the Model-A history crm2 built and still maintains.
 */
class ExpenseRejectPoolReversalTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private CashPool $pool;

    private ExpenseCategory $category;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->pool = CashPool::factory()->create(['opening_balance' => 10000, 'cached_balance' => 10000]);
        $this->category = ExpenseCategory::query()->first();
        // A DIFFERENT user than the acting admin — reject()/void() enforce
        // separation of duties (you can't reject/void your own expense).
        $this->creator = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);
    }

    public function test_rejecting_an_expense_reverses_the_pool(): void
    {
        $before = $this->poolBalance();

        $expense = $this->makeExpense(1500);
        $this->assertSame($before - 1500, $this->poolBalance(), 'Sanity: create debits the pool.');

        app(ExpenseService::class)->reject($expense->id, 'not valid', accountId: 1);

        $this->assertSame($before, $this->poolBalance(),
            'Rejecting an expense MUST reverse the pool deduction (Model A).');
    }

    public function test_resubmitting_a_rejected_expense_re_debits_the_pool(): void
    {
        $before = $this->poolBalance();

        $expense = $this->makeExpense(1500);
        app(ExpenseService::class)->reject($expense->id, 'fix it', accountId: 1);
        $this->assertSame($before, $this->poolBalance(), 'Sanity: reject reversed the debit.');

        // Resubmit = status flips Rejected -> Pending (what adminEdit does on
        // save). The observer must re-apply the deduction with the current
        // amount.
        $expense->fresh()->update(['status' => ExpenseStatus::Pending]);

        $this->assertSame($before - 1500, $this->poolBalance(),
            'Resubmitting a rejected expense MUST re-debit the pool.');
    }

    public function test_voiding_a_rejected_expense_does_not_double_credit(): void
    {
        $before = $this->poolBalance();

        $expense = $this->makeExpense(1500);
        app(ExpenseService::class)->reject($expense->id, 'reject first', accountId: 1);
        $this->assertSame($before, $this->poolBalance(), 'Sanity: reject reversed the debit.');

        // Voiding an already-rejected expense must NOT credit the pool again —
        // reject already gave the money back.
        app(ExpenseService::class)->void($expense->id, 'then void', accountId: 1);

        $this->assertSame($before, $this->poolBalance(),
            'Voiding an already-rejected expense must NOT double-credit the pool.');
    }

    public function test_recompute_excludes_rejected_expenses(): void
    {
        CashflowSetting::setValue('go_live_date', now()->subWeek()->toDateString(), 1);

        $this->makeExpense(2000);                 // stays approved -> counted
        $rejected = $this->makeExpense(1500);
        app(ExpenseService::class)->reject($rejected->id, 'rejected', accountId: 1);

        // Incremental balance now: 10000 - 2000 (approved) = 8000
        // (the rejected 1500 was debited at create then reversed at reject).
        $this->assertSame(8000.0, $this->poolBalance(), 'Sanity: only the approved expense stands.');

        // The read-only recompute must match the incremental balance for this
        // pool (diff 0) — which only holds if it EXCLUDES the rejected row.
        // If recompute still counted the rejected 1500, calculated would be
        // 6500 vs cached 8000 -> diff -1500. Asserting diff 0 is immune to any
        // fixture movements (they'd land in both cached and calculated).
        $report = app(PoolService::class)->calculateExpectedBalances(1);
        $mine = collect($report['per_pool'])->firstWhere('pool_id', $this->pool->id);

        $this->assertNotNull($mine, 'This pool must appear in the recompute report.');
        $this->assertSame(8000.0, (float) $mine['calculated_balance'],
            'Recompute must count only the approved expense (rejected excluded).');
        $this->assertSame(0.0, (float) $mine['diff'],
            'Recompute (excludes rejected) must match the incremental balance (reversed rejected).');
    }

    /* --------------------------------------------------------------- */

    private function poolBalance(): float
    {
        return (float) $this->pool->fresh()->cached_balance;
    }

    private function makeExpense(int $amount): Expense
    {
        return Expense::create([
            'account_id' => 1,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => $amount,
            'category_id' => $this->category->id,
            'paid_from_pool_id' => $this->pool->id,
            'payment_method_id' => 1,
            'description' => "test {$amount}",
            'status' => ExpenseStatus::Approved,
            'verified_by' => 1,
            'is_flagged' => 0,
            'created_by' => $this->creator->id,
            'is_for_general' => 1,
            // Needed for the recompute window (system_created_at >= go_live).
            'system_created_at' => now(),
        ]);
    }
}
