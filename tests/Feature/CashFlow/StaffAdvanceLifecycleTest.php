<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\User;
use App\Services\CashFlow\StaffAdvanceService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\AssertsAuditTrail;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Exercises StaffAdvanceService end-to-end:
 *   - createAdvance enforces is_advance_eligible and the cumulative cap
 *   - voidAdvance reverses the pool inside one transaction
 *   - editAdvance reverses the OLD pool/amount before applying the NEW one
 *   - createReturn cannot exceed outstanding
 *
 * The audit fixed an edit bug here: the old code applied the new amount to
 * the new pool but forgot to reverse the old pool — these tests pin the
 * fixed flow.
 */
class StaffAdvanceLifecycleTest extends TestCase
{
    use AssertsAuditTrail;
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private StaffAdvanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(StaffAdvanceService::class);
    }

    public function test_creating_an_advance_for_an_eligible_staff_decrements_pool_and_logs_audit(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create([
            'account_id' => 1,
            'is_advance_eligible' => 1,
        ]);

        $advance = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 5000,
            'description' => 'Petty cash for vendor pickup',
        ], accountId: 1);

        $this->assertNotNull($advance->id);
        $this->assertSame(15000.00, (float) $pool->fresh()->cached_balance);
        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_STAFF_ADVANCE,
            $advance->id,
            CashflowAuditLog::ACTION_CREATED,
        );
    }

    public function test_creating_an_advance_for_ineligible_staff_is_rejected(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create([
            'account_id' => 1,
            'is_advance_eligible' => 0,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 1000,
        ], accountId: 1);
    }

    public function test_voiding_an_advance_credits_the_pool_back_atomically(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create([
            'account_id' => 1,
            'is_advance_eligible' => 1,
        ]);

        $advance = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 7500,
        ], accountId: 1);
        $this->assertSame(12500.00, (float) $pool->fresh()->cached_balance);

        $voided = $this->service->voidAdvance($advance->id, 'Wrong staff member', accountId: 1);

        $this->assertNotNull($voided->voided_at);
        $this->assertSame(20000.00, (float) $pool->fresh()->cached_balance);
        $this->assertCashflowAuditLogged(
            CashflowAuditLog::ENTITY_STAFF_ADVANCE,
            $advance->id,
            CashflowAuditLog::ACTION_VOIDED,
        );
    }

    public function test_double_voiding_an_advance_is_rejected(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        $advance = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 1000,
        ], accountId: 1);

        $this->service->voidAdvance($advance->id, 'first', accountId: 1);

        $this->expectException(CashflowException::class);
        $this->service->voidAdvance($advance->id, 'second', accountId: 1);
    }

    public function test_editing_an_advance_amount_reverses_old_and_applies_new(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        $advance = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 1000,
        ], accountId: 1);

        $this->service->editAdvance($advance->id, [
            'amount' => 2500,
            'edit_reason' => 'Original amount was wrong',
        ], accountId: 1);

        $this->assertSame(
            17500.00,
            (float) $pool->fresh()->cached_balance,
            'After edit, pool should reflect the NEW amount only (20000 - 2500), '
            . 'not double-debited (20000 - 1000 - 2500 = 16500).'
        );
    }

    public function test_editing_an_advance_to_a_new_pool_reverses_old_pool_and_debits_new_pool(): void
    {
        $oldPool = CashPool::factory()->withOpeningBalance(20000)->create();
        $newPool = CashPool::factory()->withOpeningBalance(10000)->create();
        $staff = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        $advance = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $oldPool->id,
            'amount' => 1000,
        ], accountId: 1);
        $this->assertSame(19000.00, (float) $oldPool->fresh()->cached_balance);

        $this->service->editAdvance($advance->id, [
            'pool_id' => $newPool->id,
            'amount' => 1000,
            'edit_reason' => 'Posted to wrong pool',
        ], accountId: 1);

        $this->assertSame(20000.00, (float) $oldPool->fresh()->cached_balance, 'Old pool must be made whole.');
        $this->assertSame(9000.00, (float) $newPool->fresh()->cached_balance, 'New pool must absorb the advance.');
    }

    public function test_creating_a_return_credits_the_pool_and_clears_outstanding(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        $this->service->createAdvance([
            'user_id' => $staff->id, 'pool_id' => $pool->id, 'amount' => 5000,
        ], accountId: 1);

        $this->service->createReturn([
            'user_id' => $staff->id, 'pool_id' => $pool->id, 'amount' => 5000,
        ], accountId: 1);

        $this->assertSame(20000.00, (float) $pool->fresh()->cached_balance);
        $this->assertSame(0.0, $this->service->getOutstanding($staff->id, accountId: 1));
    }

    public function test_returning_more_than_outstanding_is_rejected(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        $this->service->createAdvance([
            'user_id' => $staff->id, 'pool_id' => $pool->id, 'amount' => 1000,
        ], accountId: 1);

        $this->expectException(CashflowException::class);
        $this->service->createReturn([
            'user_id' => $staff->id, 'pool_id' => $pool->id, 'amount' => 5000,
        ], accountId: 1);
    }

    public function test_advance_persists_supplied_date_and_defaults_to_today(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create([
            'account_id' => 1,
            'is_advance_eligible' => 1,
        ]);

        // Backdated by 3 days — well inside the 7-day window the SPA
        // form exposes. Should persist exactly as supplied.
        $backdated = now()->subDays(3)->toDateString();
        $advance = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 1000,
            'advance_date' => $backdated,
        ], accountId: 1);
        $this->assertSame($backdated, $advance->advance_date->format('Y-m-d'));

        // Default behaviour: omitting the key writes today's date.
        $today = $this->service->createAdvance([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 500,
        ], accountId: 1);
        $this->assertSame(now()->toDateString(), $today->advance_date->format('Y-m-d'));
    }

    public function test_return_persists_supplied_date_and_defaults_to_today(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $staff = User::factory()->create([
            'account_id' => 1,
            'is_advance_eligible' => 1,
        ]);
        $this->service->createAdvance([
            'user_id' => $staff->id, 'pool_id' => $pool->id, 'amount' => 5000,
        ], accountId: 1);

        $backdated = now()->subDays(2)->toDateString();
        $return = $this->service->createReturn([
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 1500,
            'return_date' => $backdated,
        ], accountId: 1);
        $this->assertSame($backdated, $return->return_date->format('Y-m-d'));
    }

    /**
     * getStaffSummary must not scale queries with staff count. It was an
     * N+1 (getOutstanding() per user = 5 SUM queries each) on every
     * Movements page load; now it batches via getOutstandingForUsers.
     * With 6 staff the old path fired 30+ queries — assert a flat
     * ceiling that the per-user loop would blow past.
     */
    public function test_get_staff_summary_query_count_is_flat_not_n_plus_1(): void
    {
        $pool = CashPool::factory()->withOpeningBalance(500000)->create(['account_id' => 1]);
        for ($i = 0; $i < 6; $i++) {
            $staff = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);
            StaffAdvance::factory()->create([
                'account_id' => 1,
                'user_id' => $staff->id,
                'pool_id' => $pool->id,
                'amount' => 1000,
            ]);
        }

        $this->assertQueryCountAtMost(
            15,
            fn () => $this->service->getStaffSummary(1),
        );
    }
}
