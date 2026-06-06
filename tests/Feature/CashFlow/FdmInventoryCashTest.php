<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashflowSetting;
use App\Models\CashFlow\CashPool;
use App\Models\Order;
use App\Models\PaymentModes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins that the FDM cash view (/api/cashflow/fdm/data — also the dashboard's
 * FDM cash panel) includes inventory ORDER cash.
 *
 * Inventory orders are overlay-only: they're deliberately NOT written to the
 * package_advances ledger (shared-DB parity with crm2). The FDM view used to
 * read inventory cash from package_advances `whereNotNull('order_id')`, which
 * matched no rows — so "inventory sales" showed 0 and the cash balance never
 * moved for cash orders. The fix sums inventory cash straight from `orders`
 * (cash payment modes via PaymentModes::cashIds), folded into the opening
 * balance and the weekly inventory KPI.
 */
class FdmInventoryCashTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private function setUpBranch(int $locationId, float $opening): void
    {
        // getUserBranches() reads user_has_locations — the acting user must own
        // the branch for it to appear in the FDM view.
        DB::table('user_has_locations')->insert([
            'user_id' => Auth::id(),
            'location_id' => $locationId,
            'region_id' => 1,
        ]);

        CashPool::factory()->withOpeningBalance($opening)->create([
            'account_id' => 1,
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => $locationId,
        ]);

        // Go-live well in the past so we exercise the computed opening path.
        CashflowSetting::setValue('go_live_date', Carbon::now()->subDays(60)->toDateString(), 1);
    }

    /** Create an order directly — the OrderFactory's warehouse/patient defaults
     *  don't fit the test-DB schema (reverted FIFO columns). */
    private function makeOrder(int $locationId, int $paymentModeId, float $total): void
    {
        Order::create([
            'account_id' => 1,
            'location_id' => $locationId,
            'order_type' => 'sale',
            'payment_mode' => $paymentModeId,
            'total_price' => $total,
            'created_by' => Auth::id(),
            'status' => 1,
        ]);
    }

    public function test_fdm_view_includes_inventory_cash_orders(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // Super-Admin bypasses the cashflow.fdm.view gate

        $location = $this->defaultLocation;
        $this->setUpBranch($location->id, 1000);

        $cash = PaymentModes::factory()->cash()->create(['account_id' => 1, 'active' => 1]);
        $this->makeOrder($location->id, $cash->id, 2500);

        $res = $this->getJson('/api/cashflow/fdm/data?branch_id=' . $location->id);

        $res->assertOk();
        // The inventory KPI now reflects the order (was 0 before the fix).
        $this->assertSame(2500.0, (float) $res->json('data.inventory_cash_inflows'));
        $this->assertSame(1, (int) $res->json('data.inventory_cash_inflows_count'));
        // Live balance folds inventory in: opening (1000, no prior movements) + 2500.
        $this->assertSame(3500.0, (float) $res->json('data.pool_balance'));
    }

    public function test_non_cash_orders_do_not_inflate_the_cash_view(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $location = $this->defaultLocation;
        $this->setUpBranch($location->id, 1000);

        // A CARD payment mode (not cash) — must be excluded from the cash view.
        $card = PaymentModes::factory()->create([
            'account_id' => 1,
            'name' => 'CardOnly',
            'payment_type' => 2,
            'active' => 1,
        ]);
        $this->makeOrder($location->id, $card->id, 9999);

        $res = $this->getJson('/api/cashflow/fdm/data?branch_id=' . $location->id);

        $res->assertOk();
        $this->assertSame(0.0, (float) $res->json('data.inventory_cash_inflows'));
        $this->assertSame(1000.0, (float) $res->json('data.pool_balance'));
    }
}
