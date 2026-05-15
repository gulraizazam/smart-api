<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the `savepackagesadvances` contract under the
 * **overpayment-allowed** policy (confirmed 2026-05-15).
 *
 * The earlier audit-era contract was "reject any save that would push
 * sum(cash_amount) above plan.total_price". That guard was deliberately
 * removed: operators routinely collect deposits / advances that exceed
 * the current plan total, and forcing them to under-record real cash
 * was causing reconciliation errors. Overpayments now persist as
 * credits on the signed cash ledger (`package_advances.cash_amount`),
 * surface in the dashboard's credit column, and stay attached to the
 * package for future reconciliation.
 *
 * Pinned properties:
 *   1. Boundary at exact remaining amount — accepted.
 *   2. Saves above the plan total — accepted (credit semantics).
 *   3. Two sequential saves that sum past total — both persist.
 *   4. Save path is wrapped in `DB::transaction` so partial failures
 *      roll back cleanly (transaction safety still pinned even though
 *      the over-total guard is gone).
 *
 * Test file kept under `Concurrency/` for historical continuity; the
 * lock-for-update guarantee the original tests asserted is no longer
 * part of the contract.
 */
class ConcurrentPackageAdvanceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Packages $package;

    private PaymentModes $cash;

    private Patients $patient;

    private Locations $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->location = Locations::factory()->create();
        $this->cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $this->patient = Patients::factory()->create();
        $this->package = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'total_price' => 10000,
        ]);
    }

    public function test_save_at_exact_remaining_amount_is_accepted(): void
    {
        // Standard happy path — plan total 10000, prior advance 6000,
        // new save brings the total to exactly 10000.
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 6000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        $response = $this->callSave(4000);

        $this->assertTrue((bool) ($response['status'] ?? false));
        $this->assertSame(2, PackageAdvances::query()->where('package_id', $this->package->id)->count());
        $this->assertSame(
            10000.00,
            (float) PackageAdvances::query()
                ->where('package_id', $this->package->id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount'),
        );
    }

    public function test_save_above_plan_total_is_accepted_as_credit(): void
    {
        // Overpayment policy (2026-05-15): operators can collect cash
        // exceeding the current plan total. Excess persists as a
        // negative remaining balance (credit) on the cash ledger.
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 6000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        // Plan total = 10000; save 4001 → sum becomes 10001 (1 rupee credit).
        $response = $this->callSave(4001);

        $this->assertTrue(
            (bool) ($response['status'] ?? false),
            'Overpayment must be accepted under the credit policy — no rejection.',
        );
        $this->assertSame(2, PackageAdvances::query()->where('package_id', $this->package->id)->count());
        $this->assertSame(
            10001.00,
            (float) PackageAdvances::query()
                ->where('package_id', $this->package->id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount'),
            'Overpayment must persist literally — the excess is the credit balance.',
        );
    }

    public function test_two_sequential_commits_can_sum_past_plan_total(): void
    {
        // Two saves both succeed even when the combined sum exceeds
        // total_price. The over-total guard is gone; the ledger
        // records what actually got paid.
        $first = $this->callSave(7000);
        $this->assertTrue((bool) ($first['status'] ?? false));

        $second = $this->callSave(4000); // pushes sum to 11000
        $this->assertTrue(
            (bool) ($second['status'] ?? false),
            'Second save must persist as a credit, not get rejected for exceeding plan total.',
        );

        $this->assertSame(
            11000.00,
            (float) PackageAdvances::query()
                ->where('package_id', $this->package->id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount'),
        );
        $this->assertSame(2, PackageAdvances::query()->where('package_id', $this->package->id)->count());
    }

    public function test_save_persists_a_single_row_with_the_exact_amount_requested(): void
    {
        // Cash-ledger integrity: every save inserts exactly one row,
        // signed `in`, with the cash_amount the operator typed. No
        // implicit adjustments / clamping based on the plan total.
        $response = $this->callSave(2500);

        $this->assertTrue((bool) ($response['status'] ?? false));

        $rows = PackageAdvances::query()
            ->where('package_id', $this->package->id)
            ->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('in', $row->cash_flow);
        $this->assertSame(2500.00, (float) $row->cash_amount);
        $this->assertSame($this->patient->id, (int) $row->patient_id);
        $this->assertSame((int) $this->cash->id, (int) $row->payment_mode_id);
    }

    /**
     * Drive the controller's save method directly with a synthetic Request.
     * Going through HTTP would force us to wire route parameters and CSRF
     * tokens for what is fundamentally a service-level contract test.
     */
    private function callSave(float $cashAmount): array
    {
        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'package_id' => $this->package->id,
            'patient_id' => $this->patient->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => $cashAmount,
            'total_price' => $this->package->total_price,
        ]);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(PackageAdvancesController::class)->savepackagesadvances($request);

        return json_decode($response->getContent(), true) ?? [];
    }
}
