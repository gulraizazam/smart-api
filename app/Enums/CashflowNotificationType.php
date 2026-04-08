<?php

declare(strict_types=1);

namespace App\Enums;

enum CashflowNotificationType: string
{
    case ExpensePending = 'expense_pending';
    case ExpenseApproved = 'expense_approved';
    case ExpenseRejected = 'expense_rejected';
    case VendorRequest = 'vendor_request';
    case CategoryRequest = 'category_request';
    case StaffAdvance = 'staff_advance';
    case NegativePool = 'negative_pool';
    case ExpenseForBranch = 'expense_for_branch';
    case TransferForBranch = 'transfer_for_branch';

    public function label(): string
    {
        return match ($this) {
            self::ExpensePending => 'Expense Pending',
            self::ExpenseApproved => 'Expense Approved',
            self::ExpenseRejected => 'Expense Rejected',
            self::VendorRequest => 'Vendor Request',
            self::CategoryRequest => 'Category Request',
            self::StaffAdvance => 'Staff Advance',
            self::NegativePool => 'Negative Pool',
            self::ExpenseForBranch => 'Expense for Branch',
            self::TransferForBranch => 'Transfer for Branch',
        };
    }
}
