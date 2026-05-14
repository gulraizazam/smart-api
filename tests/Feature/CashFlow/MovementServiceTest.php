<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\User;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\MovementService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the Phase-A unified surface over Transfers + StaffAdvances + StaffReturns.
 *
 * Test coverage matrix:
 *   - Dispatch: each (source_type, dest_type) routes to the right per-kind
 *     service AND writes the right audit_log entity_type. The audit row is
 *     the canary — if a future refactor mis-routes, the wrong constant
 *     fires and these tests fail.
 *   - Invariant preservation: every per-kind guard (period-lock, eligibility,
 *     threshold, outstanding cap, attachment, transfer-date window) keeps
 *     firing when called through MovementService.
 *   - list(): UNION shape includes all kinds, filters scope correctly.
 *   - void(): each kind routes to the right voider; staff↔staff in Phase A
 *     is refused.
 *   - getPoolLedger(): chronological feed UNIONing inflow + outflow.
 */
class MovementServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private MovementService $movementService;
    private User $admin;
    private CashPool $poolA;
    private CashPool $poolB;
    private User $eligibleStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->admin = $this->actingAsAdmin();

        $this->grantCashflowPerms([
            'cashflow_transfer_view', 'cashflow_transfer_create', 'cashflow_transfer_void',
            'cashflow_staff_advance_view', 'cashflow_staff_advance_create', 'cashflow_staff_advance_void',
            'cashflow_staff_return_create', 'cashflow_staff_return_void',
            'cashflow_audit_view', 'cashflow_manage',
        ]);

        $this->poolA = CashPool::factory()->create([
            'account_id' => 1,
            'type' => CashPool::TYPE_HEAD_OFFICE_CASH,
            'location_id' => null,
            'name' => 'HQ Cash',
            'opening_balance' => 100000,
            'cached_balance' => 100000,
        ]);
        $this->poolB = CashPool::factory()->create([
            'account_id' => 1,
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => $this->defaultLocation->id,
            'name' => 'Branch Cash',
            'opening_balance' => 0,
            'cached_balance' => 0,
        ]);

        $this->eligibleStaff = User::factory()->create([
            'account_id' => 1,
            'is_advance_eligible' => 1,
        ]);

        $this->movementService = app(MovementService::class);
    }

    private function grantCashflowPerms(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('test-movement-' . uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // -----------------------------------------------------------------
    // Dispatch matrix — the central contract of this service.
    // -----------------------------------------------------------------

    public function test_pool_to_pool_dispatches_to_transfer_service(): void
    {
        $movement = $this->movementService->create(1, [
            'source_type' => 'pool',
            'source_id' => $this->poolA->id,
            'dest_type' => 'pool',
            'dest_id' => $this->poolB->id,
            'amount' => 1000,
            'transfer_date' => now()->toDateString(),
            'method' => 'physical_cash',
            'attachment_url' => 'https://drive.google.com/file/d/abc123/view',
            'description' => 'HQ → Branch float top-up',
        ]);

        $this->assertSame('transfer', $movement['kind']);
        $this->assertDatabaseHas('cash_transfers', [
            'id' => $movement['id'],
            'amount' => 1000,
            'from_pool_id' => $this->poolA->id,
            'to_pool_id' => $this->poolB->id,
        ]);
        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_TRANSFER,
            'entity_id' => $movement['id'],
            'action' => CashflowAuditLog::ACTION_CREATED,
        ]);
    }

    public function test_pool_to_staff_dispatches_to_create_advance(): void
    {
        $movement = $this->movementService->create(1, [
            'source_type' => 'pool',
            'source_id' => $this->poolA->id,
            'dest_type' => 'staff',
            'dest_id' => $this->eligibleStaff->id,
            'amount' => 500,
            'description' => 'Advance for travel',
        ]);

        $this->assertSame('staff_advance', $movement['kind']);
        $this->assertDatabaseHas('staff_advances', [
            'id' => $movement['id'],
            'amount' => 500,
            'pool_id' => $this->poolA->id,
            'user_id' => $this->eligibleStaff->id,
        ]);
        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_STAFF_ADVANCE,
            'entity_id' => $movement['id'],
        ]);
    }

    public function test_staff_to_pool_dispatches_to_create_return(): void
    {
        // Seed an advance so the return has an outstanding balance to settle against.
        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->eligibleStaff->id,
            'pool_id' => $this->poolA->id,
            'amount' => 1000,
            'created_by' => $this->admin->id,
        ]);

        $movement = $this->movementService->create(1, [
            'source_type' => 'staff',
            'source_id' => $this->eligibleStaff->id,
            'dest_type' => 'pool',
            'dest_id' => $this->poolA->id,
            'amount' => 400,
            'description' => 'Partial return',
        ]);

        $this->assertSame('staff_return', $movement['kind']);
        $this->assertDatabaseHas('staff_returns', [
            'id' => $movement['id'],
            'amount' => 400,
            'user_id' => $this->eligibleStaff->id,
        ]);
        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_STAFF_RETURN,
            'entity_id' => $movement['id'],
        ]);
    }

    public function test_staff_to_staff_dispatches_to_staff_transfer_service(): void
    {
        $other = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        // Source needs outstanding before a handover can flow. Seed an advance.
        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->eligibleStaff->id,
            'pool_id' => $this->poolA->id,
            'amount' => 500,
            'created_by' => $this->admin->id,
        ]);

        $movement = $this->movementService->create(1, [
            'source_type' => 'staff',
            'source_id' => $this->eligibleStaff->id,
            'dest_type' => 'staff',
            'dest_id' => $other->id,
            'amount' => 200,
            'description' => 'Asma → Hassan handover',
        ]);

        $this->assertSame('staff_transfer', $movement['kind']);
        $this->assertDatabaseHas('staff_transfers', [
            'id' => $movement['id'],
            'amount' => 200,
            'from_user_id' => $this->eligibleStaff->id,
            'to_user_id' => $other->id,
        ]);
        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_STAFF_TRANSFER,
            'entity_id' => $movement['id'],
            'action' => CashflowAuditLog::ACTION_CREATED,
        ]);
    }

    public function test_staff_to_staff_rejects_same_user(): void
    {
        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->eligibleStaff->id,
            'pool_id' => $this->poolA->id,
            'amount' => 500,
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(\App\Exceptions\CashflowException::class);
        $this->movementService->create(1, [
            'source_type' => 'staff',
            'source_id' => $this->eligibleStaff->id,
            'dest_type' => 'staff',
            'dest_id' => $this->eligibleStaff->id,
            'amount' => 50,
        ]);
    }

    public function test_staff_to_staff_caps_at_source_outstanding(): void
    {
        $other = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->eligibleStaff->id,
            'pool_id' => $this->poolA->id,
            'amount' => 200,
            'created_by' => $this->admin->id,
        ]);

        $this->expectExceptionMessage('exceeds source outstanding balance');
        $this->movementService->create(1, [
            'source_type' => 'staff',
            'source_id' => $this->eligibleStaff->id,
            'dest_type' => 'staff',
            'dest_id' => $other->id,
            'amount' => 500,
        ]);
    }

    public function test_staff_to_staff_shifts_outstanding_between_users(): void
    {
        $other = \App\Models\User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->eligibleStaff->id,
            'pool_id' => $this->poolA->id,
            'amount' => 1000,
            'created_by' => $this->admin->id,
        ]);

        $sa = app(\App\Services\CashFlow\StaffAdvanceService::class);
        $this->assertSame(1000.0, $sa->getOutstanding($this->eligibleStaff->id, 1));
        $this->assertSame(0.0, $sa->getOutstanding($other->id, 1));

        $this->movementService->create(1, [
            'source_type' => 'staff',
            'source_id' => $this->eligibleStaff->id,
            'dest_type' => 'staff',
            'dest_id' => $other->id,
            'amount' => 300,
        ]);

        $this->assertSame(700.0, $sa->getOutstanding($this->eligibleStaff->id, 1),
            'Source outstanding should decrease by the handover amount.');
        $this->assertSame(300.0, $sa->getOutstanding($other->id, 1),
            'Destination outstanding should increase by the handover amount.');
    }

    // -----------------------------------------------------------------
    // Invariants — each guard the underlying service runs must still fire.
    // -----------------------------------------------------------------

    public function test_period_lock_blocks_pool_to_pool_in_locked_month(): void
    {
        PeriodLock::create([
            'account_id' => 1,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'locked_by' => $this->admin->id,
            'locked_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\CashflowException::class);
        $this->movementService->create(1, [
            'source_type' => 'pool',
            'source_id' => $this->poolA->id,
            'dest_type' => 'pool',
            'dest_id' => $this->poolB->id,
            'amount' => 100,
            'transfer_date' => now()->toDateString(),
            'method' => 'physical_cash',
            'attachment_url' => 'https://drive.google.com/file/d/abc/view',
        ]);
    }

    public function test_pool_to_staff_rejects_ineligible_staff(): void
    {
        $ineligible = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 0]);

        $this->expectExceptionMessage('not eligible');
        $this->movementService->create(1, [
            'source_type' => 'pool',
            'source_id' => $this->poolA->id,
            'dest_type' => 'staff',
            'dest_id' => $ineligible->id,
            'amount' => 500,
        ]);
    }

    public function test_pool_to_staff_enforces_cumulative_threshold(): void
    {
        app(CashflowSettingService::class)->set('cumulative_advance_threshold', 1000, 1);

        // First advance at the cap.
        $this->movementService->create(1, [
            'source_type' => 'pool',
            'source_id' => $this->poolA->id,
            'dest_type' => 'staff',
            'dest_id' => $this->eligibleStaff->id,
            'amount' => 1000,
        ]);

        $this->expectExceptionMessage('exceeds the threshold');
        $this->movementService->create(1, [
            'source_type' => 'pool',
            'source_id' => $this->poolA->id,
            'dest_type' => 'staff',
            'dest_id' => $this->eligibleStaff->id,
            'amount' => 1,
        ]);
    }

    public function test_staff_to_pool_rejects_when_amount_exceeds_outstanding(): void
    {
        // Only 500 outstanding.
        StaffAdvance::create([
            'account_id' => 1,
            'user_id' => $this->eligibleStaff->id,
            'pool_id' => $this->poolA->id,
            'amount' => 500,
            'created_by' => $this->admin->id,
        ]);

        $this->expectExceptionMessage('exceeds outstanding balance');
        $this->movementService->create(1, [
            'source_type' => 'staff',
            'source_id' => $this->eligibleStaff->id,
            'dest_type' => 'pool',
            'dest_id' => $this->poolA->id,
            'amount' => 1000,
        ]);
    }

    // -----------------------------------------------------------------
    // list() — UNION shape + filters.
    // -----------------------------------------------------------------

    public function test_list_unions_all_three_kinds_in_chronological_order(): void
    {
        // Three movements of each kind, dated different days.
        $transfer = CashTransfer::create([
            'account_id' => 1, 'transfer_date' => now()->subDays(2)->toDateString(),
            'amount' => 100, 'from_pool_id' => $this->poolA->id, 'to_pool_id' => $this->poolB->id,
            'method' => 'physical_cash', 'attachment_url' => 'https://drive.google.com/x',
            'created_by' => $this->admin->id,
        ]);
        $advance = StaffAdvance::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 200, 'created_by' => $this->admin->id,
        ]);
        // Backdate to yesterday via direct DB write (StaffAdvance has no advance_date column).
        DB::table('staff_advances')->where('id', $advance->id)->update(['created_at' => now()->subDay()]);

        $return = StaffReturn::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 50, 'created_by' => $this->admin->id,
        ]);
        // created_at = today (default).

        $paginator = $this->movementService->list(1, [], 25, 1);
        $items = collect($paginator->items());

        $this->assertCount(3, $items);
        $kinds = $items->pluck('kind')->all();
        // Newest first: return (today) → advance (yesterday) → transfer (2 days ago).
        $this->assertSame(['staff_return', 'staff_advance', 'transfer'], $kinds);
        $this->assertSame($transfer->id, $items[2]['id']);
        $this->assertSame($advance->id, $items[1]['id']);
        $this->assertSame($return->id, $items[0]['id']);
    }

    public function test_list_includes_staff_transfer_rows(): void
    {
        $other = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);
        StaffAdvance::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 500, 'created_by' => $this->admin->id,
        ]);
        \App\Models\CashFlow\StaffTransfer::create([
            'account_id' => 1,
            'from_user_id' => $this->eligibleStaff->id,
            'to_user_id' => $other->id,
            'amount' => 100,
            'created_by' => $this->admin->id,
        ]);

        $paginator = $this->movementService->list(1, ['kind' => 'staff_transfer'], 25, 1);
        $items = collect($paginator->items());
        $this->assertCount(1, $items);
        $row = $items->first();
        $this->assertSame('staff_transfer', $row['kind']);
        $this->assertSame('staff', $row['source']['type']);
        $this->assertSame('staff', $row['dest']['type']);
        $this->assertSame($this->eligibleStaff->id, $row['source']['id']);
        $this->assertSame($other->id, $row['dest']['id']);
    }

    public function test_list_filters_by_kind(): void
    {
        CashTransfer::create([
            'account_id' => 1, 'transfer_date' => now()->toDateString(),
            'amount' => 100, 'from_pool_id' => $this->poolA->id, 'to_pool_id' => $this->poolB->id,
            'method' => 'physical_cash', 'attachment_url' => 'https://drive.google.com/x',
            'created_by' => $this->admin->id,
        ]);
        StaffAdvance::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 200, 'created_by' => $this->admin->id,
        ]);

        $paginator = $this->movementService->list(1, ['kind' => 'transfer'], 25, 1);
        $items = collect($paginator->items());

        $this->assertCount(1, $items);
        $this->assertSame('transfer', $items->first()['kind']);
    }

    // -----------------------------------------------------------------
    // void() — dispatches by kind.
    // -----------------------------------------------------------------

    public function test_void_each_kind_routes_to_right_voider(): void
    {
        $other = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => 1]);

        $transfer = CashTransfer::create([
            'account_id' => 1, 'transfer_date' => now()->toDateString(),
            'amount' => 100, 'from_pool_id' => $this->poolA->id, 'to_pool_id' => $this->poolB->id,
            'method' => 'physical_cash', 'attachment_url' => 'https://drive.google.com/x',
            'created_by' => $this->admin->id,
        ]);
        $advance = StaffAdvance::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 200, 'created_by' => $this->admin->id,
        ]);
        $return = StaffReturn::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 50, 'created_by' => $this->admin->id,
        ]);
        $handover = \App\Models\CashFlow\StaffTransfer::create([
            'account_id' => 1,
            'from_user_id' => $this->eligibleStaff->id,
            'to_user_id' => $other->id,
            'amount' => 25,
            'created_by' => $this->admin->id,
        ]);

        $this->movementService->void(1, 'transfer', $transfer->id, 'wrong pool selected');
        $this->movementService->void(1, 'staff_advance', $advance->id, 'duplicate entry today');
        $this->movementService->void(1, 'staff_return', $return->id, 'amount was wrong');
        $this->movementService->void(1, 'staff_transfer', $handover->id, 'wrong recipient selected');

        $this->assertNotNull(CashTransfer::find($transfer->id)->voided_at);
        $this->assertNotNull(StaffAdvance::find($advance->id)->voided_at);
        $this->assertNotNull(StaffReturn::find($return->id)->voided_at);
        $this->assertNotNull(\App\Models\CashFlow\StaffTransfer::find($handover->id)->voided_at);
    }

    public function test_void_unknown_kind_throws(): void
    {
        $this->expectException(\App\Exceptions\CashflowException::class);
        $this->movementService->void(1, 'bogus', 1, 'whatever 12345');
    }

    // -----------------------------------------------------------------
    // getPoolLedger() — chronological inflow + outflow feed.
    // -----------------------------------------------------------------

    public function test_pool_ledger_unions_inbound_outbound_transfers_advances_returns(): void
    {
        // Two transfers (one each direction), one advance, one return — all
        // touching poolA.
        CashTransfer::create([ // outbound from A
            'account_id' => 1, 'transfer_date' => now()->subDays(3)->toDateString(),
            'amount' => 100, 'from_pool_id' => $this->poolA->id, 'to_pool_id' => $this->poolB->id,
            'method' => 'physical_cash', 'attachment_url' => 'https://drive.google.com/x',
            'created_by' => $this->admin->id,
        ]);
        CashTransfer::create([ // inbound to A
            'account_id' => 1, 'transfer_date' => now()->subDays(2)->toDateString(),
            'amount' => 200, 'from_pool_id' => $this->poolB->id, 'to_pool_id' => $this->poolA->id,
            'method' => 'physical_cash', 'attachment_url' => 'https://drive.google.com/y',
            'created_by' => $this->admin->id,
        ]);
        StaffAdvance::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 50, 'created_by' => $this->admin->id,
        ]);
        StaffReturn::create([
            'account_id' => 1, 'user_id' => $this->eligibleStaff->id, 'pool_id' => $this->poolA->id,
            'amount' => 25, 'created_by' => $this->admin->id,
        ]);

        // An unrelated transfer between poolB and a third pool should NOT
        // surface in poolA's ledger.
        $poolC = CashPool::factory()->create(['account_id' => 1, 'name' => 'Other']);
        CashTransfer::create([
            'account_id' => 1, 'transfer_date' => now()->toDateString(),
            'amount' => 999, 'from_pool_id' => $this->poolB->id, 'to_pool_id' => $poolC->id,
            'method' => 'physical_cash', 'attachment_url' => 'https://drive.google.com/z',
            'created_by' => $this->admin->id,
        ]);

        $ledger = $this->movementService->getPoolLedger(1, $this->poolA->id);

        $this->assertCount(4, $ledger, 'Ledger should contain only movements touching the pool.');
        $kinds = $ledger->pluck('kind')->countBy()->all();
        // assertEquals (not assertSame) — key order varies by first-appearance
        // in the collection, but the counts must match.
        $this->assertEquals(['transfer' => 2, 'staff_advance' => 1, 'staff_return' => 1], $kinds);
    }
}
