<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\PaymentModes;
use App\Services\CashFlow\ExpenseService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\AssertsAuditTrail;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Exercises ExpenseService::create / void end-to-end.
 *
 * Reaches the service through the container (not HTTP) so the test does not
 * couple to the controller wiring or the form-request shape — that lets the
 * suite stay green when controllers are refactored, while still pinning the
 * pool-balance, audit-log, and state-transition contracts.
 *
 * The approve / reject / resubmit flows were removed in the Option-A
 * migration (2026-05-13): every payment posts as Approved by default and
 * review happens via flags, so no separate transition is needed. The
 * tests that exercised those methods were retired alongside the methods.
 */
class ExpenseLifecycleTest extends TestCase
{
    use AssertsAuditTrail;
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

    public function test_create_persists_expense_and_decrements_pool_and_writes_audit_log(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $category = ExpenseCategory::factory()->create();
        $paymentMethod = PaymentModes::query()->where('name', 'Card')->firstOrFail();

        $expense = $this->service->create([
            'expense_date' => now()->toDateString(),
            'amount' => 1234.56,
            'category_id' => $category->id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $paymentMethod->id,
            'description' => 'Office printer cartridge',
            'attachment_url' => 'attachments/test.pdf',
        ], accountId: 1);

        $this->assertNotNull($expense->id);
        $this->assertSame(48765.44, (float) $pool->fresh()->cached_balance);
        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_EXPENSE,
            $expense->id,
            CashflowAuditLog::ACTION_CREATED,
        );
    }

    public function test_void_credits_pool_back_and_marks_voided(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 7500,
            'status' => ExpenseStatus::Approved,
        ]);
        $this->assertSame(12500.00, (float) $pool->fresh()->cached_balance);

        $voided = $this->service->void($expense->id, 'Posted to wrong account', accountId: 1);

        $this->assertNotNull($voided->voided_at);
        $this->assertSame(20000.00, (float) $pool->fresh()->cached_balance);
        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_EXPENSE,
            $expense->id,
            CashflowAuditLog::ACTION_VOIDED,
        );
    }

    public function test_double_voiding_an_expense_is_rejected(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1000,
            'status' => ExpenseStatus::Approved,
        ]);

        $this->service->void($expense->id, 'first void', 1);

        $this->expectExceptionMessage('already voided');
        $this->service->void($expense->id, 'second void', 1);
    }
}
