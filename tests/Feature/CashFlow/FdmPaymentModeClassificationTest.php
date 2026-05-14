<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\PaymentModes;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\DashboardService;
use Illuminate\Support\Facades\DB;
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
     * Regression: pool balances must reflect a cash sale made through a
     * non-id-1 payment mode. Before the fix, `getPoolBalances()` literally
     * filtered `where('payment_mode', 1)`, so a tenant whose primary cash
     * mode was seeded at id ≠ 1 silently saw zero inventory cash on every
     * branch pool. Pin the centralized-helper path.
     */
    public function test_pool_balances_counts_cash_sales_via_non_id_one_cash_mode(): void
    {
        $accountId = 1;
        $location = $this->defaultLocation;

        // A "Cash - Petty" row will be created with an auto-increment id well
        // past 1 (after the 4 default modes from the fixture seeder).
        $petty = PaymentModes::create([
            'account_id' => $accountId,
            'name' => 'Cash - Petty',
            'active' => 1,
            'payment_type' => 0,
        ]);
        $this->assertGreaterThan(1, $petty->id, 'Test premise: the new cash mode must not be id=1.');

        // Branch-cash pool tied to the seeded test location.
        $pool = CashPool::factory()->create([
            'account_id' => $accountId,
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => $location->id,
            'opening_balance' => 0,
            'cached_balance' => 0,
        ]);

        // Go-live yesterday so the post-first-week branch in getPoolBalances
        // is exercised — that's the path that runs the cash classifier query.
        app(CashflowSettingService::class)->set('go_live_date', now()->subDay()->toDateString(), $accountId);

        // Insert one cash sale through the new payment mode. Use DB::table
        // to bypass the OrderFactory's legacy `payment_mode => 'cash'` string
        // default (the column has been bigint for two years; the factory just
        // hasn't been updated). Schema fields: created_by NOT NULL, quantity
        // is a varchar with default '0'.
        DB::table('orders')->insert([
            'account_id' => $accountId,
            'location_id' => $location->id,
            'patient_id' => null,
            'total_price' => 1234,
            'order_type' => 'sale',
            'payment_mode' => $petty->id,
            'status' => 1,
            'quantity' => '1',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balances = app(DashboardService::class)->getPoolBalances($accountId);
        $row = collect($balances)->firstWhere('id', $pool->id);
        $this->assertNotNull($row, 'Pool should appear in getPoolBalances output.');
        $this->assertEqualsWithDelta(1234.0, (float) $row['cached_balance'], 0.01,
            'Cash sale through a non-id-1 cash mode must be reflected in branch pool balance.');
    }
}
