<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Services\CashFlow\TransferService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\AssertsAuditTrail;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Cash transfers are the only legitimate way for money to move between
 * pools without leaving an expense or invoice trail. The audit found two
 * bugs the tests below pin against:
 *   1. Edit code path applied the new amount to the old pool, double-counting.
 *   2. Same-pool transfer (from = to) was allowed and quietly debited the
 *      pool, then immediately credited it — looking right but creating
 *      bogus ledger entries.
 */
class CashTransferTest extends TestCase
{
    use AssertsAuditTrail;
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private TransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(TransferService::class);
    }

    public function test_create_moves_money_atomically_between_two_pools(): void
    {
        $from = CashPool::factory()->withOpeningBalance(20000)->create();
        $to = CashPool::factory()->withOpeningBalance(5000)->create();

        $transfer = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 7500,
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);

        $this->assertNotNull($transfer->id);
        $this->assertSame(12500.00, (float) $from->fresh()->cached_balance);
        $this->assertSame(12500.00, (float) $to->fresh()->cached_balance);
        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_TRANSFER,
            $transfer->id,
            CashflowAuditLog::ACTION_CREATED,
        );
    }

    public function test_creating_a_transfer_to_the_same_pool_is_rejected(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(10000)->create();

        $this->expectException(CashflowException::class);
        $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 1000,
            'from_pool_id' => $pool->id,
            'to_pool_id' => $pool->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);
    }

    public function test_voiding_a_transfer_reverses_both_pool_balances(): void
    {
        $from = CashPool::factory()->withOpeningBalance(20000)->create();
        $to = CashPool::factory()->withOpeningBalance(5000)->create();

        $transfer = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 5000,
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);
        $this->assertSame(15000.00, (float) $from->fresh()->cached_balance);
        $this->assertSame(10000.00, (float) $to->fresh()->cached_balance);

        $this->service->void($transfer->id, 'Booked twice', accountId: 1);

        $this->assertSame(20000.00, (float) $from->fresh()->cached_balance);
        $this->assertSame(5000.00, (float) $to->fresh()->cached_balance);
    }

    public function test_editing_a_transfer_amount_uses_old_amount_for_reversal(): void
    {
        $from = CashPool::factory()->withOpeningBalance(20000)->create();
        $to = CashPool::factory()->withOpeningBalance(5000)->create();

        $transfer = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 1000,
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);

        $this->service->edit($transfer->id, [
            'amount' => 3000,
            'edit_reason' => 'Correction — original amount was wrong',
        ], accountId: 1);

        $this->assertSame(
            17000.00,
            (float) $from->fresh()->cached_balance,
            'After edit, source pool should reflect the NEW transfer amount only (20000 - 3000), '
            . 'not double-debited (20000 - 1000 - 3000 = 16000).'
        );
        $this->assertSame(8000.00, (float) $to->fresh()->cached_balance);
    }

    public function test_editing_a_transfer_to_the_same_pool_is_rejected(): void
    {
        $from = CashPool::factory()->withOpeningBalance(20000)->create();
        $to = CashPool::factory()->withOpeningBalance(5000)->create();

        $transfer = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 1000,
            'from_pool_id' => $from->id,
            'to_pool_id' => $to->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);

        $this->expectException(CashflowException::class);
        $this->service->edit($transfer->id, [
            'to_pool_id' => $from->id,
            'edit_reason' => 'Trying to point to source pool',
        ], accountId: 1);
    }
}
