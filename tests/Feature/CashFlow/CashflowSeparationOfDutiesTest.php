<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Exceptions\CashflowException;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Services\CashFlow\ExpenseService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Separation of duties: when an expense has been rejected and edited
 * back to Pending, the second approval MUST come from a different
 * admin than the one who created it. Without this guard, an admin
 * could create + auto-approve, get rejected by a second admin, edit
 * their row, then approve their own fix — bypassing review while the
 * audit log shows two normal-looking approvals by the same person.
 *
 * void / reject are NOT subject to the same constraint — they don't
 * advance the row's state forward, they only invalidate or kick back.
 */
class CashflowSeparationOfDutiesTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ExpenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->service = app(ExpenseService::class);
    }

    public function test_creator_cannot_approve_their_own_pending_expense(): void
    {
        $creator = $this->actingAsAdmin();

        $expense = $this->makePendingExpense(createdBy: $creator->id);

        $this->expectException(CashflowException::class);
        $this->expectExceptionMessageMatches('/cannot approve an expense you created/i');

        $this->service->approve($expense->id, accountId: 1);
    }

    public function test_creator_cannot_approve_their_own_rejected_expense_after_edit(): void
    {
        $creator = $this->actingAsAdmin();

        // Build the full reject + edit cycle so we're testing the exact
        // workflow an attacker would walk: their own create gets
        // rejected by another admin, they fix it, then try to approve.
        $expense = Expense::create([
            'account_id' => 1,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'creator self-approve probe',
            'status' => ExpenseStatus::Rejected,    // already rejected by second admin
            'rejection_reason' => 'try again',
            'is_flagged' => 0,
            'created_by' => $creator->id,
            'is_for_general' => 1,
        ]);

        // Creator edits — adminEdit flips Rejected → Pending. That part
        // is legitimate (the creator owns the fix path).
        $this->service->adminEdit($expense->id, [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 150,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'creator self-approve probe v2',
            'is_for_general' => 1,
            'edit_reason' => 'tightening the number',
        ], accountId: 1);

        // Now the violation: same creator calls approve.
        $this->expectException(CashflowException::class);
        $this->expectExceptionMessageMatches('/cannot approve an expense you created/i');

        $this->service->approve($expense->id, accountId: 1);
    }

    public function test_different_admin_can_approve_the_pending_expense(): void
    {
        $creator = $this->actingAsAdmin();
        $expense = $this->makePendingExpense(createdBy: $creator->id);

        // Switch acting user to a different admin and try to approve.
        $reviewer = $this->actingAsAdmin();
        $this->assertNotSame($creator->id, $reviewer->id, 'Sanity: two distinct admins.');

        $approved = $this->service->approve($expense->id, accountId: 1);

        $this->assertSame(ExpenseStatus::Approved->value, $approved->status->value);
        $this->assertSame($reviewer->id, (int) $approved->verified_by,
            'verified_by must record the second admin, not the creator.');
    }

    /* --------------------------------------------------------------- */

    private function makePendingExpense(int $createdBy): Expense
    {
        return Expense::create([
            'account_id' => 1,
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 100,
            'category_id' => ExpenseCategory::query()->first()->id,
            'paid_from_pool_id' => $this->defaultCashPool->id,
            'payment_method_id' => 1,
            'description' => 'pending probe',
            'status' => ExpenseStatus::Pending,
            'is_flagged' => 0,
            'created_by' => $createdBy,
            'is_for_general' => 1,
        ]);
    }
}
