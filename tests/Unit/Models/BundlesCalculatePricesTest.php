<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Bundles;
use PHPUnit\Framework\TestCase;

/**
 * Bundles::calculatePrices is the model-level pricing rebalancer used by
 * the plan-creation paths (PlanService, PlanDiscountService,
 * PackagesController). It overlaps with BundleHelper::calculatePrices but
 * adds a last-row absorption step so SUM(calculated_price) lands EXACTLY
 * on the bundle price after rounding.
 *
 * Regression history that pinned these tests:
 *   • Adding two identical Aqualyx sessions to a plan landed at
 *     [11,696.90, 8,298.10] instead of [9,997.50, 9,997.50]. Root cause
 *     was caller-side: `service_price` in the input array was being
 *     populated from `bundle_has_services.calculated_price` (the
 *     pre-distributed sold-share) instead of `service_price` (the
 *     catalog regular per-row). When the helper then re-divided the
 *     already-divided shares against the pre-divided total, the math
 *     skewed asymmetric.
 *
 * These tests pin the SAME-INPUT → SAME-OUTPUT invariant the helper has
 * always promised, so any future caller that regresses will fail here
 * loudly instead of silently shipping asymmetric prices.
 */
class BundlesCalculatePricesTest extends TestCase
{
    public function test_identical_services_split_symmetrically_under_discount(): void
    {
        // The exact case that regressed: 2× Aqualyx, regular 11,995 each
        // (total 23,990), bundle sold for 19,995. Both rows must land
        // at 9,997.50 — anything else is the bug.
        $services = [
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
        ];

        $result = Bundles::calculatePrices($services, 23990.0, 19995.0);

        $this->assertSame(9997.50, $result[0]['calculated_price']);
        $this->assertSame(9997.50, $result[1]['calculated_price']);
    }

    public function test_three_identical_services_split_symmetrically(): void
    {
        // Generalises the 2× case to N — symmetry must hold for any N.
        $services = [
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
        ];

        $result = Bundles::calculatePrices($services, 35985.0, 24000.0);

        $this->assertSame(8000.00, $result[0]['calculated_price']);
        $this->assertSame(8000.00, $result[1]['calculated_price']);
        $this->assertSame(8000.00, $result[2]['calculated_price']);
    }

    public function test_mixed_services_split_proportionally(): void
    {
        // Different catalog prices SHOULD land at different shares —
        // proportion is the whole point of the helper. The 11,995 row
        // takes ~80% of the sold price, the 3,000 row takes ~20%.
        $services = [
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 100],
            ['service_price' => 3000.0,  'calculated_price' => 0.0, 'service_id' => 200],
        ];

        $result = Bundles::calculatePrices($services, 14995.0, 12000.0);

        $this->assertEqualsWithDelta(9599.20, $result[0]['calculated_price'], 0.01);
        $this->assertEqualsWithDelta(2400.80, $result[1]['calculated_price'], 0.01);
    }

    public function test_no_discount_preserves_catalog_prices(): void
    {
        // services_price == bundle_price → fast path, calculated_price
        // mirrors service_price exactly.
        $services = [
            ['service_price' => 5000.0, 'calculated_price' => 0.0, 'service_id' => 1],
            ['service_price' => 5000.0, 'calculated_price' => 0.0, 'service_id' => 1],
        ];

        $result = Bundles::calculatePrices($services, 10000.0, 10000.0);

        $this->assertSame(5000.00, $result[0]['calculated_price']);
        $this->assertSame(5000.00, $result[1]['calculated_price']);
    }

    public function test_markup_splits_symmetrically_for_identical_services(): void
    {
        // Operator-override case: bundle sold ABOVE the catalog total
        // (a "premium" markup). Identical services must still split
        // identically.
        $services = [
            ['service_price' => 5000.0, 'calculated_price' => 0.0, 'service_id' => 1],
            ['service_price' => 5000.0, 'calculated_price' => 0.0, 'service_id' => 1],
        ];

        $result = Bundles::calculatePrices($services, 10000.0, 12000.0);

        $this->assertSame(6000.00, $result[0]['calculated_price']);
        $this->assertSame(6000.00, $result[1]['calculated_price']);
    }

    public function test_sum_lands_exactly_on_bundle_price_via_last_row_absorption(): void
    {
        // The model helper's distinguishing feature: after the
        // proportional split, the LAST row absorbs any rounding drift
        // so SUM(calculated_price) === bundle_price down to the cent.
        // Pick prices that produce a non-terminating decimal to exercise
        // the absorption logic.
        $services = [
            ['service_price' => 333.0, 'calculated_price' => 0.0, 'service_id' => 1],
            ['service_price' => 333.0, 'calculated_price' => 0.0, 'service_id' => 2],
            ['service_price' => 333.0, 'calculated_price' => 0.0, 'service_id' => 3],
        ];

        $result = Bundles::calculatePrices($services, 999.0, 777.77);

        $sum = array_sum(array_column($result, 'calculated_price'));
        $this->assertSame(777.77, round($sum, 2));
    }

    public function test_zero_services_price_returns_input_untouched(): void
    {
        // Division-by-zero guard. A degenerate input must not throw.
        $input = [['service_price' => 500.0, 'calculated_price' => 0.0, 'service_id' => 1]];

        $result = Bundles::calculatePrices($input, 0.0, 1000.0);

        $this->assertSame($input, $result);
    }

    public function test_string_inputs_with_commas_are_normalised(): void
    {
        // The model helper accepts string inputs (some legacy callers
        // pass formatted numbers like "23,990"). Must strip commas
        // before the math.
        $services = [
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
            ['service_price' => 11995.0, 'calculated_price' => 0.0, 'service_id' => 56],
        ];

        $result = Bundles::calculatePrices($services, '23,990', '19,995');

        $this->assertSame(9997.50, $result[0]['calculated_price']);
        $this->assertSame(9997.50, $result[1]['calculated_price']);
    }
}
