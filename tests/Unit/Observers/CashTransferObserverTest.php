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

    public function test_chained_transfers_accumulate_correctly(): void
    {
        // A → B → C chain: every intermediate balance must reflect both
        // the debit from the outgoing leg and the credit from the incoming.
        $a = CashPool::factory()->create(['opening_balance' => 10000, 'cached_balance' => 10000]);
        $b = CashPool::factory()->create(['opening_balance' => 0, 'cached_balance' => 0]);
        $c = CashPool::factory()->create(['opening_balance' => 0, 'cached_balance' => 0]);

        CashTransfer::factory()->create([
            'from_pool_id' => $a->id,
            'to_pool_id' => $b->id,
            'amount' => 3000,
        ]);

        CashTransfer::factory()->create([
            'from_pool_id' => $b->id,
            'to_pool_id' => $c->id,
            'amount' => 1500,
        ]);

        $this->assertSame(7000.00, (float) $a->fresh()->cached_balance);
        $this->assertSame(1500.00, (float) $b->fresh()->cached_balance);
        $this->assertSame(1500.00, (float) $c->fresh()->cached_balance);
    }

    public function test_uninvolved_pool_is_not_affected_by_transfer(): void
    {
        $from = CashPool::factory()->create(['opening_balance' => 5000, 'cached_balance' => 5000]);
        $to = CashPool::factory()->create(['opening_balance' => 5000, 'cached_balance' => 5000]);
        $bystander = CashPool::factory()->create(['opening_balance' => 9999, 'cached_balance' => 9999]);

        CashTransfer::factory()->create([
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'amount' => 2000,
        ]);

        $this->assertSame(9999.00, (float) $bystander->fresh()->cached_balance,
            'A pool not involved in the transfer must not be affected.');
    }
}
