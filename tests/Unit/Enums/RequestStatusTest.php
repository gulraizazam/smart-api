<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\RequestStatus;
use PHPUnit\Framework\TestCase;

/**
 * RequestStatus gates the "vendor / category approval request" flow:
 * a branch user requests a new vendor, admin approves or dismisses.
 * The ternary (pending → approved | dismissed) is load-bearing for
 * the notification dispatcher; a fourth state would leave stranded
 * notifications.
 */
class RequestStatusTest extends TestCase
{
    public function test_enum_has_exactly_three_cases(): void
    {
        $this->assertCount(3, RequestStatus::cases());
    }

    public function test_case_values_are_the_canonical_lowercase_slugs(): void
    {
        $this->assertSame('pending', RequestStatus::Pending->value);
        $this->assertSame('approved', RequestStatus::Approved->value);
        $this->assertSame('dismissed', RequestStatus::Dismissed->value);
    }

    public function test_from_parses_a_valid_slug_into_the_correct_case(): void
    {
        $this->assertSame(RequestStatus::Pending, RequestStatus::from('pending'));
        $this->assertSame(RequestStatus::Approved, RequestStatus::from('approved'));
        $this->assertSame(RequestStatus::Dismissed, RequestStatus::from('dismissed'));
    }

    public function test_try_from_returns_null_for_an_unknown_slug(): void
    {
        $this->assertNull(RequestStatus::tryFrom('cancelled'));
    }

    public function test_label_returns_title_cased_display_string(): void
    {
        $this->assertSame('Pending', RequestStatus::Pending->label());
        $this->assertSame('Approved', RequestStatus::Approved->label());
        $this->assertSame('Dismissed', RequestStatus::Dismissed->label());
    }
}
