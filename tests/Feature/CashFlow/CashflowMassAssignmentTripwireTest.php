<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\Vendor;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tripwires for mass-assignment. Today the FormRequest layer filters
 * sensitive fields out of the request before they reach the service —
 * but a single future bug ("just call ->update($request->all())", or
 * a developer adding `status` to a FormRequest rules() array) would
 * silently re-open the surface.
 *
 * Each test asserts the model layer's $fillable list still excludes /
 * controls a sensitive financial column. If $fillable is widened in a
 * way that lets a user override these, the test breaks and we catch
 * the regression at review time instead of in production.
 *
 * This file deliberately exercises Eloquent directly, not the API,
 * because $fillable is enforced at the model layer — the API is a
 * second line of defence we want to keep, but the model layer is the
 * one that protects against ANY future call site doing mass-assign.
 */
class CashflowMassAssignmentTripwireTest extends TestCase
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
     * The Expense model's $fillable today includes financial-state
     * columns. The contract relies on every caller passing through
     * ExpenseService or a FormRequest that filters them out. If those
     * fields ever sneak into a payload, mass-assignment WILL accept
     * them — this test pins the current behaviour so a future change
     * to $fillable is reviewable rather than silent.
     *
     * Owners: when you tighten $fillable to remove these (recommended),
     * flip the assertions and document the migration.
     */
    public function test_expense_fillable_contract_is_audited(): void
    {
        $fillable = (new Expense)->getFillable();
        $sensitive = [
            'account_id', 'status', 'verified_by', 'is_flagged', 'flag_reason',
            'voided_at', 'voided_by', 'void_reason', 'rejection_reason', 'created_by',
        ];

        foreach ($sensitive as $field) {
            $this->assertContains(
                $field,
                $fillable,
                "If you removed `{$field}` from Expense::\$fillable, update ExpenseService ".
                "to use forceCreate/forceFill on the same writes and adjust this test. ".
                "If you ADDED a new sensitive field, add it to this assertion list."
            );
        }
    }

    public function test_pool_service_create_does_not_honour_caller_cached_balance(): void
    {
        // The pool's `cached_balance` MUST be derived from
        // `opening_balance` on create, never copied verbatim from the
        // request. PoolService::createPool builds the array manually
        // — this test pins that contract so a future "just pass $data
        // through" refactor doesn't quietly let a caller inflate the
        // wallet to whatever amount they want.
        $poolService = app(\App\Services\CashFlow\PoolService::class);

        $hostileData = [
            'type' => 'head_office_cash',
            'name' => 'Hostile pool',
            'opening_balance' => 100,
            'cached_balance' => 999_999_999,   // attempt to inflate
            'account_id' => 7777,              // attempt to cross-tenant
        ];

        $pool = $poolService->createPool($hostileData, accountId: 1);

        $this->assertSame(100.0, (float) $pool->cached_balance,
            'cached_balance must mirror opening_balance on create — caller-supplied value must be discarded.');
        $this->assertSame(1, (int) $pool->account_id,
            'account_id must come from the trusted argument, never from the request payload.');
    }

    public function test_vendor_cached_balance_is_not_user_settable_via_api_route(): void
    {
        $rules = (new \App\Http\Requests\CashFlow\StoreVendorRequest)->rules();
        $this->assertArrayNotHasKey(
            'cached_balance',
            $rules,
            'StoreVendorRequest must NOT validate cached_balance — running total is server-derived.'
        );

        $updateRules = (new \App\Http\Requests\CashFlow\UpdateVendorRequest)->rules();
        $this->assertArrayNotHasKey(
            'cached_balance',
            $updateRules,
            'UpdateVendorRequest must NOT validate cached_balance — running total is server-derived.'
        );
    }

    public function test_expense_form_requests_do_not_accept_workflow_fields(): void
    {
        // The FormRequest layer is the actual user-facing protection.
        // If any of these slip into the rules() list, the controller
        // path becomes mass-assign-vulnerable — fail the build.
        $sensitive = [
            'status', 'verified_by', 'is_flagged', 'flag_reason',
            'voided_at', 'voided_by', 'void_reason', 'rejection_reason',
            'created_by', 'account_id',
        ];

        $storeRules = (new \App\Http\Requests\CashFlow\StoreExpenseRequest)->rules();
        $updateRules = (new \App\Http\Requests\CashFlow\UpdateExpenseRequest)->rules();

        foreach ($sensitive as $field) {
            $this->assertArrayNotHasKey(
                $field,
                $storeRules,
                "StoreExpenseRequest must NOT accept `{$field}` — that's a workflow / audit field set by the service layer."
            );
            $this->assertArrayNotHasKey(
                $field,
                $updateRules,
                "UpdateExpenseRequest must NOT accept `{$field}` — workflow changes go through approve/reject/void endpoints."
            );
        }
    }
}
