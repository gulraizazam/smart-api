<?php

declare(strict_types=1);

namespace Tests\Feature\Upselling;

use App\Models\Locations;
use App\Models\Patients;
use App\Models\UpsellCollectedMonthly;
use App\Models\User;
use App\Services\Upselling\Rollup\UpsellCollectedBuilder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The monthly "upsell collected" rollup builder. Pins the behaviours the pure
 * allocator can't: self-consultation exclusion, the 90-day window, recognition
 * in the receipt month, refund clawback (negative month), and the frozen-month
 * guard. Full model: memory `project_upsell_collected_incentive`.
 */
class UpsellCollectedBuilderTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $consultant;
    private int $upseller;
    private int $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->consultant = User::factory()->doctor()->create()->id;
        $this->upseller = User::factory()->doctor()->create()->id;
        $this->location = Locations::factory()->create()->id;
    }

    private function builder(): UpsellCollectedBuilder
    {
        return app(UpsellCollectedBuilder::class);
    }

    /**
     * Create a plan tied to a CONSULTANCY appointment (doctor = $this->consultant)
     * with the given upsold lines and cash events.
     *
     * @param  array<int, array{sold_by:int, value:float, consumed?:int, order?:int, sale?:string}>  $lines
     * @param  array<int, array{flow:string, amount:float, date:string, refund?:int, cancel?:int}>  $cash
     */
    private function makePlan(string $saleDate, array $lines, array $cash): int
    {
        $patient = Patients::factory()->create();

        $apptId = DB::table('appointments')->insertGetId([
            'appointment_type_id' => 1, // Consultancy
            'doctor_id' => $this->consultant,
            'patient_id' => $patient->id,
            'location_id' => $this->location,
            'account_id' => 1,
            'lead_id' => 0,
            'region_id' => 0,
            'city_id' => 0,
            'created_at' => $saleDate,
        ]);

        $packageId = DB::table('packages')->insertGetId([
            'account_id' => 1,
            'patient_id' => $patient->id,
            'location_id' => $this->location,
            'appointment_id' => $apptId,
            'total_price' => array_sum(array_column($lines, 'value')),
            'created_at' => $saleDate,
        ]);

        foreach ($lines as $ln) {
            DB::table('package_services')->insert([
                'package_id' => $packageId,
                'sold_by' => $ln['sold_by'],
                'tax_including_price' => $ln['value'],
                'is_consumed' => $ln['consumed'] ?? 0,
                'consumption_order' => $ln['order'] ?? 0,
                'service_id' => 1,
                'created_at' => $ln['sale'] ?? $saleDate,
            ]);
        }

        foreach ($cash as $c) {
            DB::table('package_advances')->insert([
                'account_id' => 1,
                'package_id' => $packageId,
                'cash_flow' => $c['flow'],
                'cash_amount' => $c['amount'],
                'is_refund' => $c['refund'] ?? 0,
                'is_cancel' => $c['cancel'] ?? 0,
                'created_at' => $c['date'],
            ]);
        }

        return $packageId;
    }

    private function collected(int $doctorId, string $month): float
    {
        return (float) UpsellCollectedMonthly::query()
            ->where('doctor_id', $doctorId)
            ->where('month', $month)
            ->sum('collected_amount');
    }

    public function test_credits_the_upseller_and_excludes_the_self_consultation_line(): void
    {
        // Same plan: the consultant's own line (self-consultation) + a treating
        // doctor's upsell, each worth 100. Cash 200 lands on the sale day.
        $this->makePlan('2026-03-05', [
            ['sold_by' => $this->consultant, 'value' => 100], // self-consult -> NOT an upsell
            ['sold_by' => $this->upseller, 'value' => 100],   // the upsell
        ], [
            ['flow' => 'in', 'amount' => 200, 'date' => '2026-03-05'],
        ]);

        $this->builder()->rebuild(1, '2026-03-01');

        // Only the upsell line is in the pool; it's capped at its 100 value.
        $this->assertSame(100.0, $this->collected($this->upseller, '2026-03-01'));
        $this->assertSame(0.0, $this->collected($this->consultant, '2026-03-01'));
    }

    public function test_cash_is_recognised_in_its_receipt_month_and_gated_by_the_90_day_window(): void
    {
        // Sold Mar 1; payment lands Apr 10 (day 40, inside the window).
        $this->makePlan('2026-03-01', [
            ['sold_by' => $this->upseller, 'value' => 100],
        ], [
            ['flow' => 'in', 'amount' => 100, 'date' => '2026-04-10'],
        ]);
        $this->builder()->rebuild(1, '2026-03-01');
        $this->builder()->rebuild(1, '2026-04-01');

        $this->assertSame(0.0, $this->collected($this->upseller, '2026-03-01'), 'no cash in March');
        $this->assertSame(100.0, $this->collected($this->upseller, '2026-04-01'), 'recognised in the receipt month');

        // A second plan whose payment arrives well past day 90 earns nothing.
        $late = User::factory()->doctor()->create()->id;
        $this->makePlan('2026-03-01', [
            ['sold_by' => $late, 'value' => 100],
        ], [
            ['flow' => 'in', 'amount' => 100, 'date' => '2026-08-01'], // day ~153, retired
        ]);
        $this->builder()->rebuild(1, '2026-08-01');
        $this->assertSame(0.0, $this->collected($late, '2026-08-01'), 'cash past the 90-day window is not collected');
    }

    public function test_a_refund_produces_a_clawback_in_its_month(): void
    {
        $this->makePlan('2026-03-01', [
            ['sold_by' => $this->upseller, 'value' => 100],
        ], [
            ['flow' => 'in', 'amount' => 100, 'date' => '2026-03-05'],
            ['flow' => 'out', 'amount' => 60, 'date' => '2026-04-10', 'refund' => 1],
        ]);

        $this->builder()->rebuild(1, '2026-03-01');
        $this->builder()->rebuild(1, '2026-04-01');

        $this->assertSame(100.0, $this->collected($this->upseller, '2026-03-01'));
        $this->assertSame(-60.0, $this->collected($this->upseller, '2026-04-01'), 'the refund claws back in its month');
    }

    public function test_a_frozen_month_is_never_overwritten(): void
    {
        $this->makePlan('2026-03-05', [
            ['sold_by' => $this->upseller, 'value' => 100],
        ], [
            ['flow' => 'in', 'amount' => 100, 'date' => '2026-03-05'],
        ]);
        $this->builder()->rebuild(1, '2026-03-01');

        // Freeze the row at a sentinel value (simulating a paid month).
        UpsellCollectedMonthly::query()
            ->where('doctor_id', $this->upseller)
            ->where('month', '2026-03-01')
            ->update(['collected_amount' => 999, 'is_closed' => true]);

        // A later rebuild must leave the frozen row untouched.
        $this->builder()->rebuild(1, '2026-03-01');

        $this->assertSame(999.0, $this->collected($this->upseller, '2026-03-01'));
    }

    public function test_rebuild_is_idempotent(): void
    {
        $this->makePlan('2026-03-05', [
            ['sold_by' => $this->upseller, 'value' => 80],
        ], [
            ['flow' => 'in', 'amount' => 80, 'date' => '2026-03-06'],
        ]);

        $this->builder()->rebuild(1, '2026-03-01');
        $first = $this->collected($this->upseller, '2026-03-01');
        $this->builder()->rebuild(1, '2026-03-01');
        $second = $this->collected($this->upseller, '2026-03-01');

        $this->assertSame(80.0, $first);
        $this->assertSame($first, $second);
        // exactly one row (no duplication on re-run)
        $this->assertSame(1, UpsellCollectedMonthly::query()
            ->where('doctor_id', $this->upseller)->where('month', '2026-03-01')->count());
    }
}
