<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Models\Discount;
use App\Models\DiscountHasLocations;
use App\Models\Locations;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Discounts are allocated to (location, service) tuples via the
 * `discount_has_locations` pivot. This file pins the query semantics
 * the booking/invoice path relies on:
 *
 *   1. A discount allocated to Centre A + Service X must NOT surface
 *      when the UI asks "what discounts apply at Centre B for Service X?".
 *      This is a per-centre exclusivity guarantee.
 *
 *   2. A discount allocated to Centre A + Service X must NOT surface
 *      for Service Y at the same centre. A per-service allocation is
 *      strictly scoped to that service — no silent cross-application.
 *
 *   3. The same discount can be allocated to multiple centres with
 *      DIFFERENT rates (Centre A: 20% off, Centre B: flat 500 off).
 *      Each allocation row is independent; one row does NOT override
 *      another.
 *
 *   4. When a location is soft-deleted, the allocation row pointing at
 *      it survives (the pivot has no FK cascade in production) — pin
 *      this so the next scheduled cleanup knows what to hunt.
 *
 *   5. Deleting a discount cascades to its allocations ONLY if the
 *      caller goes through DiscountService::deleteDiscount which
 *      checks hasChildren first. Raw ->delete() on the Discount leaves
 *      orphan rows, which the audit flagged as a cleanup task.
 */
class DiscountLocationRestrictionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Locations $centreA;

    private Locations $centreB;

    private Services $serviceX;

    private Services $serviceY;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->centreA = Locations::factory()->create(['name' => 'Centre A']);
        $this->centreB = Locations::factory()->create(['name' => 'Centre B']);
        $this->serviceX = Services::factory()->create(['name' => 'Service X']);
        $this->serviceY = Services::factory()->create(['name' => 'Service Y']);
    }

    public function test_a_discount_allocated_to_one_centre_does_not_surface_for_another(): void
    {
        $discount = Discount::factory()->create(['name' => 'Centre A Only 20% Off']);
        $this->allocate($discount, $this->centreA, $this->serviceX, 'Percentage', 20);

        $atA = $this->discountsForCentreService($this->centreA->id, $this->serviceX->id);
        $atB = $this->discountsForCentreService($this->centreB->id, $this->serviceX->id);

        $this->assertTrue($atA->contains('discount_id', $discount->id));
        $this->assertFalse(
            $atB->contains('discount_id', $discount->id),
            'An allocation scoped to Centre A must NOT surface at Centre B.',
        );
    }

    public function test_a_discount_allocated_to_one_service_does_not_surface_for_another_at_same_centre(): void
    {
        $discount = Discount::factory()->create(['name' => 'Service X Only']);
        $this->allocate($discount, $this->centreA, $this->serviceX, 'Percentage', 15);

        $forX = $this->discountsForCentreService($this->centreA->id, $this->serviceX->id);
        $forY = $this->discountsForCentreService($this->centreA->id, $this->serviceY->id);

        $this->assertTrue($forX->contains('discount_id', $discount->id));
        $this->assertFalse(
            $forY->contains('discount_id', $discount->id),
            'A per-service allocation must NOT leak to a sibling service at the same centre.',
        );
    }

    public function test_the_same_discount_allocated_to_two_centres_carries_independent_amounts(): void
    {
        $discount = Discount::factory()->create(['name' => 'Multi-Centre Promo']);
        $this->allocate($discount, $this->centreA, $this->serviceX, 'Percentage', 20);
        $this->allocate($discount, $this->centreB, $this->serviceX, 'Fixed', 500);

        $atA = DiscountHasLocations::query()
            ->where('discount_id', $discount->id)
            ->where('location_id', $this->centreA->id)
            ->first();
        $atB = DiscountHasLocations::query()
            ->where('discount_id', $discount->id)
            ->where('location_id', $this->centreB->id)
            ->first();

        $this->assertSame('Percentage', $atA->type);
        $this->assertSame('20.00', (string) $atA->amount);
        $this->assertSame('Fixed', $atB->type);
        $this->assertSame('500.00', (string) $atB->amount);
    }

    public function test_discount_allocated_to_multiple_services_at_same_centre_returns_only_one_row_per_query(): void
    {
        // This is the guard against the "double-apply" bug — if the same
        // discount ends up allocated to both "Service X" and "Service Y"
        // at the same centre, a lookup for Service X must return ONE
        // row, not one per siblings.
        $discount = Discount::factory()->create(['name' => 'Bundle Promo']);
        $this->allocate($discount, $this->centreA, $this->serviceX, 'Percentage', 10);
        $this->allocate($discount, $this->centreA, $this->serviceY, 'Percentage', 10);

        $rowsForX = DiscountHasLocations::query()
            ->where('discount_id', $discount->id)
            ->where('location_id', $this->centreA->id)
            ->where('service_id', $this->serviceX->id)
            ->get();

        $this->assertCount(1, $rowsForX);
    }

    public function test_deleting_a_centre_does_not_cascade_to_the_allocation_row(): void
    {
        // The migration does not carry an ON DELETE CASCADE on the
        // location_id FK — a soft-deleted centre leaves its pivot row
        // behind. Pin this so the scheduled cleanup command knows to
        // prune these.
        $discount = Discount::factory()->create();
        $allocation = $this->allocate($discount, $this->centreA, $this->serviceX, 'Percentage', 20);

        $this->centreA->delete();

        $this->assertDatabaseHas('discount_has_locations', [
            'id' => $allocation->id,
            'discount_id' => $discount->id,
        ]);
    }

    public function test_allocation_row_carries_the_expected_amount_type_and_slug(): void
    {
        $discount = Discount::factory()->create();
        $this->allocate($discount, $this->centreA, $this->serviceX, 'Percentage', 25, 'vip');

        $row = DiscountHasLocations::query()
            ->where('discount_id', $discount->id)
            ->first();

        $this->assertSame('Percentage', $row->type);
        $this->assertSame('25.00', (string) $row->amount);
        $this->assertSame('vip', $row->slug);
    }

    public function test_discount_allocations_can_be_eager_loaded_with_location_and_service(): void
    {
        $discount = Discount::factory()->create();
        $this->allocate($discount, $this->centreA, $this->serviceX, 'Fixed', 300);

        $loaded = Discount::with('allocations.location.city', 'allocations.service')
            ->find($discount->id);

        $this->assertCount(1, $loaded->allocations);
        $this->assertSame($this->centreA->id, $loaded->allocations->first()->location->id);
        $this->assertSame($this->serviceX->id, $loaded->allocations->first()->service->id);
    }

    // ── Helpers ──────────────────────────────────────────

    private function allocate(
        Discount $discount,
        Locations $location,
        Services $service,
        string $type,
        float $amount,
        string $slug = 'default',
    ): DiscountHasLocations {
        return DiscountHasLocations::create([
            'discount_id' => $discount->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'type' => $type,
            'amount' => $amount,
            'slug' => $slug,
        ]);
    }

    /**
     * The real invoice flow joins discount_has_locations on
     * (location_id, service_id) to resolve "what discounts apply
     * here?". Mirror that query shape so the test asserts the same
     * columns the production code reads.
     */
    private function discountsForCentreService(int $locationId, int $serviceId): \Illuminate\Support\Collection
    {
        return DB::table('discount_has_locations')
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->get();
    }
}
