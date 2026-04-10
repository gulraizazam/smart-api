<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\VendorTransactionStatus;
use PHPUnit\Framework\TestCase;

/**
 * VendorTransactionStatus marks a purchase transaction as either
 * ORDERED (goods not yet received — not eligible for inventory count)
 * or DELIVERED (goods received — inventory updated). The inventory
 * valuation report filters on this column.
 */
class VendorTransactionStatusTest extends TestCase
{
    public function test_enum_has_exactly_two_cases(): void
    {
        $this->assertCount(2, VendorTransactionStatus::cases());
    }

    public function test_case_values_are_the_canonical_lowercase_slugs(): void
    {
        $this->assertSame('ordered', VendorTransactionStatus::Ordered->value);
        $this->assertSame('delivered', VendorTransactionStatus::Delivered->value);
    }

    public function test_from_parses_a_valid_slug_into_the_correct_case(): void
    {
        $this->assertSame(VendorTransactionStatus::Ordered, VendorTransactionStatus::from('ordered'));
        $this->assertSame(VendorTransactionStatus::Delivered, VendorTransactionStatus::from('delivered'));
    }

    public function test_try_from_returns_null_for_an_unknown_slug(): void
    {
        $this->assertNull(VendorTransactionStatus::tryFrom('cancelled'));
    }

    public function test_label_returns_title_cased_display_string(): void
    {
        $this->assertSame('Ordered', VendorTransactionStatus::Ordered->label());
        $this->assertSame('Delivered', VendorTransactionStatus::Delivered->label());
    }
}
