<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * sanitize_money() is the central money parser used across the financial
 * spine. It replaces FILTER_SANITIZE_NUMBER_INT, which silently destroyed
 * decimal precision by stripping the dot — the audit found this caused
 * several PKR-amount truncation bugs (e.g., 1234.56 → 123456).
 *
 * Test directly against the global function (no Laravel boot) to keep
 * the test fast and isolate the contract: any input → safe float, with
 * decimals preserved.
 */
class SanitizeMoneyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Pull in the helper file directly so we don't need to boot Laravel.
        require_once __DIR__ . '/../../../app/Helpers/helper.php';
    }

    public static function moneyCases(): array
    {
        return [
            'integer string'            => ['1000', 1000.0],
            'decimal string'            => ['1234.56', 1234.56],
            'comma thousands separator' => ['1,000.50', 1000.50],
            'multiple commas'           => ['1,234,567.89', 1234567.89],
            'leading zero decimal'      => ['0.001', 0.001],
            'whole number with comma'   => ['1,000', 1000.0],
            'negative integer'          => ['-100', -100.0],
            'negative decimal'          => ['-1,234.56', -1234.56],
            'currency prefix'           => ['PKR 5000', 5000.0],
            'currency symbol'           => ['$1,500.00', 1500.0],
            'whitespace padding'        => ['  2500.50  ', 2500.50],
            'numeric int input'         => [2500, 2500.0],
            'numeric float input'       => [2500.75, 2500.75],
            'empty string'              => ['', 0.0],
            'pure alpha string'         => ['abc', 0.0],
            'mixed alpha numeric'       => ['1a2b3', 123.0],
            'zero'                      => ['0', 0.0],
            'zero decimal'              => ['0.00', 0.0],
        ];
    }

    #[DataProvider('moneyCases')]
    public function test_sanitize_money_normalises_inputs(mixed $input, float $expected): void
    {
        $this->assertSame($expected, sanitize_money($input));
    }

    public function test_sanitize_money_preserves_decimal_precision_against_filter_sanitize_number_int_regression(): void
    {
        // The original bug: FILTER_SANITIZE_NUMBER_INT('1234.56') returned
        // '123456' which then cast to 123456.0 — a 100x error in PKR amounts.
        $this->assertNotSame(123456.0, sanitize_money('1234.56'));
        $this->assertSame(1234.56, sanitize_money('1234.56'));
    }

    public function test_sanitize_money_handles_null_input(): void
    {
        $this->assertSame(0.0, sanitize_money(null));
    }
}
