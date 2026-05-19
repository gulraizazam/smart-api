<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\PackageAdvances;
use App\Models\PaymentModes;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\DashboardService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Phase-9 contract: cash classification is centralised in
 * `PaymentModes::cashIds(accountId)`.
 *
 * Pre-fix, the FDM dashboard used `where('payment_mode', 1)` literals AND
 * in-process `str_contains($name, 'cash')` substring scans in different
 * places of the same controller. Both broke when a tenant renamed their
 * primary cash mode or seeded a second one ("Cash - Petty"). The helper
 * detects every active payment mode whose `payment_type = 1` OR whose name
 * contains "cash" (case-insensitive), and returns `[1]` as a last-resort
 * fallback.
 */
class FdmPaymentModeClassificationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_default_cash_mode_is_detected(): void
    {
        // The fixture seeder always creates a "Cash" row. Look it up by name
        // rather than assuming id=1 — `firstOrCreate` doesn't guarantee the id.
        $cashId = PaymentModes::query()->where('name', 'Cash')->value('id');
        $this->assertNotNull($cashId, 'Fixture seeder should create a "Cash" payment mode.');

        $ids = PaymentModes::cashIds(1);
        $this->assertContains($cashId, $ids);
    }

    public function test_a_second_cash_mode_is_also_detected(): void
    {
        PaymentModes::create([
            'account_id' => 1,
            'name' => 'Cash - Petty',
            'active' => 1,
            'payment_type' => 0,
        ]);

        $ids = PaymentModes::cashIds(1);
        $this->assertGreaterThanOrEqual(2, count($ids));
        $names = PaymentModes::query()->whereIn('id', $ids)->pluck('name')->all();
        $this->assertContains('Cash - Petty', $names);
    }

    public function test_payment_type_one_classifies_as_cash_even_without_cash_in_name(): void
    {
        // A tenant who flags `payment_type = 1` as the canonical cash flag
        // should be classified as cash even if they rename the row.
        $row = PaymentModes::create([
            'account_id' => 1,
            'name' => 'Counter Cashier',
            'active' => 1,
            'payment_type' => 1,
        ]);

        $ids = PaymentModes::cashIds(1);
        $this->assertContains($row->id, $ids);
    }

    public function test_bank_mode_is_excluded(): void
    {
        $row = PaymentModes::create([
            'account_id' => 1,
            'name' => 'HBL Wire Transfer',
            'active' => 1,
            'payment_type' => 0,
        ]);

        $ids = PaymentModes::cashIds(1);
        $this->assertNotContains($row->id, $ids);
    }

    public function test_inactive_cash_modes_are_excluded(): void
    {
        $row = PaymentModes::create([
            'account_id' => 1,
            'name' => 'Cash - Retired',
            'active' => 0,
            'payment_type' => 0,
        ]);

        $ids = PaymentModes::cashIds(1);
        $this->assertNotContains($row->id, $ids);
    }

    public function test_falls_back_to_id_one_when_no_match(): void
    {
        // Wipe every cash row for the account.
        PaymentModes::query()
            ->where(function ($q) {
                $q->where('payment_type', 1)
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%cash%']);
            })
            ->delete();

        $ids = PaymentModes::cashIds(1);
        $this->assertSame([1], $ids, 'Fallback to id=1 should kick in when no cash mode is configured.');
    }

    /**
     * Regression: a cash sale through a non-id-1 cash mode must reach the
     * branch cash pool's balance.
     *
     * The mechanism changed in the 2026-05 inventory revamp. Old path:
     * getPoolBalances() layered a runtime Order::sum() on top of
     * cached_balance, first filtered by a hardcoded payment_mode = 1,
     * later by an inline name-only scan -- both misclassified a tenant
     * whose cash mode was not id=1 / not literally named "cash". New
     * path: an order writes a package_advances ledger row, and
     * PackageAdvanceObserver credits the pool resolved from the payment
     * mode, classified via the centralized PaymentModes::cashIds()
     * helper (payment_type OR name match + fallback). This pins that
     * contract end-to-end: ledger row with a non-id-1 cash mode ->
     * observer -> branch pool cached_balance -> getPoolBalances().
     */
    public function test_pool_balances_counts_cash_sales_via_non_id_one_cash_mode(): void
    {
        $accountId = 1;

        // Cash mode seeded well past id=1 (after the fixture defaults).
        // The premise is that classification must not depend on id=1.
        $petty = PaymentModes::create([
            'account_id' => $accountId,
            'name' => 'Cash - Petty',
            'active' => 1,
            'payment_type' => 0,
        ]);
        $this->assertGreaterThan(1, $petty->id, 'Test premise: the cash mode must not be id=1.');
        $this->assertContains($petty->id, PaymentModes::cashIds($accountId),
            'Sanity: the centralized helper must classify this mode as cash.');

        // Resolve the exact pool the observer will credit, deterministically.
        // resolvePool() runs CashPool::where(account, location, branch_cash,
        // is_active)->first() in primary-key order. Picking the lowest-id
        // active branch-cash pool that has a location guarantees that, for
        // that pool's location, resolvePool() returns this very pool -- so
        // the assertion does not depend on fixture/factory pool ordering.
        $expectedPool = CashPool::where('account_id', $accountId)
            ->whereNotNull('location_id')
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();
        $this->assertNotNull($expectedPool, 'Fixtures should seed an active branch-cash pool.');
        $balanceBefore = (float) $expectedPool->cached_balance;

        // Go-live yesterday so the ledger row (system_created_at = now)
        // is post-go-live and the observer applies the pool impact.
        app(CashflowSettingService::class)->set('go_live_date', now()->subDay()->toDateString(), $accountId);

        // The ledger row OrderService now writes per cash sale. Build it
        // through the model (not a raw insert) so PackageAdvanceObserver
        // fires -- under the new design that observer is the single
        // source of truth for cached_balance. Direct attribute assignment
        // sidesteps mass-assignment guarding.
        $advance = new PackageAdvances();
        $advance->account_id = $accountId;
        $advance->location_id = $expectedPool->location_id;
        $advance->payment_mode_id = $petty->id;
        $advance->cash_flow = 'in';
        $advance->cash_amount = 1234;
        $advance->is_cancel = 0;
        $advance->is_refund = 0;
        $advance->system_created_at = now();
        $advance->save();

        // End-to-end: observer credited the resolved branch pool, and
        // getPoolBalances() surfaces the updated cached_balance.
        $balances = app(DashboardService::class)->getPoolBalances($accountId);
        $row = collect($balances)->firstWhere('id', $expectedPool->id);
        $this->assertNotNull($row, 'Resolved pool should appear in getPoolBalances output.');
        $this->assertEqualsWithDelta($balanceBefore + 1234.0, (float) $row['cached_balance'], 0.01,
            'Cash sale through a non-id-1 cash mode must reach the branch pool balance.');
    }
}
