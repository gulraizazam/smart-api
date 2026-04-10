<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\Discount;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Services\Membership\MembershipService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Memberships exist to unlock tier-specific discounts. Two schema
 * paths connect the two tables:
 *
 *   1. `discounts.customer_type_id` — FK to membership_types.id.
 *      A discount can be scoped to a single tier ("Gold Tier members
 *      get 20% off all services"). The MembershipService's
 *      buildServiceUsageData walks this column to list what a
 *      patient has consumed under their tier.
 *
 *   2. `membership_type_has_discounts` — many-to-many pivot. A tier
 *      can attach multiple discounts (loyalty + seasonal + referral)
 *      and a discount can be re-used across tiers (a flat "staff
 *      discount" that applies to both Gold and Platinum).
 *
 * This test pins both paths end-to-end: a discount linked to a tier
 * is reachable from a patient's active membership, and a patient
 * with no active membership sees no tier-scoped discounts.
 */
class MembershipDiscountApplicationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Patients $patient;

    private MembershipType $tier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->patient = Patients::factory()->create();
        $this->tier = MembershipType::factory()->create([
            'name' => 'Gold Tier',
            'period' => 12,
            'active' => 1,
        ]);
    }

    public function test_discount_can_be_scoped_to_a_single_membership_tier_via_customer_type_id(): void
    {
        $discount = Discount::factory()->create([
            'name' => 'Gold Members 20% Off',
            'type' => 'Percentage',
            'amount' => 20,
            'customer_type_id' => $this->tier->id,
        ]);

        $this->assertSame($this->tier->id, (int) $discount->customer_type_id);

        // The reverse walk: find all discounts scoped to this tier.
        $tierDiscounts = Discount::query()
            ->where('customer_type_id', $this->tier->id)
            ->get();

        $this->assertCount(1, $tierDiscounts);
        $this->assertSame($discount->id, $tierDiscounts->first()->id);
    }

    public function test_membership_type_has_discounts_pivot_attaches_many_discounts_to_one_tier(): void
    {
        $loyalty = Discount::factory()->create(['name' => 'Loyalty 5% Off']);
        $seasonal = Discount::factory()->create(['name' => 'Seasonal 10% Off']);

        $this->tier->discounts()->sync([
            $loyalty->id,
            $seasonal->id,
        ]);

        $attached = $this->tier->discounts()->get();
        $this->assertCount(2, $attached);
        $this->assertTrue($attached->contains('id', $loyalty->id));
        $this->assertTrue($attached->contains('id', $seasonal->id));
    }

    public function test_the_same_discount_can_attach_to_multiple_tiers(): void
    {
        // "Staff discount" applies to Gold AND Platinum tiers — pin
        // the many-to-many shape so a refactor that tightens it to a
        // one-to-many surfaces as a schema break.
        $platinum = MembershipType::factory()->create(['name' => 'Platinum Tier']);
        $staffDiscount = Discount::factory()->create(['name' => 'Staff 15% Off']);

        DB::table('membership_type_has_discounts')->insert([
            [
                'membership_type_id' => $this->tier->id,
                'discount_id' => $staffDiscount->id,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'membership_type_id' => $platinum->id,
                'discount_id' => $staffDiscount->id,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $goldAttached = $this->tier->discounts()->where('discounts.id', $staffDiscount->id)->exists();
        $platinumAttached = $platinum->discounts()->where('discounts.id', $staffDiscount->id)->exists();

        $this->assertTrue($goldAttached);
        $this->assertTrue($platinumAttached);
    }

    public function test_patient_with_active_gold_membership_resolves_tier_scoped_discounts(): void
    {
        // End-to-end: patient → active membership → tier → discounts.
        // This is the walk the discount engine performs before
        // displaying which discounts a patient qualifies for.
        $service = app(MembershipService::class);

        Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(11),
            'active' => 1,
        ]);

        $goldDiscount = Discount::factory()->create([
            'name' => 'Gold Only 25%',
            'type' => 'Percentage',
            'amount' => 25,
            'customer_type_id' => $this->tier->id,
        ]);

        Cache::flush();
        $activeMembership = $service->getPatientActiveMembership($this->patient->id);
        $this->assertNotNull($activeMembership);
        $this->assertSame($this->tier->id, $activeMembership->membership_type_id);

        $applicable = Discount::query()
            ->where('customer_type_id', $activeMembership->membership_type_id)
            ->get();

        $this->assertCount(1, $applicable);
        $this->assertSame($goldDiscount->id, $applicable->first()->id);
    }

    public function test_patient_without_active_membership_sees_no_tier_scoped_discounts(): void
    {
        $service = app(MembershipService::class);

        Discount::factory()->create([
            'customer_type_id' => $this->tier->id,
        ]);

        Cache::flush();
        $active = $service->getPatientActiveMembership($this->patient->id);

        $this->assertNull($active);
    }

    public function test_expired_membership_falls_off_the_tier_discount_walk(): void
    {
        // A membership whose date range has ended must not leak tier
        // discounts to the patient — this is the "free services for
        // expired memberships" failure mode the audit flagged.
        $service = app(MembershipService::class);

        Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subMonth(),
            'active' => 1,
        ]);

        Discount::factory()->create([
            'customer_type_id' => $this->tier->id,
        ]);

        Cache::flush();
        $active = $service->getPatientActiveMembership($this->patient->id);

        $this->assertNull(
            $active,
            'An expired membership must not resolve as the "active" membership — '
            . 'otherwise tier-scoped discounts leak to expired patients.',
        );
    }

    public function test_deactivating_a_membership_type_cascades_to_discount_visibility(): void
    {
        // Pin the current state: an INACTIVE tier row still holds
        // its pivot discounts (the rows stay in place), but the
        // discount engine's "active tiers only" walk filters it out.
        $loyalty = Discount::factory()->create(['name' => 'Loyalty Tier Discount']);
        $this->tier->discounts()->sync([$loyalty->id]);

        $this->tier->update(['active' => 0]);

        // Pivot rows survive deactivation.
        $this->assertSame(1, DB::table('membership_type_has_discounts')
            ->where('membership_type_id', $this->tier->id)
            ->count());

        // But the "active tiers" query no longer picks the tier up.
        $activeTiers = MembershipType::query()->active()->get();
        $this->assertFalse($activeTiers->contains('id', $this->tier->id));
    }
}
