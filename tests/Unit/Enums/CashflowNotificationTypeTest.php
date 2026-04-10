<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CashflowNotificationType;
use PHPUnit\Framework\TestCase;

/**
 * CashflowNotificationType is the full taxonomy of toasts/emails the
 * cash-flow module can dispatch. The notification dispatcher
 * (`CashflowNotificationService`) does a match on `->value`; adding
 * a case without updating the match arms would silently drop the
 * corresponding notification. This test freezes the current taxonomy
 * so that kind of drift is visible in code review.
 */
class CashflowNotificationTypeTest extends TestCase
{
    public function test_enum_has_exactly_nine_cases(): void
    {
        // Freezing the count is the cheapest smoke for "someone
        // added a case and forgot to update the dispatcher".
        $this->assertCount(9, CashflowNotificationType::cases());
    }

    public function test_case_values_are_the_canonical_snake_case_slugs(): void
    {
        $this->assertSame('expense_pending', CashflowNotificationType::ExpensePending->value);
        $this->assertSame('expense_approved', CashflowNotificationType::ExpenseApproved->value);
        $this->assertSame('expense_rejected', CashflowNotificationType::ExpenseRejected->value);
        $this->assertSame('vendor_request', CashflowNotificationType::VendorRequest->value);
        $this->assertSame('category_request', CashflowNotificationType::CategoryRequest->value);
        $this->assertSame('staff_advance', CashflowNotificationType::StaffAdvance->value);
        $this->assertSame('negative_pool', CashflowNotificationType::NegativePool->value);
        $this->assertSame('expense_for_branch', CashflowNotificationType::ExpenseForBranch->value);
        $this->assertSame('transfer_for_branch', CashflowNotificationType::TransferForBranch->value);
    }

    public function test_from_parses_every_canonical_slug(): void
    {
        foreach (CashflowNotificationType::cases() as $case) {
            $this->assertSame($case, CashflowNotificationType::from($case->value));
        }
    }

    public function test_try_from_returns_null_for_an_unknown_slug(): void
    {
        $this->assertNull(CashflowNotificationType::tryFrom('payroll_run'));
    }

    public function test_every_case_has_a_distinct_non_empty_label(): void
    {
        // All cases must produce a label — a match-arm regression
        // would throw \UnhandledMatchError on access, but a weaker
        // "returns ->name" fallback could return an empty-feeling
        // string. Assert the label is populated and distinct.
        $labels = array_map(fn ($case) => $case->label(), CashflowNotificationType::cases());

        foreach ($labels as $label) {
            $this->assertNotEmpty($label);
        }

        $this->assertSame(
            count($labels),
            count(array_unique($labels)),
            'Every notification type must have a unique human-readable label.',
        );
    }

    public function test_label_for_each_case_is_title_cased(): void
    {
        // A sample of the labels to document the expected format.
        $this->assertSame('Expense Pending', CashflowNotificationType::ExpensePending->label());
        $this->assertSame('Negative Pool', CashflowNotificationType::NegativePool->label());
        $this->assertSame('Expense for Branch', CashflowNotificationType::ExpenseForBranch->label());
    }
}
