<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\User;
use App\Services\CashFlow\StaffAdvanceService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Phase-7 contract: staff advance + return CRUD respects period locks.
 *
 * Staff advances and returns have no explicit `date` column; they're dated by
 * `created_at`. So:
 *   - createAdvance / createReturn: block when TODAY's month is locked.
 *   - voidAdvance / voidReturn / editAdvance: block when the existing record's
 *     created_at month is locked.
 *
 * Pre-fix, StaffAdvanceService had no period-lock guard. Locking a closed
 * month wouldn't stop new advances/returns or voids against past records.
 */
class StaffPeriodLockEnforcementTest extends TestCase
{
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

    public function test_creating_a_staff_advance_when_current_month_is_locked_is_rejected(): void
    {
        // Lock the CURRENT month so any new advance (which dates to now) is blocked.
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => now()->month,
            'year' => now()->year,
        ]);

        $user = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();

        $this->expectException(CashflowException::class);
        $this->service->createAdvance([
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'amount' => 500,
            'description' => 'Test',
        ], accountId: 1);
    }

    public function test_creating_a_staff_return_when_current_month_is_locked_is_rejected(): void
    {
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => now()->month,
            'year' => now()->year,
        ]);

        $user = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        // Pre-existing advance so there's something to return against; the
        // advance bypasses the lock since it's created via factory, not service.
        StaffAdvance::factory()->create([
            'account_id' => 1,
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'amount' => 1000,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->createReturn([
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'amount' => 200,
            'description' => 'Refund',
        ], accountId: 1);
    }

    public function test_voiding_an_advance_in_a_locked_month_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        $user = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $advance = StaffAdvance::factory()->create([
            'account_id' => 1,
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'created_at' => $previous,
            'updated_at' => $previous,
        ]);

        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->voidAdvance($advance->id, 'Reversing this', accountId: 1);
    }

    public function test_voiding_a_return_in_a_locked_month_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        $user = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $return = StaffReturn::factory()->create([
            'account_id' => 1,
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'created_at' => $previous,
            'updated_at' => $previous,
        ]);

        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->voidReturn($return->id, 'Reversing this', accountId: 1);
    }

    public function test_editing_an_advance_in_a_locked_month_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        $user = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();
        $advance = StaffAdvance::factory()->create([
            'account_id' => 1,
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'amount' => 1000,
            'created_at' => $previous,
            'updated_at' => $previous,
        ]);

        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->editAdvance($advance->id, [
            'amount' => 1500,
            'pool_id' => $pool->id,
            'description' => 'Updated',
            'edit_reason' => 'Correcting',
        ], accountId: 1);
    }

    public function test_creating_an_advance_with_no_period_lock_succeeds(): void
    {
        $user = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $pool = CashPool::factory()->withOpeningBalance(20000)->create();

        $advance = $this->service->createAdvance([
            'user_id' => $user->id,
            'pool_id' => $pool->id,
            'amount' => 500,
            'description' => 'Routine',
        ], accountId: 1);

        $this->assertNotNull($advance->id);
    }
}
