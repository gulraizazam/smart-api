<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\StaffAdvance;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StaffAdvanceObserver simply decrements the source pool when a new advance
 * is created. This is the *only* observer hook on the model — void/edit are
 * handled inside StaffAdvanceService inside an explicit DB transaction so
 * pool reversal cannot drift from the row update.
 */
class StaffAdvanceObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_a_staff_advance_decrements_the_source_pool_balance(): void
    {
        $pool = CashPool::factory()->create([
            'opening_balance' => 10000,
            'cached_balance' => 10000,
        ]);

        StaffAdvance::factory()->create([
            'pool_id' => $pool->id,
            'amount' => 2500,
        ]);

        $this->assertSame(7500.00, (float) $pool->fresh()->cached_balance);
    }

    public function test_two_advances_against_the_same_pool_compound(): void
    {
        $pool = CashPool::factory()->create([
            'opening_balance' => 10000,
            'cached_balance' => 10000,
        ]);

        StaffAdvance::factory()->create(['pool_id' => $pool->id, 'amount' => 2000]);
        StaffAdvance::factory()->create(['pool_id' => $pool->id, 'amount' => 1500]);

        $this->assertSame(6500.00, (float) $pool->fresh()->cached_balance);
    }

    public function test_advances_against_different_pools_only_touch_their_own_pool(): void
    {
        $poolA = CashPool::factory()->create(['opening_balance' => 10000, 'cached_balance' => 10000]);
        $poolB = CashPool::factory()->create(['opening_balance' => 5000, 'cached_balance' => 5000]);

        StaffAdvance::factory()->create(['pool_id' => $poolA->id, 'amount' => 1000]);
        StaffAdvance::factory()->create(['pool_id' => $poolB->id, 'amount' => 500]);

        $this->assertSame(9000.00, (float) $poolA->fresh()->cached_balance);
        $this->assertSame(4500.00, (float) $poolB->fresh()->cached_balance);
    }
}
