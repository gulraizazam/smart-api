<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Models\Discount;
use App\Models\DiscountHasLocations;
use App\Models\Locations;
use App\Models\Services;
use App\Services\Discount\DiscountService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * DiscountService::saveAllocations is the write path that scopes a
 * discount to (location, service) tuples. It's the gate between "this
 * discount exists in the catalogue" and "the front-desk UI should offer
 * it at this centre for this service". The audit called out several
 * subtleties that these tests pin:
 *
 *   1. Duplicate guard — saving the same (discount, location, service)
 *      twice must be a no-op (returns an error, not an insert). Without
 *      this guard a double-click would silently stack allocations and
 *      the discount's display count would inflate.
 *
 *   2. "All Services" sentinel row — when the caller picks the "All
 *      Services" row for a location, any previously-saved individual
 *      service allocations at that same location are removed. Pin this
 *      so a refactor can't quietly drop the cleanup step, leaving
 *      orphaned per-service rows that silently win over the "all" row.
 *
 *   3. Parent/child service conflict — if a user adds a child service
 *      while its parent category is already allocated, the save must
 *      refuse with a descriptive error. This prevents double-booking
 *      a service through both a generic bucket and a specific row.
 *
 *   4. Delete path — single and bulk deletes work.
 */
class DiscountAllocationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private DiscountService $service;

    private Discount $discount;

    private Locations $location;

    private Services $serviceA;

    private Services $serviceB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(DiscountService::class);
        $this->discount = Discount::factory()->create([
            'name' => 'Summer 2026 Promo',
            'discount_type' => 'Treatment',
        ]);
        $this->location = Locations::factory()->create([
            'name' => 'Downtown Clinic',
        ]);
        $this->serviceA = Services::factory()->create(['name' => 'Laser Service A']);
        $this->serviceB = Services::factory()->create(['name' => 'Laser Service B']);
    }

    public function test_save_allocations_writes_a_row_per_selected_service(): void
    {
        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$this->serviceA->id, $this->serviceB->id],
            'allocation_type' => 'Percentage',
            'allocation_amount' => 15,
            'allocation_slug' => 'default',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['records']);

        $this->assertDatabaseHas('discount_has_locations', [
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
            'type' => 'Percentage',
        ]);
        $this->assertDatabaseHas('discount_has_locations', [
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceB->id,
        ]);
    }

    public function test_save_allocations_skips_duplicates_and_reports_them(): void
    {
        // Seed an allocation that already covers service A.
        DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
            'type' => 'Fixed',
            'amount' => 100,
            'slug' => 'default',
        ]);

        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            // A is a duplicate; B is new.
            'service_ids' => [$this->serviceA->id, $this->serviceB->id],
            'allocation_type' => 'Fixed',
            'allocation_amount' => 200,
            'allocation_slug' => 'default',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['records']);
        $this->assertStringContainsString('duplicate(s) skipped', $result['message']);

        // The original allocation is untouched — type stayed 'Fixed' with
        // amount 100, NOT overwritten by the second save.
        $this->assertSame(1, DiscountHasLocations::query()
            ->where('discount_id', $this->discount->id)
            ->where('service_id', $this->serviceA->id)
            ->where('amount', 100)
            ->count());
    }

    public function test_save_allocations_refuses_when_every_service_is_a_duplicate(): void
    {
        DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
            'type' => 'Fixed',
            'amount' => 100,
            'slug' => 'default',
        ]);

        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$this->serviceA->id],
            'allocation_type' => 'Fixed',
            'allocation_amount' => 200,
            'allocation_slug' => 'default',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exist', $result['message']);
    }

    public function test_delete_allocation_removes_a_single_row(): void
    {
        $allocation = DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
            'type' => 'Percentage',
            'amount' => 10,
            'slug' => 'default',
        ]);

        $this->service->deleteAllocation($allocation->id);

        $this->assertDatabaseMissing('discount_has_locations', ['id' => $allocation->id]);
    }

    public function test_delete_allocation_group_removes_many_rows_at_once(): void
    {
        $first = DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
            'type' => 'Percentage',
            'amount' => 10,
            'slug' => 'default',
        ]);
        $second = DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceB->id,
            'type' => 'Percentage',
            'amount' => 10,
            'slug' => 'default',
        ]);

        $deleted = $this->service->deleteAllocationGroup([$first->id, $second->id]);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseMissing('discount_has_locations', ['id' => $first->id]);
        $this->assertDatabaseMissing('discount_has_locations', ['id' => $second->id]);
    }

    public function test_allocations_relationship_returns_only_rows_for_this_discount(): void
    {
        $otherDiscount = Discount::factory()->create();

        DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
            'type' => 'Fixed',
            'amount' => 50,
            'slug' => 'default',
        ]);
        DiscountHasLocations::create([
            'discount_id' => $otherDiscount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceB->id,
            'type' => 'Fixed',
            'amount' => 75,
            'slug' => 'default',
        ]);

        $this->assertSame(1, $this->discount->allocations()->count());
        $this->assertSame(
            $this->serviceA->id,
            (int) $this->discount->allocations()->first()->service_id,
        );
    }

    // ── Overlap / conflict guards ──────────────────────────────────────
    // saveAllocations must refuse allocations that would double-cover a
    // service through a broader bucket. These pin each guard so a refactor
    // can't silently let overlapping discounts through.

    public function test_blocks_any_allocation_once_all_centres_all_services_is_allocated(): void
    {
        // The catch-all ("All Centres" + "All Services") already covers
        // everything for this discount.
        $allCentres = Locations::factory()->create(['slug' => 'all', 'name' => 'All Centres']);
        $allServices = Services::factory()->create(['slug' => 'all', 'name' => 'All Services']);
        DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $allCentres->id,
            'service_id' => $allServices->id,
            'type' => 'Percentage', 'amount' => 10, 'slug' => 'default',
        ]);

        // Any further (centre, service) allocation must be refused.
        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$this->serviceA->id],
            'allocation_type' => 'Percentage', 'allocation_amount' => 20, 'allocation_slug' => 'default',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('All Centres', $result['message']);
        $this->assertStringContainsString('All Services', $result['message']);
        // Nothing extra was written — only the original catch-all row.
        $this->assertSame(1, DiscountHasLocations::where('discount_id', $this->discount->id)->count());
    }

    public function test_blocks_individual_service_when_all_services_already_allocated_at_location(): void
    {
        // "All Services" is allocated at this centre (but not the global
        // All-Centres catch-all, so the global guard doesn't pre-empt this).
        $allServices = Services::factory()->create(['slug' => 'all', 'name' => 'All Services']);
        DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $allServices->id,
            'type' => 'Percentage', 'amount' => 10, 'slug' => 'default',
        ]);

        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$this->serviceA->id],
            'allocation_type' => 'Percentage', 'allocation_amount' => 20, 'allocation_slug' => 'default',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('All Services', $result['message']);
        $this->assertStringContainsString('individual', $result['message']);
        // The individual service row was NOT created.
        $this->assertDatabaseMissing('discount_has_locations', [
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceA->id,
        ]);
    }

    public function test_allocating_all_services_removes_existing_individual_rows_at_that_location(): void
    {
        $allServices = Services::factory()->create(['slug' => 'all', 'name' => 'All Services']);
        // Two individual allocations already exist at the centre.
        foreach ([$this->serviceA->id, $this->serviceB->id] as $sid) {
            DiscountHasLocations::create([
                'discount_id' => $this->discount->id,
                'location_id' => $this->location->id,
                'service_id' => $sid,
                'type' => 'Fixed', 'amount' => 100, 'slug' => 'default',
            ]);
        }

        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$allServices->id],
            'allocation_type' => 'Percentage', 'allocation_amount' => 25, 'allocation_slug' => 'default',
        ]);

        $this->assertTrue($result['success']);
        // The two individual rows are gone; only the All-Services row remains
        // at this centre — no double-coverage.
        $rows = DiscountHasLocations::where('discount_id', $this->discount->id)
            ->where('location_id', $this->location->id)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame($allServices->id, (int) $rows->first()->service_id);
    }

    public function test_blocks_child_service_when_parent_category_already_allocated(): void
    {
        $parent = Services::factory()->create(['name' => 'Facials Category']);
        $child = Services::factory()->create(['name' => 'HydraFacial']);
        $child->parent_id = $parent->id; // direct set — bypasses fillable
        $child->save();

        // Parent category is already allocated at this centre.
        DiscountHasLocations::create([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_id' => $parent->id,
            'type' => 'Percentage', 'amount' => 10, 'slug' => 'default',
        ]);

        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$child->id],
            'allocation_type' => 'Percentage', 'allocation_amount' => 20, 'allocation_slug' => 'default',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Parent category', $result['message']);
        $this->assertStringContainsString('Facials Category', $result['message']);
        $this->assertDatabaseMissing('discount_has_locations', [
            'discount_id' => $this->discount->id,
            'service_id' => $child->id,
        ]);
    }

    public function test_drops_child_when_its_parent_is_selected_in_the_same_submission(): void
    {
        $parent = Services::factory()->create(['name' => 'Body Category']);
        $child = Services::factory()->create(['name' => 'CoolSculpt']);
        $child->parent_id = $parent->id;
        $child->save();

        // Operator picks BOTH the parent category and one of its children in
        // one go — the child is redundant (the parent already covers it).
        $result = $this->service->saveAllocations([
            'discount_id' => $this->discount->id,
            'location_id' => $this->location->id,
            'service_ids' => [$parent->id, $child->id],
            'allocation_type' => 'Percentage', 'allocation_amount' => 15, 'allocation_slug' => 'default',
        ]);

        $this->assertTrue($result['success']);
        $rows = DiscountHasLocations::where('discount_id', $this->discount->id)
            ->where('location_id', $this->location->id)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame($parent->id, (int) $rows->first()->service_id);
    }
}
