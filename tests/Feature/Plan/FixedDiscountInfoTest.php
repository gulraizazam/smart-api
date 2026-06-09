<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanDiscountService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Simple (auto-apply) Fixed discount allocation on a plan.
 *
 * Reproduces the user's bug: a Fixed Rs.1000 allocation threw "limit exceed"
 * when applied on a plan. Root cause was the allocation being tagged
 * slug='custom', which routed it through the operator-entered, percentage-
 * oriented validation that rejected fixed rupee amounts. Simple allocations
 * are slug='default' and auto-apply their pivot amount via
 * getDiscountInfoForPlan, capped at the service price (net 0 when the amount
 * exceeds the price) — never an error.
 */
class FixedDiscountInfoTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanDiscountService $discountService;

    private int $patientId;

    private int $locationId;

    private int $serviceId;

    private float $servicePrice = 5000.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->discountService = app(PlanDiscountService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Test Centre',
            'city_id' => 1,
            'region_id' => 1,
            'address' => '1 Test St',
            'tax_percentage' => 0,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Fixed Patient',
            'email' => 'fixed-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000060',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Service',
            'price' => $this->servicePrice,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Create a simple Fixed discount + a default allocation for the given amount. */
    private function seedFixedAllocation(float $amount): int
    {
        $discountId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Fixed Discount '.uniqid(),
            'slug' => 'fixed-'.uniqid(),
            'type' => 'Fixed',
            'amount' => $amount,
            'discount_type' => 'Treatment',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discount_has_locations')->insert([
            'discount_id' => $discountId,
            'location_id' => $this->locationId,
            'service_id' => $this->serviceId,
            'type' => 'Fixed',
            'amount' => $amount,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $discountId;
    }

    public function test_fixed_amount_below_price_applies_and_does_not_error(): void
    {
        $discountId = $this->seedFixedAllocation(amount: 1000);

        $info = $this->discountService->getDiscountInfoForPlan([
            'service_id' => $this->serviceId,
            'discount_id' => $discountId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
        ]);

        $this->assertTrue($info['success']);
        // Auto-apply, no operator value-entry form ("limit exceed" path).
        $this->assertSame(0, (int) $info['data']['custom_checked']);
        $this->assertSame(1000.0, (float) $info['data']['discount_price']);
        $this->assertSame(4000.0, (float) $info['data']['net_amount']);
    }

    public function test_fixed_amount_above_price_caps_at_price_making_service_free(): void
    {
        // The user's exact scenario: a Fixed amount larger than the service
        // price. Must make the service free (net 0), NOT throw "limit exceed".
        $discountId = $this->seedFixedAllocation(amount: 8000);

        $info = $this->discountService->getDiscountInfoForPlan([
            'service_id' => $this->serviceId,
            'discount_id' => $discountId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
        ]);

        $this->assertTrue($info['success']);
        $this->assertSame(0, (int) $info['data']['custom_checked']);
        $this->assertSame(
            $this->servicePrice,
            (float) $info['data']['discount_price'],
            'A fixed amount above the price must cap the discount at the service price.',
        );
        $this->assertSame(
            0.0,
            (float) $info['data']['net_amount'],
            'With the discount capped at the price, the service is free (net 0).',
        );
    }
}
