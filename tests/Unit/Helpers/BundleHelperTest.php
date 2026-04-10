<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\BundleHelper;
use PHPUnit\Framework\TestCase;

/**
 * BundleHelper::calculatePrices is the pricing rebalancer behind every
 * multi-service package bundle. It distributes a bundle's quoted price
 * across its component services proportionally so the reports can
 * attribute revenue per service without double-counting.
 *
 * The three branches under test:
 *
 *   1. `services_price == bundle_price` — no rebalance, calculated_price
 *      mirrors service_price. (The "nothing to do" fast path.)
 *
 *   2. `services_price > bundle_price` — services are DISCOUNTED toward
 *      the bundle price. A 1500-rupee service-list sold as a 1000-rupee
 *      bundle means each service takes a proportional haircut. Pin:
 *      the sum of calculated_prices must equal (within rounding) the
 *      bundle price, not the services_price.
 *
 *   3. `services_price < bundle_price` — services are MARKED UP to the
 *      bundle price. Rare but valid (a "premium bundle" of base
 *      services).
 *
 *   4. `services_price == 0` — safety exit. Dividing by zero would
 *      throw; the helper returns the input untouched.
 *
 * `isValidDateRange()` and `calculateTotalServicesPrice()` are covered
 * alongside because they're the other pure-logic surface of the helper.
 * Cache-backed methods (`getServices`, `getActiveBundles`, etc.) require
 * Auth + DB and are covered in feature tests.
 */
class BundleHelperTest extends TestCase
{
    // =========================================================================
    // calculatePrices — the three branches + the division-by-zero exit
    // =========================================================================

    public function test_calculate_prices_returns_input_untouched_when_services_price_is_zero(): void
    {
        // Division-by-zero guard: the helper must NOT touch the array
        // if services_price is 0. A caller that accidentally passes
        // an empty list gets its input back instead of a warning.
        $input = [
            ['service_price' => 500.0, 'calculated_price' => 0.0],
        ];

        $result = BundleHelper::calculatePrices($input, 0.0, 1000.0);

        $this->assertSame($input, $result);
    }

    public function test_calculate_prices_copies_service_price_to_calculated_price_when_equal(): void
    {
        // Fast path: no rebalance needed. calculated_price is just a
        // memcpy of service_price.
        $services = [
            ['service_price' => 300.0, 'calculated_price' => 0.0],
            ['service_price' => 700.0, 'calculated_price' => 0.0],
        ];

        $result = BundleHelper::calculatePrices($services, 1000.0, 1000.0);

        $this->assertSame(300.0, $result[0]['calculated_price']);
        $this->assertSame(700.0, $result[1]['calculated_price']);
    }

    public function test_calculate_prices_discounts_services_when_services_price_exceeds_bundle_price(): void
    {
        // Services total = 1500, bundle sold for 1000. Ratio = 1 - 1000/1500 = 0.3333...
        // Each service takes a 33.33% haircut.
        //   500 - 500*0.3333... = 333.33
        //  1000 - 1000*0.3333... = 666.67
        // Sum = 1000.00 (within rounding) — matches the bundle price.
        $services = [
            ['service_price' => 500.0, 'calculated_price' => 0.0],
            ['service_price' => 1000.0, 'calculated_price' => 0.0],
        ];

        $result = BundleHelper::calculatePrices($services, 1500.0, 1000.0);

        $this->assertSame(333.33, $result[0]['calculated_price']);
        $this->assertSame(666.67, $result[1]['calculated_price']);

        // The sum-to-bundle-price invariant (within a cent of rounding).
        $sum = $result[0]['calculated_price'] + $result[1]['calculated_price'];
        $this->assertEqualsWithDelta(1000.0, $sum, 0.01);
    }

