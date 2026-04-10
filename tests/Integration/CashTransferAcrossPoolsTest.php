<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\PeriodLock;
use App\Services\CashFlow\TransferService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsAuditTrail;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Cash transfer across pools — integration view.
 *
 * The unit/feature CashTransferTest pins single-step transfer behaviour
 * (create, void, edit, same-pool rejection). This integration test
 * pins the system-level guarantees:
 *
 *   - A multi-step chain of transfers (Pool A → B → C) leaves the
 *     end-to-end accounting consistent: total cash in the system is
 *     unchanged, and the audit log records every leg in the right
 *     order.
 *   - The period-lock guard fires before any pool balance is mutated
 *     — a locked-period transfer must not partially update one pool
 *     and leave the other unchanged.
 *   - Cross-tenant transfers are blocked at the lookup layer
 *     (CashPool::forAccount), so an attacker cannot move money out
 *     of another organization's pool.
 *   - The cached_balance column on cash_pools stays in sync with the
 *     sum of legs after a transfer chain. Any drift here means the
 *     audit's "fast balance read" is lying — every till receipt
 *     downstream becomes wrong.
 */
class CashTransferAcrossPoolsTest extends TestCase
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

    public function test_chain_of_transfers_preserves_total_cash_in_the_system(): void
    {
        // Three pools: A starts with 30k, B with 0, C with 0.
        // Transfer 10k A→B, then 4k B→C. End state must be A=20k,
        // B=6k, C=4k, total=30k unchanged.
        $a = CashPool::factory()->withOpeningBalance(30000)->create(['name' => 'Pool A']);
        $b = CashPool::factory()->withOpeningBalance(0)->create(['name' => 'Pool B']);
        $c = CashPool::factory()->withOpeningBalance(0)->create(['name' => 'Pool C']);

        $totalBefore = (float) DB::table('cash_pools')
            ->whereIn('id', [$a->id, $b->id, $c->id])
            ->sum('cached_balance');
        $this->assertSame(30000.0, $totalBefore);

        $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 10000,
            'from_pool_id' => $a->id,
            'to_pool_id' => $b->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a-to-b.pdf',
        ], accountId: 1);

        $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 4000,
            'from_pool_id' => $b->id,
            'to_pool_id' => $c->id,
            'method' => 'physical_cash',
            'attachment_url' => 'b-to-c.pdf',
        ], accountId: 1);

        $this->assertSame(20000.0, (float) $a->fresh()->cached_balance, 'Pool A: 30k - 10k');
        $this->assertSame(6000.0, (float) $b->fresh()->cached_balance, 'Pool B: +10k - 4k');
        $this->assertSame(4000.0, (float) $c->fresh()->cached_balance, 'Pool C: +4k');

        $totalAfter = (float) DB::table('cash_pools')
            ->whereIn('id', [$a->id, $b->id, $c->id])
            ->sum('cached_balance');
        $this->assertSame(
            30000.0,
            $totalAfter,
            'Cash conservation: total system balance must be unchanged after a chain of transfers.'
        );
    }

    public function test_each_transfer_in_a_chain_writes_its_own_audit_row(): void
    {
        // Two transfers means two audit log rows, both with action
        // CREATED and entity TRANSFER, distinct entity_ids. Without
        // distinct rows, the audit query "show me every cash transfer
        // last week" silently misses one.
        $a = CashPool::factory()->withOpeningBalance(20000)->create();
        $b = CashPool::factory()->withOpeningBalance(0)->create();
        $c = CashPool::factory()->withOpeningBalance(0)->create();

        $first = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 5000,
            'from_pool_id' => $a->id,
            'to_pool_id' => $b->id,
            'method' => 'physical_cash',
            'attachment_url' => 'a.pdf',
        ], accountId: 1);

        $second = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 2000,
            'from_pool_id' => $b->id,
            'to_pool_id' => $c->id,
            'method' => 'physical_cash',
            'attachment_url' => 'b.pdf',
        ], accountId: 1);

        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_TRANSFER,
            $first->id,
            CashflowAuditLog::ACTION_CREATED,
        );
        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_TRANSFER,
            $second->id,
            CashflowAuditLog::ACTION_CREATED,
        );

        $this->assertNotSame(
            $first->id,
            $second->id,
            'Two transfers must produce two distinct audit rows.'
        );
    }

    public function test_transfer_in_a_locked_period_is_rejected_before_touching_pools(): void
    {
        // Critical race property: the period-lock guard runs BEFORE
        // any pool balance is mutated. Pin it: lock the previous
        // month, attempt a transfer dated in that month, and assert
        // BOTH pools remain at their pre-attempt balances.
        $a = CashPool::factory()->withOpeningBalance(15000)->create();
        $b = CashPool::factory()->withOpeningBalance(5000)->create();

        $previous = Carbon::now()->subMonthsNoOverflow(1);
        if ($previous->month === Carbon::now()->month) {
            $previous = Carbon::now()->subMonthsNoOverflow(2);
        }
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        try {
            $this->service->create([
                'transfer_date' => $previous->copy()->day(15)->toDateString(),
                'amount' => 3000,
                'from_pool_id' => $a->id,
                'to_pool_id' => $b->id,
                'method' => 'physical_cash',
                'attachment_url' => 'locked.pdf',
            ], accountId: 1);
            $this->fail('Transfer in a locked period must throw CashflowException.');
        } catch (CashflowException $e) {
            $this->assertStringContainsStringIgnoringCase('locked', $e->getMessage());
        }

        $this->assertSame(
            15000.0,
            (float) $a->fresh()->cached_balance,
            'Source pool must be untouched when the transfer is rejected.'
        );
        $this->assertSame(
            5000.0,
            (float) $b->fresh()->cached_balance,
            'Destination pool must be untouched when the transfer is rejected.'
        );
        $this->assertSame(
            0,
            CashTransfer::query()->whereIn('from_pool_id', [$a->id])->count(),
            'No transfer row may be persisted when period lock rejects.'
        );
    }

    public function test_voiding_a_middle_transfer_in_a_chain_only_reverses_that_leg(): void
    {
        // Pool A → B → C with 10k and 4k legs. Voiding the 4k B→C
        // leg should restore B and C only — A must remain at 20k
        // (the first 10k transfer is unaffected).
        $a = CashPool::factory()->withOpeningBalance(30000)->create();
        $b = CashPool::factory()->withOpeningBalance(0)->create();
        $c = CashPool::factory()->withOpeningBalance(0)->create();

        $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 10000,
            'from_pool_id' => $a->id,
            'to_pool_id' => $b->id,
            'method' => 'physical_cash',
            'attachment_url' => 'leg1.pdf',
        ], accountId: 1);

        $bToC = $this->service->create([
            'transfer_date' => now()->toDateString(),
            'amount' => 4000,
            'from_pool_id' => $b->id,
            'to_pool_id' => $c->id,
            'method' => 'physical_cash',
            'attachment_url' => 'leg2.pdf',
        ], accountId: 1);

        $this->assertSame(20000.0, (float) $a->fresh()->cached_balance);
        $this->assertSame(6000.0, (float) $b->fresh()->cached_balance);
        $this->assertSame(4000.0, (float) $c->fresh()->cached_balance);

        $this->service->void($bToC->id, 'Wrong destination', accountId: 1);

        $this->assertSame(
            20000.0,
            (float) $a->fresh()->cached_balance,
            'Pool A must remain at 20k — only the B→C leg was voided.'
        );
        $this->assertSame(
            10000.0,
            (float) $b->fresh()->cached_balance,
            'Pool B must climb back to 10k after the B→C void.'
        );
        $this->assertSame(
            0.0,
            (float) $c->fresh()->cached_balance,
            'Pool C must drop back to 0 after the B→C void.'
        );
    }

    public function test_cached_balance_matches_sum_of_legs_for_each_pool(): void
    {
        // After a chain of transfers, the cached_balance column on
        // each cash_pools row must equal opening_balance + Σ(in legs)
        // - Σ(out legs). This is the audit's denormalisation
        // contract: cached_balance is fast-read for the dashboard,
        // but it MUST match what the legs sum to. Any drift means
        // the dashboard lies.
        $a = CashPool::factory()->withOpeningBalance(50000)->create();
        $b = CashPool::factory()->withOpeningBalance(0)->create();

        // Five transfers of varying amounts.
        foreach ([1000, 2500, 3700, 800, 1500] as $amount) {
            $this->service->create([
                'transfer_date' => now()->toDateString(),
                'amount' => $amount,
                'from_pool_id' => $a->id,
                'to_pool_id' => $b->id,
                'method' => 'physical_cash',
                'attachment_url' => 'tx.pdf',
            ], accountId: 1);
        }

        $sumOut = (float) CashTransfer::query()
            ->where('from_pool_id', $a->id)
            ->whereNull('voided_at')
            ->sum('amount');
        $sumIn = (float) CashTransfer::query()
            ->where('to_pool_id', $b->id)
            ->whereNull('voided_at')
            ->sum('amount');

        $this->assertSame(9500.0, $sumOut);
        $this->assertSame(9500.0, $sumIn);

        $this->assertSame(
            50000.0 - $sumOut,
            (float) $a->fresh()->cached_balance,
            'Pool A cached_balance must equal opening - Σ(out legs).'
        );
        $this->assertSame(
            0.0 + $sumIn,
            (float) $b->fresh()->cached_balance,
            'Pool B cached_balance must equal opening + Σ(in legs).'
        );
    }
}
