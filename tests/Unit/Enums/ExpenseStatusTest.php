<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ExpenseStatus;
use PHPUnit\Framework\TestCase;

/**
 * ExpenseStatus drives the cash-flow approval state machine. A
 * refactor that renames or adds a case without updating the downstream
 * switch/match sites (ExpenseService, audit-log labelling, the report
 * filter dropdowns) would silently miscategorise expenses — so we pin
 * the case set and the labels here.
 */
class ExpenseStatusTest extends TestCase
{
    public function test_enum_has_exactly_three_cases(): void
    {
        // The approval flow is ternary: pending → approved | rejected.
        // A fourth state would require an entire service-layer review.
        $this->assertCount(3, ExpenseStatus::cases());
    }

    public function test_case_values_are_the_canonical_lowercase_slugs(): void
    {
        // The DB column stores these slugs (the model casts to enum),
        // so the values are the wire format — they must not drift.
        $this->assertSame('pending', ExpenseStatus::Pending->value);
        $this->assertSame('approved', ExpenseStatus::Approved->value);
        $this->assertSame('rejected', ExpenseStatus::Rejected->value);
    }

    public function test_from_parses_a_valid_slug_into_the_correct_case(): void
    {
        $this->assertSame(ExpenseStatus::Pending, ExpenseStatus::from('pending'));
        $this->assertSame(ExpenseStatus::Approved, ExpenseStatus::from('approved'));
        $this->assertSame(ExpenseStatus::Rejected, ExpenseStatus::from('rejected'));
    }

    public function test_try_from_returns_null_for_an_unknown_slug(): void
    {
        // A stale DB row or a typo in a request payload must NOT blow
        // up the controller — the service layer uses tryFrom and
        // handles null gracefully.
        $this->assertNull(ExpenseStatus::tryFrom('draft'));
    }

    public function test_label_returns_title_cased_display_string_for_each_case(): void
    {
        // These labels are rendered in the approval UI and the
        // CSV exports. A refactor that hands the raw ->value straight
        // to the view would break the audit logs.
        $this->assertSame('Pending', ExpenseStatus::Pending->label());
        $this->assertSame('Approved', ExpenseStatus::Approved->label());
        $this->assertSame('Rejected', ExpenseStatus::Rejected->label());
    }
}
