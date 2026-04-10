<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashTransferObserver is the only place pool balances move during a
 * transfer create() call (TransferService::create() does NOT touch pools
 * directly — it relies on this observer). Both legs must move atomically:
 * source debited, destination credited, by the same amount.
 */
class CashTransferObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_a_transfer_debits_source_and_credits_destination(): void
    {
        $from = CashPool::factory()->create(['opening_balance' => 20000, 'cached_balance' => 20000]);
        $to = CashPool::factory()->create(['opening_balance' => 5000, 'cached_balance' => 5000]);

        CashTransfer::factory()->create([
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'amount' => 7500,
        ]);

        $this->assertSame(12500.00, (float) $from->fresh()->cached_balance);
        $this->assertSame(12500.00, (float) $to->fresh()->cached_balance);
    }

    public function test_total_pool_money_is_conserved_across_a_transfer(): void
    {
        $from = CashPool::factory()->create(['opening_balance' => 1234.56, 'cached_balance' => 1234.56]);
        $to = CashPool::factory()->create(['opening_balance' => 1000.00, 'cached_balance' => 1000.00]);

        $totalBefore = (float) $from->fresh()->cached_balance + (float) $to->fresh()->cached_balance;

        CashTransfer::factory()->create([
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'amount' => 234.56,
        ]);

        $totalAfter = (float) $from->fresh()->cached_balance + (float) $to->fresh()->cached_balance;

        $this->assertSame(
            $totalBefore,
            $totalAfter,
            'Cash transfers must conserve total money in the system.'
        );
    }
}
