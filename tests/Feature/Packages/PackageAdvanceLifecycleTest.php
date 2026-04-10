<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Models\CashFlow\CashPool;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\PlanInvoice;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the lifecycle behaviour of PackageAdvances at the model layer:
 *
 *   1. Creating an `in` advance:
 *      - persists the row
 *      - creates a corresponding plan_invoice (sync table the audit added)
 *      - moves the branch cash pool via PackageAdvanceObserver
 *
 *   2. CancelRecord() flips is_cancel and the observer reverses the pool
 *      impact (so cancelled rows do not contribute to balances).
 *
 *   3. Pre-go-live records (created before the cashflow_setting cutoff)
 *      do NOT touch the pool — they were captured in opening balances.
 *      The current observer treats records with no cutoff as in-scope, so
 *      this test pins the *current* default behaviour and acts as a
 *      regression guard if the cutoff logic changes.
 */
class PackageAdvanceLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_an_in_advance_persists_row_and_credits_branch_pool(): void
    {
        // LocationCashflowObserver::created auto-creates the branch cash
        // pool with cached_balance = 0. Fetch *that* pool — creating a
        // second pool for the same (location, type=branch_cash) tuple
        // would never receive observer credits because PackageAdvance
        // resolves the destination pool by location_id alone.
        $location = Locations::factory()->create();
        $pool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $patient = Patients::factory()->create();
        $package = Packages::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'total_price' => 10000,
        ]);

        $advance = PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 2500,
            'patient_id' => $patient->id,
            'package_id' => $package->id,
            'location_id' => $location->id,
            'payment_mode_id' => $cash->id,
        ]);

        $this->assertNotNull($advance->id);
        $this->assertSame(2500.00, (float) $pool->fresh()->cached_balance);
    }

    public function test_creating_an_in_advance_writes_a_plan_invoice_sync_row(): void
    {
        $location = Locations::factory()->create();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $patient = Patients::factory()->create();
        $package = Packages::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'total_price' => 10000,
        ]);

        $advance = PackageAdvances::createRecord_onlyadvances([
            'cash_flow' => 'in',
            'cash_amount' => 1500,
            'patient_id' => $patient->id,
            'package_id' => $package->id,
            'location_id' => $location->id,
            'payment_mode_id' => $cash->id,
            'account_id' => 1,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->assertDatabaseHas('plan_invoices', [
            'package_advance_id' => $advance->id,
            'package_id' => $package->id,
            'total_price' => 1500,
        ]);
    }

    public function test_cancel_record_marks_is_cancel_and_observer_reverses_pool(): void
    {
        $location = Locations::factory()->create();
        // Use the observer-created pool, then prime its balance to 5000.
        $pool = CashPool::query()
            ->where('location_id', $location->id)
            ->where('type', CashPool::TYPE_BRANCH_CASH)
            ->firstOrFail();
        $pool->forceFill(['opening_balance' => 5000, 'cached_balance' => 5000])->save();
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        $advance = PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 1200,
            'payment_mode_id' => $cash->id,
            'location_id' => $location->id,
        ]);
        $this->assertSame(6200.00, (float) $pool->fresh()->cached_balance);

        PackageAdvances::CancelRecord($advance->id, accountId: 1);

        $this->assertSame(
            5000.00,
            (float) $pool->fresh()->cached_balance,
            'CancelRecord must reverse the original pool credit so balances reflect reality.'
        );
        $this->assertSame(1, (int) $advance->fresh()->is_cancel);
    }

    public function test_settled_out_advance_does_not_touch_the_branch_pool(): void
    {
        $location = Locations::factory()->create();
        $pool = CashPool::factory()->create([
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => $location->id,
            'opening_balance' => 5000,
            'cached_balance' => 5000,
        ]);
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();

        // out + is_refund = 0 => settlement (uses already-collected money,
        // never leaves the pool)
        PackageAdvances::factory()->out()->create([
            'cash_amount' => 1500,
            'payment_mode_id' => $cash->id,
            'location_id' => $location->id,
            'is_refund' => 0,
        ]);

        $this->assertSame(5000.00, (float) $pool->fresh()->cached_balance);
    }
}
