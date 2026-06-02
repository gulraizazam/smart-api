<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashflowSetting;
use App\Services\CashFlow\PoolService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Regression: inventory (Order) cash sales are layered onto branch pool
 * balances at DISPLAY time (PoolService::applyInventoryOverlay, reached via
 * getAllPools / DashboardService::getPoolBalances) — they are NOT folded into
 * the stored `cash_pools.cached_balance` by recalculatePoolBalances().
 *
 * Why this split matters: crm2 (legacy Blade) and crm3 (this SPA) run side by
 * side against ONE shared production DB off the same `cached_balance` column.
 * The legacy app never bakes inventory into that column — it overlays it only
 * when rendering. If the SPA's recompute baked inventory in instead, the
 * legacy app would read the inflated cached value AND add its own overlay on
 * top -> double-counting. So the contract is:
 *   - recalculatePoolBalances(): stored cached_balance EXCLUDES inventory.
 *   - applyInventoryOverlay() (display): adds cash sales to branch pools
 *     (and debits cash refunds), exactly as the legacy app does.
 *
 * Note: orders.payment_mode is an int column (1 = cash) — the legacy calc
 * mirrored here matches cash via `payment_mode = 1`, so the cash sale below
 * uses 1.
 */
class PoolBalanceInventoryRecomputeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    public function test_inventory_cash_sales_overlay_at_display_but_not_in_stored_balance(): void
    {
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();
        CashflowSetting::setValue('go_live_date', now()->subWeek()->toDateString(), 1);

        $location = $this->defaultLocation;

        $pool = CashPool::factory()->create([
            'account_id' => 1,
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => $location->id,
            'opening_balance' => 0,
            'cached_balance' => 0,
        ]);

        // A cash inventory sale at this branch, after go-live. Inserted
        // directly (FK checks off) rather than via OrderFactory, whose deep
        // nested graph (product→brand→warehouse→city) is irrelevant here —
        // the overlay only reads location_id / payment_mode / total_price /
        // order_type.
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        \DB::table('orders')->insert([
            'patient_id' => $admin->id,
            'order_type' => 'sale',
            'payment_mode' => 1, // 1 = cash (orders.payment_mode is an int column)
            'location_id' => $location->id,
            'total_price' => 1500,
            'quantity' => 1,
            'status' => 1,
            'created_by' => $admin->id,
            'account_id' => 1,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $service = app(PoolService::class);

        // 1. Recompute must NOT bake inventory into the stored column — the
        //    shared legacy app reads this value and overlays inventory itself.
        $service->recalculatePoolBalances(1);

        $this->assertSame(
            0.0,
            (float) $pool->fresh()->cached_balance,
            'recalculatePoolBalances must leave inventory OUT of the stored cached_balance '
            .'(legacy app shares this column and overlays inventory at display).'
        );

        // 2. The display path overlays the inventory cash sale onto the branch
        //    pool, so the rendered balance reflects real cash on hand.
        $displayed = $service->getAllPools(1)->firstWhere('id', $pool->id);

        $this->assertNotNull($displayed, 'Branch pool should be returned by getAllPools.');
        $this->assertSame(
            1500.0,
            (float) $displayed->cached_balance,
            'getAllPools must overlay the inventory cash sale onto the branch pool at display time.'
        );
    }
}
