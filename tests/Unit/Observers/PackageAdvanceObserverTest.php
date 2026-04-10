<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\CashFlow\CashPool;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PaymentModes;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The PackageAdvanceObserver routes pool impact differently for cash vs
 * card vs bank payments and resolves the destination pool from the
 * (location_id, payment_mode_id) tuple. The audit fixed several bugs in
 * this routing — these tests pin every relevant branch.
 *
 * Pre-go-live records and `is_cancel = 1` records must NOT touch any pool
 * because they are captured in opening balances or never landed.
 */
class PackageAdvanceObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_cash_inflow_credits_the_branch_cash_pool_for_the_location(): void
    {
        // LocationCashflowObserver auto-creates the branch cash pool when
        // the location is created. Use that pool — creating a duplicate
        // would never receive observer credits because the routing logic
        // resolves the destination by location_id alone.
        $location = Locations::factory()->create();
        $branchPool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $branchPool->forceFill(['opening_balance' => 1000, 'cached_balance' => 1000])->save();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 2500,
            'payment_mode_id' => $cash->id,
            'location_id' => $location->id,
        ]);

        $this->assertSame(3500.00, (float) $branchPool->fresh()->cached_balance);
    }

    public function test_card_inflow_credits_the_bank_account_pool_not_the_branch_cash_pool(): void
    {
        $location = Locations::factory()->create();
        $branchPool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $branchPool->forceFill(['opening_balance' => 1000, 'cached_balance' => 1000])->save();
        $bankPool = CashPool::factory()->create([
            'type' => CashPool::TYPE_BANK_ACCOUNT,
            'location_id' => null,
            'opening_balance' => 5000,
            'cached_balance' => 5000,
        ]);
        $card = PaymentModes::query()->where('name', 'Card')->firstOrFail();

        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 1500,
            'payment_mode_id' => $card->id,
            'location_id' => $location->id,
        ]);

        $this->assertSame(
            1000.00,
            (float) $branchPool->fresh()->cached_balance,
            'Branch cash pool must not move when payment is by card.'
        );
        $this->assertSame(
            6500.00,
            (float) $bankPool->fresh()->cached_balance,
            'Bank pool must receive the full card payment.'
        );
    }

    public function test_settlement_advance_with_no_cash_movement_does_not_touch_any_pool(): void
    {
        $location = Locations::factory()->create();
        $branchPool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $branchPool->forceFill(['opening_balance' => 1000, 'cached_balance' => 1000])->save();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        // out + is_refund = 0 → settlement → no pool change
        PackageAdvances::factory()->create([
            'cash_flow' => 'out',
            'cash_amount' => 2000,
            'payment_mode_id' => $cash->id,
            'location_id' => $location->id,
            'is_refund' => 0,
        ]);

        $this->assertSame(1000.00, (float) $branchPool->fresh()->cached_balance);
    }

    public function test_refund_advance_debits_the_branch_cash_pool(): void
    {
        $location = Locations::factory()->create();
        $branchPool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $branchPool->forceFill(['opening_balance' => 5000, 'cached_balance' => 5000])->save();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        PackageAdvances::factory()->create([
            'cash_flow' => 'out',
            'cash_amount' => 1500,
            'payment_mode_id' => $cash->id,
            'location_id' => $location->id,
            'is_refund' => 1,
        ]);

        $this->assertSame(3500.00, (float) $branchPool->fresh()->cached_balance);
    }

    public function test_cancelling_an_advance_after_creation_reverses_the_pool_impact(): void
    {
        $location = Locations::factory()->create();
        $branchPool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $branchPool->forceFill(['opening_balance' => 1000, 'cached_balance' => 1000])->save();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        $advance = PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 2000,
            'payment_mode_id' => $cash->id,
            'location_id' => $location->id,
        ]);
        $this->assertSame(3000.00, (float) $branchPool->fresh()->cached_balance);

        $advance->update(['is_cancel' => 1]);

        $this->assertSame(
            1000.00,
            (float) $branchPool->fresh()->cached_balance,
            'Cancelling an inflow advance must remove the inflow from the pool.'
        );
    }
}
