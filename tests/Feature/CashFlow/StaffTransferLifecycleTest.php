<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffTransfer;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\User;
use App\Services\CashFlow\StaffAdvanceService;
use App\Services\CashFlow\StaffTransferService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the StaffTransferService — Phase B's peer-handover lifecycle.
 *
 * Invariants:
 *   - source ≠ dest
 *   - source must have enough outstanding (no minting on the dest side)
 *   - period-lock guard fires on today's date
 *   - voiding reverses both ledger sides; pool balances stay untouched
 *   - outstanding shifts atomically between source and destination
 */
class StaffTransferLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private StaffTransferService $service;
    private StaffAdvanceService $staffService;
    private User $admin;
    private User $alice;
    private User $bob;
    private CashPool $pool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->admin = $this->actingAsAdmin();

        $this->pool = CashPool::factory()->create([
            'account_id' => 1,
            'type' => CashPool::TYPE_HEAD_OFFICE_CASH,
            'location_id' => null,
            'cached_balance' => 50000,
            'opening_balance' => 50000,
        ]);
        $this->alice = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);
        $this->bob = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        // Alice starts with PKR 1000 outstanding so she can hand over up to that.
        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->alice->id,
            'pool_id' => $this->pool->id,
            'amount' => 1000,
            'created_by' => $this->admin->id,
        ]);

        $this->service = app(StaffTransferService::class);
        $this->staffService = app(StaffAdvanceService::class);
    }

    public function test_create_records_transfer_and_audit_log(): void
    {
        $transfer = $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 250,
            'description' => 'Lunch float',
        ], 1);

        $this->assertDatabaseHas('staff_transfers', [
            'id' => $transfer->id,
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_STAFF_TRANSFER,
            'entity_id' => $transfer->id,
            'action' => CashflowAuditLog::ACTION_CREATED,
        ]);
    }

    public function test_create_rejects_same_source_and_destination(): void
    {
        $this->expectExceptionMessage('Source and destination staff must be different.');
        $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->alice->id,
            'amount' => 10,
        ], 1);
    }

    public function test_create_rejects_amount_above_source_outstanding(): void
    {
        $this->expectExceptionMessage('exceeds source outstanding balance');
        $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 5000,
        ], 1);
    }

    public function test_create_rejected_when_today_is_in_a_locked_period(): void
    {
        PeriodLock::create([
            'account_id' => 1,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'locked_by' => $this->admin->id,
            'locked_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\CashflowException::class);
        $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 100,
        ], 1);
    }

    public function test_create_shifts_outstanding_between_source_and_destination(): void
    {
        $this->assertSame(1000.0, $this->staffService->getOutstanding($this->alice->id, 1));
        $this->assertSame(0.0, $this->staffService->getOutstanding($this->bob->id, 1));

        $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 400,
        ], 1);

        $this->assertSame(600.0, $this->staffService->getOutstanding($this->alice->id, 1));
        $this->assertSame(400.0, $this->staffService->getOutstanding($this->bob->id, 1));
    }

    public function test_create_does_not_mutate_pool_balance(): void
    {
        $beforeBalance = (float) $this->pool->fresh()->cached_balance;

        $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 200,
        ], 1);

        $this->assertSame($beforeBalance, (float) $this->pool->fresh()->cached_balance,
            'Pool balance must NOT change on a peer handover.');
    }

    public function test_void_marks_row_and_reverses_outstanding_shift(): void
    {
        $transfer = $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 300,
        ], 1);

        $this->assertSame(700.0, $this->staffService->getOutstanding($this->alice->id, 1));
        $this->assertSame(300.0, $this->staffService->getOutstanding($this->bob->id, 1));

        $this->service->void($transfer->id, 'wrong recipient selected', 1);

        $voided = StaffTransfer::find($transfer->id);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame('wrong recipient selected', $voided->void_reason);

        // Voided rows drop out of the outstanding formula (it filters voided_at).
        $this->assertSame(1000.0, $this->staffService->getOutstanding($this->alice->id, 1));
        $this->assertSame(0.0, $this->staffService->getOutstanding($this->bob->id, 1));

        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_STAFF_TRANSFER,
            'entity_id' => $transfer->id,
            'action' => CashflowAuditLog::ACTION_VOIDED,
        ]);
    }

    public function test_void_rejects_already_voided_row(): void
    {
        $transfer = $this->service->create([
            'from_user_id' => $this->alice->id,
            'to_user_id' => $this->bob->id,
            'amount' => 100,
        ], 1);
        $this->service->void($transfer->id, 'first void reason', 1);

        $this->expectExceptionMessage('already voided');
        $this->service->void($transfer->id, 'second void attempt', 1);
    }
}
