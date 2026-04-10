<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\CashFlow\StoreExpenseRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StoreExpenseRequest is the first line of defence for the CashFlow
 * module — every expense entered into the system passes through its
 * rules() before an Expense row is written. A regression that lets a
 * zero amount, a future-dated expense, or an expense missing a branch
 * slip through would corrupt the P&L report and undermine the audit
 * trail.
 *
 * Invariants pinned:
 *
 *   1. `amount` is required, numeric, min 1, max 99,999,999, and an
 *      INTEGER (no paisa — the production DB column is an int to
 *      avoid the float-rounding class of bugs the audit fixed).
 *   2. `expense_date` is required and cannot be in the future (an
 *      operator cannot pre-log an expense), and cannot be older than
 *      7 days (stops someone back-dating into a closed period).
 *   3. `description` is required, min 3 chars, max 100 — the audit-
 *      log receipt relies on this being non-empty.
 *   4. The `withValidator()` after-callback enforces two cross-field
 *      invariants that pure rule strings cannot express:
 *        - `for_branch_id` OR `is_for_general` must be set. A branch-
 *          less company-wide expense must be explicitly marked.
 *        - `paid_from_pool_id` OR `staff_id` must be set. Every
 *          expense has a funding source — cash pool or staff float.
 */
class StoreExpenseRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    /**
     * Build a payload that is fully valid except for the keys the
     * caller wants to override. Keeps the individual tests focused on
     * one field at a time.
     */
    private function validPayload(array $overrides = []): array
    {
        $poolId = \App\Models\CashFlow\CashPool::first()->id;
        $categoryId = \App\Models\CashFlow\ExpenseCategory::first()->id;
        $paymentModeId = \App\Models\PaymentModes::where('name', '!=', 'Cash')->first()->id
            ?? \App\Models\PaymentModes::first()->id;
        $locationId = \App\Models\Locations::first()->id;

        return array_merge([
            'expense_date' => now()->toDateString(),
            'amount' => 500,
            'category_id' => $categoryId,
            'paid_from_pool_id' => $poolId,
            'for_branch_id' => $locationId,
            'payment_method_id' => $paymentModeId,
            'description' => 'Office supplies',
        ], $overrides);
    }

    /**
     * Instantiate the form request and run its rules + withValidator
     * hook against a data array. Returns the underlying Validator
     * instance so assertions can inspect errors.
     */
    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = StoreExpenseRequest::create('/', 'POST', $payload);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);

        $validator = Validator::make($payload, $request->rules(), $request->messages() ?? []);
        $request->withValidator($validator);

        return $validator;
    }

    public function test_valid_payload_passes_all_rules(): void
    {
        $validator = $this->validatorFor($this->validPayload());

        $this->assertTrue(
            $validator->passes(),
            'Baseline valid payload should pass — errors: ' . $validator->errors()->first(),
        );
    }

    public function test_amount_must_be_at_least_1(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 0]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_rejects_decimals(): void
    {
        // The integer rule is the load-bearing one — paisa values are
        // not allowed because the DB column is int.
        $validator = $this->validatorFor($this->validPayload(['amount' => 99.50]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_rejects_values_over_the_hard_cap(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 100_000_000]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_expense_date_cannot_be_in_the_future(): void
    {
        $validator = $this->validatorFor($this->validPayload([
            'expense_date' => now()->addDay()->toDateString(),
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expense_date', $validator->errors()->toArray());
    }

    public function test_expense_date_cannot_be_older_than_7_days(): void
    {
        // 8 days ago — outside the "edit window" cap that stops
        // operators back-dating into a closed period.
        $validator = $this->validatorFor($this->validPayload([
            'expense_date' => now()->subDays(8)->toDateString(),
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expense_date', $validator->errors()->toArray());
    }

    public function test_description_below_min_length_fails(): void
    {
        $validator = $this->validatorFor($this->validPayload(['description' => 'ab']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }

    public function test_category_id_must_reference_an_existing_category(): void
    {
        $validator = $this->validatorFor($this->validPayload(['category_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category_id', $validator->errors()->toArray());
    }

    public function test_cross_field_guard_requires_branch_or_general_flag(): void
    {
        // Neither for_branch_id nor is_for_general — the
        // withValidator callback writes an error against for_branch_id.
        $validator = $this->validatorFor($this->validPayload([
            'for_branch_id' => null,
            'is_for_general' => null,
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('for_branch_id', $validator->errors()->toArray());
    }

    public function test_cross_field_guard_accepts_general_flag_without_branch(): void
    {
        // is_for_general replaces the need for a specific branch —
        // this is the "company-wide expense" path.
        $validator = $this->validatorFor($this->validPayload([
            'for_branch_id' => null,
            'is_for_general' => true,
        ]));

        $this->assertTrue(
            $validator->passes(),
            'General flag without branch should be valid — errors: ' . $validator->errors()->first(),
        );
    }

    public function test_cross_field_guard_requires_pool_or_staff_source(): void
    {
        // Neither funding source — withValidator writes an error
        // against paid_from_pool_id.
        $validator = $this->validatorFor($this->validPayload([
            'paid_from_pool_id' => null,
            'staff_id' => null,
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('paid_from_pool_id', $validator->errors()->toArray());
    }
}
