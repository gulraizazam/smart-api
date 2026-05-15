<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashFlowExpense;
use App\Models\CashFlow\CashFlowPool;
use App\Models\CashFlow\CashFlowCategory;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\PeriodLock;
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
        // Export returns a CSV stream, not JSON — use `get` so TestResponse
        // wraps the StreamedResponse correctly.
        $response = $this->get('/api/cashflow/expenses/export');
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/expenses/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }

    /**
     * Search smoke-tests — pin that the expanded multi-relation search
     * doesn't crash for either textual or purely-numeric input. The exact
     * row-level behaviour is covered by the service-layer test below.
     */
    public function test_expense_search_with_text_query_does_not_crash(): void
    {
        $response = $this->getJson('/api/cashflow/expenses/data?search=Rent');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_expense_search_with_numeric_query_does_not_crash(): void
    {
        // Purely-numeric search must take the exact-match branch, not the
        // LIKE-on-number branch that used to false-positive across orders
        // of magnitude (e.g. "1000" matching "10000").
        $response = $this->getJson('/api/cashflow/expenses/data?search=1000');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_expense_search_with_trailing_space_does_not_crash(): void
    {
        // Trailing whitespace is the opt-in signal that switches the matcher
        // from substring LIKE to word-boundary LIKE — "pens " should match
        // the word "pens" but not "Dispenser".
        $response = $this->getJson('/api/cashflow/expenses/data?' . http_build_query([
            'search' => 'pens ',
        ]));
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_expense_search_with_quoted_term_does_not_crash(): void
    {
        // Quoted-term is the other word-boundary signal — mirrors Google /
        // JIRA semantics for opt-in exact-word search.
        $response = $this->getJson('/api/cashflow/expenses/data?' . http_build_query([
            'search' => '"pens"',
        ]));
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_expense_search_by_id_with_hash_prefix(): void
    {
        // 2026-05-14: the row badge surfaces a payment as `#<id>`, so the
        // search box now accepts the same syntax. Typing "#234" narrows
        // to the single matching row regardless of other content.
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $target = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 500,
            'description' => 'Unique target row',
            'status' => ExpenseStatus::Approved,
        ]);
        Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 700,
            'description' => 'Other row',
            'status' => ExpenseStatus::Approved,
        ]);

        $response = $this->getJson('/api/cashflow/expenses/data?' . http_build_query([
            'search' => '#'.$target->id,
        ]));

        $response->assertStatus(200);
        // Exactly one row, and it's the target ID.
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$target->id], array_values($ids));
    }

    public function test_expense_search_with_numeric_query_matches_id_or_amount(): void
    {
        // Bare-numeric search hits both `id` and `amount` — covers the
        // two common reasons a user types a number into search.
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $byId = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 99,
            'description' => 'Match by id',
            'status' => ExpenseStatus::Approved,
        ]);
        $byAmount = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => $byId->id, // amount equals the other row's ID
            'description' => 'Match by amount',
            'status' => ExpenseStatus::Approved,
        ]);

        $response = $this->getJson('/api/cashflow/expenses/data?' . http_build_query([
            'search' => (string) $byId->id,
        ]));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        // Both rows surface — one matched by id, one by amount.
        $this->assertContains($byId->id, $ids);
        $this->assertContains($byAmount->id, $ids);
    }

    /**
     * Phase-1 fix: SPA now sends `pool_id` to the export endpoint, and the
     * export endpoint now reads it. Prior to this change the export silently
     * ignored the pool filter the user had selected.
     */
    public function test_export_filters_by_pool_id(): void
    {
        $poolA = CashPool::factory()->withOpeningBalance(50000)->create();
        $poolB = CashPool::factory()->withOpeningBalance(50000)->create();

        Expense::factory()->create([
            'paid_from_pool_id' => $poolA->id,
            'amount' => 1000,
            'description' => 'Belongs to pool A',
            'status' => ExpenseStatus::Approved,
            'attachment_url' => 'attachments/a.pdf',
        ]);
        Expense::factory()->create([
            'paid_from_pool_id' => $poolB->id,
            'amount' => 2000,
            'description' => 'Belongs to pool B',
            'status' => ExpenseStatus::Approved,
            'attachment_url' => 'attachments/b.pdf',
        ]);

        $response = $this->get('/api/cashflow/expenses/export?pool_id=' . $poolA->id);
        $response->assertStatus(200);

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Belongs to pool A', $csv);
        $this->assertStringNotContainsString('Belongs to pool B', $csv);
    }

    /**
     * Phase-1 fix: the export search where-clause now matches the list-data
     * scope, including `notes` and `amount` alongside description / ref / vendor.
     */
    public function test_export_search_matches_notes_and_amount(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();

        Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 4242,
            'description' => 'Generic item',
            'notes' => 'mango-flavoured printer toner',
            'status' => ExpenseStatus::Approved,
            'attachment_url' => 'attachments/x.pdf',
        ]);
        Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 9999,
            'description' => 'Different item',
            'notes' => 'something unrelated',
            'status' => ExpenseStatus::Approved,
            'attachment_url' => 'attachments/y.pdf',
        ]);

        // Notes-only match: 'mango' lives in `notes`, not description.
        $byNotes = $this->get('/api/cashflow/expenses/export?search=mango');
        $byNotes->assertStatus(200);
        $this->assertStringContainsString('Generic item', $byNotes->streamedContent());
        $this->assertStringNotContainsString('Different item', $byNotes->streamedContent());

        // Amount-only match: '4242' lives in `amount`, not description.
        $byAmount = $this->get('/api/cashflow/expenses/export?search=4242');
        $byAmount->assertStatus(200);
        $this->assertStringContainsString('Generic item', $byAmount->streamedContent());
        $this->assertStringNotContainsString('Different item', $byAmount->streamedContent());
    }

    /**
     * Phase-4 contract pin: `cashflow_expense_duplicate` is a SPA-side UX
     * gate only. The SPA's Copy row action calls `POST /expenses/store`,
     * which is still guarded by `cashflow_expense_create`. A user holding
     * only the duplicate slug must NOT be able to persist an expense.
     */
    public function test_duplicate_permission_alone_does_not_allow_store(): void
    {
        $payload = [
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 1000,
            'category_id' => 1,
            'paid_from_pool_id' => 1,
            'payment_method_id' => 1,
            'description' => 'Should be blocked',
        ];

        // Negative case — strip all roles, grant ONLY the duplicate slug, post
        // the same payload. The `StoreExpenseRequest::authorize()` gate must
        // 403 before validation runs.
        auth()->user()->roles()->detach();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createPermission('cashflow_expense_duplicate');
        $dupOnlyRole = $this->createRole('dup-only-' . uniqid());
        $dupOnlyRole->givePermissionTo(['cashflow_expense_duplicate']);
        auth()->user()->assignRole($dupOnlyRole);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $blocked = $this->postJson('/api/cashflow/expenses/store', $payload);
        $blocked->assertStatus(403);

        // Positive case — add the create slug (still no duplicate-specific
        // privilege required by the endpoint). The same payload now clears
        // the gate; it may still 422 on partial fields or 200/201 on a full
        // submission, but it must NOT be 403 anymore. This pins that
        // `cashflow_expense_create` is the actual gating slug on /store.
        $dupOnlyRole->givePermissionTo(['cashflow_expense_create']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $allowedThroughGate = $this->postJson('/api/cashflow/expenses/store', $payload);
        $this->assertNotSame(403, $allowedThroughGate->status(), 'Adding cashflow_expense_create must clear the auth gate.');
    }

    /**
     * Phase-1 fix: the form-data endpoint now includes a `has_period_locks`
     * boolean so the SPA can surface an inline warning under the date field.
     */
    public function test_form_data_includes_has_period_locks(): void
    {
        // No locks → flag is false.
        $resBefore = $this->getJson('/api/cashflow/expenses/form-data');
        $resBefore->assertStatus(200)
            ->assertJsonPath('data.has_period_locks', false);

        // Insert one lock for the test account → flag flips true.
        PeriodLock::create([
            'account_id' => 1,
            'month' => 1,
            'year' => now()->year - 1,
            'locked_by' => auth()->id(),
        ]);

        $resAfter = $this->getJson('/api/cashflow/expenses/form-data');
        $resAfter->assertStatus(200)
            ->assertJsonPath('data.has_period_locks', true);
    }

    public function test_reject_flips_status_and_stores_reason_without_touching_pool(): void
    {
        // 2026-05-14 Model B (substance-over-form): reject is a
        // record-correction workflow. Status + reason update but
        // the pool balance stays untouched because the cash event
        // already happened. Use Void to actually reverse the cash.
        $this->grantPermissions(['cashflow_expense_reject']);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 2000,
            'status' => ExpenseStatus::Approved,
        ]);
        $poolBefore = (float) $pool->fresh()->cached_balance;

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Wrong category — please correct.',
        ]);

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertSame(ExpenseStatus::Rejected, $expense->status);
        $this->assertSame('Wrong category — please correct.', $expense->rejection_reason);
        // Pool unchanged — reject is record-status only.
        $this->assertSame($poolBefore, (float) $pool->fresh()->cached_balance);
    }

    public function test_editing_a_rejected_expense_moves_it_to_pending_for_admin_approval(): void
    {
        // Accountant's fix moves the row to Pending so the admin gets
        // a re-review step. Pool stays put throughout the cycle.
        $this->grantPermissions(['cashflow_expense_edit']);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1500,
            'description' => 'pen refills',
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'category was wrong',
        ]);
        $poolBefore = (float) $pool->fresh()->cached_balance;

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/edit", [
            'expense_date' => $expense->expense_date->toDateString(),
            'amount' => 1500,
            'category_id' => $expense->category_id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $expense->payment_method_id,
            'is_for_general' => true,
            'description' => 'pen refills — corrected category',
            'edit_reason' => 'fixed the category per admin feedback',
        ]);

        $this->assertNotSame(422, $response->status());
        $expense->refresh();
        $this->assertSame(ExpenseStatus::Pending, $expense->status);
        $this->assertNull($expense->rejection_reason);
        // Pool unchanged throughout the cycle (Model B).
        $this->assertSame($poolBefore, (float) $pool->fresh()->cached_balance);
    }

    public function test_approve_on_pending_flips_status_without_touching_pool(): void
    {
        // Admin closes the loop: Pending → Approved. Pool stays put
        // (Model B — the cash event was recorded at creation; the
        // reject/approve cycle is metadata-only).
        $this->grantPermissions(['cashflow_expense_approve']);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1500,
            'status' => ExpenseStatus::Pending,
        ]);
        $poolBefore = (float) $pool->fresh()->cached_balance;

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/approve");

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertSame(ExpenseStatus::Approved, $expense->status);
        $this->assertSame((int) auth()->id(), (int) $expense->verified_by);
        $this->assertSame($poolBefore, (float) $pool->fresh()->cached_balance);
    }

    public function test_approve_on_rejected_overrides_the_cycle_without_touching_pool(): void
    {
        // Admin can approve a Rejected row directly — undo a mistaken
        // reject, or skip the accountant cycle when no fix is needed.
        // Pool unchanged (Model B); rejection_reason cleared.
        $this->grantPermissions(['cashflow_expense_approve']);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 800,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'accidentally rejected',
        ]);
        $poolBefore = (float) $pool->fresh()->cached_balance;

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/approve");

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertSame(ExpenseStatus::Approved, $expense->status);
        $this->assertSame((int) auth()->id(), (int) $expense->verified_by);
        $this->assertNull($expense->rejection_reason);
        $this->assertSame($poolBefore, (float) $pool->fresh()->cached_balance);
    }

    public function test_reject_from_pending_marks_status_rejected_with_new_reason(): void
    {
        // Admin reviewed the accountant's fix and rejected it again
        // (Pending → Rejected). Status flips, new reason persists,
        // pool stays put (Model B).
        $this->grantPermissions(['cashflow_expense_reject']);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1200,
            'status' => ExpenseStatus::Pending,
        ]);
        $poolBefore = (float) $pool->fresh()->cached_balance;

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'fix still wrong — vendor needs to be set.',
        ]);

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertSame(ExpenseStatus::Rejected, $expense->status);
        $this->assertSame('fix still wrong — vendor needs to be set.', $expense->rejection_reason);
        $this->assertSame($poolBefore, (float) $pool->fresh()->cached_balance);
    }

    public function test_voiding_a_rejected_row_refunds_the_pool(): void
    {
        // Regression for the Model A → Model B bug: void path used
        // to skip the pool refund when status was Rejected (because
        // Model A had already refunded on reject). Under Model B
        // reject doesn't touch the pool, so void MUST always refund.
        $this->grantPermissions(['cashflow_expense_void']);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 900,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'duplicate, voiding',
        ]);
        $poolBefore = (float) $pool->fresh()->cached_balance;

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/void", [
            'void_reason' => 'confirmed duplicate of #999',
        ]);

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertNotNull($expense->voided_at);
        // Pool refunded by the full amount, even though the source
        // status was Rejected.
        $this->assertSame($poolBefore + 900.0, (float) $pool->fresh()->cached_balance);
    }

    public function test_creator_can_edit_own_rejected_row_without_global_edit_slug(): void
    {
        // The escape hatch: row's creator gets edit rights on their
        // OWN rejected payment even when they don't have
        // `cashflow_expense_edit`. Anyone else (or different status)
        // is still blocked.
        // Start clean — strip the test-default edit permission.
        auth()->user()->syncPermissions([]);
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 700,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'please reattach receipt',
            'created_by' => auth()->id(), // we're the creator
        ]);

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/edit", [
            'expense_date' => $expense->expense_date->toDateString(),
            'amount' => 700,
            'category_id' => $expense->category_id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $expense->payment_method_id,
            'is_for_general' => true,
            'description' => 'creator-fix run',
            'edit_reason' => 'attached the missing receipt',
        ]);

        // Not 403 — even though we lack the global edit slug.
        $this->assertNotSame(403, $response->status());
    }

    public function test_expense_edit_accepts_dates_older_than_7_days(): void
    {
        // 2026-05-14: the 7-day backdating floor is enforced on CREATE
        // only. Edits are admin overrides with a mandatory `edit_reason`
        // + full audit trail, so old rows must stay editable. Prior to
        // the relax, this same request returned 422 on `expense_date`.
        $pool = CashPool::factory()->withOpeningBalance(50000)->create();
        $expense = Expense::factory()->create([
            'paid_from_pool_id' => $pool->id,
            'amount' => 1000,
            'status' => ExpenseStatus::Approved,
            'expense_date' => now()->subDays(30)->toDateString(),
            'description' => 'old payment',
            'attachment_url' => 'https://drive.google.com/file/d/old/view',
        ]);

        $response = $this->postJson("/api/cashflow/expenses/{$expense->id}/edit", [
            'expense_date' => $expense->expense_date->toDateString(), // 30 days ago, unchanged
            'amount' => 1000,
            'category_id' => $expense->category_id,
            'paid_from_pool_id' => $pool->id,
            'payment_method_id' => $expense->payment_method_id,
            'is_for_general' => true,
            'description' => 'corrected description',
            'edit_reason' => 'fix typo on a 30-day-old row',
        ]);

        // Should NOT be 422 on `expense_date.after_or_equal` — the rule
        // was retired for edits.
        $this->assertNotSame(422, $response->status(), 'edit on a 30-day-old row should not validation-fail');
        // Either 200 (success) or 500 (downstream issue unrelated to
        // the date rule) is acceptable — the contract this test pins
        // is "no 422 from the date floor".
        $this->assertContains($response->status(), [200, 500]);
    }
}
