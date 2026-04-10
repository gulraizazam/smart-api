<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\VendorTransactionType;
use PHPUnit\Framework\TestCase;

/**
 * VendorTransactionType tags every row in `vendor_transactions` as
 * either a PURCHASE (we owe the vendor) or a PAYMENT (we paid the
 * vendor). The vendor-ledger SQL SUMs these with a sign flip by type;
 * a third case would silently zero out balances.
 */
class VendorTransactionTypeTest extends TestCase
{
    public function test_enum_has_exactly_two_cases(): void
    {
        // Binary classification — purchase vs payment. If a third
        // case is added, the ledger aggregate needs a full rewrite.
        $this->assertCount(2, VendorTransactionType::cases());
    }

    public function test_case_values_are_the_canonical_lowercase_slugs(): void
    {
        $this->assertSame('purchase', VendorTransactionType::Purchase->value);
        $this->assertSame('payment', VendorTransactionType::Payment->value);
    }

    public function test_from_parses_a_valid_slug_into_the_correct_case(): void
    {
        $this->assertSame(VendorTransactionType::Purchase, VendorTransactionType::from('purchase'));
        $this->assertSame(VendorTransactionType::Payment, VendorTransactionType::from('payment'));
    }

    public function test_try_from_returns_null_for_an_unknown_slug(): void
    {
        $this->assertNull(VendorTransactionType::tryFrom('refund'));
    }

    public function test_label_returns_title_cased_display_string(): void
    {
        $this->assertSame('Purchase', VendorTransactionType::Purchase->label());
        $this->assertSame('Payment', VendorTransactionType::Payment->label());
    }
}
