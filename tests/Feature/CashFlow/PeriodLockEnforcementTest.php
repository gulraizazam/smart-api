<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\PeriodLock;
use App\Models\PaymentModes;
use App\Services\CashFlow\ExpenseService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Period locks are the mechanism that stops users from posting backdated
 * entries into a closed accounting month. The audit added the period_locks
 * table and the `CashflowHelper::isDateInLockedPeriod` guard — these tests
 * pin both the happy and the rejected paths.
 */
class PeriodLockEnforcementTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ExpenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(ExpenseService::class);
    }

    public function test_creating_an_expense_in_an_unlocked_period_succeeds(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $category = ExpenseCategory::factory()->create();
        $paymentMode = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        $expense = $this->service->create([
            'expense_date' => now()->toDateString(),
            'amount' => 500,
            'category_id' => $category->id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $paymentMode->id,
            'description' => 'Today\'s receipt',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);

        $this->assertNotNull($expense->id);
    }

    public function test_creating_an_expense_inside_a_locked_period_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $category = ExpenseCategory::factory()->create();
        $paymentMode = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        $this->expectException(CashflowException::class);
        $this->service->create([
            'expense_date' => $previous->copy()->day(15)->toDateString(),
            'amount' => 500,
            'category_id' => $category->id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $paymentMode->id,
            'description' => 'Backdated entry',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);
    }

    public function test_a_locked_period_only_blocks_its_own_account(): void
    {
        $previous = now()->subMonthNoOverflow();
        PeriodLock::factory()->create([
            'account_id' => 999, // different tenant
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $category = ExpenseCategory::factory()->create();
        $paymentMode = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        $expense = $this->service->create([
            'expense_date' => $previous->copy()->day(15)->toDateString(),
            'amount' => 500,
            'category_id' => $category->id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $paymentMode->id,
            'description' => 'Allowed because lock belongs to a different account',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);

        $this->assertNotNull($expense->id);
    }
}
