<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\StaffReturn;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StaffReturnObserver mirrors StaffAdvanceObserver: when staff return cash
 * the destination pool's cached_balance must increment by the return amount.
 */
class StaffReturnObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_a_staff_return_credits_the_destination_pool(): void
    {
        $pool = CashPool::factory()->create([
            'opening_balance' => 1000,
            'cached_balance' => 1000,
        ]);

        StaffReturn::factory()->create([
            'pool_id' => $pool->id,
            'amount' => 2500,
        ]);

        $this->assertSame(3500.00, (float) $pool->fresh()->cached_balance);
    }

    public function test_returns_to_different_pools_do_not_cross_contaminate(): void
    {
        $poolA = CashPool::factory()->create(['opening_balance' => 0, 'cached_balance' => 0]);
        $poolB = CashPool::factory()->create(['opening_balance' => 0, 'cached_balance' => 0]);

        StaffReturn::factory()->create(['pool_id' => $poolA->id, 'amount' => 750]);

        $this->assertSame(750.00, (float) $poolA->fresh()->cached_balance);
        $this->assertSame(0.00, (float) $poolB->fresh()->cached_balance);
    }
}
