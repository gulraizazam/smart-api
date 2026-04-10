<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Models\Discount;
use App\Services\Discount\DiscountService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The `discounts` table is overloaded: it stores BOTH regular treatment
 * discounts AND vouchers (discount_type = 'voucher'). The voucher type
 * service writes to the same table with a different tag, and the
 * regular discount screens have to hide voucher rows so a "Summer Sale"
 * promo doesn't pollute the voucher-only UI and vice versa.
 *
 * Two scopes enforce this separation:
 *
 *   1. `scopeExcludeVouchers()` — removes rows where discount_type
 *      equals 'voucher'. DiscountService's datatable query hangs off
 *      this scope, so a voucher row leaking into the discount index
 *      would surface as a visible bug.
 *
 *   2. `scopeCurrentlyValid()` — returns only rows within their
 *      start..end validity window. The audit called out that stale
 *      (expired) promos occasionally resurface on widget lists because
 *      the reporting queries forgot this filter. Pin it here.
 *
 *   3. `scopeActive()` — filters `active = 1` rows. The combination
 *      Active + CurrentlyValid + ExcludeVouchers is what the public
 *      discount listing hangs off; each scope is tested individually
 *      AND in combination so a refactor that accidentally AND's the
 *      wrong conditions together surfaces here.
 */
class DiscountExclusivityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_exclude_vouchers_scope_filters_out_discount_type_voucher_rows(): void
    {
        $treatment = Discount::factory()->create([
            'name' => 'Treatment Promo',
            'discount_type' => 'Treatment',
        ]);
        $voucher = Discount::factory()->create([
            'name' => 'Gift Voucher',
            'discount_type' => 'voucher',
        ]);

        $rows = Discount::query()->excludeVouchers()->get();

        $this->assertTrue($rows->contains('id', $treatment->id));
        $this->assertFalse($rows->contains('id', $voucher->id));
    }

    public function test_active_scope_filters_out_deactivated_discounts(): void
    {
        $live = Discount::factory()->create(['active' => 1]);
        $inactive = Discount::factory()->create(['active' => 0]);

        $rows = Discount::query()->active()->get();

        $this->assertTrue($rows->contains('id', $live->id));
        $this->assertFalse($rows->contains('id', $inactive->id));
    }

    public function test_currently_valid_scope_excludes_past_and_future_discounts(): void
    {
        $current = Discount::factory()->create([
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ]);
        $past = Discount::factory()->create([
            'start' => now()->subMonths(2),
            'end' => now()->subMonth(),
        ]);
        $future = Discount::factory()->create([
            'start' => now()->addMonth(),
            'end' => now()->addMonths(2),
        ]);

        $rows = Discount::query()->currentlyValid()->get();

        $this->assertTrue($rows->contains('id', $current->id));
        $this->assertFalse($rows->contains('id', $past->id));
        $this->assertFalse(
            $rows->contains('id', $future->id),
            'A discount whose start date is still in the future must not be "currently valid".',
        );
    }

    public function test_for_account_scope_pins_discount_visibility_to_the_calling_tenant(): void
    {
        // Seed a second tenant row first. Note: going through Eloquent
        // triggers BaseModel's GuardsTenantBoundary hook, which forces
        // account_id back to the authenticated user's tenant (1) on
        // create — a deliberate defense against cross-tenant writes.
        // Tests need to write the raw row directly to simulate a
        // "real" second tenant existing in the same DB.
        $theirsId = \DB::table('discounts')->insertGetId([
            'name' => 'Other Tenant Promo',
            'slug' => 'other-tenant-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 100,
            'discount_type' => 'Treatment',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ours = Discount::factory()->create();

        $rows = Discount::query()->forAccount(1)->get();

        $this->assertTrue($rows->contains('id', $ours->id));
        $this->assertFalse(
            $rows->contains('id', $theirsId),
            'A discount scoped to another tenant must NOT leak into the current tenant query.',
        );
    }

    public function test_get_active_discounts_service_combines_account_valid_and_active_filters(): void
    {
        // Ship every edge case and assert only the one "healthy" row
        // comes back — the combination is load-bearing for the public
        // discount list on the booking UI.
        $service = app(DiscountService::class);

        $healthy = Discount::factory()->create([
            'active' => 1,
            'start' => now()->subDay(),
            'end' => now()->addDay(),
            'discount_type' => 'Treatment',
        ]);
        Discount::factory()->create([
            'active' => 0, // inactive → filtered
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ]);
        // Wrong tenant row — bypass the Eloquent creating hook that
        // forces account_id back to the authenticated user's tenant.
        \DB::table('discounts')->insert([
            'name' => 'Other Tenant Discount',
            'slug' => 'other-tenant-active-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 50,
            'discount_type' => 'Treatment',
            'start' => now()->subDay(),
            'end' => now()->addDay(),
            'active' => 1,
            'account_id' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Discount::factory()->create([
            'active' => 1,
            'start' => now()->subMonths(2), // expired → filtered
            'end' => now()->subMonth(),
        ]);

        $rows = $service->getActiveDiscounts(1);

        $this->assertSame(1, $rows->count());
        $this->assertSame($healthy->id, $rows->first()->id);
    }

    public function test_voucher_discount_does_not_appear_in_the_report_active_list_for_the_wrong_tenant(): void
    {
        // getActiveDiscountsForReport drops the currently-valid filter
        // (reports span historical periods) but still enforces tenant.
        $service = app(DiscountService::class);

        $ours = Discount::factory()->create(['active' => 1]);

        // Bypass Eloquent tenant-boundary guard for the foreign tenant
        // row.
        \DB::table('discounts')->insert([
            'name' => 'Foreign Active Promo',
            'slug' => 'foreign-active-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 75,
            'discount_type' => 'Treatment',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = $service->getActiveDiscountsForReport(1);

        $this->assertTrue($rows->contains('id', $ours->id));
        $this->assertSame(1, $rows->count());
    }

    public function test_has_children_returns_true_when_allocations_exist(): void
    {
        // `hasChildren` is the guard DiscountService::deleteDiscount
        // relies on to refuse deleting a discount that's still in use.
        $discount = Discount::factory()->create();
        $this->assertFalse($discount->hasChildren());

        \DB::table('discount_has_locations')->insert([
            'discount_id' => $discount->id,
            'location_id' => 1,
            'service_id' => 1,
            'type' => 'Fixed',
            'amount' => 100,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            $discount->fresh()->hasChildren(),
            'A discount with an allocation row must report hasChildren() = true so delete is blocked.',
        );
    }
}