    public function test_calculate_prices_marks_up_services_when_services_price_is_below_bundle_price(): void
    {
        // Services total = 1000, bundle sold for 1500. Ratio = -1 * (1 - 1500/1000) = 0.5
        // Each service takes a 50% markup.
        //  400 + 400*0.5 = 600
        //  600 + 600*0.5 = 900
        // Sum = 1500 — matches the bundle price.
        $services = [
            ['service_price' => 400.0, 'calculated_price' => 0.0],
            ['service_price' => 600.0, 'calculated_price' => 0.0],
        ];

        $result = BundleHelper::calculatePrices($services, 1000.0, 1500.0);

        $this->assertEqualsWithDelta(600.0, $result[0]['calculated_price'], 0.01);
        $this->assertEqualsWithDelta(900.0, $result[1]['calculated_price'], 0.01);

        $sum = $result[0]['calculated_price'] + $result[1]['calculated_price'];
        $this->assertEqualsWithDelta(1500.0, $sum, 0.01);
    }

    public function test_calculate_prices_handles_single_service_bundles(): void
    {
        // A "bundle" with one service is still a valid input — the
        // helper must not special-case the singleton path.
        $services = [['service_price' => 500.0, 'calculated_price' => 0.0]];

        $result = BundleHelper::calculatePrices($services, 500.0, 400.0);

        $this->assertEqualsWithDelta(400.0, $result[0]['calculated_price'], 0.01);
    }

    public function test_calculate_prices_preserves_service_price_column(): void
    {
        // The rebalance must ONLY overwrite calculated_price — the
        // original service_price must survive untouched so the report
        // can show "was X, now Y" deltas.
        $services = [
            ['service_price' => 500.0, 'calculated_price' => 0.0],
            ['service_price' => 500.0, 'calculated_price' => 0.0],
        ];

        $result = BundleHelper::calculatePrices($services, 1000.0, 800.0);

        $this->assertSame(500.0, $result[0]['service_price']);
        $this->assertSame(500.0, $result[1]['service_price']);
    }

    // =========================================================================
    // isValidDateRange — form-request guard for bundle start/end window
    // =========================================================================

    public function test_is_valid_date_range_accepts_null_start_and_end(): void
    {
        // Empty window = "always active" — a valid state.
        $this->assertTrue(BundleHelper::isValidDateRange(null, null));
    }

    public function test_is_valid_date_range_accepts_a_null_start_with_a_defined_end(): void
    {
        $this->assertTrue(BundleHelper::isValidDateRange(null, '2030-12-31'));
    }

    public function test_is_valid_date_range_accepts_a_defined_start_with_a_null_end(): void
    {
        $this->assertTrue(BundleHelper::isValidDateRange('2020-01-01', null));
    }

    public function test_is_valid_date_range_accepts_start_before_end(): void
    {
        $this->assertTrue(BundleHelper::isValidDateRange('2020-01-01', '2030-12-31'));
    }

    public function test_is_valid_date_range_accepts_same_start_and_end(): void
    {
        // A one-day promotion window is legal.
        $this->assertTrue(BundleHelper::isValidDateRange('2025-06-01', '2025-06-01'));
    }

    public function test_is_valid_date_range_rejects_start_after_end(): void
    {
        $this->assertFalse(BundleHelper::isValidDateRange('2030-01-01', '2020-01-01'));
    }

    // =========================================================================
    // calculateTotalServicesPrice — array_sum with float coercion
    // =========================================================================

    public function test_calculate_total_services_price_sums_an_array_of_floats(): void
    {
        $this->assertSame(
            1500.0,
            BundleHelper::calculateTotalServicesPrice([1, 2, 3], [500.0, 400.0, 600.0])
        );
    }

    public function test_calculate_total_services_price_coerces_numeric_strings_to_floats(): void
    {
        // MariaDB returns DECIMAL columns as strings. The helper must
        // accept that shape without a TypeError.
        $this->assertSame(
            1200.0,
            BundleHelper::calculateTotalServicesPrice([1, 2], ['500.50', '699.50'])
        );
    }

    public function test_calculate_total_services_price_returns_zero_for_an_empty_array(): void
    {
        $this->assertSame(0.0, BundleHelper::calculateTotalServicesPrice([], []));
    }
}
