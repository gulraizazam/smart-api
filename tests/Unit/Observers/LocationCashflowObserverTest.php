<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\Locations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * LocationCashflowObserver bridges the operational and financial worlds —
 * when a clinic branch is opened, the system must auto-create a branch cash
 * pool so the cashflow ledger has a place to put receipts. The audit added
 * this observer to remove a manual step that often caused locations to
 * exist without a corresponding pool, breaking the cashflow dashboard.
 *
 * Pin the contract:
 *   - new active location  → branch_cash pool auto-created and audit-logged
 *   - inactive location    → no pool created
 *   - location deactivated → existing pool is_active flipped to 0
 *   - location reactivated → soft-deleted pool restored
 */
class LocationCashflowObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_an_active_location_auto_creates_its_branch_cash_pool(): void
    {
        $location = Locations::factory()->create(['active' => 1]);

        $pool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->first();

        $this->assertNotNull($pool, 'A branch_cash pool must be auto-created for the new location.');
        $this->assertSame(1, (int) $pool->is_active);
        $this->assertSame(0.00, (float) $pool->cached_balance);
    }

    public function test_auto_created_pool_is_recorded_in_the_cashflow_audit_log(): void
    {
        $location = Locations::factory()->create(['active' => 1]);

        $pool = CashPool::query()
            ->where('location_id', $location->id)
            ->firstOrFail();

        $this->assertDatabaseHas('cashflow_audit_logs', [
            'entity_type' => CashflowAuditLog::ENTITY_CASH_POOL,
            'entity_id' => $pool->id,
            'action' => CashflowAuditLog::ACTION_AUTO_CREATED,
        ]);
    }

    public function test_creating_an_inactive_location_does_not_auto_create_a_pool(): void
    {
        $location = Locations::factory()->create(['active' => 0]);

        $this->assertSame(
            0,
            CashPool::query()->where('location_id', $location->id)->count(),
            'No pool should be created for an inactive location.',
        );
    }

    public function test_deactivating_a_location_marks_its_pool_inactive(): void
    {
        $location = Locations::factory()->create(['active' => 1]);
        $pool = CashPool::query()->where('location_id', $location->id)->firstOrFail();
        $this->assertSame(1, (int) $pool->is_active);

        $location->update(['active' => 0]);

        $this->assertSame(0, (int) $pool->fresh()->is_active);
    }

    public function test_reactivating_a_location_restores_its_pool(): void
    {
        // Docblock contract: "location reactivated → soft-deleted pool
        // restored". Create active → deactivate → reactivate and verify
        // the pool comes back to is_active = 1.
        $location = Locations::factory()->create(['active' => 1]);
        $pool = CashPool::query()->where('location_id', $location->id)->firstOrFail();

        $location->update(['active' => 0]);
        $this->assertSame(0, (int) $pool->fresh()->is_active);

        $location->update(['active' => 1]);
        $this->assertSame(
            1,
            (int) $pool->fresh()->is_active,
            'Reactivating a location must restore its pool to is_active = 1.',
        );
    }
}
