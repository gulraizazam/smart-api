<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanDiscountService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Server-side price authority on the "add service to plan" staging path
 * (PlanDiscountService::saveServiceForPlan, simple-discount branch).
 *
 * The 2026-06-10 audit found the server stored the client-supplied
 * net_amount verbatim — so a buggy SPA build (a swallowed catalog lookup
 * stages 0 → the realistic undercharge) or a crafted payload could persist
 * an arbitrary sold price. Business rules confirmed by the product owner:
 *   - a line's sold price is NEVER above the catalog/regular price, and
 *   - a 0 price is only valid with a discount applied.
 *
 * These tests pin the backstop. `service.price` (pre-tax catalog) and
 * `net_amount` (pre-tax sold) share a basis, so the cap is apples-to-apples.
 * The configurable Buy/Get branch derives every amount from the catalog and
 * is covered separately (ConfigurableSaveServiceTest).
 */
class PlanPriceAuthorityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanDiscountService $discountService;

    private int $locationId;

    private int $serviceId;

    private const CATALOG_PRICE = 10000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->discountService = app(PlanDiscountService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Price Authority Centre', 'city_id' => 1, 'region_id' => 1,
            'address' => '1 Test St', 'tax_percentage' => 0, 'account_id' => 1,
            'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Laser Session', 'price' => self::CATALOG_PRICE, 'end_node' => 1,
            'tax_treatment_type_id' => 1, 'account_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function save(array $overrides): array
    {
        return $this->discountService->saveServiceForPlan(array_merge([
            'service_id' => $this->serviceId,
            'location_id' => $this->locationId,
            'random_id' => 'PA-'.uniqid(),
            'package_total' => '0',
            'is_exclusive' => 0,
        ], $overrides));
    }

    private function makeFixedDiscount(int $amount): int
    {
        return (int) DB::table('discounts')->insertGetId([
            'name' => 'Fixed '.$amount, 'slug' => 'fx-'.uniqid(), 'type' => 'Fixed',
            'amount' => $amount, 'discount_type' => 'Treatment', 'active' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ---------------- rejections (the bug) ---------------- */

    public function test_rejects_sold_price_above_catalog(): void
    {
        $result = $this->save(['net_amount' => self::CATALOG_PRICE + 5000]);

        $this->assertFalse($result['success'], 'A sold price above catalog must be rejected.');
        $this->assertSame(0, DB::table('package_bundles')->count(),
            'A rejected above-catalog price must not persist a row.');
    }

    public function test_rejects_zero_price_without_a_discount(): void
    {
        // The realistic undercharge: a swallowed catalog lookup stages 0
        // with no discount selected.
        $result = $this->save(['net_amount' => 0]);

        $this->assertFalse($result['success'], 'A zero price with no discount must be rejected.');
        $this->assertSame(0, DB::table('package_bundles')->count());
    }

    public function test_rejects_negative_price(): void
    {
        $result = $this->save(['net_amount' => -100]);

        $this->assertFalse($result['success'], 'A negative price must be rejected.');
        $this->assertSame(0, DB::table('package_bundles')->count());
    }

    /* ---------------- positive controls (legit saves still pass) ---------------- */

    public function test_allows_discounted_price_below_catalog(): void
    {
        $discountId = $this->makeFixedDiscount(2000);

        $result = $this->save([
            'net_amount' => self::CATALOG_PRICE - 2000, // 8000, a real discount
            'discount_id' => $discountId,
        ]);

        $this->assertTrue($result['success'], 'A legit discounted (below-catalog) price must be accepted.');
        $this->assertSame(1, DB::table('package_bundles')->count());
        $this->assertEqualsWithDelta(
            8000.0,
            (float) DB::table('package_services')->value('price'),
            0.5,
            'The accepted discounted price must persist as-sent.',
        );
    }

    public function test_allows_full_catalog_price_without_discount(): void
    {
        $result = $this->save(['net_amount' => self::CATALOG_PRICE]);

        $this->assertTrue($result['success'], 'A full catalog-price line (no discount) must be accepted.');
        $this->assertSame(1, DB::table('package_bundles')->count());
    }

    public function test_allows_zero_price_when_a_discount_is_applied(): void
    {
        // A full (>= catalog) fixed discount legitimately zeroes the line.
        $discountId = $this->makeFixedDiscount(self::CATALOG_PRICE);

        $result = $this->save([
            'net_amount' => 0,
            'discount_id' => $discountId,
        ]);

        $this->assertTrue($result['success'], 'A 0 price WITH a discount applied must be accepted.');
        $this->assertSame(1, DB::table('package_bundles')->count());
    }
}
