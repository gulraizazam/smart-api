<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Packages;
use App\Services\Plan\ConsumptionReservation;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ConsumptionReservation — the single rule that decides how much of a plan's
 * payment is "committed" by what has been consumed (Shahid 2026-06-10).
 *
 * Configurable Buy/Get groups have consumption_order 1 = paid BUY, 2 = priced
 * GET, 3 = free GET (all source_type='service'). Once a plan is fully paid the
 * client may consume in any order; the moment a GET is used ahead of its BUY,
 * that BUY's money is RESERVED so it can't be diverted to a new service or
 * refunded. These pin the math both gates rely on.
 */
class ConsumptionReservationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ConsumptionReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->reservation = new ConsumptionReservation();
    }

    public function test_in_order_consumption_reserves_nothing(): void
    {
        // Paid BUY consumed first, free GET still unused → nothing reserved.
        $plan = $this->makePlan();
        $this->configSession($plan, 5001, order: 1, sold: 10000, regular: 10000, consumed: 1); // BUY consumed
        $this->configSession($plan, 5001, order: 3, sold: 0, regular: 10000, consumed: 0);     // free GET unused

        $this->assertSame(0, (int) $this->reservation->reservedRefundAmount($plan->id),
            'Using the paid BUY first reserves nothing.');
        $this->assertSame(10000, (int) $this->reservation->requiredFloor($plan->id),
            'Floor is just the consumed BUY.');
    }

    public function test_out_of_order_get_reserves_the_paid_buy(): void
    {
        // Free GET consumed BEFORE the paid BUY → the BUY is reserved.
        $plan = $this->makePlan();
        $this->configSession($plan, 5002, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY unused
        $this->configSession($plan, 5002, order: 3, sold: 0, regular: 10000, consumed: 1);     // free GET consumed

        $this->assertSame(10000, (int) $this->reservation->reservedRefundAmount($plan->id),
            'A free GET used ahead of its BUY reserves the BUY money.');
        $this->assertSame(10000, (int) $this->reservation->requiredFloor($plan->id),
            'Floor = consumed free (0) + reserved BUY (10000).');
    }

    public function test_reserves_every_earlier_unconsumed_sibling(): void
    {
        // Buy 1 / 2nd discounted / 3rd free; free used first → BUY + discount reserved.
        $plan = $this->makePlan();
        $this->configSession($plan, 5003, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY
        $this->configSession($plan, 5003, order: 2, sold: 5000, regular: 10000, consumed: 0);  // discounted GET
        $this->configSession($plan, 5003, order: 3, sold: 0, regular: 10000, consumed: 1);     // free GET, consumed

        $this->assertSame(15000, (int) $this->reservation->reservedRefundAmount($plan->id),
            'Both the paid BUY (10000) and the discounted GET (5000) are reserved.');
    }

    public function test_consuming_session_param_counts_toward_the_floor(): void
    {
        // "Floor if I also consume this" includes the session being consumed.
        // Two sessions so the plan sold total (14000) does not cap the 10000
        // regular price we are checking flows through.
        $plan = $this->makePlan();
        $s = $this->configSession($plan, null, order: 0, sold: 7000, regular: 10000, consumed: 0, sourceType: 'bundle');
        $this->configSession($plan, null, order: 0, sold: 7000, regular: 10000, consumed: 0, sourceType: 'bundle');

        $this->assertSame(0, (int) $this->reservation->requiredFloor($plan->id),
            'Nothing consumed yet → floor 0.');
        $this->assertSame(10000, (int) $this->reservation->requiredFloor($plan->id, $s),
            'Including the bundle session adds its REGULAR price.');
    }

    public function test_floor_is_capped_at_the_plan_sold_total(): void
    {
        // Consumed/reserved required can never exceed what the plan was sold for.
        $plan = $this->makePlan();
        $this->configSession($plan, 5004, order: 1, sold: 7000, regular: 10000, consumed: 1, sourceType: 'bundle');
        $this->configSession($plan, 5004, order: 2, sold: 7000, regular: 10000, consumed: 1, sourceType: 'bundle');

        // raw regular required 20000, capped at the 14000 sold total.
        $this->assertSame(14000, (int) $this->reservation->requiredFloor($plan->id),
            'Floor is capped at the plan sold total (14000), not the raw regular sum (20000).');
    }

    public function test_discounted_get_before_buy_reserves_only_the_buy(): void
    {
        // Discounted GET (order 2) used before the BUY (order 1): only the
        // earlier-order BUY is reserved; the free GET (order 3) above it is not.
        $plan = $this->makePlan();
        $this->configSession($plan, 5101, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY
        $this->configSession($plan, 5101, order: 2, sold: 5000, regular: 10000, consumed: 1);  // discounted GET used
        $this->configSession($plan, 5101, order: 3, sold: 0, regular: 10000, consumed: 0);     // free GET untouched

        $this->assertSame(10000, (int) $this->reservation->reservedRefundAmount($plan->id),
            'Only the earlier-order BUY (10000) is reserved; the free GET above it is not.');
    }

    public function test_reservation_is_isolated_per_config_group(): void
    {
        // Out-of-order use in group A must not reserve anything in group B.
        $plan = $this->makePlan();
        $this->configSession($plan, 5102, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY A
        $this->configSession($plan, 5102, order: 3, sold: 0, regular: 10000, consumed: 1);     // free A used
        $this->configSession($plan, 5103, order: 1, sold: 8000, regular: 8000, consumed: 0);   // BUY B
        $this->configSession($plan, 5103, order: 3, sold: 0, regular: 8000, consumed: 0);      // free B untouched

        $this->assertSame(10000, (int) $this->reservation->reservedRefundAmount($plan->id),
            'Only group A reserves its BUY (10000); group B is untouched.');
    }

    public function test_reservation_clears_once_the_buy_is_consumed(): void
    {
        // Once the paid BUY is also consumed, nothing is stranded → reserved 0.
        $plan = $this->makePlan();
        $this->configSession($plan, 5104, order: 1, sold: 10000, regular: 10000, consumed: 1); // BUY now consumed
        $this->configSession($plan, 5104, order: 3, sold: 0, regular: 10000, consumed: 1);     // free GET consumed

        $this->assertSame(0, (int) $this->reservation->reservedRefundAmount($plan->id),
            'When the BUY is consumed too, the reservation auto-clears.');
    }

    /* ===================== helpers ===================== */

    private function makePlan(): Packages
    {
        return Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'plan_type' => 'plan',
            'total_price' => 0,
            'random_id' => 'RES-'.uniqid(),
        ]);
    }

    private function configSession(Packages $plan, ?int $configGroupId, int $order, float $sold, float $regular, int $consumed, string $sourceType = 'service'): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1,
            'package_id' => $plan->id,
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => $sourceType,
            'config_group_id' => $configGroupId,
            'service_price' => $regular,
            'net_amount' => $sold,
            'tax_including_price' => $sold,
            'random_id' => $plan->random_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Svc '.uniqid(),
            'price' => $regular,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $plan->id,
            'package_bundle_id' => $bundleRowId,
            'service_id' => $serviceId,
            'price' => $sold,
            'orignal_price' => $regular,
            'tax_including_price' => $sold,
            'is_consumed' => $consumed,
            'consumption_order' => $order,
            'random_id' => $plan->random_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
